<?php

namespace App\Http\Controllers\API\Auth;

use Exception;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email',
                'password' => 'required|string|min:6',
            ]);

            $user = User::where('email', $request->email)->first();

            if (!Auth::attempt($request->only('email', 'password'))) {
                return response()->json([
                    'message' => 'Invalid login credentials'
                ], 401);
            }

            if (isset($user->is_active) && !$user->is_active) {
                return response()->json([
                    'message' => 'Account is not active'
                ], 403);
            }

            $user->tokens()->delete();

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'message' => 'Login successful',
                'user' => new UserResource($user),
                'roles' => $user->getRoleNames(),
                'permissions' => $user->getAllPermissions()->pluck('name'),
                'token' => $token,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'An error occurred during login',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function logout(Request $request)
    {
        try {
            $request->user()->tokens()->delete();

            return response()->json([
                'message' => 'Logout successful',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'An error occurred during logout',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function me(Request $request)
    {
        try{
            $user = $request->user();

            return response()->json([
                'user' => new UserResource($user),
                'roles' => $user->getRoleNames(),
                'permissions' => $user->getAllPermissions()->pluck('name'),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'An error occurred while fetching user details',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
