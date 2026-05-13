<?php

namespace App\Http\Controllers\API\Manager;

use Exception;
use App\Models\Contract;
use Illuminate\Support\Str;
use App\Models\Notification;
use Illuminate\Http\Request;
use App\Models\ContractSigner;
use Illuminate\Http\JsonResponse;
use App\Mail\ContractRejectedMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Models\ContractSignerReview;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Models\ExternalSignatureToken;
use App\Mail\ExternalSigningRequestMail;
use App\Http\Resources\Contract\ContractResource;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ContractReviewController extends Controller
{
    /**
     * GET /api/manager/contracts
     * Daftar kontrak yang di-assign ke manager ini (sebagai signer).
     */
    public function index(): JsonResponse
    {
        try {
            $user = Auth::user();

            $contractIds = ContractSigner::where('user_id', $user->id)
                ->where('signer_type', 'internal')
                ->pluck('contract_id');

            $contracts = Contract::with([
                'template.category:id,name',
                'creator:id,name',
                'latestVersion',
                'signers.user:id,name,email',
                'addendums',
            ])
                ->whereIn('id', $contractIds)
                ->whereIn('status', ['review', 'revision', 'approved', 'active', 'rejected'])
                ->orderByDesc('updated_at')
                ->get();

            return response()->json([
                'message' => 'Contracts retrieved successfully',
                'data'    => ContractResource::collection($contracts)->resolve(),
            ]);
        } catch (Exception $e) {
            Log::error('Manager index error', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Server error', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/manager/contracts/{id}
     * Detail kontrak untuk halaman preview (read-only).
     */
    public function show(int $id): JsonResponse
    {
        try {
            $user = Auth::user();

            // Pastikan manager adalah signer kontrak ini
            $this->ensureIsAssignedSigner($id, $user->id);

            $contract = Contract::with([
                'template.category:id,name',
                'creator:id,name',
                'latestVersion',
                'versions' => fn($q) => $q->orderByDesc('version_number')->limit(5),
                'signers.user:id,name,email',
                'signers.reviews' => fn($q) => $q->orderByDesc('iteration'),
                'addendums',
            ])->findOrFail($id);

            return response()->json([
                'message' => 'Contract retrieved successfully',
                'data'    => new ContractResource($contract),
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Contract not found'], 404);
        } catch (AuthorizationException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (Exception $e) {
            Log::error('Manager show error', ['contract_id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Server error', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/manager/contracts/{id}/review
     * Manager approve atau minta revisi.
     */
    public function review(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:approved,revised,rejected',
            'notes'  => 'nullable|string|max:2000',
        ]);

        if (in_array($validated['status'], ['revised', 'rejected']) && empty($validated['notes'])) {
            return response()->json(['message' => 'Notes is required for revision or rejection.'], 422);
        }

        try {
            $user = Auth::user();

            $contract = Contract::with(['latestVersion', 'creator', 'signers'])->findOrFail($id);

            if ($contract->status !== 'review') {
                return response()->json(['message' => 'Contract is not in review status.'], 422);
            }

            // Pastikan manager adalah signer yang di-assign
            $signer = $this->ensureIsAssignedSigner($id, $user->id);

            $latestVersion = $contract->latestVersion;
            if (!$latestVersion) {
                return response()->json(['message' => 'Contract has no content to review.'], 422);
            }

            // Hitung iterasi berikutnya untuk signer ini
            $iteration = ContractSignerReview::where('contract_signer_id', $signer->id)->max('iteration') ?? 0;
            $iteration++;

            DB::transaction(function () use ($contract, $signer, $latestVersion, $validated, $iteration) {
                // Catat review ke tabel contract_signer_reviews
                ContractSignerReview::create([
                    'contract_signer_id'  => $signer->id,
                    'contract_version_id' => $latestVersion->id,
                    'iteration'           => $iteration,
                    'status'              => $validated['status'],
                    'notes'               => $validated['notes'] ?? null,
                    'reviewed_at'         => now(),
                ]);

                if ($validated['status'] === 'approved') {
                    // Ubah status kontrak → approved
                    $contract->update(['status' => 'approved']);

                    // Kirim email + token ke external signers
                    $this->dispatchExternalSigningEmails($contract, $iteration + 1);

                    // Notifikasi ke HRD (pembuat kontrak)
                    Notification::create([
                        'user_id'     => $contract->created_by,
                        'contract_id' => $contract->id,
                        'type'        => 'manager_approved',
                        'message'     => "Manager telah menyetujui kontrak {$contract->contract_number}. Email dikirim ke pihak eksternal.",
                        'is_read'     => false,
                    ]);
                } elseif ($validated['status'] === 'rejected') {
                    // Ubah status kontrak → rejected
                    $contract->update(['status' => 'rejected']);

                    // Kirim email penolakan ke external
                    $this->dispatchExternalRejectionEmails($contract, $validated['notes']);

                    // Notifikasi ke HRD
                    Notification::create([
                        'user_id'     => $contract->created_by,
                        'contract_id' => $contract->id,
                        'type'        => 'manager_rejected',
                        'message'     => "Manager telah menolak kontrak {$contract->contract_number}. Alasan: {$validated['notes']}",
                        'is_read'     => false,
                    ]);
                } else {
                    // Ubah status kontrak → revision (kembali ke HRD)
                    $contract->update(['status' => 'revision']);

                    // Notifikasi ke HRD agar melakukan perbaikan
                    Notification::create([
                        'user_id'     => $contract->created_by,
                        'contract_id' => $contract->id,
                        'type'        => 'manager_revision_requested',
                        'message'     => "Manager meminta revisi untuk kontrak {$contract->contract_number}: {$validated['notes']}",
                        'is_read'     => false,
                    ]);
                }
            });

            $responseMessage = '';
            if ($validated['status'] === 'approved') {
                $responseMessage = 'Kontrak disetujui. Email dikirim ke pihak eksternal.';
            } elseif ($validated['status'] === 'rejected') {
                $responseMessage = 'Kontrak ditolak. Pihak eksternal telah diberitahu.';
            } else {
                $responseMessage = 'Permintaan revisi berhasil dikirim ke HRD.';
            }

            return response()->json([
                'message' => $responseMessage,
                'data' => new ContractResource($contract->fresh(['latestVersion'])),
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Kontrak tidak ditemukan.'], 404);
        } catch (AuthorizationException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (Exception $e) {
            Log::error('Manager review error', ['contract_id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Server error', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Pastikan user adalah internal signer kontrak. Return signer model.
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    private function ensureIsAssignedSigner(int $contractId, int $userId): ContractSigner
    {
        $signer = ContractSigner::where('contract_id', $contractId)
            ->where('user_id', $userId)
            ->where('signer_type', 'internal')
            ->first();

        if (!$signer) {
            throw new AuthorizationException(
                'Anda tidak terdaftar sebagai reviewer kontrak ini.'
            );
        }

        return $signer;
    }

    /**
     * Buat token unik dan kirim email ke semua external signer pada kontrak ini.
     * Setiap iterasi baru → token baru (token lama dinonaktifkan).
     */
    private function dispatchExternalSigningEmails(Contract $contract, int $iteration): void
    {
        $externalSigners = $contract->signers()
            ->where('signer_type', 'external')
            ->whereNotNull('external_email')
            ->get();

        foreach ($externalSigners as $signer) {
            // Nonaktifkan token lama (tandai sebagai expired)
            ExternalSignatureToken::where('contract_signer_id', $signer->id)
                ->whereNull('used_at')
                ->update(['expired_at' => now()]);

            // Buat token baru untuk iterasi ini
            $token = Str::random(64);

            ExternalSignatureToken::create([
                'contract_signer_id' => $signer->id,
                'token'              => $token,
                'iteration'          => $iteration,
                'review_status'      => 'pending',
                'expired_at'         => now()->addDays(7),
            ]);

            // Kirim email
            $signingUrl = config('app.frontend_url')
                . '/external/sign?token=' . $token;

            try {
                Mail::to($signer->external_email)
                    ->send(new ExternalSigningRequestMail($contract, $signingUrl, $iteration));
            } catch (Exception $e) {
                Log::error('Gagal mengirim email external signing', [
                    'contract_id' => $contract->id,
                    'email'       => $signer->external_email,
                    'error'       => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Kirim email penolakan ke semua external signer.
     */
    private function dispatchExternalRejectionEmails(Contract $contract, string $reason): void
    {
        $externalSigners = $contract->signers()
            ->where('signer_type', 'external')
            ->whereNotNull('external_email')
            ->get();

        foreach ($externalSigners as $signer) {
            try {
                Mail::to($signer->external_email)
                    ->send(new ContractRejectedMail($contract, $reason));
            } catch (Exception $e) {
                Log::error('Gagal mengirim email penolakan', [
                    'contract_id' => $contract->id,
                    'email'       => $signer->external_email,
                    'error'       => $e->getMessage(),
                ]);
            }
        }
    }
}
