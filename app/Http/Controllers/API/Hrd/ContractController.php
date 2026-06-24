<?php

namespace App\Http\Controllers\API\Hrd;

use Exception;
use App\Models\User;
use App\Models\Party;
use App\Models\Contract;
use App\Models\Template;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use App\Models\Notification;
use Illuminate\Http\Request;
use App\Models\ContractParty;
use App\Models\ContractSigner;
use App\Models\ContractCategory;
use Illuminate\Http\JsonResponse;
use App\Models\PartyCompanyDetail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Http\Resources\Contract\ContractResource;
use App\Http\Requests\Contract\StoreContractRequest;
use App\Http\Requests\Contract\UpdateContractRequest;

class ContractController extends Controller
{
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
                    'signers.reviews',
                    'parties.party.individualDetail:id,party_id,full_name',
                    'parties.party.companyDetail:id,party_id,company_name',
                    'latestVersion.fieldValues.fieldDefinition',
                ])
                ->orderByDesc('created_at');

            // Secara default hanya menampilkan kontrak internal.
            // Jika parameter include_external=1 dikirim (dipakai halaman Aktif/Arsip),
            // tampilkan juga kontrak mitra (contract_type = 'external').
            if (!$request->boolean('include_external')) {
                $query->where('contract_type', 'internal');
            };

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
                        ->orWhereHas('parties.party', function ($party) use ($search) {
                            $party->whereHas('individualDetail', function ($individual) use ($search) {
                                $individual->where('full_name', 'like', "%{$search}%");
                            })->orWhereHas('companyDetail', function ($company) use ($search) {
                                $company->where('company_name', 'like', "%{$search}%");
                            });
                        });
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
                $validated['contract_number'] = $this->generateContractNumber($categoryId);
            }

            if (empty($validated['paper_size'])) {
                $template = !empty($validated['template_id'])
                    ? Template::find($validated['template_id'])
                    : null;
                $validated['paper_size'] = $template?->paper_size ?? 'f4';
            }

            $contract = DB::transaction(function () use ($validated) {
                $contract = Contract::create(array_merge(Arr::except($validated, ['content', 'field_values', 'partner_id', 'partner_name', 'parent_contract_id', 'signers']), [
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
                            $user = User::where('name', $signerData['name'])->first();
                            if ($user) $userId = $user->id;
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
                if (!empty($validated['partner_id'])) {
                    ContractParty::create([
                        'contract_id' => $contract->id,
                        'party_id' => (int) $validated['partner_id'],
                        'party_order' => 2,
                    ]);
                } elseif (!empty($validated['partner_name'])) {
                    $name = trim($validated['partner_name']);
                    $existing = Party::whereHas('companyDetail', function ($q) use ($name) {
                        $q->whereRaw('LOWER(company_name) = ?', [strtolower($name)]);
                    })->first();

                    if (!$existing) {
                        $party = Party::create(['party_type' => 'company']);
                        PartyCompanyDetail::create([
                            'party_id' => $party->id,
                            'company_name' => $name,
                            'address' => null,
                        ]);
                        $partyId = $party->id;
                    } else {
                        $partyId = $existing->id;
                    }

                    if (!empty($partyId)) {
                        ContractParty::create([
                            'contract_id' => $contract->id,
                            'party_id' => $partyId,
                            'party_order' => 2,
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
                'parties.party.individualDetail:id,party_id,full_name',
                'parties.party.companyDetail:id,party_id,company_name',
                'versions.creator:id,name',
            ]);

            return response()->json([
                'message' => 'Contract created successfully',
                'data' => new ContractResource($contract),
            ], 201);
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
     * @param int|null $categoryId
     * @return string
     */
    private function generateContractNumber(?int $categoryId = null): string
    {
        $prefix = $this->resolveContractNumberPrefix($categoryId);
        $year = now()->year;
        $month = now()->month;
        $romanMonth = $this->getRomanMonth($month);
        $sequence = $this->getNextContractSequence($prefix, $year, $month);
        $candidate = $this->buildContractNumber($prefix, $sequence, $romanMonth, $year);

        while (Contract::where('contract_number', $candidate)->exists()) {
            $sequence++;
            $candidate = $this->buildContractNumber($prefix, $sequence, $romanMonth, $year);
        }

        return $candidate;
    }

    private function resolveContractNumberPrefix(?int $categoryId): string
    {
        if (!$categoryId) {
            return 'SPK';
        }

        $category = ContractCategory::find($categoryId);
        if (!$category) {
            return 'SPK';
        }

        if (!empty($category->number_prefix)) {
            return strtoupper(trim($category->number_prefix));
        }

        return $this->buildPrefixFromCategoryName($category->name);
    }

    private function buildPrefixFromCategoryName(?string $categoryName): string
    {
        if (empty($categoryName)) {
            return 'SPK';
        }

        $words = preg_split('/[^\p{L}\p{N}]+/u', trim($categoryName)) ?: [];
        $letters = [];

        foreach ($words as $word) {
            $word = trim($word);
            if ($word === '') {
                continue;
            }

            $letters[] = mb_substr($word, 0, 1, 'UTF-8');
        }

        return count($letters) > 0 ? strtoupper(implode('', $letters)) : 'SPK';
    }

    private function getRomanMonth(int $month): string
    {
        $romanMonths = [
            1 => 'I',
            2 => 'II',
            3 => 'III',
            4 => 'IV',
            5 => 'V',
            6 => 'VI',
            7 => 'VII',
            8 => 'VIII',
            9 => 'IX',
            10 => 'X',
            11 => 'XI',
            12 => 'XII',
        ];

        return $romanMonths[$month] ?? 'I';
    }

    private function getNextContractSequence(string $prefix, int $year, int $month): int
    {
        $count = Contract::whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->where('contract_number', 'like', "{$prefix}-%")
            ->count();

        return $count + 1;
    }

    private function buildContractNumber(string $prefix, int $sequence, string $romanMonth, int $year): string
    {
        $sequenceNumber = str_pad($sequence, 3, '0', STR_PAD_LEFT);

        return "{$prefix}-{$sequenceNumber}/SLAB/{$romanMonth}/{$year}";
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
                'parties.party.individualDetail:id,party_id,full_name',
                'parties.party.companyDetail:id,party_id,company_name',
                'versions.creator:id,name',
            ])->findOrFail($id);

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
                'parties.party.individualDetail:id,party_id,full_name',
                'parties.party.companyDetail:id,party_id,company_name',
            ])->findOrFail($id);

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

            if (!in_array($contract->status, ['draft', 'revision'])) {
                return response()->json([
                    'message' => 'Hanya kontrak dengan status draft atau revision yang dapat diubah.'
                ], 422);
            }

            DB::transaction(function () use ($contract, $validated) {
                $contract->update(Arr::except($validated, ['content', 'field_values', 'partner_id', 'partner_name', 'parent_contract_id', 'signers']));

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
                            $user = User::where('name', $signerData['name'])->first();
                            if ($user) $userId = $user->id;
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

                // Update or create partner ContractParty (party_order = 2)
                if (array_key_exists('partner_id', $validated) || array_key_exists('partner_name', $validated)) {
                    $partnerId = null;

                    if (array_key_exists('partner_id', $validated) && $validated['partner_id']) {
                        $partnerId = (int) $validated['partner_id'];
                    } elseif (array_key_exists('partner_name', $validated) && $validated['partner_name']) {
                        $name = trim($validated['partner_name']);
                        $existing = Party::whereHas('companyDetail', function ($q) use ($name) {
                            $q->whereRaw('LOWER(company_name) = ?', [strtolower($name)]);
                        })->first();
                        if (!$existing) {
                            $party = Party::create(['party_type' => 'company']);
                            PartyCompanyDetail::create([
                                'party_id' => $party->id,
                                'company_name' => $name,
                                'address' => null,
                            ]);
                            $partnerId = $party->id;
                        } else {
                            $partnerId = $existing->id;
                        }
                    }

                    $existingLink = $contract->parties()->where('party_order', 2)->first();
                    if ($partnerId && $existingLink) {
                        $existingLink->update(['party_id' => $partnerId]);
                    } elseif ($partnerId && !$existingLink) {
                        ContractParty::create([
                            'contract_id' => $contract->id,
                            'party_id' => $partnerId,
                            'party_order' => 2,
                        ]);
                    } elseif (!$partnerId && $existingLink) {
                        // remove partner link if partner_id null
                        $existingLink->delete();
                    }
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
                'parties.party.individualDetail:id,party_id,full_name',
                'parties.party.companyDetail:id,party_id,company_name',
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

            if ($contract->status !== 'draft' && $contract->status !== 'revision') {
                return response()->json([
                    'message' => 'Only draft or revision contracts can be submitted for review',
                ], 422);
            }

            $signers = ContractSigner::where('contract_id', $id)->get();

            $hasInternal = $signers->where('signer_type', 'internal')->isNotEmpty();
            $hasExternal = $signers->where('signer_type', 'external')->isNotEmpty();

            if (!$hasInternal) {
                return response()->json([
                    'message' => 'Contract must have at least one internal and one external signer before submission',
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

            $contract->update(['status' => 'review']);

            // Notify signers (Manager)
            foreach ($signers as $signer) {
                if ($signer->user_id && $signer->signer_type === 'internal') {
                    Notification::create([
                        'user_id' => $signer->user_id,
                        'contract_id' => $contract->id,
                        'type' => 'review_requested',
                        'message' => "{$contract->title} memerlukan peninjauan Anda.",
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
}
