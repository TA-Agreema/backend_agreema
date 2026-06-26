<?php

namespace App\Http\Controllers\API\Auth;

use Exception;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\AuthResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Mail\PasswordResetMail;

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
                'user' => new AuthResource($user),
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

    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $request->email)->first();

        if ($user && (!isset($user->is_active) || $user->is_active)) {
            $plainToken = Str::random(64);

            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $request->email],
                [
                    'token' => Hash::make($plainToken),
                    'created_at' => now(),
                ],
            );

            $resetUrl = rtrim(config('app.frontend_url'), '/') .
                '/reset-password?email=' . urlencode($request->email) .
                '&token=' . urlencode($plainToken);

            Mail::to($user->email)->send(new PasswordResetMail($user, $resetUrl));
        }

        return response()->json([
            'message' => 'Jika email terdaftar, link reset password telah dikirim.',
        ]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'token' => 'required|string',
            'password' => [
                'required',
                'string',
                'confirmed',
                'min:8',
                'regex:/[a-z]/',
                'regex:/[A-Z]/',
                'regex:/[0-9]/',
                'regex:/[^A-Za-z0-9]/',
            ],
        ], [
            'password.regex' => 'Password harus berisi huruf besar, huruf kecil, angka, dan simbol.',
        ]);

        $resetToken = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (
            !$resetToken ||
            !Hash::check($request->token, $resetToken->token) ||
            now()->diffInMinutes($resetToken->created_at) > 60
        ) {
            return response()->json([
                'message' => 'Token reset password tidak valid atau sudah kedaluwarsa.',
            ], 422);
        }

        $user = User::where('email', $request->email)->firstOrFail();

        if (Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Password baru tidak boleh sama dengan password sebelumnya.',
            ], 422);
        }

        $user->update([
            'password' => $request->password,
        ]);
        $user->tokens()->delete();

        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return response()->json([
            'message' => 'Password berhasil diperbarui.',
        ]);
    }

    public function me(Request $request)
    {
        try{
            $user = $request->user();

            return response()->json([
                'user' => new AuthResource($user),
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
