<?php

namespace App\Http\Controllers\API\Admin;

use Exception;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Http\Resources\User\UserResource;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use Illuminate\Validation\ValidationException;
use Illuminate\Container\Attributes\Auth;

class UserController extends Controller
{
    public function index()
    {
        try {
            $users = User::with('roles')->get();
            return UserResource::collection($users);
        } catch (Exception $e) {
            Log::error('Error retrieving users: ' . $e->getMessage());

            return response()->json([
                'message' => 'An error occurred while retrieving users',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function store(StoreUserRequest $request)
    {
        try {
            if (User::where('email', $request->email)->exists()) {
                Log::error ('Attempt to create user with existing email', [
                    'email' => $request->email,
                ]);

                return response()->json([
                    'message' => 'Email already in use',
                    'errors' => [
                        'email' => ['The email has already been taken.'],
                    ],
                ], 422);
            } 

            DB::transaction(function () use ($request, &$user) {
                $user = User::create([
                    'name' => $request->name,
                    'email' => $request->email,
                    'password' => bcrypt($request->password),
                    'job_title' => $request->job_title,
                    'department' => $request->department,
                    'is_active' => $request->is_active ?? true,
                ]);

                $user->syncRoles([$request->role]);
            });

            return new UserResource($user);
        } catch (Exception $e) {
            Log::error('Error creating User', [
                'error' => $e->getMessage(),
                'input' => $request->except('password'),
            ]);

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
            Log::error('Error retrieving User', [
                'user_id' => $id,
                'error' => $e->getMessage(),
            ]);

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

                if ($request->has('email') && $request->email !== $user->email) {
                    if (User::where('email', $request->email)->exists()) {
                        Log::error ('Attempt to update user with existing email', [
                            'user_id' => $id,
                            'email' => $request->email,
                        ]);
                    }
                    
                    throw ValidationException::withMessages([
                         'email' => ['The email has already been taken.'],
                    ]);
                }

                $user->update($request->only([
                    'name',
                    'job_title',
                    'department',
                    'is_active',
                ]));
            });

            return new UserResource($user);
        } catch (Exception $e) {
            Log::error('Error updating User', [
                'user_id' => $id,
                'error' => $e->getMessage(),
                'input' => $request->except('password'),
            ]);

            return response()->json([
                'message' => 'User not found',
                'error' => $e->getMessage(),
            ], 404);
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
            Log::error('Error deleting User', [
                'user_id' => $id,
                'error' => $e->getMessage(),
            ]);

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
                'role' => 'required|string|exists:roles,name',
            ]);

            DB::transaction(function () use ($request, $id, &$user) {
                $user = User::findOrFail($id);
                $user->syncRoles([$request->role]);
            });

            return new UserResource($user);
        } catch (Exception $e) {
            Log::error('Error updating User roles', [
                'user_id' => $id,
                'error' => $e->getMessage(),
                'role' => $request->role,
            ]);

            return response()->json([
                'message' => 'An error occurred while updating user roles',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
