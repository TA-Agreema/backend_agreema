<?php

namespace App\Http\Controllers\API\Admin;

use Exception;
use App\Models\FieldDefinition;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;

class FieldDefinitionController extends Controller
{
    /**
     * GET /api/field-definitions
     */
    public function index(): JsonResponse
    {
        try {
            $fields = FieldDefinition::where('is_active', true)->get();

            return response()->json([
                'message' => 'Field definitions retrieved successfully',
                'data'    => $fields,
            ]);
        } catch (Exception $e) {
            Log::error('Error retrieving field definitions', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => 'An error occurred while retrieving field definitions',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
