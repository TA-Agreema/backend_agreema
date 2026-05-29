<?php

namespace App\Http\Controllers\API\Admin;

use Exception;
use App\Models\ContractCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Http\Requests\Category\StoreCategoryRequest;
use App\Http\Requests\Category\UpdateCategoryRequest;

class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $categories = ContractCategory::query()
                ->withCount('templates')
                ->orderByDesc('created_at')
                ->get();

            return response()->json([
                'message' => 'Categories retrieved successfully',
                'data' => $categories,
            ]);
        } catch (Exception $e) {
            Log::error('Error retrieving categories', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'An error occurred while retrieving categories',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        try {
            $category = ContractCategory::create([
                'name'          => $request->name,
                'number_prefix' => $request->number_prefix,
                'description'   => $request->description,
                'is_active'     => $request->boolean('is_active', true),
            ]);

            $category->loadCount('templates');

            return response()->json([
                'message' => 'Category created successfully',
                'data' => $category,
            ], 201);
        } catch (Exception $e) {
            Log::error('Error creating category', [
                'error' => $e->getMessage(),
                'input' => $request->all(),
            ]);

            return response()->json([
                'message' => 'An error occurred while creating category',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        try {
            $category = ContractCategory::query()
                ->withCount('templates')
                ->findOrFail($id);

            return response()->json([
                'message' => 'Category retrieved successfully',
                'data' => $category,
            ]);
        } catch (Exception $e) {
            Log::error('Error retrieving category', [
                'category_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Category not found',
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    public function update(UpdateCategoryRequest $request, int $id): JsonResponse
    {
        try {
            $category = ContractCategory::findOrFail($id);

            $category->update($request->only([
                'name',
                'number_prefix',
                'description',
                'is_active',
            ]));

            $category->loadCount('templates');

            return response()->json([
                'message' => 'Category updated successfully',
                'data' => $category,
            ]);
        } catch (Exception $e) {
            Log::error('Error updating category', [
                'category_id' => $id,
                'error' => $e->getMessage(),
                'input' => $request->all(),
            ]);

            return response()->json([
                'message' => 'An error occurred while updating category',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $category = ContractCategory::findOrFail($id);

            if ($category->templates()->exists()) {
                return response()->json([
                    'message' => 'Category cannot be deleted because it is used by templates',
                ], 422);
            }

            $category->delete();

            return response()->json([
                'message' => 'Category deleted successfully',
            ]);
        } catch (Exception $e) {
            Log::error('Error deleting category', [
                'category_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'An error occurred while deleting category',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function toggleStatus(int $id): JsonResponse
    {
        try {
            $category = ContractCategory::findOrFail($id);

            $category->update([
                'is_active' => !$category->is_active,
            ]);

            $category->loadCount('templates');

            return response()->json([
                'message' => 'Category status updated successfully',
                'data' => $category,
            ]);
        } catch (Exception $e) {
            Log::error('Error toggling category status', [
                'category_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'An error occurred while updating category status',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
