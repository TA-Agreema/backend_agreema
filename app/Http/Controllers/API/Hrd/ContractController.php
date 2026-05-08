<?php

namespace App\Http\Controllers\API\Hrd;

use Exception;
use App\Models\Contract;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\Contract\StoreContractRequest;
use App\Http\Requests\Contract\UpdateContractRequest;
use App\Http\Resources\Contract\ContractResource;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

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

            // Auto-generate contract_number if not supplied, using category prefix
            if (empty($validated['contract_number'])) {
                $categoryId = isset($validated['category_id']) ? (int) $validated['category_id'] : null;
                $validated['contract_number'] = $this->generateContractNumber($categoryId);
            }

            $contract = DB::transaction(function () use ($validated) {
                $contract = Contract::create(array_merge(Arr::except($validated, ['content', 'field_values']), [
                    'created_by' => Auth::id(),
                ]));

                if (!empty($validated['content'])) {
                    $version = $contract->versions()->create([
                        'version_number' => 1,
                        'content' => $validated['content'],
                        'created_by' => Auth::id(),
                    ]);

                    if (!empty($validated['field_values'])) {
                        foreach ($validated['field_values'] as $fv) {
                            $version->fieldValues()->create([
                                'field_definition_id' => $fv['field_definition_id'],
                                'value' => $fv['value'] ?? '',
                            ]);
                        }
                    }
                }
                return $contract;
            });

            $contract->load([
                'template.category:id,name',
                'creator:id,name',
                'addendums:id,contract_id,addendum_number,description,effective_date,created_at',
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
     * GET /api/contracts/{id}
     * Show contract detail
     */
    public function show(int $id): JsonResponse
    {
        try {
            $contract = Contract::with([
                'template.category:id,name',
                'creator:id,name',
                'addendums:id,contract_id,addendum_number,description,effective_date,created_at',
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

            DB::transaction(function () use ($contract, $validated) {
                $contract->update(Arr::except($validated, ['content']));

                if (!empty($validated['content'])) {
                    $latestVersion = $contract->latestVersion;
                    
                    // Create new version only if content changed
                    if (!$latestVersion || $latestVersion->content !== $validated['content']) {
                        $nextVersion = ($latestVersion->version_number ?? 0) + 1;
                        $version = $contract->versions()->create([
                            'version_number' => $nextVersion,
                            'content' => $validated['content'],
                            'created_by' => Auth::id(),
                        ]);

                        if (!empty($validated['field_values'])) {
                            foreach ($validated['field_values'] as $fv) {
                                $version->fieldValues()->create([
                                    'field_definition_id' => $fv['field_definition_id'],
                                    'value' => $fv['value'] ?? '',
                                ]);
                            }
                        }
                    }
                }
            });

            $contract->load([
                'template.category:id,name',
                'creator:id,name',
                'addendums:id,contract_id,addendum_number,description,effective_date,created_at',
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

            if (! array_key_exists('status', $validated)) {
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
     * Generate a unique contract number based on category prefix.
     * Format: [PREFIX]-[3-digit-seq]/SLAB/[ROMAN_MONTH]/[YEAR]
     * Example: PKS-001/SLAB/V/2026  |  NDA-001/SLAB/V/2026
     *
     * @param int|null $categoryId 
     */
    private function generateContractNumber(?int $categoryId = null): string
    {
        $romanMonths = [
            1 => 'I',  2 => 'II',  3 => 'III', 4 => 'IV',
            5 => 'V',  6 => 'VI',  7 => 'VII', 8 => 'VIII',
            9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII',
        ];

        // Resolve prefix: category's number_prefix → fallback 'SPK'
        $prefix = 'SPK';
        if ($categoryId) {
            $cat = \App\Models\ContractCategory::find($categoryId);
            if ($cat && !empty($cat->number_prefix)) {
                $prefix = strtoupper(trim($cat->number_prefix));
            }
        }

        $year  = now()->year;
        $month = now()->month;
        $roman = $romanMonths[$month];

        // Count contracts of this prefix/month/year for the sequence
        $count = Contract::whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->where('contract_number', 'like', "{$prefix}-%")
            ->count();

        $seq       = str_pad($count + 1, 3, '0', STR_PAD_LEFT);
        $candidate = "{$prefix}-{$seq}/SLAB/{$roman}/{$year}";

        // Ensure uniqueness (bump seq on collision)
        while (Contract::where('contract_number', $candidate)->exists()) {
            $count++;
            $seq       = str_pad($count + 1, 3, '0', STR_PAD_LEFT);
            $candidate = "{$prefix}-{$seq}/SLAB/{$roman}/{$year}";
        }

        return $candidate;
    }

    /**
     * GET /api/contracts/generate-number?category_id={id}
     * 
     */
    public function generateNumber(\Illuminate\Http\Request $request): JsonResponse
    {
        $categoryId = $request->query('category_id') ? (int) $request->query('category_id') : null;

        return response()->json([
            'contract_number' => $this->generateContractNumber($categoryId),
        ]);
    }
}
