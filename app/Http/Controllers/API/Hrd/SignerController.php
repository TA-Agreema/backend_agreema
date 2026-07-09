<?php

namespace App\Http\Controllers\API\Hrd;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\User\UserResource;

class SignerController extends Controller
{
    /**
     * GET /api/signers/internal
     * Mendapatkan daftar user internal yang bisa menjadi penandatangan.
     */
    public function internalSigners(): JsonResponse
    {
        try {
            // Mengambil manager aktif yang memiliki job_title
            $users = User::role('manager')
                ->where('is_active', 1)
                ->whereNotNull('job_title')
                ->select('id', 'name', 'job_title', 'email')
                ->get();

            return response()->json([
                'message' => 'Internal signers retrieved successfully',
                'data' => $users,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve internal signers',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
