<?php

namespace App\Http\Controllers\API\Admin;

use Exception;
use App\Models\FieldDefinition;
use App\Models\Template;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;

use App\Http\Requests\Field\StoreFieldDefinitionRequest;
use App\Http\Requests\Field\UpdateFieldDefinitionRequest;
use App\Http\Resources\Field\FieldDefinitionResource;

class FieldDefinitionController extends Controller
{
    /**
     * GET /api/field-definitions
     */
    public function index(): JsonResponse
    {
        try {
            $fields = FieldDefinition::all();

            return response()->json([
                'message' => 'Field definitions retrieved successfully',
                'data'    => FieldDefinitionResource::collection($fields),
            ]);
        } catch (Exception $e) {
            Log::error('Error retrieving field definitions', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => 'An error occurred while retrieving field definitions',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/field-definitions
     */
    public function store(StoreFieldDefinitionRequest $request): JsonResponse
    {
        try {
            $field = FieldDefinition::create($request->validated());

            return response()->json([
                'message' => 'Field definition created successfully',
                'data'    => new FieldDefinitionResource($field),
            ], 201);
        } catch (Exception $e) {
            Log::error('Error creating field definition', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'An error occurred while creating field definition',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * PATCH /api/field-definitions/{id}
     */
    public function update(UpdateFieldDefinitionRequest $request, $id): JsonResponse
    {
        try {
            $field = FieldDefinition::findOrFail($id);
            $field->update($request->validated());

            return response()->json([
                'message' => 'Field definition updated successfully',
                'data'    => new FieldDefinitionResource($field),
            ]);
        } catch (Exception $e) {
            Log::error('Error updating field definition', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'An error occurred while updating field definition',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * DELETE /api/field-definitions/{id}
     */
    public function destroy($id): JsonResponse
    {
        try {
            $field = FieldDefinition::findOrFail($id);

            if ($this->isFieldUsed($field)) {
                $field->update(['is_active' => false]);

                return response()->json([
                    'message' => 'Field sudah digunakan sehingga dinonaktifkan, bukan dihapus permanen.',
                    'action' => 'deactivated',
                    'data' => new FieldDefinitionResource($field),
                ]);
            }

            $field->delete();

            return response()->json([
                'message' => 'Field definition deleted successfully',
                'action' => 'deleted',
            ]);
        } catch (Exception $e) {
            Log::error('Error deleting field definition', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'An error occurred while deleting field definition',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    private function isFieldUsed(FieldDefinition $field): bool
    {
        return $field->fieldValues()->exists() || $this->isFieldUsedInTemplate($field);
    }

    private function isFieldUsedInTemplate(FieldDefinition $field): bool
    {
        return Template::query()
            ->where('content', 'like', '%data-contract-field-id="' . $field->id . '"%')
            ->orWhere('content', 'like', "%data-contract-field-id='" . $field->id . "'%")
            ->orWhere('content', 'like', '%{{' . $field->field_key . '}}%')
            ->exists();
    }
}
