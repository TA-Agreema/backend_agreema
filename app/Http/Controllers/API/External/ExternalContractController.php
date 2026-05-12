<?php

namespace App\Http\Controllers\API\External;

use Exception;
use App\Models\Contract;
use App\Models\ContractSigner;
use App\Models\ContractSignerReview;
use App\Models\ExternalSignatureToken;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Http\Controllers\Controller;
use App\Http\Resources\Contract\ContractResource;
use App\Mail\ExternalSigningRequestMail;

class ExternalContractController extends Controller
{
    /**
     * GET /api/external/contracts/preview?token=xxx
     * External signer membuka link dari email → lihat kontrak (read-only).
     * Tidak membutuhkan auth sanctum, cukup valid token.
     */
    public function preview(Request $request): JsonResponse
    {
        $tokenStr = $request->query('token');

        if (!$tokenStr) {
            return response()->json(['message' => 'Token tidak ditemukan.'], 400);
        }

        $tokenRecord = ExternalSignatureToken::with([
            'contractSigner.contract.latestVersion',
            'contractSigner.contract.template.category:id,name',
            'contractSigner.contract.creator:id,name',
        ])
            ->where('token', $tokenStr)
            ->first();

        if (!$tokenRecord) {
            return response()->json(['message' => 'Token tidak valid.'], 404);
        }

        if ($tokenRecord->isExpired()) {
            return response()->json(['message' => 'Token sudah kadaluarsa.'], 410);
        }

        if ($tokenRecord->isUsed()) {
            return response()->json(['message' => 'Token ini sudah digunakan sebelumnya. Tanggapan Anda telah tercatat.'], 409);
        }

        $signer   = $tokenRecord->contractSigner;
        $contract = $signer->contract;

        return response()->json([
            'message'   => 'Contract retrieved successfully',
            'iteration' => $tokenRecord->iteration,
            'signer_id' => $signer->id,
            'data'      => new ContractResource($contract),
        ]);
    }

    /**
     * POST /api/external/contracts/review
     * External signer submit keputusan: approved atau revised.
     */
    public function review(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token'  => 'required|string',
            'status' => 'required|in:approved,revised',
            'notes'  => 'nullable|string|max:2000',
        ]);

        if ($validated['status'] === 'revised' && empty($validated['notes'])) {
            return response()->json(['message' => 'Notes wajib diisi saat meminta revisi.'], 422);
        }

        try {
            $tokenRecord = ExternalSignatureToken::with([
                'contractSigner.contract.creator',
                'contractSigner.contract.signers',
            ])
                ->where('token', $validated['token'])
                ->first();

            if (!$tokenRecord) {
                return response()->json(['message' => 'Token tidak valid.'], 404);
            }

            if ($tokenRecord->isExpired()) {
                return response()->json(['message' => 'Token sudah kadaluarsa.'], 410);
            }

            if ($tokenRecord->isUsed()) {
                return response()->json(['message' => 'Token sudah digunakan.'], 409);
            }

            $signer   = $tokenRecord->contractSigner;
            $contract = $signer->contract;

            // Kontrak harus dalam status approved (setelah manager approve)
            if ($contract->status !== 'approved') {
                return response()->json(['message' => 'Kontrak belum siap untuk ditandatangani.'], 422);
            }

            $latestVersion = $contract->latestVersion;
            if (!$latestVersion) {
                return response()->json(['message' => 'Kontrak tidak memiliki versi konten.'], 422);
            }

            DB::transaction(function () use ($contract, $signer, $tokenRecord, $latestVersion, $validated) {
                // Catat review external ke tabel contract_signer_reviews
                ContractSignerReview::create([
                    'contract_signer_id'  => $signer->id,
                    'contract_version_id' => $latestVersion->id,
                    'iteration'           => $tokenRecord->iteration,
                    'status'              => $validated['status'],
                    'notes'               => $validated['notes'] ?? null,
                    'reviewed_at'         => now(),
                ]);

                // Update status token
                $tokenRecord->update([
                    'used_at'        => now(),
                    'review_status'  => $validated['status'],
                    'review_notes'   => $validated['notes'] ?? null,
                ]);

                if ($validated['status'] === 'approved') {
                    // Cek apakah SEMUA external signer pada iterasi ini sudah approved
                    $allApproved = $this->checkAllExternalApproved($contract, $tokenRecord->iteration);

                    if ($allApproved) {
                        // Kontrak siap ditandatangani / fully approved
                        $contract->update(['status' => 'active']);

                        Notification::create([
                            'user_id'     => $contract->created_by,
                            'contract_id' => $contract->id,
                            'type'        => 'external_all_approved',
                            'message'     => "Semua pihak eksternal telah menyetujui kontrak {$contract->contract_number}. Kontrak aktif.",
                            'is_read'     => false,
                        ]);
                    } else {
                        // Notifikasi sebagian saja
                        Notification::create([
                            'user_id'     => $contract->created_by,
                            'contract_id' => $contract->id,
                            'type'        => 'external_partial_approved',
                            'message'     => "Satu pihak eksternal telah menyetujui kontrak {$contract->contract_number}. Menunggu pihak lain.",
                            'is_read'     => false,
                        ]);
                    }
                } else {
                    // External minta revisi → kontrak kembali ke revision
                    $contract->update(['status' => 'revision']);

                    // Nonaktifkan semua token iterasi ini (batalkan proses signing)
                    ExternalSignatureToken::whereHas('contractSigner', function ($q) use ($contract) {
                        $q->where('contract_id', $contract->id);
                    })
                        ->whereNull('used_at')
                        ->update(['expired_at' => now()]);

                    // Notifikasi HRD untuk perbaikan
                    Notification::create([
                        'user_id'     => $contract->created_by,
                        'contract_id' => $contract->id,
                        'type'        => 'external_revision_requested',
                        'message'     => "Pihak eksternal meminta revisi kontrak {$contract->contract_number}: {$validated['notes']}",
                        'is_read'     => false,
                    ]);
                }
            });

            return response()->json([
                'message' => $validated['status'] === 'approved'
                    ? 'Kontrak berhasil disetujui.'
                    : 'Permintaan revisi berhasil dikirim.',
            ]);
        } catch (Exception $e) {
            Log::error('External review error', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Server error', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Cek apakah semua external signer pada iterasi tertentu sudah approve.
     */
    private function checkAllExternalApproved(Contract $contract, int $iteration): bool
    {
        $externalSignerIds = ContractSigner::where('contract_id', $contract->id)
            ->where('signer_type', 'external')
            ->pluck('id');

        if ($externalSignerIds->isEmpty()) {
            return true; // Tidak ada external signer = langsung full approved
        }

        // Hitung yang sudah approved pada iterasi ini
        $approvedCount = ContractSignerReview::whereIn('contract_signer_id', $externalSignerIds)
            ->where('iteration', $iteration)
            ->where('status', 'approved')
            ->count();

        return $approvedCount >= $externalSignerIds->count();
    }
}
