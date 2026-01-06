<?php

namespace App\Http\Controllers\API\Admin;

use Exception;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use App\Http\Controllers\Controller;
use App\Http\Resources\Role\RoleResources;
use App\Http\Requests\Role\StoreRoleRequest;
use App\Http\Requests\Role\UpdateRoleRequest;

class UserRoleController extends Controller
{
    public function index()
    {
        $roles = Role::where('guard_name', 'web')->get();
        return response()->json([
            'message' => 'User roles retrieved successfully',
            'roles' => $roles,
        ]);
    }

    public function store(StoreRoleRequest $request)
    {
        try {
            DB::transaction(function () use ($request, &$role) {
                $role = Role::create([
                    'name' => $request->name,
                    'guard_name' => 'web',
                ]);

                if ($request->has('permissions')) {
                    $role->syncPermissions($request->permissions);
                }
            });

            return new RoleResources($role);
        } catch (Exception $e) {
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

            return new RoleResources($role);
        } catch (Exception $e) {
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
                $role->update([
                    'name' => $request->name,
                ]);

                if ($request->has('permissions')) {
                    $role->syncPermissions($request->permissions);
                }
            });

            return new RoleResources($role);
        } catch (Exception $e) {
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
            $role->delete();

            return response()->json([
                'message' => 'Role deleted successfully',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'An error occurred while deleting the role',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
