<?php

namespace App\Http\Controllers\API\External;

use Exception;
use Carbon\Carbon;
use App\Models\Contract;
use Illuminate\Support\Str;
use App\Models\Notification;
use Illuminate\Http\Request;
use App\Models\ContractSigner;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Mail\ContractActivatedMail;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Models\ContractSignerReview;
use App\Services\ContractPdfService;
use Illuminate\Support\Facades\Mail;
use App\Models\ExternalSignatureToken;
use App\Models\ContractSignerSignature;
use Illuminate\Support\Facades\Storage;
// use App\Mail\ExternalSigningRequestMail;
use App\Http\Resources\Contract\ContractResource;

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

        // Cari metode TTD yang sudah dipakai signer internal (kalau sudah ada),
        // agar signer eksternal diarahkan memakai metode yang sama.
        $internalSignatureType = $contract->signers
            ->where('signer_type', 'internal')
            ->flatMap(fn($s) => $s->signatures ?? collect())
            ->filter(fn($sig) => in_array($sig->signature_type, ['canvas', 'upload']))
            ->sortByDesc('signed_at')
            ->first()
            ?->signature_type;

        return response()->json([
            'message'   => 'Contract retrieved successfully',
            'iteration' => $tokenRecord->iteration,
            'signer_id' => $signer->id,
            'data'      => new ContractResource($contract),
            'is_signed' => $isSigned,
            'signature_path' => $signature?->signature_path,
            'internal_signature_type' => $internalSignatureType,
        ]);
    }

    /**
     * POST /api/external/contracts/review
     * External signer submit keputusan: approved atau revised.
     */
    public function review(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token'           => 'required|string',
            'status'          => 'required|in:approved,revised,confirmed',
            'notes'           => 'nullable|string|max:2000',
            'review_document' => 'nullable|file|mimes:pdf,doc,docx|max:10240',
        ]);

        $notes = $validated['notes'] ?? null;
        $hasFile = $request->hasFile('review_document');

        if ($validated['status'] === 'revised' && !$hasFile && empty($notes)) {
            return response()->json(['message' => 'Catatan atau dokumen revisi wajib diisi saat meminta revisi.'], 422);
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

            // Simpan file dokumen revisi (jika ada)
            $reviewDocumentPath = null;
            if ($hasFile) {
                $reviewDocumentPath = $request->file('review_document')
                    ->store('review-documents', 'public');
            }

            // Handle confirmed (upload manual — eksternal hanya konfirmasi tanpa TTD)
            if ($validated['status'] === 'confirmed') {
                DB::transaction(function () use ($contract, $signer, $tokenRecord, $latestVersion, $validated, $reviewDocumentPath, $notes) {
                    ContractSignerReview::create([
                        'contract_signer_id'   => $signer->id,
                        'contract_version_id'  => $latestVersion->id,
                        'iteration'            => $tokenRecord->iteration,
                        'status'               => 'approved',
                        'notes'                => $notes,
                        'review_document_path' => $reviewDocumentPath,
                        'reviewed_at'          => now(),
                    ]);

                    $tokenRecord->update([
                        'used_at'       => now(),
                        'review_status' => 'approved',
                    ]);

                    $contract->update(['status' => 'signed']);

                    // Langsung aktifkan jika start_date sudah tiba
                    $this->activateIfReady($contract);

                    // Kirim email notifikasi ke semua pihak
                    $this->sendActivationEmails($contract);

                    $contract->loadMissing('signers.user');

                    if ($contract->status === 'active') {
                        $startDate = $contract->start_date
                            ? $contract->start_date->locale('id')->isoFormat('D MMMM YYYY')
                            : '(belum ditentukan)';

                        $endDate = $contract->end_date
                            ? $contract->end_date->locale('id')->isoFormat('D MMMM YYYY')
                            : '(belum ditentukan)';

                    // Notifikasi ke HRD — kontrak langsung aktif
                        Notification::create([
                            'user_id'     => $contract->created_by,
                            'contract_id' => $contract->id,
                            'type'        => 'contract_activated',
                            'message'     => "Pihak kedua telah menyetujui {$contract->title}. Kontrak telah aktif sampai pada tanggal {$endDate}.",
                            'is_read'     => false,
                        ]);

                        foreach ($contract->signers as $signerItem) {
                            if ($signerItem->signer_type === 'internal' && $signerItem->user_id) {
                                Notification::create([
                                    'user_id'     => $signerItem->user_id,
                                    'contract_id' => $contract->id,
                                    'type'        => 'contract_activated',
                                    'message'     => "Pihak kedua telah menyetujui {$contract->title}. Kontrak telah aktif sampai pada tanggal {$endDate}.",
                                    'is_read'     => false,
                                ]);
                            }
                        }
                    } else {
                        $startDate = $contract->start_date
                            ? $contract->start_date->locale('id')->isoFormat('D MMMM YYYY')
                            : '(belum ditentukan)';

                        // Notifikasi ke HRD — kontrak belum aktif
                            Notification::create([
                                'user_id'     => $contract->created_by,
                                'contract_id' => $contract->id,
                                'type'        => 'external_approved',
                                'message'     => "Pihak kedua telah menyetujui {$contract->title}. Kontrak akan aktif pada tanggal {$startDate}.",
                                'is_read'     => false,
                            ]);

                            foreach ($contract->signers as $signerItem) {
                                if ($signerItem->signer_type === 'internal' && $signerItem->user_id) {
                                    Notification::create([
                                        'user_id'     => $signerItem->user_id,
                                        'contract_id' => $contract->id,
                                        'type'        => 'external_approved',
                                        'message'     => "Pihak kedua telah menyetujui dokumen {$contract->title} yang diupload. Kontrak akan aktif pada tanggal {$startDate}.",
                                        'is_read'     => false,
                                    ]);
                                }
                            }
                        }
                    });
                    return response()->json(['message' => 'Kontrak berhasil disetujui dan kini aktif.']);
                }

                DB::transaction(function () use ($contract, $signer, $tokenRecord, $latestVersion, $validated, $reviewDocumentPath, $notes) {
                    ContractSignerReview::create([
                        'contract_signer_id'   => $signer->id,
                        'contract_version_id'  => $latestVersion->id,
                        'iteration'            => $tokenRecord->iteration,
                        'status'               => $validated['status'],
                        'notes'                => $notes,
                        'review_document_path' => $reviewDocumentPath,
                        'reviewed_at'          => now(),
                    ]);

                    $tokenRecord->update([
                        'used_at'       => now(),
                        'review_status' => $validated['status'],
                        'review_notes'  => $notes,
                    ]);

                    if ($validated['status'] === 'approved') {
                        $allApproved = $this->checkAllExternalApproved($contract, $tokenRecord->iteration);

                        if ($allApproved) {
                            $contract->update(['status' => 'signed']);

                            Notification::create([
                                'user_id'     => $contract->created_by,
                                'contract_id' => $contract->id,
                                'type'        => 'external_approved',
                                'message'     => "Pihak kedua telah menyetujui {$contract->title}. Kontrak aktif.",
                                'is_read'     => false,
                            ]);
                        // hapus
                        } else {
                            Notification::create([
                                'user_id'     => $contract->created_by,
                                'contract_id' => $contract->id,
                                'type'        => 'external_partial_approved',
                                'message'     => "Satu pihak eksternal telah menyetujui {$contract->title}. Menunggu pihak lain.",
                                'is_read'     => false,
                            ]);
                        }
                    } else {
                        $contract->update(['status' => 'revision']);

                        // Expire semua token external yang belum digunakan
                        ExternalSignatureToken::whereHas('contractSigner', function ($q) use ($contract) {
                            $q->where('contract_id', $contract->id);
                        })
                            ->whereNull('used_at')
                            ->update(['expired_at' => now()]);

                        // Reset TTD internal — hapus semua signature iterasi saat ini
                        // agar semua internal signer harus TTD ulang
                        $internalSignerIds = ContractSigner::where('contract_id', $contract->id)
                            ->where('signer_type', 'internal')
                            ->pluck('id');

                        if ($internalSignerIds->isNotEmpty()) {
                            // Ambil iterasi tertinggi yang ada
                            $currentIteration = ContractSignerSignature::whereIn('contract_signer_id', $internalSignerIds)
                                ->max('iteration') ?? 0;

                            // Soft-invalidate: tandai signature iterasi ini dengan iteration = -iteration
                            // (tidak menghapus data, hanya menyimpan riwayat)
                            ContractSignerSignature::whereIn('contract_signer_id', $internalSignerIds)
                                ->where('iteration', $currentIteration)
                                ->update(['iteration' => -$currentIteration]);
                        }

                        // Notifikasi ke HRD
                        Notification::create([
                            'user_id'     => $contract->created_by,
                            'contract_id' => $contract->id,
                            'type'        => 'external_revision_requested',
                            'message'     => "Pihak kedua meminta revisi {$contract->title}. Alasan: {$notes}",
                            'is_read'     => false,
                        ]);

                        // Notifikasi ke Manager
                        foreach ($contract->signers as $signer) {
                            if ($signer->signer_type === 'internal' && $signer->user_id && $signer->user_id !== $contract->created_by) {
                                Notification::create([
                                    'user_id'     => $signer->user_id,
                                    'contract_id' => $contract->id,
                                    'type'        => 'external_revision_requested',
                                    'message'     => "{$contract->title} memiliki revisi dari pihak kedua. Alasan: {$notes}",
                                    'is_read'     => false,
                                ]);
                            }
                        }
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
                    Storage::disk('public')->put($filename, $imageData);
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

                // Cek apakah semua signer sudah TTD
                $allSigned = $contract->signers()
                    ->whereDoesntHave('signatures')
                    ->doesntExist();

                if ($allSigned) {
                    $contract->update(['status' => 'signed']);

                    // cek start_date, baru aktif jika sudah tiba
                    $this->activateIfReady($contract);

                    // Kirim email notifikasi ke semua pihak
                    $this->sendActivationEmails($contract);
                }

                $contract->loadMissing('signers.user');

                $startDate = $contract->start_date
                    ? $contract->start_date->locale('id')->isoFormat('D MMMM YYYY')
                    : '(belum ditentukan)';

                $endDate = $contract->end_date
                    ? $contract->end_date->locale('id')->isoFormat('D MMMM YYYY')
                    : '(belum ditentukan)';

                $isNowActive = $contract->status === 'active';

                // Notifikasi ke HRD (pembuat kontrak)
                Notification::create([
                    'user_id'     => $contract->created_by,
                    'contract_id' => $contract->id,
                    'type'        => $isNowActive ? 'contract_activated' : 'all_reviewers_signed',
                    'message'     => $isNowActive
                        ? "Pihak kedua telah menandatangani {$contract->title}. Kontrak telah aktif sampai pada tanggal {$endDate}."
                        : "Pihak kedua telah menandatangani {$contract->title}. Kontrak akan aktif pada tanggal {$startDate}.",
                    'is_read'     => false,
                ]);

                // Notifikasi ke semua internal signer (manager)
                foreach ($contract->signers as $signer) {
                    if ($signer->signer_type === 'internal' && $signer->user_id) {
                        Notification::create([
                            'user_id'     => $signer->user_id,
                            'contract_id' => $contract->id,
                            'type'        => $isNowActive ? 'contract_activated' : 'all_reviewers_signed',
                            'message'     => $isNowActive
                                ? "Pihak kedua telah menandatangani {$contract->title}. Kontrak telah aktif sampai pada tanggal {$endDate}."
                                : "Pihak kedua telah menandatangani {$contract->title}. Kontrak akan aktif pada tanggal {$startDate}.",
                            'is_read'     => false,
                        ]);
                    }
                }
            });

            // Ambil status terbaru setelah transaksi selesai
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
     * Kirim email notifikasi ke semua pihak (internal + eksternal) saat kontrak aktif.
     * Generate PDF terlebih dahulu dan simpan path-nya ke contract.
     */
    private function sendActivationEmails(Contract $contract): void
    {
        // Generate PDF dan simpan path ke contract
        try {
            $pdfService = app(ContractPdfService::class);
            $pdfPath    = $pdfService->generateSignedPdf($contract);

            $contract->update(['signed_document_path' => $pdfPath]);
            $contract->signed_document_path = $pdfPath; // update in-memory juga
        } catch (\Exception $e) {
            Log::error('Gagal generate PDF kontrak', [
                'contract_id' => $contract->id,
                'error'       => $e->getMessage(),
            ]);
            // Tetap kirim email meski PDF gagal, tanpa attachment
        }

        $contract->loadMissing('signers.user');

        foreach ($contract->signers as $signer) {
            if ($signer->signer_type === 'internal' && $signer->user) {
                Mail::to($signer->user->email)
                    ->send(new ContractActivatedMail($contract, $signer->user->name));
            } elseif ($signer->signer_type === 'external' && $signer->external_email) {
                $name = $signer->signer_name ?? 'Pihak Eksternal';
                Mail::to($signer->external_email)
                    ->send(new ContractActivatedMail($contract, $name));
            }
        }
    }

    /**
     * Langsung aktifkan kontrak jika start_date sudah tiba atau hari ini.
     * Dipanggil setelah status diubah ke signed.
     */
    private function activateIfReady(Contract $contract): void
    {
        $contract->refresh();

        if ($contract->start_date && $contract->start_date->lte(Carbon::today())) {
            $contract->update(['status' => 'active']);
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

    /**
     * GET /api/external/contracts/download?token=xxx
     * Download PDF kontrak untuk pihak eksternal
    */
    public function downloadPdf(Request $request)
    {
        $tokenStr = $request->query('token');

        if (!$tokenStr) {
            return response()->json(['message' => 'Token tidak ditemukan.'], 400);
        }

        $tokenRecord = ExternalSignatureToken::with([
            'contractSigner.contract.latestVersion',
            'contractSigner.contract.signers.signatures',
            'contractSigner.contract.signers.user',
            'contractSigner.contract.creator',
        ])->where('token', $tokenStr)->first();

        if (!$tokenRecord || $tokenRecord->isExpired()) {
            return response()->json(['message' => 'Token tidak valid atau kadaluarsa.'], 410);
        }

        $contract = $tokenRecord->contractSigner->contract;
        $pdfService = new ContractPdfService();
        $pdfPath = $pdfService->generateSignedPdf($contract);

        return Storage::disk('public')->download(
            $pdfPath,
            $contract->title . '.pdf'
        );
    }
}
