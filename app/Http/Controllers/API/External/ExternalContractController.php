<?php

namespace App\Http\Controllers\API\External;

use Exception;
use App\Models\Contract;
use App\Models\ContractSigner;
use App\Models\ContractSignerReview;
use App\Models\ContractSignerSignature;
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

        $validated = $request->validate([
            'token' => 'required|string',
        ]);

        try {
            $tokenRecord = ExternalSignatureToken::with([
                'contractSigner.contract.latestVersion',
                'contractSigner.contract.template.category:id,name',
                'contractSigner.contract.creator:id,name',
                'contractSigner.contract.signers.user:id,name,email,job_title',
                'contractSigner.contract.signers.reviews',
                'contractSigner.contract.signers.signatures', // untuk cek is_signed & ambil TTD internal
                'contractSigner.signatures', // untuk cek is_signed & ambil TTD signer yang memiliki token
            ])
                ->where('token', $tokenStr)
                ->first();
        } catch (Exception $e) {
            Log::error('External preview error', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Server error', 'error' => $e->getMessage()], 500);
        };

        if (!$tokenRecord) {
            return response()->json(['message' => 'Token tidak valid.'], 404);
        }

        if ($tokenRecord->isExpired()) {
            return response()->json(['message' => 'Token sudah kadaluarsa.'], 410);
        }

        $signer   = $tokenRecord->contractSigner;
        $contract = $signer->contract;

        $isSigned = $tokenRecord->isUsed();
        $signature = $isSigned
            ? ContractSignerSignature::where('contract_signer_id', $signer->id)->orderBy('signed_at', 'desc')->first()
            : null;

        return response()->json([
            'message'   => 'Contract retrieved successfully',
            'iteration' => $tokenRecord->iteration,
            'signer_id' => $signer->id,
            'data'      => new ContractResource($contract),
            'is_signed' => $isSigned,
            'signature_path' => $signature?->signature_path,
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
            'status' => 'required|in:approved,revised,confirmed',
            'notes'  => 'nullable|string|max:2000',
        ]);

        if ($validated['status'] === 'revised' && empty($validated['notes'])) {
            return response()->json(['message' => 'Notes wajib diisi saat meminta revisi.'], 422);
        }

        try {
            $tokenRecord = ExternalSignatureToken::with([
                'contractSigner.contract.creator',
                'contractSigner.contract.signers',
                'contractSigner.contract.latestVersion',
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

            if ($contract->status !== 'approved') {
                return response()->json(['message' => 'Kontrak belum siap untuk ditandatangani.'], 422);
            }

            $latestVersion = $contract->latestVersion;
            if (!$latestVersion) {
                return response()->json(['message' => 'Kontrak tidak memiliki versi konten.'], 422);
            }

            // Handle confirmed (upload manual — eksternal hanya konfirmasi tanpa TTD)
            if ($validated['status'] === 'confirmed') {
                DB::transaction(function () use ($contract, $signer, $tokenRecord, $latestVersion, $validated) {
                    ContractSignerReview::create([
                        'contract_signer_id'  => $signer->id,
                        'contract_version_id' => $latestVersion->id,
                        'iteration'           => $tokenRecord->iteration,
                        'status'              => 'approved',
                        'notes'               => $validated['notes'] ?? null,
                        'reviewed_at'         => now(),
                    ]);

                    $tokenRecord->update([
                        'used_at'       => now(),
                        'review_status' => 'approved',
                    ]);

                    $contract->update(['status' => 'active']);

                    Notification::create([
                        'user_id'     => $contract->created_by,
                        'contract_id' => $contract->id,
                        'type'        => 'contract_activated',
                        'message'     => "Kontrak {$contract->contract_number} telah disetujui pihak eksternal dan kini aktif.",
                        'is_read'     => false,
                    ]);
                });

                return response()->json(['message' => 'Kontrak berhasil disetujui dan kini aktif.']);
            }

            DB::transaction(function () use ($contract, $signer, $tokenRecord, $latestVersion, $validated) {
                ContractSignerReview::create([
                    'contract_signer_id'  => $signer->id,
                    'contract_version_id' => $latestVersion->id,
                    'iteration'           => $tokenRecord->iteration,
                    'status'              => $validated['status'],
                    'notes'               => $validated['notes'] ?? null,
                    'reviewed_at'         => now(),
                ]);

                $tokenRecord->update([
                    'used_at'       => now(),
                    'review_status' => $validated['status'],
                    'review_notes'  => $validated['notes'] ?? null,
                ]);

                if ($validated['status'] === 'approved') {
                    $allApproved = $this->checkAllExternalApproved($contract, $tokenRecord->iteration);

                    if ($allApproved) {
                        $contract->update(['status' => 'active']);

                        Notification::create([
                            'user_id'     => $contract->created_by,
                            'contract_id' => $contract->id,
                            'type'        => 'external_all_approved',
                            'message'     => "Semua pihak eksternal telah menyetujui kontrak {$contract->contract_number}. Kontrak aktif.",
                            'is_read'     => false,
                        ]);
                    } else {
                        Notification::create([
                            'user_id'     => $contract->created_by,
                            'contract_id' => $contract->id,
                            'type'        => 'external_partial_approved',
                            'message'     => "Satu pihak eksternal telah menyetujui kontrak {$contract->contract_number}. Menunggu pihak lain.",
                            'is_read'     => false,
                        ]);
                    }
                } else {
                    $contract->update(['status' => 'revision']);

                    ExternalSignatureToken::whereHas('contractSigner', function ($q) use ($contract) {
                        $q->where('contract_id', $contract->id);
                    })
                        ->whereNull('used_at')
                        ->update(['expired_at' => now()]);

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

    public function sign(Request $request): JsonResponse
    {
        $request->validate([
            'token'          => 'required|string',
            'signature_type' => 'required|in:canvas,upload',
            'signature_data' => 'required_if:signature_type,canvas|string',
            'signature_file' => 'required_if:signature_type,upload|file|mimes:pdf,png,jpg,jpeg',
        ]);

        try {
            $tokenRecord = ExternalSignatureToken::with('contractSigner.contract')
                ->where('token', $request->token)
                ->first();

            if (!$tokenRecord) return response()->json(['message' => 'Token tidak valid.'], 404);
            if ($tokenRecord->isExpired()) return response()->json(['message' => 'Token sudah kadaluarsa.'], 410);
            if ($tokenRecord->isUsed()) return response()->json(['message' => 'Token sudah digunakan.'], 409);

            $signer   = $tokenRecord->contractSigner;
            $contract = $signer->contract;

            if ($contract->status !== 'approved') {
                return response()->json(['message' => 'Kontrak belum siap untuk ditandatangani.'], 422);
            }

            DB::transaction(function () use ($request, $signer, $contract, $tokenRecord) {
                $signaturePath = null;

                if ($request->signature_type === 'canvas') {
                    $imageData = str_replace('data:image/png;base64,', '', $request->signature_data);
                    $imageData = base64_decode($imageData);
                    $filename  = 'signatures/' . uniqid() . '.png';
                    \Storage::disk('public')->put($filename, $imageData);
                    $signaturePath = $filename;
                } else {
                    $signaturePath = $request->file('signature_file')
                        ->store('signatures', 'public');
                }

                $latestVersion = $contract->versions()->latest()->first();

                $iteration = ContractSignerSignature::where('contract_signer_id', $signer->id)
                    ->count() + 1;

                ContractSignerSignature::create([
                    'contract_signer_id'  => $signer->id,
                    'contract_version_id' => $latestVersion->id,
                    'iteration'           => $iteration,
                    'signature_type'      => $request->signature_type,
                    'signature_path'      => $signaturePath,
                    'ip_address'          => $request->ip(),
                    'user_agent'          => $request->userAgent(),
                    'signed_at'           => now(),
                ]);

                $tokenRecord->update([
                    'used_at'       => now(),
                    'review_status' => 'approved',
                ]);

                // ✅ TAMBAHAN: Cek apakah semua signer sudah TTD
                $allSigned = $contract->signers()
                    ->whereDoesntHave('signatures')
                    ->doesntExist();

                if ($allSigned) {
                    $contract->update(['status' => 'active']);
                }
            });

            // ✅ TAMBAHAN: Ambil status terbaru setelah transaksi selesai
            $contract->refresh();

            return response()->json([
                'message'         => 'Tanda tangan berhasil disimpan.',
                'contract_status' => $contract->status, // 'active' jika semua sudah TTD
            ]);

        } catch (Exception $e) {
            Log::error('External sign error', ['error' => $e->getMessage()]);
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
