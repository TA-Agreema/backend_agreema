<?php

namespace App\Http\Controllers\API\Hrd;

use App\Models\Contract;
use App\Models\Notification;
use App\Http\Controllers\Controller;
use App\Http\Resources\Contract\ContractResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExternalPartnerContractController extends Controller
{
    public function index(): JsonResponse
    {
        $contracts = Contract::where('contract_type', 'external')
            ->with([
                'creator',
                'termination',
                'addendums',
            ])
            ->latest()
            ->get();

        return response()->json(['data' => ContractResource::collection($contracts)]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title'                    => 'required|string|max:255',
            'contract_number'          => 'nullable|string|max:100',
            'external_contract_number' => 'nullable|string|max:100',
            'partner_name'             => 'required|string|max:255',
            'status'                   => 'required|in:signed,active',
            'start_date'               => 'nullable|date',
            'end_date'                 => 'nullable|date',
            'document'                 => 'required|file|mimes:pdf|max:10240',
            'notes'                    => 'nullable|string',
        ]);

        try {
            $documentPath = $request->file('document')
                ->store('contracts/external', 'public');

            $contract = DB::transaction(function () use ($validated, $documentPath) {
                $contract = Contract::create([
                    'contract_number'          => $validated['contract_number'] ?? null,
                    'external_contract_number' => $validated['external_contract_number'] ?? null,
                    'contract_type'            => 'external',
                    'title'                    => $validated['title'],
                    'partner_name'             => $validated['partner_name'],
                    'start_date'               => $validated['start_date'] ?? null,
                    'end_date'                 => $validated['end_date'] ?? null,
                    'status'                   => $validated['status'],
                    'signed_document_path'     => $documentPath,
                    'created_by'               => Auth::id(),
                ]);

                $statusLabel = $validated['status'] === 'active' ? 'aktif' : 'disahkan';
                Notification::create([
                    'user_id'     => Auth::id(),
                    'contract_id' => $contract->id,
                    'type' => $validated['status'] === 'active' ? 'contract_activated' : 'partner_contract_added',
                    'message'     => "{$contract->title} berhasil ditambahkan dengan status {$statusLabel}.",
                    'is_read'     => false,
                ]);

                return $contract;
            });

            $contract->load([
                'creator',
                'termination',
                'addendums',
            ]);

            return response()->json([
                'message' => 'Kontrak mitra berhasil ditambahkan.',
                'data'    => new ContractResource($contract),
            ], 201);
        } catch (\Exception $e) {
            Log::error('Store external contract error', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Server error', 'error' => $e->getMessage()], 500);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        $contract = Contract::where('contract_type', 'external')->findOrFail($id);
        $contract->delete();
        return response()->json(['message' => 'Kontrak berhasil dihapus.']);
    }
}
