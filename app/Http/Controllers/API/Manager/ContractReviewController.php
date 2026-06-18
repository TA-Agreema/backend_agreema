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
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\Controller;
use App\Models\ContractSignerReview;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Models\ExternalSignatureToken;
use App\Mail\ExternalSigningRequestMail;
use App\Http\Resources\Contract\ContractResource;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use App\Models\ContractSignerSignature;

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
                'latestVersion.fieldValues.fieldDefinition',
                'signers.user:id,name,email,job_title',
                'addendums',
            ])
                ->whereIn('id', $contractIds)
                ->whereIn('status', ['review', 'revision', 'approved','signed', 'active', 'rejected'])
                ->orderByDesc('updated_at')
                ->get();

            return response()->json([
                'message' => 'Contracts retrieved successfully',
                'data' => ContractResource::collection($contracts)->resolve(),
            ]);
        } catch (Exception $e) {
            Log::error('Manager index error', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Server error', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/manager/contracts/archive
     * Daftar kontrak arsip (rejected, terminated, expired) yang di-assign ke manager ini.
     */
    public function archive(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            $contractIds = ContractSigner::where('user_id', $user->id)
                ->where('signer_type', 'internal')
                ->pluck('contract_id');

            $query = Contract::with([
                'template.category:id,name',
                'creator:id,name',
                'addendums:id,contract_id,addendum_number,title,description,document_path,effective_date,created_at',
                'termination',
                'signers.reviews',
                'parties.party.individualDetail:id,party_id,full_name',
                'parties.party.companyDetail:id,party_id,company_name',
                'latestVersion.fieldValues.fieldDefinition',
            ])
                ->whereIn('id', $contractIds)
                ->whereIn('status', ['rejected', 'terminated', 'expired'])
                ->orderByDesc('updated_at');

            $validated = $request->validate([
                'search' => 'nullable|string|max:255',
            ]);

            if ($search = $validated['search'] ?? null) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhereHas('template.category', fn($cat) => $cat->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('parties.party', fn($p) => $p->whereHas('companyDetail', fn($cd) => $cd->where('company_name', 'like', "%{$search}%")));
                });
            }

            $contracts = $query->get();

            return response()->json([
                'message' => 'Archived contracts retrieved successfully',
                'data' => ContractResource::collection($contracts)->resolve(),
            ]);
        } catch (Exception $e) {
            Log::error('Manager archive error', ['error' => $e->getMessage()]);
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

            $this->ensureIsAssignedSigner($id, $user->id);

            $contract = Contract::with([
                'template.category:id,name',
                'creator:id,name',
                'latestVersion',
                'signers.user:id,name,email,job_title',
                'versions' => fn($q) => $q->with('creator:id,name')->orderByDesc('id')->limit(5),
                'signers.reviews' => fn($q) => $q->orderByDesc('iteration'),
                'signers.signatures',
                'addendums',
                'statusLogs.changedBy:id,name',
            ])->findOrFail($id);

            return response()->json([
                'message' => 'Contract retrieved successfully',
                'data' => new ContractResource($contract),
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
            'notes' => 'nullable|string|max:2000',
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

            DB::transaction(function () use ($contract, $signer, $latestVersion, $validated, $iteration, $user) {
                // Catat review ke tabel contract_signer_reviews
                ContractSignerReview::create([
                    'contract_signer_id' => $signer->id,
                    'contract_version_id' => $latestVersion->id,
                    'iteration' => $iteration,
                    'status' => $validated['status'],
                    'notes' => $validated['notes'] ?? null,
                    'reviewed_at' => now(),
                ]);

                if ($validated['status'] === 'approved') {
                    $hasExternal = $contract->signers->where('signer_type', 'external')->isNotEmpty();
                    $allInternalSigners = $contract->signers->where('signer_type', 'internal');

                    // Cek apakah semua internal signer sudah approve di iterasi ini
                    $approvedSignerIds = ContractSignerReview::whereIn('contract_signer_id', $allInternalSigners->pluck('id'))
                        ->where('iteration', $iteration)
                        ->where('status', 'approved')
                        ->pluck('contract_signer_id');

                    $allInternalApproved = $allInternalSigners->count() > 0
                        && $allInternalSigners->count() === $approvedSignerIds->count();

                    if ($allInternalApproved) {
                        if ($hasExternal) {
                            // Semua internal approve + ada external → kirim ke eksternal
                            $contract->update(['status' => 'approved']);
                            $this->dispatchExternalSigningEmails($contract, $iteration + 1);

                            // Notifikasi ke HRD (pembuat kontrak)
                            Notification::create([
                                'user_id'     => $contract->created_by,
                                'contract_id' => $contract->id,
                                'type'        => 'manager_approved',
                                'message'     => "Manager telah menyetujui kontrak {$contract->contract_number}. Email dikirim ke pihak eksternal.",
                                'is_read'     => false,
                            ]);
                        } else {
                            // Semua internal approve + tidak ada external → langsung ke tahap TTD
                            $contract->update(['status' => 'approved']);

                            Notification::create([
                                'user_id'     => $contract->created_by,
                                'contract_id' => $contract->id,
                                'type'        => 'manager_approved',
                                'message'     => "Semua pihak internal telah menyetujui kontrak {$contract->contract_number}. Silakan lanjutkan penandatanganan.",
                                'is_read'     => false,
                            ]);

                            // Notifikasi ke semua internal signer agar TTD
                            foreach ($allInternalSigners as $internalSigner) {
                                if ($internalSigner->user_id) {
                                    Notification::create([
                                        'user_id'     => $internalSigner->user_id,
                                        'contract_id' => $contract->id,
                                        'type'        => 'review_requested',
                                        'message'     => "Semua pihak telah menyetujui kontrak {$contract->contract_number}. Silakan lakukan penandatanganan.",
                                        'is_read'     => false,
                                    ]);
                                }
                            }
                        }
                    } else {
                        // Belum semua internal approve → notifikasi signer berikutnya
                        $approvedIds = $approvedSignerIds->toArray();
                        $nextSigner = $allInternalSigners
                            ->sortBy('sequence')
                            ->first(fn($s) => !in_array($s->id, $approvedIds));

                        if ($nextSigner && $nextSigner->user_id) {
                            Notification::create([
                                'user_id'     => $nextSigner->user_id,
                                'contract_id' => $contract->id,
                                'type'        => 'review_requested',
                                'message'     => "Kontrak {$contract->contract_number} memerlukan persetujuan Anda.",
                                'is_read'     => false,
                            ]);
                        }

                        // Notifikasi konfirmasi ke reviewer saat ini
                        Notification::create([
                            'user_id'     => $user->id,
                            'contract_id' => $contract->id,
                            'type'        => 'manager_approved',
                            'message'     => "Anda telah menyetujui kontrak {$contract->contract_number}. Menunggu persetujuan pihak internal lainnya.",
                            'is_read'     => false,
                        ]);
                    }
                } elseif ($validated['status'] === 'rejected') {
                    // Ubah status kontrak → rejected
                    $contract->update(['status' => 'rejected']);

                    // Kirim email penolakan ke external
                    $this->dispatchExternalRejectionEmails($contract, $validated['notes']);

                    // Notifikasi ke HRD
                    Notification::create([
                        'user_id' => $contract->created_by,
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
                        'user_id' => $contract->created_by,
                        'contract_id' => $contract->id,
                        'type'        => 'manager_revision_requested',
                        'message'     => "Manager meminta revisi untuk kontrak {$contract->contract_number}: {$validated['notes']}",
                        'is_read'     => false,
                    ]);
                }
            });

            $responseMessage = '';
            if ($validated['status'] === 'approved') {
                $fresh = $contract->fresh(['signers']);
                $hasExternal = $fresh->signers->where('signer_type', 'external')->isNotEmpty();
                if ($fresh->status === 'approved' && $hasExternal) {
                    $responseMessage = 'Kontrak disetujui dan akan dikirim ke pihak kedua.';
                } elseif ($fresh->status === 'approved' && !$hasExternal) {
                    $responseMessage = 'Kontrak disetujui. Silakan lanjutkan penandatanganan.';
                } else {
                    $responseMessage = 'Persetujuan Anda berhasil disimpan. Menunggu persetujuan pihak internal lainnya.';
                }
            } elseif ($validated['status'] === 'rejected') {
                $responseMessage = 'Kontrak ditolak dan pihak kedua telah diberitahu.';
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
    /**
     * Langsung aktifkan kontrak jika start_date sudah tiba atau hari ini.
     */
    private function activateIfReady(Contract $contract): void
    {
        $contract->refresh();
        if ($contract->start_date && $contract->start_date->lte(\Carbon\Carbon::today())) {
            $contract->update(['status' => 'active']);
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
                'token' => $token,
                'iteration' => $iteration,
                'review_status' => 'pending',
                'expired_at' => now()->addDays(7),
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
                    'email' => $signer->external_email,
                    'error' => $e->getMessage(),
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
                    'email' => $signer->external_email,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * POST /api/manager/contracts/{id}/sign
     */
    public function sign(Request $request, int $id): JsonResponse
    {
        try {
            $request->validate([
                'signature_type' => 'required|in:canvas,upload',
                'signature_data' => 'required_if:signature_type,canvas|string',
                'signature_file' => 'required_if:signature_type,upload|file|mimes:pdf,png,jpg,jpeg|max:20480',
            ]);

            $contract = Contract::with('latestVersion', 'signers')->findOrFail($id);
            $user = Auth::user();

            // Cari signer yang cocok dengan user yang login
            $signer = ContractSigner::where('contract_id', $id)
                ->where('user_id', $user->id)
                ->first();

            if (!$signer) {
                return response()->json([
                    'message' => 'Anda tidak terdaftar sebagai penandatangan kontrak ini.',
                ], 403);
            }

            // Cek urutan TTD berdasarkan sequence
            // Signer hanya boleh TTD jika semua signer dengan sequence lebih kecil sudah approve
            $allInternalSigners = $contract->signers->where('signer_type', 'internal')->sortBy('sequence');
            foreach ($allInternalSigners as $prevSigner) {
                if ($prevSigner->sequence >= $signer->sequence) break;

                // Ambil iterasi review tertinggi signer sebelumnya
                $prevApproved = ContractSignerReview::where('contract_signer_id', $prevSigner->id)
                    ->where('status', 'approved')
                    ->exists();

                if (!$prevApproved) {
                    return response()->json([
                        'message' => 'Anda belum dapat menandatangani. Penandatangan sebelumnya belum menyetujui kontrak.',
                    ], 422);
                }
            }

            $latestVersion = $contract->latestVersion;
            if (!$latestVersion) {
                return response()->json([
                    'message' => 'Kontrak tidak memiliki versi aktif.',
                ], 422);
            }


            // Simpan file tanda tangan
            $signaturePath = null;

            if ($request->signature_type === 'canvas') {
                $imageData = preg_replace('/^data:image\/\w+;base64,/', '', $request->signature_data);

                $imageData = str_replace(' ', '+', $imageData);
                $decoded = base64_decode($imageData);

                $filename      = 'signatures/' . $id . '_' . $user->id . '_' . time() . '.png';
                Storage::disk('public')->put($filename, $decoded);
                $signaturePath = $filename;
            } else {
                $file = $request->file('signature_file');
                $filename = 'signed-documents/' . $id . '_' . time() . '.pdf';
                $signaturePath = $file->storeAs('signed-documents', basename($filename), 'public');
            }

            // Hitung iterasi
            $iteration = ContractSignerSignature::where('contract_signer_id', $signer->id)
                ->max('iteration') ?? 0;
            $iteration++;

            // Simpan ke tabel contract_signer_signatures
            ContractSignerSignature::create([
                'contract_signer_id' => $signer->id,
                'contract_version_id' => $latestVersion->id,
                'iteration' => $iteration,
                'signature_type' => $request->signature_type,
                'signature_path' => $signaturePath,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'signed_at' => now(),
            ]);

            // Cek apakah internal signer sudah menandatangani
            $allInternalSigners = ContractSigner::where('contract_id', $id)
                ->where('signer_type', 'internal')
                ->pluck('id');

            // Ambil iterasi tertinggi yang valid (positif) dari semua internal signer
            $currentIteration = ContractSignerSignature::whereIn('contract_signer_id', $allInternalSigners)
                ->where('iteration', '>', 0)
                ->max('iteration') ?? 0;

            $signedSignerIds = ContractSignerSignature::whereIn('contract_signer_id', $allInternalSigners)
                ->where('iteration', $currentIteration)
                ->where('iteration', '>', 0)
                ->distinct('contract_signer_id')
                ->pluck('contract_signer_id');

            $allSigned = $currentIteration > 0
                && $allInternalSigners->count() > 0
                && $allInternalSigners->count() === $signedSignerIds->count();

            if ($allSigned) {
                $contract->load('signers');
                $hasExternalSigner = $contract->signers->where('signer_type', 'external')->isNotEmpty();

                if ($hasExternalSigner) {
                    // Ada pihak eksternal — kirim ke eksternal untuk TTD
                    $contract->update(['status' => 'approved']);
                    $this->dispatchExternalSigningEmails($contract, $iteration + 1);

                Notification::create([
                    'user_id' => $contract->created_by,
                    'contract_id' => $contract->id,
                    'type'        => 'all_internal_signed',
                    'message'     => "Pihak pertama telah menandatangani kontrak {$contract->title}. Email dikirim ke pihak kedua.",
                    'is_read'     => false,
                ]);
            } else {
                    // Tidak ada pihak eksternal — langsung signed/active ketika start_date tiba
                    $contract->update(['status' => 'signed']);
                    $this->activateIfReady($contract);

                    $isNowActive = $contract->fresh()->status === 'active';
                    $startDate = $contract->start_date
                        ? $contract->start_date->locale('id')->isoFormat('D MMMM YYYY')
                        : null;

                    $messageHrd = $isNowActive
                        ? "{$contract->title} telah ditandatangani semua pihak internal dan kini aktif."
                        : "{$contract->title} telah ditandatangani semua pihak internal. Kontrak akan aktif pada {$startDate}.";

                    $messageManager = $isNowActive
                        ? "Anda telah menandatangani {$contract->title}. Kontrak kini aktif."
                        : "Anda telah menandatangani {$contract->title}. Kontrak akan aktif pada {$startDate}.";

                    Notification::create([
                        'user_id'     => $contract->created_by,
                        'contract_id' => $contract->id,
                        'type'        => $isNowActive ? 'contract_activated' : 'all_reviewers_signed',
                        'message'     => $messageHrd,
                        'is_read'     => false,
                    ]);

                    Notification::create([
                        'user_id'     => $user->id,
                        'contract_id' => $contract->id,
                        'type'        => $isNowActive ? 'contract_activated' : 'manager_approved',
                        'message'     => $messageManager,
                        'is_read'     => false,
                    ]);
                }
            }

            // Reload signers sebelum dispatch
            $contract->load('signers');

            // Reload contract dengan relasi lengkap
            $contract->load([
                'template.category:id,name',
                'creator:id,name',
                'latestVersion',
                'signers.user:id,name,job_title',
                'signers.signatures',
                'statusLogs.changedBy:id,name',
                'addendums:id,contract_id,addendum_number,description,effective_date,created_at',
                'termination',
            ]);

            return response()->json([
                'message' => 'Tanda tangan berhasil disimpan.',
                'data' => new ContractResource($contract),
            ]);
        } catch (Exception $e) {
            Log::error('Manager: error signing contract', ['contract_id' => $id, 'error' => $e->getMessage()]);

            return response()->json([
                'message' => 'Gagal menyimpan tanda tangan.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/manager/contracts/{id}/download
     * Generate dan download kontrak sebagai PDF.
     */
    public function download(int $id)
    {
        try {
            $contract = Contract::with([
                'latestVersion',
                'creator:id,name',
                'signers.user:id,name,job_title',
                'template.category:id,name',
                'parties.party.individualDetail:id,party_id,full_name',
                'parties.party.companyDetail:id,party_id,company_name',
            ])->findOrFail($id);

            // ← Kalau sudah ada dokumen fisik yang diupload, kembalikan.
            if ($contract->signed_document_path) {
                $filePath = storage_path('app/public/' . $contract->signed_document_path);
                if (file_exists($filePath)) {
                    $filename = ($contract->title ?? 'kontrak-' . $id) . '-signed.pdf';
                    return response()->download($filePath, $filename, [
                        'Content-Type' => 'application/pdf',
                    ]);
                }
            }

            // Fallback: generate dari template
            $content = $contract->latestVersion?->content ?? '<p>Konten tidak tersedia.</p>';
            $html = view('pdf.contract', [
                'contract' => $contract,
                'content' => $content,
            ])->render();

            $pdf = Pdf::loadHTML($html)
                ->setPaper(($contract->paper_size ?? 'f4') === 'f4' ? [0, 0, 609.45, 935.43] : 'a4', 'portrait')
                ->setOptions([
                    'defaultFont' => 'sans-serif',
                    'isRemoteEnabled' => false,
                    'isHtml5ParserEnabled' => true,
                ]);

            $filename = ($contract->title ?? 'kontrak-' . $id) . '.pdf';
            $filename = preg_replace('/[\/\\\\]/', '-', $filename);

            return $pdf->download($filename);
        } catch (Exception $e) {
            Log::error('Manager: error downloading contract PDF', ['contract_id' => $id, 'error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Gagal mengunduh dokumen.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/manager/contracts/{id}/upload-signed
     * Manager upload dokumen PDF yang sudah ditandatangani kedua pihak secara fisik.
     * Setelah upload, pihak kedua menerima email konfirmasi (bukan TTD lagi).
     */
    public function uploadSignedDocument(Request $request, int $id): JsonResponse
    {
        try {
            $request->validate([
                'signed_document' => 'required|file|mimes:pdf|max:20480',
            ]);

            $user = Auth::user();
            $contract = Contract::with(['latestVersion', 'signers', 'creator'])->findOrFail($id);

            // Pastikan user adalah signer kontrak ini
            $signer = ContractSigner::where('contract_id', $id)
                ->where('user_id', $user->id)
                ->first();

            if (!$signer) {
                return response()->json([
                    'message' => 'Anda tidak terdaftar sebagai penandatangan kontrak ini.',
                ], 403);
            }

            $latestVersion = $contract->latestVersion;
            if (!$latestVersion) {
                return response()->json([
                    'message' => 'Kontrak tidak memiliki versi aktif.',
                ], 422);
            }

            // Simpan file PDF yang sudah ditandatangani
            $file = $request->file('signed_document');
            $filename = $id . '_signed_' . time() . '.pdf';
            $signaturePath = $file->storeAs('signed-documents', $filename, 'public');

            // Hitung iterasi
            $iteration = ContractSignerSignature::where('contract_signer_id', $signer->id)
                ->max('iteration') ?? 0;
            $iteration++;

            DB::transaction(function () use ($contract, $signer, $latestVersion, $signaturePath, $iteration, $request, $user) {
                // Simpan record tanda tangan
                ContractSignerSignature::create([
                    'contract_signer_id' => $signer->id,
                    'contract_version_id' => $latestVersion->id,
                    'iteration' => $iteration,
                    'signature_type' => 'upload',
                    'signature_path' => $signaturePath,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'signed_at' => now(),
                ]);

                // Update status kontrak
                $contract->update([
                    'status' => 'approved',
                    'signed_document_path' => $signaturePath,
                ]);

                // Kirim email konfirmasi ke pihak kedua
                $this->dispatchExternalConfirmationEmails($contract, $signaturePath, $iteration);

                // Notifikasi ke HRD
                Notification::create([
                    'user_id' => $contract->created_by,
                    'contract_id' => $contract->id,
                    'type'        => 'signed_document_uploaded',
                    'message'     => "Dokumen kontrak {$contract->contract_number} yang sudah ditandatangani telah diupload. Menunggu konfirmasi pihak eksternal.",
                    'is_read'     => false,
                ]);
            });

            return response()->json([
                'message'         => 'Dokumen berhasil diupload. Pihak eksternal akan menerima email konfirmasi.',
                'contract_status' => $contract->fresh()->status,
            ]);
        } catch (Exception $e) {
            Log::error('Manager: error uploading signed document', [
                'contract_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Gagal mengupload dokumen.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Kirim email konfirmasi ke external signer dengan link untuk menyetujui/menolak.
     * Berbeda dengan dispatchExternalSigningEmails — ini tidak meminta TTD, hanya konfirmasi.
     */
    private function dispatchExternalConfirmationEmails(Contract $contract, string $documentPath, int $iteration): void
    {
        $externalSigners = $contract->signers()
            ->where('signer_type', 'external')
            ->whereNotNull('external_email')
            ->get();

        foreach ($externalSigners as $signer) {
            // Nonaktifkan token lama
            ExternalSignatureToken::where('contract_signer_id', $signer->id)
                ->whereNull('used_at')
                ->update(['expired_at' => now()]);

            // Buat token baru
            $token = Str::random(64);

            ExternalSignatureToken::create([
                'contract_signer_id' => $signer->id,
                'token' => $token,
                'iteration' => $iteration,
                'review_status' => 'pending',
                'expired_at' => now()->addDays(7),
            ]);

            // URL konfirmasi (bukan URL TTD)
            $confirmUrl = config('app.frontend_url') . '/external/confirm?token=' . $token;

            try {
                // Gunakan ExternalSigningRequestMail yang sudah ada,
                // atau buat ExternalConfirmationMail baru jika ingin teks berbeda
                Mail::to($signer->external_email)
                    ->send(new ExternalSigningRequestMail($contract, $confirmUrl, $iteration));
            } catch (Exception $e) {
                Log::error('Gagal mengirim email konfirmasi eksternal', [
                    'contract_id' => $contract->id,
                    'email' => $signer->external_email,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
