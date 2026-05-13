<?php

namespace App\Http\Controllers\API\Hrd;

use Exception;
use App\Models\Contract;
use Illuminate\Support\Arr;
use App\Models\Notification;
use Illuminate\Http\Request;
use App\Models\ContractSigner;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
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
                    'addendums:id,contract_id,addendum_number,description,effective_date,created_at',
                    'termination',
                    'parties.party.individualDetail:id,party_id,full_name',
                    'parties.party.companyDetail:id,party_id,company_name',
                ])
                ->orderByDesc('created_at');

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
                    $tpl = \App\Models\Template::find($validated['template_id']);
                    if ($tpl && !empty($tpl->category_id)) {
                        $categoryId = (int) $tpl->category_id;
                    }
                }
                $validated['contract_number'] = $this->generateContractNumber($categoryId);
            }

            $contract = DB::transaction(function () use ($validated) {
                $contract = Contract::create(array_merge(Arr::except($validated, ['content', 'partner_id', 'signers']), [
                    'created_by' => Auth::id(),
                ]));

                // Insert signers if provided
                if (!empty($validated['signers']) && is_array($validated['signers'])) {
                    foreach ($validated['signers'] as $index => $signerData) {
                        $userId = null;
                        if ($signerData['type'] === 'internal') {
                            $user = \App\Models\User::where('name', $signerData['name'])->first();
                            if ($user) $userId = $user->id;
                        }

                        \App\Models\ContractSigner::create([
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

                // If partner_id provided, link it as party_order = 2 (partner)
                if (!empty($validated['partner_id'])) {
                    \App\Models\ContractParty::create([
                        'contract_id' => $contract->id,
                        'party_id' => (int) $validated['partner_id'],
                        'party_order' => 2,
                    ]);
                } elseif (!empty($validated['partner_name'])) {
                    // If partner_name provided (manual input), try to find an existing company party
                    $name = trim($validated['partner_name']);
                    $existing = \App\Models\Party::whereHas('companyDetail', function ($q) use ($name) {
                        $q->whereRaw('LOWER(company_name) = ?', [strtolower($name)]);
                    })->first();

                    if (!$existing) {
                        $party = \App\Models\Party::create(['party_type' => 'company']);
                        \App\Models\PartyCompanyDetail::create([
                            'party_id' => $party->id,
                            'company_name' => $name,
                            'address' => null,
                        ]);
                        $partyId = $party->id;
                    } else {
                        $partyId = $existing->id;
                    }

                    if (!empty($partyId)) {
                        \App\Models\ContractParty::create([
                            'contract_id' => $contract->id,
                            'party_id' => $partyId,
                            'party_order' => 2,
                        ]);
                    }
                }

                if (!empty($validated['content'])) {
                    $contract->versions()->create([
                        'version_number' => 1,
                        'content' => $validated['content'],
                        'created_by' => Auth::id(),
                    ]);
                }
                return $contract;
            });

            $contract->load([
                'template.category:id,name',
                'creator:id,name',
                'signers.user',
                'signers.reviews',
                'statusLogs.changedBy:id,name',
                'addendums:id,contract_id,addendum_number,description,effective_date,created_at',
                'termination',
                'parties.party.individualDetail:id,party_id,full_name',
                'parties.party.companyDetail:id,party_id,company_name',
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
     * Generate a unique contract number based on category prefix.
     * Prefix is derived from category.number_prefix if present, otherwise
     * from the category name by taking the first character of each word
     * (e.g. "Perjanjian Kerja Waktu Tertentu" -> PKWT).
     *
     * @param int|null $categoryId
     * @return string
     */
    private function generateContractNumber(?int $categoryId = null): string
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

        // Resolve prefix: prefer explicit number_prefix, otherwise derive from category name
        $prefix = 'SPK';
        if ($categoryId) {
            $cat = \App\Models\ContractCategory::find($categoryId);
            if ($cat) {
                if (!empty($cat->number_prefix)) {
                    $prefix = strtoupper(trim($cat->number_prefix));
                } elseif (!empty($cat->name)) {
                    // Split on non-word characters and take the first letter of each word
                    $words = preg_split('/[^\p{L}\p{N}]+/u', trim($cat->name));
                    $letters = [];
                    foreach ($words as $w) {
                        $w = trim($w);
                        if ($w === '')
                            continue;
                        $letters[] = mb_substr($w, 0, 1, 'UTF-8');
                    }
                    if (count($letters) > 0) {
                        $prefix = strtoupper(implode('', $letters));
                    }
                }
            }
        }

        $year = now()->year;
        $month = now()->month;
        $roman = $romanMonths[$month];

        // Count contracts of this prefix/month/year for the sequence
        $count = Contract::whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->where('contract_number', 'like', "{$prefix}-%")
            ->count();

        $seq = str_pad($count + 1, 3, '0', STR_PAD_LEFT);
        $candidate = "{$prefix}-{$seq}/SLAB/{$roman}/{$year}";

        // Ensure uniqueness (bump seq on collision)
        while (Contract::where('contract_number', $candidate)->exists()) {
            $count++;
            $seq = str_pad($count + 1, 3, '0', STR_PAD_LEFT);
            $candidate = "{$prefix}-{$seq}/SLAB/{$roman}/{$year}";
        }

        return $candidate;
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
                'statusLogs.changedBy:id,name',
                'addendums:id,contract_id,addendum_number,description,effective_date,created_at',
                'termination',
                'parties.party.individualDetail:id,party_id,full_name',
                'parties.party.companyDetail:id,party_id,company_name',
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
                $contract->update(Arr::except($validated, ['content', 'partner_id', 'signers']));

                // Update signers if provided
                if (array_key_exists('signers', $validated) && is_array($validated['signers'])) {
                    $existingSigners = \App\Models\ContractSigner::where('contract_id', $contract->id)->get();
                    $keptSignerIds = [];

                    foreach ($validated['signers'] as $index => $signerData) {
                        $userId = null;
                        if ($signerData['type'] === 'internal') {
                            $user = \App\Models\User::where('name', $signerData['name'])->first();
                            if ($user) $userId = $user->id;
                        }

                        // Try to find an existing signer to preserve their review history
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
                            $newSigner = \App\Models\ContractSigner::create([
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
                    \App\Models\ContractSigner::where('contract_id', $contract->id)
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
                        $existing = \App\Models\Party::whereHas('companyDetail', function ($q) use ($name) {
                            $q->whereRaw('LOWER(company_name) = ?', [strtolower($name)]);
                        })->first();
                        if (!$existing) {
                            $party = \App\Models\Party::create(['party_type' => 'company']);
                            \App\Models\PartyCompanyDetail::create([
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
                        \App\Models\ContractParty::create([
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
                        $nextVersion = ($latestVersion->version_number ?? 0) + 1;
                        $contract->versions()->create([
                            'version_number' => $nextVersion,
                            'content' => $validated['content'],
                            'created_by' => Auth::id(),
                        ]);
                    }
                }
            });

            $contract->load([
                'template.category:id,name',
                'creator:id,name',
                'signers.user',
                'signers.reviews',
                'statusLogs.changedBy:id,name',
                'addendums:id,contract_id,addendum_number,description,effective_date,created_at',
                'termination',
                'parties.party.individualDetail:id,party_id,full_name',
                'parties.party.companyDetail:id,party_id,company_name',
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

            if (!$hasInternal || !$hasExternal) {
                return response()->json([
                    'message' => 'Contract must have at least one internal and one external signer before submission',
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
                        'message' => "Contract {$contract->contract_number} requires your review.",
                        'is_read' => false,
                    ]);
                }
            }

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
}
