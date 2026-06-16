<?php

namespace App\Http\Controllers\API\Hrd;

use App\Models\Contract;
use App\Models\Notification;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ExternalPartnerContractController extends Controller
{
    public function index(): JsonResponse
    {
        $contracts = Contract::where('contract_type', 'external')
            ->with(['template.category', 'creator', 'termination', 'addendums'])
            ->latest()
            ->get();

        return response()->json(['data' => $contracts]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title'                    => 'required|string|max:255',
            'contract_number'          => 'nullable|string|max:100',
            'external_contract_number' => 'nullable|string|max:100',
            'partner_name'             => 'required|string|max:255',
            'category_id'              => 'nullable|integer',
            'start_date'               => 'nullable|date',
            'end_date'                 => 'nullable|date',
            'document'                 => 'required|file|mimes:pdf|max:10240',
            'notes'                    => 'nullable|string',
        ]);

        try {
            $documentPath = $request->file('document')
                ->store('contracts/external', 'public');

            $contract = DB::transaction(function () use ($validated, $documentPath, $request) {
                $contract = Contract::create([
                    'contract_number'          => $validated['contract_number'] ?? null,
                    'external_contract_number' => $validated['external_contract_number'] ?? null,
                    'contract_type'            => 'external',
                    'title'                    => $validated['title'],
                    'start_date'               => $validated['start_date'] ?? null,
                    'end_date'                 => $validated['end_date'] ?? null,
                    'status'                   => 'active',
                    'signed_document_path'     => $documentPath,
                    'created_by'               => Auth::id(),
                ]);

                // Simpan nama partner
                if (!empty($validated['partner_name'])) {
                    \App\Models\ContractParty::create([
                        'contract_id'  => $contract->id,
                        'party_order'  => 2,
                        'display_name' => $validated['partner_name'],
                    ]);
                }

                // Notifikasi ke pembuat
                Notification::create([
                    'user_id'     => Auth::id(),
                    'contract_id' => $contract->id,
                    'type'        => 'contract_activated',
                    'message'     => "Kontrak eksternal {$contract->title} berhasil ditambahkan dan langsung aktif.",
                    'is_read'     => false,
                ]);

                return $contract;
            });

            return response()->json([
                'message' => 'Kontrak mitra berhasil ditambahkan.',
                'data'    => $contract,
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