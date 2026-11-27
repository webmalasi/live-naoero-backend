<?php

namespace App\Http\Middleware;

use App\Services\Auth\AdminAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every admin route needs the permission AdminAccess names for it: to read
 * a section, or to change it. A route without a rule is refused.
 */
class EnforceAdminPermission
{
    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();
        $name = (string) $route?->getName();
        if ($name === '' || ! AdminAccess::allows($request->user(), $name, $request->method())) {
            abort(403, 'Your account does not have permission for this section. Ask an administrator to grant it in Roles & Tiers.');
        }

        return $next($request);
    }
}
