<?php

namespace App\Http\Controllers\API\Hrd;

use Exception;
use App\Models\Contract;
use App\Models\ContractTermination;
use App\Models\Notification;
use App\Http\Requests\Termination\StoreTerminationRequest;
use App\Http\Resources\Termination\TerminationResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\ContractTerminatingMail;
use App\Mail\ContractTerminatedMail;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;


class ContractTerminationController extends Controller
{
    /**
     * GET /api/contracts/{id}/terminations
     * Daftar terminasi untuk kontrak tertentu.
     */
    public function index(int $id): JsonResponse
    {
        try {
            $contract = Contract::findOrFail($id);

            $termination = $contract->termination;

            return response()->json([
                'message' => 'Terminations retrieved successfully',
                'data'    => $termination ? [new TerminationResource($termination)] : [],
            ]);
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'Kontrak tidak ditemukan.'], 404);
        } catch (Exception $e) {
            Log::error('Termination index error', ['contract_id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Server error', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/contracts/{id}/terminations
     * HRD membuat terminasi untuk kontrak yang sudah aktif.
     */
    public function store(StoreTerminationRequest $request, int $id): JsonResponse
    {
        $validated = $request->validated();

        try {
            $contract = Contract::findOrFail($id);

            if ($contract->status !== 'active') {
                return response()->json([
                    'message' => 'Terminasi hanya dapat dibuat untuk kontrak yang sedang aktif.',
                ], 422);
            }

            // Upload dokumen pendukung jika ada
            $documentPath = null;
            if ($request->hasFile('document')) {
                $documentPath = $request->file('document')->store('terminations', 'public');
            }

            $effectiveDate = \Carbon\Carbon::parse($validated['effective_date'])->startOfDay();
            $today = \Carbon\Carbon::today();

            $reasonLabels = [
                    'mutual_agreement'   => 'Kesepakatan Bersama',
                    'breach_of_contract' => 'Pelanggaran Kontrak',
                    'force_majeure'      => 'Force Majeure',
                    'expiration'         => 'Berakhirnya Masa Kontrak',
                    'other'              => 'Lainnya',
                ];

                $reasonLabel = $reasonLabels[$validated['termination_reason']] ?? $validated['termination_reason'];
                $effectiveDateStr = $effectiveDate->locale('id')->isoFormat('D MMMM YYYY');
                $isToday = $effectiveDate->startOfDay()->lessThanOrEqualTo($today);

            $termination = DB::transaction(function () use ($contract, $validated, $documentPath, $effectiveDate, $today, $reasonLabel, $effectiveDateStr, $isToday) {
                $updateData = [
                    'end_date' => $validated['effective_date'],
                ];

                if ($effectiveDate->lessThanOrEqualTo($today)) {
                    $updateData['status'] = 'terminated';
                }

                $contract->update($updateData);

                $termination = ContractTermination::create([
                    'contract_id'               => $contract->id,
                    'termination_number'        => $validated['termination_number'],
                    'title'                     => $validated['title'],
                    'termination_reason'        => $validated['termination_reason'],
                    'termination_note'          => $validated['termination_note'] ?? null,
                    'termination_document_path' => $documentPath,
                    'effective_date'            => $validated['effective_date'],
                ]);

                // Pilih tipe notifikasi berdasarkan tanggal efektif
                if ($isToday) {
                    $notifType = 'contract_terminated';
                    $msgHrd    = "{$contract->title} telah dihentikan karena ajuan terminasi dengan alasan {$reasonLabel}.";
                    $msgMgr    = "{$contract->title} telah dihentikan karena ajuan terminasi dengan alasan {$reasonLabel}.";
                } else {
                    $notifType = 'contract_terminating';
                    $msgHrd    = "Pengajuan terminasi {$contract->title} berhasil. Kontrak akan dihentikan pada {$effectiveDateStr}. Alasan: {$reasonLabel}.";
                    $msgMgr    = "{$contract->title} akan dihentikan pada {$effectiveDateStr} karena ajuan terminasi dengan alasan {$reasonLabel}.";
                }

                // Notifikasi HRD
                Notification::create([
                    'user_id'     => $contract->created_by,
                    'contract_id' => $contract->id,
                    'type'        => $notifType,
                    'message'     => $msgHrd,
                    'is_read'     => false,
                ]);

                // Notifikasi ke manager
                $contract->loadMissing('signers.user');
                foreach ($contract->signers as $signer) {
                    if ($signer->signer_type === 'internal' && $signer->user_id) {
                        Notification::create([
                            'user_id'     => $signer->user_id,
                            'contract_id' => $contract->id,
                            'type'        => $notifType,
                            'message'     => $msgMgr,
                            'is_read'     => false,
                        ]);
                    }
                }

                // Email ke eksternal
                foreach ($contract->signers as $signer) {
                if (
                    $signer->signer_type === 'external' &&
                    !empty($signer->external_email)
                ) {
                    try {
                        Mail::to($signer->external_email)->send(
                            $isToday
                                ? new ContractTerminatedMail(
                                    $contract,
                                    $signer->signer_name,
                                    $reasonLabel,
                                    $effectiveDateStr
                                )
                                : new ContractTerminatingMail(
                                    $contract,
                                    $signer->signer_name,
                                    $reasonLabel,
                                    $effectiveDateStr
                                )
                        );

                        Log::info('Email terminasi berhasil dikirim', [
                            'email' => $signer->external_email,
                            'contract_id' => $contract->id,
                        ]);
                    } catch (\Exception $e) {
                        Log::error('Gagal mengirim email terminasi', [
                            'email' => $signer->external_email,
                            'contract_id' => $contract->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }
                return $termination;
            });

            $message = $effectiveDate->lessThanOrEqualTo($today)
                ? 'Terminasi berhasil dibuat dan kontrak telah dihentikan.'
                : 'Terminasi berhasil dibuat. Kontrak akan dihentikan pada tanggal efektif.';

            return response()->json([
                'message' => $message,
                'data'    => new TerminationResource($termination),
            ], 201);
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'Kontrak tidak ditemukan.'], 404);
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'termination_number')) {
                return response()->json([
                    'message' => 'Nomor terminasi sudah digunakan. Silakan gunakan nomor lain.',
                ], 422);
            }

            Log::error('Store termination error', ['contract_id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Server error', 'error' => $e->getMessage()], 500);
        } catch (Exception $e) {
            Log::error('Store termination error', ['contract_id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Server error', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * DELETE /api/contracts/{contractId}/terminations/{terminationId}
     * HRD menghapus terminasi.
     */
    public function destroy(int $contractId, int $terminationId): JsonResponse
    {
        try {
            $termination = ContractTermination::where('contract_id', $contractId)
                ->findOrFail($terminationId);

            DB::transaction(function () use ($termination, $contractId) {
                $termination->delete();

                // Kembalikan status kontrak ke active jika terminasi dihapus
                $contract = Contract::findOrFail($contractId);
                $contract->update(['status' => 'active']);
            });

            return response()->json(['message' => 'Terminasi berhasil dihapus dan status kontrak dikembalikan.']);
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'Terminasi tidak ditemukan.'], 404);
        } catch (Exception $e) {
            Log::error('Delete termination error', ['termination_id' => $terminationId, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Server error', 'error' => $e->getMessage()], 500);
        }
    }
}
