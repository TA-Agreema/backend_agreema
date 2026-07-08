<?php

namespace App\Http\Controllers\API\Admin;

use Exception;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;
use App\Http\Controllers\Controller;
use App\Http\Resources\Role\RoleResources;
use App\Http\Requests\Role\StoreRoleRequest;
use App\Http\Requests\Role\UpdateRoleRequest;
use Illuminate\Validation\ValidationException;

class UserRoleController extends Controller
{
    public function index()
    {
        // include permission names and user counts
        $roles = Role::where('guard_name', 'web')
            ->with('permissions:id,name')
            ->latest('id')
            ->get();

        // Compute users_count from pivot table to avoid morphedByMany errors
        $roles->transform(function ($role) {
            $role->users_count = DB::table('model_has_roles')->where('role_id', $role->id)->count();
            return $role;
        });

        return response()->json([
            'message' => 'User roles retrieved successfully',
            'roles' => RoleResources::collection($roles)->resolve(),
        ]);
    }

    public function store(StoreRoleRequest $request)
    {
        try {
            if (Role::where('name', $request->name)->where('guard_name', 'web')->exists()) {
                Log::error('Attempt to create role with existing name', [
                    'name' => $request->name,
                ]);

                return response()->json([
                    'message' => 'Nama role sudah digunakan.',
                    'errors' => [
                        'name' => ['Nama role sudah digunakan.'],
                    ],
                ], 422);
            }

            DB::transaction(function () use ($request, &$role) {
                $role = Role::create([
                    'name' => $request->name,
                    'guard_name' => 'web',
                    'description' => $request->description ?? null,
                ]);

                if ($request->has('permissions')) {
                    $role->syncPermissions($request->permissions);
                }
            });

            // Set users_count for response
            $role->users_count = DB::table('model_has_roles')->where('role_id', $role->id)->count();
            return new RoleResources($role);
        } catch (Exception $e) {
            Log::error('Error creating Role', [
                'error' => $e->getMessage(),
                'input' => $request->all(),
            ]);

            return response()->json([
                'message' => 'An error occurred while creating the role',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $role = Role::findById($id, 'web');
            $role->load('permissions');

            // Set users_count for response
            $role->users_count = DB::table('model_has_roles')->where('role_id', $role->id)->count();

            return new RoleResources($role);
        } catch (Exception $e) {
            Log::error('Error retrieving Role', [
                'role_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Role not found',
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    public function update(UpdateRoleRequest $request, $id)
    {
        try {
            DB::transaction(function () use ($request, $id, &$role) {
                $role = Role::findById($id, 'web');

                if ($request->name !== $role->name) {
                    if (Role::where('name', $request->name)->where('guard_name', 'web')->exists()) {
                        Log::error('Attempt to update role with existing name', [
                            'role_id' => $id,
                            'name' => $request->name,
                        ]);

                        throw ValidationException::withMessages([
                            'name' => ['Nama role sudah digunakan.'],
                        ]);
                    }
                }

                $role->update([
                    'name' => $request->name,
                    'description' => $request->description ?? null,
                ]);

                if ($request->has('permissions')) {
                    $role->syncPermissions($request->permissions);
                }
            });

            // Set users_count for response
            $role->users_count = DB::table('model_has_roles')->where('role_id', $role->id)->count();
            return new RoleResources($role);
        } catch (ValidationException $e) {
            throw $e;
        } catch (Exception $e) {
            Log::error('Error updating Role', [
                'role_id' => $id,
                'error' => $e->getMessage(),
                'input' => $request->all(),
            ]);

            return response()->json([
                'message' => 'An error occurred while updating the role',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $role = Role::findById($id, 'web');

            $assignedUserCount = DB::table('model_has_roles')
                ->where('role_id', $role->id)
                ->where('model_type', User::class)
                ->count();

            if ($assignedUserCount > 0) {
                return response()->json([
                    'message' => 'Role tidak dapat dihapus karena masih digunakan oleh user.',
                    'users_count' => $assignedUserCount,
                ], 422);
            }

            $role->delete();

            return response()->json([
                'message' => 'Role deleted successfully',
            ], 200);
        } catch (Exception $e) {
            Log::error('Error deleting Role', [
                'role_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'An error occurred while deleting the role',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
