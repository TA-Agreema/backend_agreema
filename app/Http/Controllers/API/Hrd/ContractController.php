<?php

namespace App\Http\Controllers\API\Hrd;

use Exception;
use App\Models\User;
use App\Models\Contract;
use App\Models\Template;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use App\Models\Notification;
use Illuminate\Http\Request;
use App\Models\ContractSigner;
use App\Models\FieldDefinition;
use App\Models\ExternalSignatureToken;
use App\Mail\ExternalSigningRequestMail;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Http\Resources\Contract\ContractResource;
use App\Http\Requests\Contract\StoreContractRequest;
use App\Http\Requests\Contract\UpdateContractRequest;

class ContractController extends Controller
{
    /** Menyediakan generator nomor untuk alur pembuatan dan perpanjangan kontrak. */
    public function __construct(
        private readonly ContractNumberController $contractNumberController,
    ) {}

    /**
     * GET /api/contracts
     * List kontrak untuk ContractListPage.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Contract::query()
                ->with([
                    'template.category:id,name',
                    'creator:id,name',
                    'addendums:id,contract_id,addendum_number,title,description,document_path,effective_date,created_at',
                    'termination',
                    'parentContract:id,contract_number,title',
                    'signers.reviews',
                    'signers.signatureTokens',
                    'latestVersion.fieldValues.fieldDefinition',
                ])
                ->withCount([
                    'childContracts',
                    'childContracts as open_renewal_count' => fn($query) =>
                    $query->whereIn('status', ['draft', 'review', 'revision', 'approved', 'signed', 'active']),
                ])
                ->orderByDesc('created_at');

            // Secara default hanya menampilkan kontrak internal.
            // Jika parameter include_external=1 dikirim (dipakai halaman Aktif/Arsip),
            // tampilkan juga kontrak mitra (contract_type = 'external').
            if (!$request->boolean('include_external')) {
                $query->where('contract_type', 'internal');
            };

            $user = $request->user();
            if (!$user->hasRole('admin')) {
                $query->where(function ($q) use ($user) {
                    $q->where('created_by', $user->id)
                        ->orWhereHas('signers', function ($signerQuery) use ($user) {
                            $signerQuery->where('signer_type', 'internal')
                                ->where('user_id', $user->id);
                        });
                });
            }

            // filter berdasarkan parameter 'archive'
            if ($request->boolean('archive')) {
                // Halaman arsip: hanya tampilkan rejected, terminated, & expired
                $query->whereIn('status', ['rejected', 'terminated', 'expired']);
            } else {
                // Daftar kontrak: sembunyikan rejected, terminated, & expired
                $query->whereNotIn('status', ['rejected', 'terminated', 'expired']);
            }

            $validated = $request->validate([
                'search' => 'nullable|string|max:255',
            ]);

            if ($search = $validated['search'] ?? null) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhereHas('template.category', function ($cat) use ($search) {
                            $cat->where('name', 'like', "%{$search}%");
                        })
                        ->orWhere('partner_name', 'like', "%{$search}%");
                });
            }

            $contracts = $query->get();
            $data = ContractResource::collection($contracts)->resolve();

            return response()->json([
                'message' => 'Contracts retrieved successfully',
                'data' => $data,
            ]);
        } catch (Exception $e) {
            Log::error('Error retrieving contracts', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'An error occurred while retrieving contracts',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/contracts
     * Create a new contract
     */
    public function store(StoreContractRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            // Auto-generate contract_number if not supplied
            if (empty($validated['contract_number'])) {
                $categoryId = isset($validated['category_id']) ? (int) $validated['category_id'] : null;
                // If category_id not provided, try derive from template (if template_id provided)
                if (empty($categoryId) && !empty($validated['template_id'])) {
                    $tpl = Template::find($validated['template_id']);
                    if ($tpl && !empty($tpl->category_id)) {
                        $categoryId = (int) $tpl->category_id;
                    }
                }
                $validated['contract_number'] = $this->contractNumberController
                    ->generateContractNumber($categoryId);
            }

            if (empty($validated['paper_size'])) {
                $template = !empty($validated['template_id'])
                    ? Template::find($validated['template_id'])
                    : null;
                $validated['paper_size'] = $template?->paper_size ?? 'f4';
            }

            if (!empty($validated['parent_contract_id'])) {
                $parentContract = Contract::findOrFail($validated['parent_contract_id']);
                if (Gate::denies('view', $parentContract)) {
                    return $this->forbiddenContractResponse();
                }
            }

            $contract = DB::transaction(function () use ($validated) {
                if (!empty($validated['parent_contract_id'])) {
                    $parentContract = Contract::query()
                        ->lockForUpdate()
                        ->findOrFail($validated['parent_contract_id']);

                    if (!in_array($parentContract->status, ['active', 'expired'], true)) {
                        throw ValidationException::withMessages([
                            'parent_contract_id' => 'Hanya kontrak aktif atau berakhir yang dapat dijadikan dasar kontrak baru.',
                        ]);
                    }

                    $hasOpenRenewal = $parentContract->childContracts()
                        ->whereIn('status', ['draft', 'review', 'revision', 'approved', 'signed', 'active'])
                        ->exists();

                    if ($hasOpenRenewal) {
                        throw ValidationException::withMessages([
                            'parent_contract_id' => 'Kontrak turunan untuk kontrak ini masih diproses.',
                        ]);
                    }
                }

                $contract = Contract::create(array_merge(Arr::except($validated, ['content', 'field_values', 'signers']), [
                    'created_by' => Auth::id(),
                ]));

                // Insert signers if provided
                if (!empty($validated['signers']) && is_array($validated['signers'])) {
                    foreach ($validated['signers'] as $index => $signerData) {
                        if (empty(trim($signerData['name'] ?? ''))) {
                            continue;
                        }

                        $userId = null;
                        if ($signerData['type'] === 'internal') {
                            $userId = $this->resolveActiveManagerSignerIdByName($signerData['name'] ?? null);
                            if (!$userId) {
                                throw ValidationException::withMessages([
                                    "signers.{$index}.name" => 'Penandatangan internal harus user manager yang aktif.',
                                ]);
                            }
                        }

                        ContractSigner::create([
                            'contract_id' => $contract->id,
                            'user_id' => $userId,
                            'signer_type' => $signerData['type'],
                            'signer_name' => $signerData['type'] === 'external' ? ($signerData['name'] ?? null) : null,
                            'signer_role' => $signerData['type'] === 'external' ? ($signerData['title'] ?? null) : null,
                            'external_email' => $signerData['type'] === 'external' ? ($signerData['email'] ?? null) : null,
                            'sequence' => $index + 1,
                        ]);
                    }
                }
                if (!empty($validated['content'])) {
                    $version = $contract->versions()->create([
                        'version_number' => 'V1',
                        'content' => $validated['content'],
                        'created_by' => Auth::id(),
                    ]);

                    $fieldValues = $this->normalizeContractFieldValues($validated['field_values'] ?? []);
                    if (!empty($fieldValues)) {
                        $version->fieldValues()->createMany($fieldValues);
                    }
                }
                return $contract;
            });

            $contract->load([
                'template.category:id,name',
                'creator:id,name',
                'signers.user',
                'signers.reviews',
                'statusLogs.changedBy:id,name',
                'addendums:id,contract_id,addendum_number,title,description,document_path,effective_date,created_at',
                'termination',
                'versions.creator:id,name',
            ]);

            return response()->json([
                'message' => 'Contract created successfully',
                'data' => new ContractResource($contract),
            ], 201);
        } catch (ValidationException $e) {
            throw $e;
        } catch (Exception $e) {
            Log::error('Error creating contract', [
                'error' => $e->getMessage(),
                'input' => $request->all(),
            ]);

            return response()->json([
                'message' => 'An error occurred while creating contract',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/contracts/{id}
     * Show contract detail
     */
    public function show(int $id): JsonResponse
    {
        try {
            $contract = Contract::with([
                'template.category:id,name',
                'creator:id,name',
                'signers.user',
                'signers.reviews',
                'signers.signatures',
                'statusLogs.changedBy:id,name',
                'addendums:id,contract_id,addendum_number,title,description,document_path,effective_date,created_at',
                'termination',
                'parentContract:id,contract_number,title',
                'versions.creator:id,name',
            ])->withCount([
                'childContracts',
                'childContracts as open_renewal_count' => fn($query) =>
                $query->whereIn('status', ['draft', 'review', 'revision', 'approved', 'signed', 'active']),
            ])->findOrFail($id);

            if (Gate::denies('view', $contract)) {
                return $this->forbiddenContractResponse();
            }

            return response()->json([
                'message' => 'Contract retrieved successfully',
                'data' => new ContractResource($contract),
            ]);
        } catch (Exception $e) {
            Log::error('Error retrieving contract', [
                'contract_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Contract not found',
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * POST /api/contracts/{id}/renew
     * Create a new draft contract from the latest approved/final contract version.
     */
    public function renew(int $id): JsonResponse
    {
        try {
            $sourceContract = Contract::with([
                'template:id,category_id,paper_size',
                'latestVersion.fieldValues',
                'signers',
            ])->findOrFail($id);

            if (Gate::denies('view', $sourceContract)) {
                return $this->forbiddenContractResponse();
            }

            if (!in_array($sourceContract->status, ['active', 'expired'], true)) {
                return response()->json([
                    'message' => 'Hanya kontrak aktif atau berakhir yang dapat dijadikan dasar kontrak baru.',
                ], 422);
            }

            if ($sourceContract->childContracts()
                ->whereIn('status', ['draft', 'review', 'revision', 'approved', 'signed', 'active'])
                ->exists()
            ) {
                return response()->json([
                    'message' => 'Kontrak turunan untuk kontrak ini masih diproses.',
                ], 422);
            }

            $latestVersion = $sourceContract->latestVersion;
            if (!$latestVersion) {
                return response()->json([
                    'message' => 'Kontrak belum memiliki versi dokumen yang dapat diperpanjang.',
                ], 422);
            }

            $renewedContract = DB::transaction(function () use ($sourceContract, $latestVersion) {
                $categoryId = $sourceContract->template?->category_id;

                $contract = Contract::create([
                    'parent_contract_id' => $sourceContract->id,
                    'contract_number' => $this->contractNumberController
                        ->generateContractNumber($categoryId),
                    'external_contract_number' => null,
                    'contract_type' => $sourceContract->contract_type ?? 'internal',
                    'title' => $sourceContract->title,
                    'partner_name' => $sourceContract->partner_name,
                    'paper_size' => $sourceContract->paper_size ?? $sourceContract->template?->paper_size ?? 'a4',
                    'start_date' => null,
                    'end_date' => null,
                    'status' => 'draft',
                    'template_id' => $sourceContract->template_id,
                    'created_by' => Auth::id(),
                ]);

                $version = $contract->versions()->create([
                    'version_number' => 'V1',
                    'content' => $latestVersion->content,
                    'created_by' => Auth::id(),
                ]);

                $fieldValues = $latestVersion->fieldValues
                    ->map(fn($fieldValue) => [
                        'field_definition_id' => $fieldValue->field_definition_id,
                        'value' => $fieldValue->value,
                    ])
                    ->values()
                    ->all();

                if (!empty($fieldValues)) {
                    $version->fieldValues()->createMany($fieldValues);
                }

                $sourceContract->signers
                    ->sortBy('sequence')
                    ->values()
                    ->each(function ($signer, int $index) use ($contract) {
                        ContractSigner::create([
                            'contract_id' => $contract->id,
                            'user_id' => $signer->user_id,
                            'external_email' => $signer->external_email,
                            'signer_type' => $signer->signer_type,
                            'signer_name' => $signer->signer_name,
                            'signer_role' => $signer->signer_role,
                            'sequence' => $signer->sequence ?? ($index + 1),
                        ]);
                    });

                return $contract;
            });

            $renewedContract->load([
                'template.category:id,name',
                'creator:id,name',
                'parentContract:id,contract_number,title',
                'signers.user',
                'signers.reviews',
                'statusLogs.changedBy:id,name',
                'addendums:id,contract_id,addendum_number,title,description,document_path,effective_date,created_at',
                'termination',
                'versions.creator:id,name',
                'latestVersion.fieldValues.fieldDefinition',
            ]);
            $renewedContract->loadCount('childContracts');

            return response()->json([
                'message' => 'Draft renewal contract created successfully',
                'data' => new ContractResource($renewedContract),
            ], 201);
        } catch (Exception $e) {
            Log::error('Error renewing contract', [
                'contract_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'An error occurred while renewing contract',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/contracts/{id}/download
     * Generate dan download kontrak sebagai PDF dari editor kontrak.
     */
    public function download(int $id)
    {
        try {
            $contract = Contract::with([
                'latestVersion',
                'creator:id,name',
                'signers.user:id,name,job_title',
                'template.category:id,name',
            ])->findOrFail($id);

            if (Gate::denies('view', $contract)) {
                return $this->forbiddenContractResponse();
            }

            if ($contract->signed_document_path) {
                $filePath = storage_path('app/public/' . $contract->signed_document_path);
                if (file_exists($filePath)) {
                    $filename = $this->makePdfFilename($contract, true);

                    return response()->download($filePath, $filename, [
                        'Content-Type' => 'application/pdf',
                    ]);
                }
            }

            $content = $contract->latestVersion?->content ?? '<p>Konten tidak tersedia.</p>';
            $html = view('pdf.contract', [
                'contract' => $contract,
                'content'  => $content,
            ])->render();

            $pdf = Pdf::loadHTML($html)
                ->setPaper(($contract->paper_size ?? 'f4') === 'f4' ? [0, 0, 609.45, 935.43] : 'a4', 'portrait')
                ->setOptions([
                    'defaultFont' => 'sans-serif',
                    'isRemoteEnabled' => false,
                    'isHtml5ParserEnabled' => true,
                ]);

            $filename = $this->makePdfFilename($contract);

            return $pdf->download($filename);
        } catch (Exception $e) {
            Log::error('Error downloading contract PDF', [
                'contract_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Gagal mengunduh dokumen.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    /** Membentuk nama file PDF yang aman dari nomor atau judul kontrak. */
    private function makePdfFilename(Contract $contract, bool $signed = false): string
    {
        $baseName = $contract->title ?: $contract->contract_number ?: 'kontrak-' . $contract->id;
        $filename = Str::slug($baseName, '-');

        if (empty($filename)) {
            $filename = 'kontrak-' . $contract->id;
        }

        return $signed ? $filename . '-signed.pdf' : $filename . '.pdf';
    }


    /**
     * PATCH /api/contracts/{id}
     * Update contract
     */
    public function update(UpdateContractRequest $request, int $id): JsonResponse
    {
        try {
            $contract = Contract::findOrFail($id);
            $validated = $request->validated();

            if (Gate::denies('update', $contract)) {
                return $this->forbiddenContractResponse();
            }

            if (!in_array($contract->status, ['draft', 'revision'])) {
                return response()->json([
                    'message' => 'Hanya kontrak dengan status draft atau revision yang dapat diubah.'
                ], 422);
            }

            DB::transaction(function () use ($contract, $validated) {
                $contract->update(Arr::except($validated, ['content', 'field_values', 'parent_contract_id', 'signers']));

                // Update signers if provided
                if (array_key_exists('signers', $validated) && is_array($validated['signers'])) {
                    $existingSigners = ContractSigner::where('contract_id', $contract->id)->get();
                    $keptSignerIds = [];

                    foreach ($validated['signers'] as $index => $signerData) {
                        if (empty(trim($signerData['name'] ?? ''))) {
                            continue;
                        }

                        $userId = null;
                        if ($signerData['type'] === 'internal') {
                            $userId = $this->resolveActiveManagerSignerIdByName($signerData['name'] ?? null);
                            if (!$userId) {
                                throw ValidationException::withMessages([
                                    "signers.{$index}.name" => 'Penandatangan internal harus user manager yang aktif.',
                                ]);
                            }
                        }


                        $existing = null;
                        if ($signerData['type'] === 'internal' && $userId) {
                            $existing = $existingSigners->where('signer_type', 'internal')->where('user_id', $userId)->first();
                        } elseif ($signerData['type'] === 'external') {
                            $existing = $existingSigners->where('signer_type', 'external')->where('external_email', $signerData['email'])->first();
                        }

                        if ($existing) {
                            $existing->update([
                                'sequence' => $index + 1,
                                'signer_role' => $signerData['type'] === 'external' ? ($signerData['title'] ?? null) : null,
                                'signer_name' => $signerData['type'] === 'external' ? ($signerData['name'] ?? null) : null,
                            ]);
                            $keptSignerIds[] = $existing->id;
                        } else {
                            $newSigner = ContractSigner::create([
                                'contract_id' => $contract->id,
                                'user_id' => $userId,
                                'signer_type' => $signerData['type'],
                                'signer_name' => $signerData['type'] === 'external' ? ($signerData['name'] ?? null) : null,
                                'signer_role' => $signerData['type'] === 'external' ? ($signerData['title'] ?? null) : null,
                                'external_email' => $signerData['type'] === 'external' ? ($signerData['email'] ?? null) : null,
                                'sequence' => $index + 1,
                            ]);
                            $keptSignerIds[] = $newSigner->id;
                        }
                    }

                    // Delete signers that are no longer part of the contract
                    ContractSigner::where('contract_id', $contract->id)
                        ->whereNotIn('id', $keptSignerIds)
                        ->delete();
                }
                if (!empty($validated['content'])) {
                    $latestVersion = $contract->latestVersion;

                    // Create new version only if content changed
                    if (!$latestVersion || $latestVersion->content !== $validated['content']) {
                        $nextVersion = $contract->versions()->count() + 1;
                        $version = $contract->versions()->create([
                            'version_number' => 'V' . $nextVersion,
                            'content' => $validated['content'],
                            'created_by' => Auth::id(),
                        ]);

                        $fieldValues = $this->normalizeContractFieldValues($validated['field_values'] ?? []);
                        if (!empty($fieldValues)) {
                            $version->fieldValues()->createMany($fieldValues);
                        }
                    } elseif ($latestVersion && array_key_exists('field_values', $validated)) {
                        $latestVersion->fieldValues()->delete();

                        $fieldValues = $this->normalizeContractFieldValues($validated['field_values'] ?? []);
                        if (!empty($fieldValues)) {
                            $latestVersion->fieldValues()->createMany($fieldValues);
                        }
                    }
                }
            });

            $contract->load([
                'template.category:id,name',
                'creator:id,name',
                'signers.user',
                'signers.reviews',
                'statusLogs.changedBy:id,name',
                'addendums:id,contract_id,addendum_number,title,description,document_path,effective_date,created_at',
                'termination',
                'versions.creator:id,name',
            ]);

            return response()->json([
                'message' => 'Contract updated successfully',
                'data' => new ContractResource($contract),
            ]);
        } catch (Exception $e) {
            Log::error('Error updating contract', [
                'contract_id' => $id,
                'error' => $e->getMessage(),
                'input' => $request->all(),
            ]);

            return response()->json([
                'message' => 'An error occurred while updating contract',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * DELETE /api/contracts/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $contract = Contract::findOrFail($id);

            if (Gate::denies('delete', $contract)) {
                return $this->forbiddenContractResponse();
            }

            $contract->delete();

            return response()->json([
                'message' => 'Contract deleted successfully',
            ]);
        } catch (Exception $e) {
            Log::error('Error deleting contract', [
                'contract_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'An error occurred while deleting contract',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * PATCH /api/contracts/{id}/toggle-status
     * Update contract status (set to provided status)
     */
    public function toggleStatus(UpdateContractRequest $request, int $id): JsonResponse
    {
        try {
            $contract = Contract::findOrFail($id);
            $validated = $request->validated();

            if (Gate::denies('update', $contract)) {
                return $this->forbiddenContractResponse();
            }

            if (!array_key_exists('status', $validated)) {
                return response()->json([
                    'message' => 'Status is required',
                ], 422);
            }

            $contract->update(['status' => $validated['status']]);

            $contract->load(['template.category:id,name', 'creator:id,name']);

            return response()->json([
                'message' => 'Contract status updated successfully',
                'data' => new ContractResource($contract),
            ]);
        } catch (Exception $e) {
            Log::error('Error toggling contract status', [
                'contract_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'An error occurred while updating contract status',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/contracts/{id}/submit
     * Submit a contract for review
     */
    public function submit(int $id): JsonResponse
    {
        try {
            $contract = Contract::findOrFail($id);

            if (Gate::denies('submit', $contract)) {
                return $this->forbiddenContractResponse();
            }

            if ($contract->status !== 'draft' && $contract->status !== 'revision') {
                return response()->json([
                    'message' => 'Only draft or revision contracts can be submitted for review',
                ], 422);
            }

            if (!$contract->start_date || !$contract->end_date) {
                return response()->json([
                    'message' => 'Tanggal mulai dan tanggal selesai wajib diisi sebelum kontrak diajukan.',
                ], 422);
            }

            if (blank($contract->partner_name)) {
                return response()->json([
                    'message' => 'Nama mitra wajib diisi sebelum kontrak diajukan.',
                ], 422);
            }
            $signers = ContractSigner::where('contract_id', $id)->get();

            $signerCompositionError = $this->validateSubmitSignerComposition($signers);
            if ($signerCompositionError) {
                return response()->json([
                    'message' => $signerCompositionError,
                ], 422);
            }

            $invalidExternalSigner = $signers->first(function ($signer) {
                return $signer->signer_type === 'external'
                    && (
                        blank($signer->signer_name)
                        || blank($signer->signer_role)
                        || blank($signer->external_email)
                        || !filter_var($signer->external_email, FILTER_VALIDATE_EMAIL)
                    );
            });

            if ($invalidExternalSigner) {
                return response()->json([
                    'message' => 'External signer must have a valid name, role, and email before submission',
                ], 422);
            }

            $missingRequiredFields = $this->getMissingRequiredContractFields($contract);
            if (!empty($missingRequiredFields)) {
                return response()->json([
                    'message' => 'Field wajib belum diisi: ' . implode(', ', $missingRequiredFields) . '.',
                ], 422);
            }

            $contract->update(['status' => 'review']);

            // Notify signers (Manager)
            foreach ($signers as $signer) {
                if ($signer->user_id && $signer->signer_type === 'internal') {
                    Notification::create([
                        'user_id' => $signer->user_id,
                        'contract_id' => $contract->id,
                        'type' => 'review_requested',
                        'message' => "{$contract->title} memerlukan peninjauan anda.",
                        'is_read' => false,
                    ]);
                }
            }

            Notification::create([
                'user_id'     => $contract->created_by,
                'contract_id' => $contract->id,
                'type'        => 'contract_submitted',
                'message'     => "{$contract->title} berhasil diajukan dan sedang menunggu peninjauan.",
                'is_read'     => false,
            ]);

            return response()->json([
                'message' => 'Contract submitted for review successfully',
                'data' => new ContractResource($contract),
            ]);
        } catch (Exception $e) {
            Log::error('Error submitting contract', [
                'contract_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'An error occurred while submitting contract',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /** Menormalisasi nilai field kontrak sebelum disimpan pada versi kontrak. */
    private function normalizeContractFieldValues(array $fieldValues): array
    {
        $normalized = [];

        foreach ($fieldValues as $fieldValue) {
            $fieldDefinitionId = (int) ($fieldValue['field_definition_id'] ?? 0);
            if ($fieldDefinitionId <= 0) {
                continue;
            }

            $value = $fieldValue['value'] ?? null;
            $value = is_string($value) ? trim($value) : $value;

            $normalized[$fieldDefinitionId] = [
                'field_definition_id' => $fieldDefinitionId,
                'value' => $value === '' ? null : $value,
            ];
        }

        return array_values($normalized);
    }
    /** Mengumpulkan field wajib yang belum memiliki nilai pada dokumen kontrak. */
    private function getMissingRequiredContractFields(Contract $contract): array
    {
        $latestVersion = $contract->latestVersion()
            ->with('fieldValues.fieldDefinition')
            ->first();

        if (!$latestVersion) {
            return [];
        }

        $content = $latestVersion->content ?? '';
        $usedFieldIds = $this->extractUsedFieldIdsFromContent($content);
        $fieldValuesById = $latestVersion->fieldValues->keyBy('field_definition_id');

        foreach ($fieldValuesById->keys() as $fieldDefinitionId) {
            $usedFieldIds[] = (int) $fieldDefinitionId;
        }

        $usedFieldIds = array_values(array_unique(array_filter($usedFieldIds)));
        if (empty($usedFieldIds)) {
            return [];
        }

        $requiredFields = FieldDefinition::query()
            ->whereIn('id', $usedFieldIds)
            ->where('is_required', true)
            ->get();

        return $requiredFields
            ->filter(function (FieldDefinition $field) use ($fieldValuesById) {
                $fieldValue = $fieldValuesById->get($field->id);

                return !$fieldValue ||
                    $this->isMissingRequiredFieldValue($fieldValue->value, $field);
            })
            ->pluck('field_label')
            ->values()
            ->all();
    }

    /** Mengekstrak ID field yang benar-benar digunakan dari HTML kontrak. */
    private function extractUsedFieldIdsFromContent(string $content): array
    {
        preg_match_all('/data-contract-field-id=["\'](\d+)["\']/', $content, $idMatches);
        $fieldIds = array_map('intval', $idMatches[1] ?? []);

        preg_match_all('/{{\s*([a-zA-Z0-9_]+)\s*}}/', $content, $keyMatches);
        $fieldKeys = array_unique($keyMatches[1] ?? []);

        if (!empty($fieldKeys)) {
            $keyFieldIds = FieldDefinition::query()
                ->whereIn('field_key', $fieldKeys)
                ->pluck('id')
                ->map(fn($id) => (int) $id)
                ->all();

            $fieldIds = array_merge($fieldIds, $keyFieldIds);
        }

        return array_values(array_unique($fieldIds));
    }

    /** Menentukan apakah nilai sebuah field wajib masih dianggap kosong. */
    private function isMissingRequiredFieldValue(?string $value, FieldDefinition $field): bool
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ||
            strcasecmp($trimmed, '[' . $field->field_label . ']') === 0 ||
            preg_match('/^{{\s*' . preg_quote($field->field_key, '/') . '\s*}}$/i', $trimmed);
    }

    /**
     * Validasi final sebelum submit: kontrak harus memiliki tepat 2 signer,
     * dengan kombinasi 2 internal atau 1 internal + 1 eksternal.
     */
    private function validateSubmitSignerComposition($signers): ?string
    {
        $total = $signers->count();
        $internalCount = $signers->where('signer_type', 'internal')->count();
        $externalCount = $signers->where('signer_type', 'external')->count();

        if ($total !== 2) {
            return 'Kontrak harus memiliki tepat 2 penandatangan sebelum diajukan.';
        }

        $validComposition = ($internalCount === 2 && $externalCount === 0)
            || ($internalCount === 1 && $externalCount === 1);

        if (!$validComposition) {
            return 'Kombinasi penandatangan hanya boleh 2 internal atau 1 internal dan 1 eksternal.';
        }

        $invalidInternalSigner = $signers->first(function ($signer) {
            return $signer->signer_type === 'internal' && !$signer->user_id;
        });

        if ($invalidInternalSigner) {
            return 'Penandatangan internal wajib terhubung dengan akun user yang valid.';
        }

        return null;
    }

    /** Mengambil ID manager aktif berdasarkan nama signer internal. */
    private function resolveActiveManagerSignerIdByName(?string $name): ?int
    {
        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }

        return User::query()
            ->role('manager')
            ->where('is_active', 1)
            ->where('name', $name)
            ->value('id');
    }

    /**
     * POST /api/contracts/{id}/resend-signing
     * Kirim ulang tautan tanda tangan ke semua signer eksternal yang belum
     * menyelesaikan proses (used_at masih null), misalnya karena token
     * sebelumnya sudah kedaluwarsa atau email tidak diterima.
     * Dipicu oleh HRD (pembuat kontrak), bukan manager.
     */
    public function resendExternalSigning(int $id): JsonResponse
    {
        try {
            $contract = Contract::with('signers')->findOrFail($id);

            if (Gate::denies('submit', $contract)) {
                return $this->forbiddenContractResponse();
            }

            if (!in_array($contract->status, ['approved', 'review'])) {
                return response()->json([
                    'message' => 'Hanya kontrak yang sedang menunggu tanda tangan eksternal yang dapat dikirim ulang.',
                ], 422);
            }

            $hasExternal = $contract->signers->where('signer_type', 'external')->isNotEmpty();
            if (!$hasExternal) {
                return response()->json([
                    'message' => 'Kontrak ini tidak memiliki pihak eksternal yang perlu menandatangani.',
                ], 422);
            }

            $latestIteration = ExternalSignatureToken::whereHas('contractSigner', function ($q) use ($contract) {
                $q->where('contract_id', $contract->id);
            })->max('iteration') ?? 0;

            $this->dispatchExternalSigningEmails($contract, $latestIteration + 1);

            return response()->json([
                'message' => 'Tautan tanda tangan berhasil dikirim ulang ke pihak eksternal.',
            ]);
        } catch (Exception $e) {
            Log::error('Gagal mengirim ulang tautan signing eksternal', [
                'contract_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Terjadi kesalahan saat mengirim ulang tautan.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Nonaktifkan token lama & buat token baru untuk semua signer eksternal,
     * lalu kirim email permintaan tanda tangan.
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
                    ->send(new ExternalSigningRequestMail($contract, $signingUrl, $iteration, $signer->signer_name));
            } catch (Exception $e) {
                Log::error('Gagal mengirim email external signing', [
                    'contract_id' => $contract->id,
                    'email' => $signer->external_email,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function forbiddenContractResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'Anda tidak memiliki akses ke kontrak ini.',
        ], 403);
    }
}
