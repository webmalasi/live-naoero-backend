<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\Auth\RequestMeta;
use App\Services\Logging\ServerLog;
use App\Services\Notifications\FlightEvents;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

/**
 * Where Zenith posts flight events (EventMap). No sign-in: the webhook key
 * (ZENITH_WEBHOOK_SECRET) in the address tells us the post is Zenith's.
 *
 * Nothing is turned away: every post is saved as an event and answered 200
 * at once (Zenith only counts 200 as delivered), and the notices are made in
 * the background. A post without the right key (or from an IP not allowed)
 * is saved as "unverified": shown in the admin panel, never notifying a
 * customer unless staff process it. A post to an address we don't know is
 * saved as "ignored". Only when the post cannot be saved at all is the
 * answer 503, so that Zenith sends it again. Every call is also saved, with
 * our answer, by RecordZenithCall.
 */
class ZenithEventController extends ApiController
{
    public function __construct(
        private readonly FlightEvents $events,
        private readonly ServerLog $log,
    ) {}

    public function store(Request $request, string $secret, string $event): Response
    {
        return $this->accept($request, $secret, $event);
    }

    /** An address without the key (e.g. .../zenith/events/flight-change). */
    public function missingSecret(Request $request, string $event): Response
    {
        return $this->accept($request, null, $event);
    }

    private function accept(Request $request, ?string $secret, string $address): Response
    {
        $outcome = fn (string $o) => $request->attributes->set('zenith.outcome', $o);
        ['event' => $name, 'test' => $test] = FlightEvents::address($address);

        $expected = (string) config('naoero.zenith_events.secret');
        $ip = RequestMeta::clientKey($request);
        $allowed = config('naoero.zenith_events.allowed_ips', []);
        $keyStatus = match (true) {
            strlen($expected) < 32 => 'not_configured',
            $secret === null || $secret === '' => 'missing',
            ! hash_equals($expected, $secret) => 'invalid',
            $allowed && ! in_array($ip, $allowed, true) => 'ip_not_allowed',
            default => 'valid',
        };

        try {
            $stored = $this->events->receive($name ?? FlightEvents::UNKNOWN, $test, (string) $request->getContent(), $ip === 'local' ? null : $ip, $address, $keyStatus);
        } catch (Throwable $e) {
            // Not saved: Zenith must send it again.
            $outcome('error');
            $this->log->error('zenith-event', 'post not saved', ['address' => $address, 'error' => $e->getMessage()]);

            return response('Not saved, please send again', 503, ['Content-Type' => 'text/plain; charset=utf-8', 'Cache-Control' => 'no-store', 'Retry-After' => '60']);
        }

        $outcome(match (true) {
            $stored['duplicate'] => 'duplicate',
            $name === null => 'unknown_event',
            $keyStatus !== 'valid' => 'unverified',
            default => 'stored',
        });
        $request->attributes->set('zenith.event_id', $stored['id']);
        $request->attributes->set('zenith.key_status', $keyStatus);

        return response('OK', 200, ['Content-Type' => 'text/plain; charset=utf-8', 'Cache-Control' => 'no-store']);
    }
}
