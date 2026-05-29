<?php

namespace App\Http\Controllers\API\Hrd;

use App\Http\Controllers\Controller;
use App\Models\Party;
use Illuminate\Http\JsonResponse;
use Exception;
use Illuminate\Support\Facades\Log;

class PartyController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $parties = Party::with(['individualDetail:id,party_id,full_name', 'companyDetail:id,party_id,company_name'])
                ->get()
                ->map(function ($p) {
                    return [
                        'id' => $p->id,
                        'display_name' => $p->display_name,
                        'party_type' => $p->party_type,
                    ];
                });

            return response()->json([
                'message' => 'Parties retrieved',
                'data' => $parties,
            ]);
        } catch (Exception $e) {
            Log::error('Error retrieving parties', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Error retrieving parties', 'error' => $e->getMessage()], 500);
        }
    }
}
