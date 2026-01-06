<?php

namespace App\Http\Controllers\API\Admin;

use Exception;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Resources\User\UserResource;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use Illuminate\Container\Attributes\Auth;

class UserController extends Controller
{
    public function index()
    {
        try {
            $users = User::with('roles')->get();
            return UserResource::collection($users);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'An error occurred while retrieving users',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function store(StoreUserRequest $request)
    {
        try {
            DB::transaction(function () use ($request, &$user) {
                $user = User::create([
                    'name' => $request->name,
                    'email' => $request->email,
                    'password' => bcrypt($request->password),
                    'job_title' => $request->job_title,
                    'department' => $request->department,
                    'is_active' => $request->is_active ?? true,
                ]);

                if ($request->has('roles')) {
                    $user->syncRoles($request->roles);
                }
            });

            return new UserResource($user);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'An error occurred while creating the user',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $user = User::with('roles')->findOrFail($id);
            return new UserResource($user);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'User not found',
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    public function update(UpdateUserRequest $request, $id)
    {
        try {
            DB::transaction(function () use ($request, $id, &$user) {
                $user = User::findOrFail($id);
                $user->update($request->only([
                    'name',
                    'job_title',
                    'department',
                    'is_active',
                ]));
            });

            return new UserResource($user);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'An error occurred while updating the user',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $user = User::findOrFail($id);
            $user->delete();

            return response()->json([
                'message' => 'User deleted successfully',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'An error occurred while deleting the user',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function updateUserRoles(Request $request, $id)
    {
        try {
            $request->validate([
                'roles' => 'required|array',
                'roles.*' => 'string|exists:roles,name',
            ]);

            DB::transaction(function () use ($request, $id, &$user) {
                $user = User::findOrFail($id);
                if ($request->has('roles')) {
                    $user->syncRoles($request->roles);
                }
            });

            return new UserResource($user);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'An error occurred while updating user roles',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
