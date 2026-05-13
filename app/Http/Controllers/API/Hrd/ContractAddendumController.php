<?php

namespace App\Http\Controllers\API\Hrd;

use Exception;
use App\Models\Contract;
use App\Models\ContractAddendum;
use App\Http\Requests\Addendum\StoreAddendumRequest;
use App\Http\Resources\Addendum\AddendumResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ContractAddendumController extends Controller
{
    /**
     * GET /api/contracts/{id}/addendums
     * Daftar addendum untuk kontrak tertentu.
     */
    public function index(int $id): JsonResponse
    {
        try {
            $contract = Contract::findOrFail($id);

            $addendums = $contract->addendums()
                ->orderByDesc('created_at')
                ->get();

            return response()->json([
                'message' => 'Addendums retrieved successfully',
                'data'    => AddendumResource::collection($addendums),
            ]);
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'Kontrak tidak ditemukan.'], 404);
        } catch (Exception $e) {
            Log::error('Addendum index error', ['contract_id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Server error', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/contracts/{id}/addendums
     * HRD membuat addendum untuk kontrak yang sudah aktif atau disetujui internal.
     */
    public function store(StoreAddendumRequest $request, int $id): JsonResponse
    {
        $validated = $request->validated();

        try {
            $contract = Contract::findOrFail($id);

            // Kontrak harus sudah aktif atau sudah disetujui internal (approved) sebelum addendum dapat dibuat
            if (!in_array($contract->status, ['active'])) {
                return response()->json([
                    'message' => 'Addendum hanya dapat dibuat untuk kontrak yang sudah aktif atau disetujui internal.',
                ], 422);
            }

            // Upload dokumen pendukung jika ada
            $documentPath = null;
            if ($request->hasFile('document')) {
                $documentPath = $request->file('document')->store('addendums', 'public');
            }

            $addendum = DB::transaction(function () use ($contract, $validated, $documentPath) {
                return ContractAddendum::create([
                    'contract_id'     => $contract->id,
                    'addendum_number' => $validated['addendum_number'],
                    'title'           => $validated['title'],
                    'description'     => $validated['description'] ?? null,
                    'document_path'   => $documentPath,
                    // Effective date otomatis mengikuti tanggal berakhir kontrak
                    'effective_date'  => $contract->end_date,
                ]);
            });

            return response()->json([
                'message' => 'Addendum berhasil dibuat.',
                'data'    => new AddendumResource($addendum),
            ], 201);
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'Kontrak tidak ditemukan.'], 404);
        } catch (Exception $e) {
            Log::error('Store addendum error', ['contract_id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Server error', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * DELETE /api/contracts/{contractId}/addendums/{addendumId}
     * HRD menghapus addendum.
     */
    public function destroy(int $contractId, int $addendumId): JsonResponse
    {
        try {
            $addendum = ContractAddendum::where('contract_id', $contractId)
                ->findOrFail($addendumId);

            $addendum->delete();

            return response()->json(['message' => 'Addendum berhasil dihapus.']);
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'Addendum tidak ditemukan.'], 404);
        } catch (Exception $e) {
            Log::error('Delete addendum error', ['addendum_id' => $addendumId, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Server error', 'error' => $e->getMessage()], 500);
        }
    }
}
