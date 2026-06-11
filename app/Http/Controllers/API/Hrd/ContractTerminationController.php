<?php

namespace App\Http\Controllers\API\Hrd;

use Exception;
use App\Models\Contract;
use App\Models\ContractTermination;
use App\Http\Requests\Termination\StoreTerminationRequest;
use App\Http\Resources\Termination\TerminationResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ContractTerminationController extends Controller
{
    /**
     * GET /api/contracts/{id}/terminations
     * Daftar terminasi untuk kontrak tertentu.
     */
    public function index(int $id): JsonResponse
    {
        try {
            $contract = Contract::findOrFail($id);

            $termination = $contract->termination;

            return response()->json([
                'message' => 'Terminations retrieved successfully',
                'data'    => $termination ? [new TerminationResource($termination)] : [],
            ]);
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'Kontrak tidak ditemukan.'], 404);
        } catch (Exception $e) {
            Log::error('Termination index error', ['contract_id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Server error', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/contracts/{id}/terminations
     * HRD membuat terminasi untuk kontrak yang sudah aktif.
     */
    public function store(StoreTerminationRequest $request, int $id): JsonResponse
    {
        $validated = $request->validated();

        try {
            $contract = Contract::findOrFail($id);

            if ($contract->status !== 'active') {
                return response()->json([
                    'message' => 'Terminasi hanya dapat dibuat untuk kontrak yang sedang aktif.',
                ], 422);
            }

            // Upload dokumen pendukung jika ada
            $documentPath = null;
            if ($request->hasFile('document')) {
                $documentPath = $request->file('document')->store('terminations', 'public');
            }

            $effectiveDate = \Carbon\Carbon::parse($validated['effective_date'])->startOfDay();
            $today = \Carbon\Carbon::today();

            $termination = DB::transaction(function () use ($contract, $validated, $documentPath, $effectiveDate, $today) {
                $updateData = [
                    'end_date' => $validated['effective_date'],
                ];

                if ($effectiveDate->lessThanOrEqualTo($today)) {
                    $updateData['status'] = 'terminated';
                }

                $contract->update($updateData);

                return ContractTermination::create([
                    'contract_id'               => $contract->id,
                    'termination_number'        => $validated['termination_number'],
                    'title'                     => $validated['title'],
                    'termination_reason'        => $validated['termination_reason'],
                    'termination_note'          => $validated['termination_note'] ?? null,
                    'termination_document_path' => $documentPath,
                    'effective_date'            => $validated['effective_date'],
                ]);
            });

            $message = $effectiveDate->lessThanOrEqualTo($today)
                ? 'Terminasi berhasil dibuat dan kontrak telah dihentikan.'
                : 'Terminasi berhasil dibuat. Kontrak akan dihentikan pada tanggal efektif.';

            return response()->json([
                'message' => $message,
                'data'    => new TerminationResource($termination),
            ], 201);
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'Kontrak tidak ditemukan.'], 404);
        } catch (Exception $e) {
            Log::error('Store termination error', ['contract_id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Server error', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * DELETE /api/contracts/{contractId}/terminations/{terminationId}
     * HRD menghapus terminasi.
     */
    public function destroy(int $contractId, int $terminationId): JsonResponse
    {
        try {
            $termination = ContractTermination::where('contract_id', $contractId)
                ->findOrFail($terminationId);

            DB::transaction(function () use ($termination, $contractId) {
                $termination->delete();

                // Kembalikan status kontrak ke active jika terminasi dihapus
                $contract = Contract::findOrFail($contractId);
                $contract->update(['status' => 'active']);
            });

            return response()->json(['message' => 'Terminasi berhasil dihapus dan status kontrak dikembalikan.']);
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'Terminasi tidak ditemukan.'], 404);
        } catch (Exception $e) {
            Log::error('Delete termination error', ['termination_id' => $terminationId, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Server error', 'error' => $e->getMessage()], 500);
        }
    }
}
