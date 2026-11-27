<?php

namespace App\Http\Middleware;

use App\Services\Auth\RequestMeta;
use App\Services\Logging\ServerLog;
use App\Services\Notifications\FlightEvents;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gives every app API request an id (returned as X-Request-Id), logs its
 * outcome, status and duration, and writes the request's log entries to
 * server_logs once the response has been sent.
 */
class LogApiRequest
{
    public function __construct(private readonly ServerLog $log) {}

    public function handle(Request $request, Closure $next): Response
    {
        $started = hrtime(true);
        $ip = RequestMeta::clientKey($request);
        // The webhook key in Zenith's event addresses is never logged.
        $path = FlightEvents::maskPath('/'.ltrim($request->path(), '/'));
        $requestId = $this->log->startRequest($request->method(), $path, $ip === 'local' ? null : $ip);

        $response = $next($request);

        $status = $response->getStatusCode();
        // "t" is the payment page's one-time secret: never logged.
        $query = array_diff_key($request->query(), ['t' => true]);
        $this->log->log(
            $status >= 500 ? 'error' : ($status >= 400 ? 'warn' : 'info'),
            'http',
            "{$request->method()} {$path} {$status}",
            status: $status,
            durationMs: (hrtime(true) - $started) / 1e6,
            detail: [
                'query' => $query ?: null,
                'userAgent' => $request->userAgent() === null ? null : mb_substr($request->userAgent(), 0, 200),
            ],
        );
        $response->headers->set('X-Request-Id', $requestId);
        if (! $response->headers->has('Cache-Control') || $response->headers->get('Cache-Control') === 'no-cache, private') {
            $response->headers->set('Cache-Control', 'no-store');
        }

        return $response;
    }

    public function terminate(Request $request, Response $response): void
    {
        $this->log->flush();
    }
}
