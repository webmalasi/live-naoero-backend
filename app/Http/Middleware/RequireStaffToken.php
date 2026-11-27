<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * API routes that change the website (e.g. POST /api/settings) are for
 * admin panel staff only: the request must carry the bearer token from
 * POST /api/auth/login, and the account must still be active. This is the
 * same rule as the admin panel itself (signed in, active account).
 */
class RequireStaffToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        $userId = $token ? Cache::get("api_token:{$token}") : null;
        $user = $userId ? User::find($userId) : null;

        if (! $user || ! $user->isActive()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated. Sign in to the admin panel API first.',
            ], 401);
        }

        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
