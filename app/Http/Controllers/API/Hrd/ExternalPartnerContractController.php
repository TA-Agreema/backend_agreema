<?php

namespace App\Http\Controllers\API\Hrd;

use App\Models\Contract;
use App\Models\Notification;
use App\Http\Controllers\Controller;
use App\Http\Resources\Contract\ContractResource;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

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
            'title' => 'required|string|max:255',
            'contract_number' => [
                'nullable',
                'string',
                'max:100',
                // Validasi unik: hanya cek kontrak bertipe external
                Rule::unique('contracts', 'contract_number')
                    ->where(fn($query) => $query->where('contract_type', 'external'))
                    ->whereNotNull('contract_number'),
            ],
            'external_contract_number' => 'nullable|string|max:100',
            'partner_name' => 'required|string|max:255',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'document' => 'required|file|mimes:pdf|max:10240',
            'notes' => 'nullable|string',
        ], [
            'contract_number.unique' => 'Nomor kontrak tersebut sudah digunakan oleh kontrak mitra lain.',
            'end_date.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.',
            'document.required' => 'Dokumen kontrak (PDF) wajib diunggah.',
            'document.mimes' => 'Dokumen harus berformat PDF.',
            'document.max' => 'Ukuran dokumen maksimal 10MB.',
        ]);

        try {
            $documentPath = $request->file('document')
                ->store('contracts/external', 'public');

            $startDate = isset($validated['start_date']) ? Carbon::parse($validated['start_date']) : null;
            $autoStatus = ($startDate && $startDate->lte(Carbon::today())) ? 'active' : 'signed';

            $contract = DB::transaction(function () use ($validated, $documentPath, $autoStatus) {
                $contract = Contract::create([
                    'contract_number' => $validated['contract_number'] ?? null,
                    'external_contract_number' => $validated['external_contract_number'] ?? null,
                    'contract_type' => 'external',
                    'title' => $validated['title'],
                    'partner_name' => $validated['partner_name'],
                    'start_date' => $validated['start_date'] ?? null,
                    'end_date' => $validated['end_date'] ?? null,
                    'status' => $autoStatus,
                    'signed_document_path' => $documentPath,
                    'created_by' => Auth::id(),
                ]);

                $statusLabel = $autoStatus === 'active' ? 'aktif' : 'menunggu aktif';
                Notification::create([
                    'user_id' => Auth::id(),
                    'contract_id' => $contract->id,
                    'type' => $autoStatus === 'active' ? 'contract_activated' : 'partner_contract_added',
                    'message' => "Kontrak mitra \"{$contract->title}\" berhasil ditambahkan dengan status {$statusLabel}.",
                    'is_read' => false,
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
                'status' => $autoStatus,
                'data' => new ContractResource($contract),
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
