<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * An admin screen opened inside the page editor (`?embed=1`) keeps being
 * "embedded" through its saves: redirects after a save carry the flag along,
 * so the editor can manage a page's lists without leaving the page.
 */
class EmbeddedAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        if ($request->boolean('embed') && $response instanceof RedirectResponse) {
            $target = $response->getTargetUrl();
            if (str_starts_with($target, url('/')) && ! str_contains($target, 'embed=')) {
                [$base, $hash] = array_pad(explode('#', $target, 2), 2, null);
                $extra = 'embed=1';
                if ($request->filled('only') && ! str_contains($target, 'only=')) {
                    $extra .= '&only='.urlencode((string) $request->input('only'));
                }
                $response->setTargetUrl($base.(str_contains($base, '?') ? '&' : '?').$extra.($hash !== null ? '#'.$hash : ''));
            }
        }

        return $response;
    }
}
