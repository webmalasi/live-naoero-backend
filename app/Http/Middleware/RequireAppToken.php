<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * When APP_ACCESS_TOKEN is set, requests must send it in X-App-Token. It
 * only keeps casual traffic off flight search (anything shipped in an app
 * can be extracted); the Zenith key never leaves the server either way.
 */
class RequireAppToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = trim((string) config('naoero.app_access_token'));
        if ($expected !== '' && ! hash_equals($expected, (string) $request->header('X-App-Token'))) {
            throw new ApiException(401, 'unauthorized', 'Missing or invalid app token.');
        }

        return $next($request);
    }
}
