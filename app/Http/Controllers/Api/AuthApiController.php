<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthApiController extends Controller
{
    /**
     * Authenticate user and return token + user profile.
     */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'The provided credentials do not match our records.',
            ], 401);
        }

        if (! $user->isActive()) {
            return response()->json([
                'success' => false,
                'message' => 'Your account is deactivated. Please contact an administrator.',
            ], 403);
        }

        $user->update(['last_login_at' => now()]);

        // Generate an API token valid for 30 days
        $token = Str::random(64);
        Cache::put("api_token:{$token}", $user->id, now()->addDays(30));

        ActivityLog::log(
            'login',
            'api_auth',
            "User {$user->email} logged in via API",
            ['ip' => $request->ip()]
        );

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'avatar_url' => $user->avatar_url,
                'job_title' => $user->job_title,
                'department' => $user->department,
                'status' => $user->status,
                'role' => $user->primary_role,
                'permissions' => $user->getAllPermissions()->pluck('name'),
            ],
        ]);
    }

    /**
     * Get the authenticated user's profile.
     */
    public function me(Request $request): JsonResponse
    {
        $user = null;

        // Try bearer token first
        $token = $request->bearerToken();
        if ($token) {
            $userId = Cache::get("api_token:{$token}");
            if ($userId) {
                $user = User::find($userId);
            }
        }

        // Fallback to Auth session if available
        if (! $user && Auth::check()) {
            $user = Auth::user();
        }

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'avatar_url' => $user->avatar_url,
                'job_title' => $user->job_title,
                'department' => $user->department,
                'status' => $user->status,
                'role' => $user->primary_role,
                'permissions' => $user->getAllPermissions()->pluck('name'),
            ],
        ]);
    }

    /**
     * Logout and invalidate API token.
     */
    public function logout(Request $request): JsonResponse
    {
        $token = $request->bearerToken();
        if ($token) {
            Cache::forget("api_token:{$token}");
        }

        if (Auth::check()) {
            Auth::logout();
        }

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully.',
        ]);
    }
}
