<?php

namespace App\Http\Controllers\API\Hrd;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class PartyController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'message' => 'Partner master data is no longer used. Use contracts.partner_name instead.',
            'data' => [],
        ]);
    }
}
