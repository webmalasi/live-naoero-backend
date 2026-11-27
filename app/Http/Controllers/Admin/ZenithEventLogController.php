<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Services\Logging\ServerLog;
use App\Services\Notifications\FlightEvents;
use Carbon\CarbonImmutable;
use DOMDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

/**
 * Zenith Flight Events: what Zenith posted to our event addresses
 * (FlightChange, SegmentFlightCancelled, FlightTransfer, FlightReminder), what we answered,
 * how each post was processed and which notices it sent. Production and
 * preproduction (the "Test" addresses) are shown apart.
 */
class ZenithEventLogController extends Controller
{
    /** Tab => is_test (null: both). All is the default, so nothing is out of sight. */
    private const ENVIRONMENTS = ['all' => null, 'production' => false, 'preproduction' => true];

    /** What became of a call => how it is shown. */
    public const OUTCOMES = [
        'stored' => 'Stored',
        'duplicate' => 'Repeat of a stored post',
        'unverified' => 'Saved, key not accepted (unverified)',
        'unknown_event' => 'Saved, unknown address (not acted on)',
        'recovered' => 'Turned away then; saved after the fix',
        'error' => 'Not saved (database error); Zenith asked to resend',
        // Before 17 Nov 2026 these were turned away; kept for the history.
        'invalid_secret' => 'Wrong webhook key (turned away)',
        'missing_secret' => 'No webhook key in the address (turned away)',
        'not_configured' => 'Webhook key not set on the server (turned away)',
        'ip_not_allowed' => 'IP not allowed (turned away)',
        'too_large' => 'Too large (turned away)',
    ];

    /** Event status => how it is shown. */
    public const STATUSES = ['received', 'processed', 'ignored', 'failed', 'unverified'];

    public function index(Request $request): View
    {
        $this->authorizeView($request);
        $env = array_key_exists((string) $request->input('env'), self::ENVIRONMENTS) ? (string) $request->input('env') : 'all';
        $test = self::ENVIRONMENTS[$env];
        $search = trim((string) $request->input('search'));

        $query = DB::table('zenith_events as e')->when($test !== null, fn ($q) => $q->where('e.is_test', $test))
            ->select('e.id', 'e.event', 'e.address', 'e.key_status', 'e.is_test', 'e.format', 'e.pnr', 'e.status', 'e.detail', 'e.attempts', 'e.ip', 'e.received_at', 'e.processed_at',
                'e.airline', 'e.flight_number', 'e.origin', 'e.destination', 'e.departure_at', 'e.new_airline', 'e.new_flight_number', 'e.new_departure_at')
            ->selectSub(fn ($q) => $q->from('customer_notifications as n')->whereColumn('n.zenith_event_id', 'e.id')->selectRaw('count(*)'), 'notified');
        if (in_array($request->input('event'), FlightEvents::EVENTS, true)) {
            $query->where('e.event', $request->input('event'));
        }
        if (in_array($request->input('status'), self::STATUSES, true)) {
            $query->where('e.status', $request->input('status'));
        }
        if ($search !== '') {
            $query->where(fn ($q) => $q->where('e.pnr', strtoupper($search))->orWhere('e.ip', $search)
                ->orWhereRaw('lower(e.raw) like ?', ['%'.mb_strtolower($search).'%']));
        }
        $rows = $query->orderByDesc('e.received_at')->paginate(30)->withQueryString();

        $since = CarbonImmutable::now()->subDay()->utc()->format('Y-m-d H:i:s.uP');
        $secret = strlen((string) config('naoero.zenith_events.secret')) >= 32 ? (string) config('naoero.zenith_events.secret') : '';
        $stats = [];
        foreach (FlightEvents::EVENTS as $address => $event) {
            $base = DB::table('zenith_events')->when($test !== null, fn ($q) => $q->where('is_test', $test))->where('event', $event);
            // The address to give Zenith; in preproduction it adds "test" itself.
            $url = route('api.v1.zenith.events', ['secret' => $secret === '' ? 'ZENITH_WEBHOOK_SECRET' : $secret, 'event' => $address.($test ? 'test' : '')]);
            $stats[$event] = [
                // Shown with the key hidden; Copy gives the full address.
                'address' => $url,
                'shown' => $secret === '' ? $url : str_replace("/{$secret}/", '/••••'.substr($secret, -4).'/', $url),
                'total' => (clone $base)->count(),
                'day' => (clone $base)->where('received_at', '>=', $since)->count(),
                'failed' => (clone $base)->where('status', 'failed')->count(),
                'unverified' => (clone $base)->where('status', 'unverified')->count(),
                'notified' => DB::table('customer_notifications as n')->join('zenith_events as x', 'x.id', '=', 'n.zenith_event_id')
                    ->where('x.event', $event)->when($test !== null, fn ($q) => $q->where('x.is_test', $test))->count(),
                'last' => (clone $base)->max('received_at'),
            ];
        }

        // Every call to the addresses. A call that made (or repeated) an event
        // is shown with its event, in the event's environment: Zenith may
        // retry a post at another address form ("flight-changetest", then
        // "flight-change"). Other calls go by the address they came to.
        $calls = DB::table('zenith_event_calls as c')->leftJoin('zenith_events as ev', 'ev.id', '=', 'c.zenith_event_id')
            ->when($test !== null, fn ($q) => $q->whereRaw('coalesce(ev.is_test, c.is_test) = ?', [$test]))
            ->when(array_key_exists($request->input('outcome'), self::OUTCOMES), fn ($q) => $q->where('c.outcome', $request->input('outcome')))
            ->orderByDesc('c.received_at')
            ->selectRaw('coalesce(ev.is_test, c.is_test) as in_test')
            ->addSelect('c.id', 'c.received_at', 'c.method', 'c.path', 'c.outcome', 'c.response_status', 'c.response_body', 'c.duration_ms', 'c.ip', 'c.request_bytes', 'c.zenith_event_id')
            ->paginate(20, pageName: 'calls')->withQueryString();
        $outcomes = self::OUTCOMES;

        $configured = $secret !== '';

        return view('admin.zenith_events.index', compact('env', 'rows', 'stats', 'calls', 'outcomes', 'configured'));
    }

    /** One call: the request as it came and the response we sent. */
    public function call(Request $request, string $call): View
    {
        $this->authorizeView($request);
        $c = DB::table('zenith_event_calls')->where('id', $call)->first();
        abort_unless($c, 404);
        $json = fn (?string $v) => $v === null ? [] : (array) json_decode($v, true);

        return view('admin.zenith_events.call', [
            'c' => $c,
            // With its event when it has one, as in the list.
            'env' => (DB::table('zenith_events')->where('id', $c->zenith_event_id)->value('is_test') ?? $c->is_test) ? 'preproduction' : 'production',
            'outcomes' => self::OUTCOMES,
            'requestHeaders' => $json($c->request_headers),
            'responseHeaders' => $json($c->response_headers),
            'body' => $c->request_body === null ? null : self::pretty($c->request_body, self::formatOf($c->request_body)),
        ]);
    }

    public function show(Request $request, string $event): View
    {
        $this->authorizeView($request);
        $e = DB::table('zenith_events')->where('id', $event)->first();
        abort_unless($e, 404);

        $notices = DB::table('customer_notifications as n')->leftJoin('customers as c', 'c.id', '=', 'n.customer_id')
            ->where('n.zenith_event_id', $e->id)->orderBy('n.created_at')
            ->get(['n.id', 'n.customer_id', 'n.booking_id', 'n.title', 'n.body', 'n.read_at', 'n.created_at', 'c.zenith_login', 'c.email as customer_email', 'c.first_name', 'c.last_name']);
        $bookings = $e->pnr ? DB::table('bookings')->where('pnr', $e->pnr)->get(['id', 'customer_id']) : collect();
        // The first post and Zenith's retries of it.
        $calls = DB::table('zenith_event_calls')->where('zenith_event_id', $e->id)->orderBy('received_at')
            ->get(['id', 'received_at', 'outcome', 'response_status', 'response_body', 'duration_ms', 'ip']);

        return view('admin.zenith_events.show', [
            'e' => $e,
            'env' => $e->is_test ? 'preproduction' : 'production',
            'raw' => self::pretty($e->raw, $e->format),
            'payload' => $e->payload ? json_encode(json_decode($e->payload), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
            'notices' => $notices,
            'bookings' => $bookings,
            'calls' => $calls,
        ]);
    }

    /** Processes the event again (e.g. after Zenith was unreachable). */
    public function retry(Request $request, string $event, FlightEvents $events): RedirectResponse
    {
        $this->authorizeView($request);
        $e = DB::table('zenith_events')->where('id', $event)->first();
        abort_unless($e, 404);
        if (! in_array($e->status, ['failed', 'received', 'unverified'], true)) {
            return back()->with('error', 'Only a waiting, failed or unverified event can be processed.');
        }
        if (in_array($e->event, [FlightEvents::UNKNOWN], true) || ! isset(FlightEvents::TYPES[$e->event])) {
            return back()->with('error', 'This post did not come to one of our event addresses, so there is nothing to process.');
        }

        // Unverified: staff checked it and process it themselves.
        DB::table('zenith_events')->where('id', $e->id)->update(['status' => 'received']);
        if ($e->status === 'unverified') {
            ActivityLog::log('updated', 'zenith-events', "Processed unverified Zenith event {$e->id} ({$e->event}, {$e->pnr}) after checking it");
        }
        try {
            $events->process($e->id);
        } catch (Throwable $err) {
            $events->failed($e->id, $err);
        }
        app(ServerLog::class)->flush();
        $now = DB::table('zenith_events')->where('id', $e->id)->first(['status', 'detail']);

        return back()->with($now->status === 'failed' ? 'error' : 'success', "Processed again: {$now->status}. {$now->detail}");
    }

    private function authorizeView(Request $request): void
    {
        abort_unless($request->user()?->can('system.zenith'), 403, 'You do not have access to the logs.');
    }

    private static function formatOf(string $body): ?string
    {
        $start = ltrim($body)[0] ?? '';

        return $start === '<' ? 'xml' : (in_array($start, ['{', '['], true) ? 'json' : null);
    }

    /** The post as received, indented for reading. */
    private static function pretty(?string $raw, ?string $format): string
    {
        $raw = (string) $raw;
        if ($format === 'json') {
            $data = json_decode($raw);

            return $data === null ? $raw : (string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        if ($format === 'xml') {
            $body = (string) preg_replace('/^\s*<\?xml[^>]*\?>\s*/', '', $raw);
            $doc = new DOMDocument;
            $doc->preserveWhiteSpace = false;
            $doc->formatOutput = true;
            $previous = libxml_use_internal_errors(true);
            $ok = $doc->loadXML("<ZenithEvent>{$body}</ZenithEvent>", LIBXML_NONET);
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            if ($ok) {
                $out = '';
                foreach ($doc->documentElement->childNodes as $node) {
                    $out .= $doc->saveXML($node)."\n";
                }

                return rtrim($out);
            }
        }

        return $raw;
    }
}
