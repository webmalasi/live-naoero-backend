<?php

namespace App\Http\Middleware;

use App\Services\Auth\RequestMeta;
use App\Services\Logging\ServerLog;
use App\Services\Notifications\FlightEvents;
use App\Services\Support\Js;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Saves every call to Zenith's event addresses in zenith_event_calls: the
 * request as it came and the response exactly as we sent it. The controller
 * says what became of the call (request attributes zenith.outcome and
 * zenith.event_id). It is saved once the answer has gone out, so it never
 * delays or changes what Zenith gets.
 */
class RecordZenithCall
{
    /** How much of each body is kept here (a stored event's full body is in zenith_events.raw). */
    private const KEEP_BYTES = 65_536;

    private const SECRET_HEADERS = ['cookie', 'authorization', 'proxy-authorization', 'x-xsrf-token', 'x-csrf-token', 'set-cookie'];

    public function __construct(private readonly ServerLog $log) {}

    public function handle(Request $request, Closure $next): Response
    {
        $started = hrtime(true);
        $response = $next($request);
        $request->attributes->set('zenith.ms', (int) round((hrtime(true) - $started) / 1e6));

        return $response;
    }

    /** With the response as sent, outer middleware headers included. */
    public function terminate(Request $request, Response $response): void
    {
        try {
            $this->record($request, $response, (int) $request->attributes->get('zenith.ms', 0));
        } catch (Throwable $e) {
            Log::warning('zenith event call not recorded: '.$e->getMessage());
        }
    }

    private function record(Request $request, Response $response, int $ms): void
    {
        $address = (string) $request->route('event');
        ['event' => $event, 'test' => $test] = FlightEvents::address($address);
        $raw = (string) $request->getContent();
        $body = FlightEvents::utf8(substr($raw, 0, self::KEEP_BYTES));
        $outcome = $request->attributes->get('zenith.outcome') ?? ($response->getStatusCode() >= 500 ? 'error' : ($event === null ? 'unknown_event' : 'error'));
        $ip = RequestMeta::clientKey($request);

        DB::table('zenith_event_calls')->insert([
            'id' => (string) Str::uuid(),
            'received_at' => Js::dbTime(),
            'is_test' => $test,
            'address' => mb_substr($address, 0, 80),
            'event' => $event,
            'outcome' => $outcome,
            'zenith_event_id' => $request->attributes->get('zenith.event_id'),
            'request_id' => $this->log->requestId,
            'method' => $request->method(),
            'path' => FlightEvents::maskPath('/'.ltrim($request->path(), '/')), // the key is never saved
            'query_string' => $request->getQueryString(),
            'ip' => filter_var($ip, FILTER_VALIDATE_IP) ? $ip : null,
            'user_agent' => $request->userAgent() === null ? null : mb_substr($request->userAgent(), 0, 500),
            'content_type' => $request->headers->get('Content-Type') === null ? null : mb_substr($request->headers->get('Content-Type'), 0, 200),
            'request_headers' => Js::encode(self::headers($request->headers->all())),
            'request_bytes' => strlen($raw),
            'request_sha256' => hash('sha256', $raw),
            'request_body' => $raw === '' ? null : $body,
            'request_body_truncated' => strlen($raw) > self::KEEP_BYTES,
            'response_status' => $response->getStatusCode(),
            'response_headers' => Js::encode(self::headers($response->headers->all())),
            'response_body' => mb_substr((string) $response->getContent(), 0, self::KEEP_BYTES),
            'duration_ms' => $ms,
        ]);
    }

    /** @return array<string, string> one line per header, secrets left out */
    private static function headers(array $all): array
    {
        $out = [];
        foreach ($all as $name => $values) {
            if (! in_array(strtolower($name), self::SECRET_HEADERS, true)) {
                $out[strtolower($name)] = mb_substr(implode(', ', (array) $values), 0, 1000);
            }
        }
        ksort($out);

        return $out;
    }
}
