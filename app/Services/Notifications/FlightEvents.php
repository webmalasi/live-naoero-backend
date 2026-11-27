<?php

namespace App\Services\Notifications;

use App\Jobs\ProcessZenithEvent;
use App\Jobs\SendPushNotification;
use App\Services\Booking\BookingService;
use App\Services\Booking\BookingSummary;
use App\Services\Logging\ServerLog;
use App\Services\Support\Js;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Flight disruptions Zenith posts to us (EventMap): a time change
 * (FlightChange), a cancelled flight (SegmentFlightCancelled) or passengers
 * moved to another flight (FlightTransfer), and the reminder Zenith sends
 * some hours before a flight (FlightReminder). Each post is stored once,
 * then the booking is reloaded and every customer holding it gets a notice
 * in the app and on the website. A reminder for a flight the booking no
 * longer holds (cancelled since) is not sent. The webhook key in the address (see
 * ZenithEventController) tells us the post is Zenith's.
 *
 * Zenith raises the first three only when the airline user ticks "notify
 * passengers"; the reminder comes by itself. Zenith retries a post until it gets HTTP 200 (the guide says 3 times, 5
 * minutes apart; preproduction retries every minute). In preproduction it
 * adds "test" to the address, in lowercase ("flight-changetest"), though
 * the guide writes "Test". Its JSON comes wrapped in EventMapNotification.
 *
 * Every post is kept and answered 200, whatever it is. One without the
 * right webhook key is kept as "unverified" and never notifies anyone
 * unless staff process it; one to an address we don't know is kept as
 * "ignored".
 */
final class FlightEvents
{
    /** Address segment => Zenith event. */
    public const EVENTS = [
        'flight-change' => 'FlightChange',
        'segment-flight-cancelled' => 'SegmentFlightCancelled',
        'flight-transfer' => 'FlightTransfer',
        'flight-reminder' => 'FlightReminder',
    ];

    /** What a post to an address we don't know is stored as. */
    public const UNKNOWN = 'Unknown';

    /** Why a post's key was not accepted, as shown to staff. */
    public const KEY_PROBLEMS = [
        'invalid' => 'The webhook key in the address is wrong.',
        'missing' => 'The address has no webhook key.',
        'not_configured' => 'ZENITH_WEBHOOK_SECRET is not set on the server, so the key could not be checked.',
        'ip_not_allowed' => 'It came from an IP address not in ZENITH_EVENTS_IPS.',
    ];

    /**
     * The event an address names, and whether it is preproduction's. Read
     * ignoring case, as Zenith posts "flight-changetest".
     *
     * @return array{event: ?string, test: bool}
     */
    public static function address(string $address): array
    {
        $address = strtolower(trim($address));
        $test = ! isset(self::EVENTS[$address]) && str_ends_with($address, 'test');
        $base = $test ? substr($address, 0, -4) : $address;

        return ['event' => self::EVENTS[$base] ?? null, 'test' => $test];
    }

    /** Zenith event => notice type. */
    public const TYPES = [
        'FlightChange' => 'flight_time_change',
        'SegmentFlightCancelled' => 'flight_cancelled',
        'FlightTransfer' => 'flight_transfer',
        'FlightReminder' => 'flight_reminder',
    ];

    /** Segment statuses of a flight no longer held (as in BookingSummary). */
    private const CANCELLED = ['HX', 'XX', 'UN', 'UC', 'NO'];

    public function __construct(
        private readonly BookingService $bookings,
        private readonly ServerLog $log,
    ) {}

    /**
     * Stores a post, whatever it is, and queues it when it can be acted on.
     * A post already stored (Zenith retrying) is not stored or processed
     * again; a verified copy of one stored as unverified verifies it.
     *
     * @param  string  $event  a Zenith event (EVENTS), or UNKNOWN
     * @param  string  $keyStatus  valid, or why the key was not accepted (KEY_PROBLEMS)
     * @return array{id: string, duplicate: bool, status: string}
     */
    public function receive(string $event, bool $test, string $raw, ?string $ip, ?string $address = null, string $keyStatus = 'valid', ?string $receivedAt = null): array
    {
        $hash = hash('sha256', $raw);
        $existing = DB::table('zenith_events')->where('event', $event)->where('payload_hash', $hash)->first(['id', 'status', 'key_status']);
        if ($existing) {
            return $this->repeat($existing, $keyStatus);
        }

        [$format, $payload] = self::parse($raw);
        [$status, $detail] = match (true) {
            $event === self::UNKNOWN => ['ignored', 'Posted to an address we do not know ('.mb_substr((string) $address, 0, 80).'). Saved, not acted on.'],
            $keyStatus !== 'valid' => ['unverified', (self::KEY_PROBLEMS[$keyStatus] ?? 'The webhook key was not accepted.').' Saved; no customer is notified unless staff process it.'],
            $payload === null => ['failed', 'The body is neither JSON nor XML.'],
            default => ['received', null],
        };
        $id = (string) Str::uuid();
        try {
            DB::table('zenith_events')->insert([
                'id' => $id,
                'event' => $event,
                'is_test' => $test,
                'format' => $format,
                'payload_hash' => $hash,
                'raw' => self::utf8($raw),
                'payload' => $payload === null ? null : Js::encode($payload),
                'pnr' => $payload === null ? null : self::pnrOf($payload),
                'status' => $status,
                'detail' => $detail,
                'ip' => $ip,
                'address' => $address === null ? null : mb_substr($address, 0, 80),
                'key_status' => $keyStatus,
                'received_at' => $receivedAt ?? Js::dbTime(),
                ...($payload === null ? [] : self::details($payload)),
            ]);
        } catch (UniqueConstraintViolationException) {
            // The same post arrived twice at once.
            return $this->repeat(DB::table('zenith_events')->where('event', $event)->where('payload_hash', $hash)->first(['id', 'status', 'key_status']), $keyStatus);
        }

        $this->log->info('zenith-event', "{$event} received", ['id' => $id, 'test' => $test, 'format' => $format, 'status' => $status]);
        if ($status === 'received') {
            $this->queue($id);
        }

        return ['id' => $id, 'duplicate' => false, 'status' => $status];
    }

    /** @return array{id: string, duplicate: bool, status: string} */
    private function repeat(object $existing, string $keyStatus): array
    {
        if ($existing->status === 'unverified' && $keyStatus === 'valid') {
            DB::table('zenith_events')->where('id', $existing->id)->update(['status' => 'received', 'key_status' => 'valid', 'detail' => null]);
            $this->queue($existing->id);

            return ['id' => $existing->id, 'duplicate' => true, 'status' => 'received'];
        }

        return ['id' => $existing->id, 'duplicate' => true, 'status' => $existing->status];
    }

    private function queue(string $id): void
    {
        try {
            ProcessZenithEvent::dispatch($id);
        } catch (Throwable $err) {
            // A queue run in-process (sync) throws its failure here. The
            // post is stored, so Zenith still gets its 200.
            $this->log->warn('zenith-event', 'processing deferred', ['id' => $id, 'error' => $err->getMessage()]);
        }
    }

    /** A call turned away before every post was kept => the key's status it implies. */
    private const TURNED_AWAY = [
        'unknown_event' => 'valid', // the key was checked first
        'too_large' => 'valid',
        'error' => 'valid',
        'invalid_secret' => 'invalid',
        'missing_secret' => 'missing',
        'not_configured' => 'not_configured',
        'ip_not_allowed' => 'ip_not_allowed',
    ];

    /**
     * Turns the calls that were turned away, before every post was kept,
     * into events (zenith_event_calls keeps each body), oldest first so an
     * event keeps the time Zenith first sent it. Each retry is linked to its
     * event and marked "recovered"; the answer we sent then is left as it
     * was. Calls with an empty or cut-off body are left out. Also corrects
     * each call's event and environment (the lowercase "test"), and reads
     * every stored event again with today's parser. Safe to run again.
     *
     * @return array{recovered: int, events: int, queued: int, skipped: int, recounted: int, reparsed: int}
     */
    public function recoverCalls(bool $dry = false, array $ips = []): array
    {
        $report = ['recovered' => 0, 'events' => 0, 'queued' => 0, 'skipped' => 0, 'recounted' => 0, 'reparsed' => 0];

        // Every call's event and environment, read ignoring case.
        foreach (DB::table('zenith_event_calls')->get(['id', 'address', 'event', 'is_test']) as $c) {
            ['event' => $event, 'test' => $test] = self::address((string) $c->address);
            if ($event !== $c->event || $test !== (bool) $c->is_test) {
                $report['recounted']++;
                if (! $dry) {
                    DB::table('zenith_event_calls')->where('id', $c->id)->update(['event' => $event, 'is_test' => $test]);
                }
            }
        }

        $calls = DB::table('zenith_event_calls')->whereNull('zenith_event_id')
            ->whereIn('outcome', array_keys(self::TURNED_AWAY))
            // Only the calls from these IPs (Zenith's), when given: not our own tests.
            ->when($ips !== [], fn ($q) => $q->whereIn('ip', $ips))
            ->orderBy('received_at')->get();
        $events = [];
        foreach ($calls as $c) {
            if ($c->request_body === null || trim($c->request_body) === '' || $c->request_body_truncated) {
                $report['skipped']++;

                continue;
            }
            ['event' => $event, 'test' => $test] = self::address((string) $c->address);
            $name = $event ?? self::UNKNOWN;
            $report['recovered']++;
            if ($dry) {
                $events[$name.'|'.$c->request_sha256] = true;

                continue;
            }
            // Zenith's retry may already be stored since the fix (same bytes).
            $id = DB::table('zenith_events')->where('event', $name)->where('payload_hash', $c->request_sha256)->value('id');
            if ($id === null) {
                $stored = $this->receive($name, $test, $c->request_body, $c->ip, $c->address, self::TURNED_AWAY[$c->outcome], Js::dbTime(CarbonImmutable::parse($c->received_at)));
                $id = $stored['id'];
                if (! $stored['duplicate']) {
                    $report['events']++;
                    $report['queued'] += $stored['status'] === 'received' ? 1 : 0;
                }
            }
            DB::table('zenith_event_calls')->where('id', $c->id)->update(['zenith_event_id' => $id, 'outcome' => 'recovered']);
        }
        if ($dry) {
            $report['events'] = count($events);
        } else {
            // An event with recovered calls: received when Zenith first sent it, at that address.
            $ids = DB::table('zenith_event_calls')->where('outcome', 'recovered')->whereNotNull('zenith_event_id')->distinct()->pluck('zenith_event_id');
            foreach ($ids as $id) {
                $first = DB::table('zenith_event_calls')->where('zenith_event_id', $id)->orderBy('received_at')->first(['received_at', 'address']);
                $e = DB::table('zenith_events')->where('id', $id)->first(['received_at']);
                if ($first && $e && CarbonImmutable::parse($first->received_at)->lt(CarbonImmutable::parse($e->received_at))) {
                    DB::table('zenith_events')->where('id', $id)->update([
                        'received_at' => Js::dbTime(CarbonImmutable::parse($first->received_at)),
                        'address' => $first->address,
                        'is_test' => self::address((string) $first->address)['test'],
                    ]);
                }
            }
        }

        // Stored events read again (the JSON wrapper, the lowercase address).
        foreach (DB::table('zenith_events')->get(['id', 'raw', 'payload', 'pnr', 'address', 'is_test', 'flight_number', 'new_departure_at']) as $e) {
            [, $payload] = self::parse((string) $e->raw);
            if ($payload === null) {
                continue;
            }
            $values = ['payload' => Js::encode($payload), 'pnr' => $e->pnr ?? self::pnrOf($payload), ...self::details($payload)];
            if ($e->address !== null) {
                $values['is_test'] = self::address($e->address)['test'];
            }
            $before = Js::decode($e->payload);
            if ($before !== $payload || $e->flight_number !== $values['flight_number'] || ($values['is_test'] ?? $e->is_test) != $e->is_test) {
                $report['reparsed']++;
                if (! $dry) {
                    DB::table('zenith_events')->where('id', $e->id)->update($values);
                }
            }
        }

        return $report;
    }

    /**
     * Reloads the booking for each customer holding it, saves it and adds the
     * notice. Safe to run again: each customer gets one notice per event.
     */
    public function process(string $id): void
    {
        $e = DB::table('zenith_events')->where('id', $id)->first();
        // An unverified post waits for staff (who set it to received first).
        if (! $e || in_array($e->status, ['processed', 'ignored', 'unverified'], true)) {
            return;
        }
        DB::table('zenith_events')->where('id', $id)->increment('attempts');
        if (! $e->pnr) {
            $this->finish($id, 'failed', 'No booking reference in the event.');

            return;
        }

        $payload = Js::decode($e->payload);
        $payload = is_array($payload) ? self::unwrap($payload) : null;
        $rows = DB::table('bookings')->where('pnr', $e->pnr)->whereNull('hidden_at')
            ->get(['id', 'customer_id', 'zenith_booking']);
        $added = false;
        if ($rows->isEmpty()) {
            // Nobody here has added the booking (booked on the airline's site,
            // say), but Zenith names its customer: when that is one of ours,
            // the booking is added to their account and they are told.
            $owner = $this->namedCustomer($e, $payload);
            if ($owner === null) {
                $named = self::namedLogin($payload);
                $this->finish($id, 'ignored', 'No customer of ours holds this booking.'
                    .($e->customer_ref || $named ? ' Zenith names customer '.trim(($named ?? '').' (ref '.($e->customer_ref ?? '?').')').', who has no account here.' : ''));

                return;
            }
            $rows = collect([(object) ['id' => null, 'customer_id' => $owner->id, 'zenith_booking' => null]]);
            $added = true;
        }

        $reminder = $e->event === 'FlightReminder';
        $told = is_array($payload) ? self::eventChange($e->event, $payload) : null;
        $notified = 0;
        $skipped = 0;
        foreach ($rows as $row) {
            $customer = DB::table('customers')->where('id', $row->customer_id)
                ->first(['id', 'zenith_environment', 'zenith_customer_ref', 'zenith_login', 'email', 'status']);
            if (! $customer) {
                continue;
            }
            $before = Js::decode($row->zenith_booking);
            $before = is_array($before) ? $before : [];
            $after = null;
            try {
                $after = $this->bookings->loadZenithBooking($e->pnr, $customer);
                $this->bookings->saveBooking($customer, $after);
                // Added just now: its row, for the notice.
                $row->id ??= DB::table('bookings')->where('customer_id', $customer->id)->where('pnr', $e->pnr)->value('id');
            } catch (Throwable $err) {
                // The notice still goes out, from the event's own details.
                $this->log->warn('zenith-event', 'booking reload failed', ['id' => $id, 'pnr' => $e->pnr, 'error' => $err->getMessage()]);
            }

            if ($reminder) {
                // The flight it is about: from the post, else from the booking.
                $flight = self::reminderFlight(is_array($payload) ? $payload : [], $after ?? $before, $e->segment_ref);
                if ($flight === false) {
                    $skipped++;

                    continue;
                }
                $changes = [];
                $notice = self::reminderNotice($e->pnr, $flight);
                $data = ['event' => $e->event, 'changes' => [], 'flight' => $flight];
            } else {
                // What the event says changed; else what changed in the booking;
                // else the notice says it in general words.
                $changes = $told ?? ($after === null ? [] : self::changes($before, $after));
                $notice = self::notice($e->event, $e->pnr, $changes);
                $data = ['event' => $e->event, 'changes' => $changes];
            }
            $noticeId = (string) Str::uuid();
            $saved = DB::table('customer_notifications')->insertOrIgnore([
                'id' => $noticeId,
                'customer_id' => $customer->id,
                'booking_id' => $row->id,
                'zenith_event_id' => $id,
                'type' => self::TYPES[$e->event] ?? 'flight_change',
                'pnr' => $e->pnr,
                'title' => $notice['title'],
                'body' => $notice['body'],
                'data' => Js::encode($data),
                'created_at' => Js::dbTime(),
            ]);
            $notified += $saved;
            if ($saved > 0) {
                // To the customer's phones as well.
                SendPushNotification::dispatch($noticeId)->afterCommit();
            }
        }

        $this->finish($id, 'processed', "{$notified} customer(s) notified."
            .($skipped ? " {$skipped} not reminded: the booking no longer holds this flight." : '')
            .($added ? ' The booking was added to the account of the customer Zenith names (they had not added it here).' : ''));
    }

    /**
     * The customer Zenith names in the post (Data.Customer: Ref and Login),
     * when they have an active account here. Both must match: a reference
     * is only unique within a Zenith environment.
     */
    private function namedCustomer(object $e, ?array $payload): ?object
    {
        $login = self::namedLogin($payload);
        if (! $e->customer_ref || $login === null) {
            return null;
        }

        return DB::table('customers')->where('zenith_customer_ref', (string) $e->customer_ref)
            ->whereRaw('lower(zenith_login) = lower(?)', [$login])->where('status', 'active')
            ->first(['id']);
    }

    private static function namedLogin(?array $payload): ?string
    {
        $data = is_array($payload['Data'] ?? null) ? $payload['Data'] : ($payload ?? []);
        $login = $data['Customer']['Login'] ?? null;

        return is_string($login) && trim($login) !== '' ? trim($login) : null;
    }

    /** After the last retry of the queued job. */
    public function failed(string $id, Throwable $e): void
    {
        $this->finish($id, 'failed', mb_substr($e->getMessage(), 0, 1000));
    }

    /** The outcome, saved and logged (every outcome, so the log alone tells what happened). */
    private function finish(string $id, string $status, string $detail): void
    {
        DB::table('zenith_events')->where('id', $id)->update([
            'status' => $status,
            'detail' => $detail,
            'processed_at' => Js::dbTime(),
        ]);
        $e = DB::table('zenith_events')->where('id', $id)->first(['event', 'pnr', 'is_test', 'received_at']);
        $context = ['id' => $id, 'event' => $e?->event, 'pnr' => $e?->pnr, 'test' => (bool) ($e?->is_test), 'status' => $status, 'detail' => $detail];
        if ($status === 'failed') {
            $this->log->error('zenith-event', "{$e?->event} failed", $context);
        } else {
            $this->log->info('zenith-event', "{$e?->event} {$status}", $context);
        }
    }

    // ------------------------------------------------------------ reading the post

    /** A path with the webhook key hidden, for logs and saved calls. */
    public static function maskPath(string $path): string
    {
        return (string) preg_replace('~^(/api/v1/zenith/events/)[^/]+(?=/)~', '$1***', $path);
    }

    /**
     * The post as UTF-8. Zenith's XML says encoding="utf-16": the bytes may
     * really be UTF-16 (with or without a byte order mark) or UTF-8.
     */
    public static function utf8(string $raw): string
    {
        if (str_starts_with($raw, "\xFF\xFE") || str_starts_with($raw, "\xFE\xFF")) {
            $raw = mb_convert_encoding(substr($raw, 2), 'UTF-8', $raw[0] === "\xFF" ? 'UTF-16LE' : 'UTF-16BE');
        } elseif (strlen($raw) >= 2 && $raw[0] === '<' && $raw[1] === "\0") {
            $raw = mb_convert_encoding($raw, 'UTF-8', 'UTF-16LE');
        } elseif (strlen($raw) >= 2 && $raw[0] === "\0" && $raw[1] === '<') {
            $raw = mb_convert_encoding($raw, 'UTF-8', 'UTF-16BE');
        } elseif (str_starts_with($raw, "\xEF\xBB\xBF")) {
            $raw = substr($raw, 3);
        }

        return mb_scrub($raw, 'UTF-8');
    }

    /**
     * Zenith posts JSON or XML, as set in EventMap. Its XML is an <Event>
     * (the flight's details) followed by a <Data> (the customer and a
     * ready-to-use LoadBookingRequest), with no element around the two.
     * Its JSON has both inside an EventMapNotification, which is taken off
     * here so every post reads the same way.
     *
     * @return array{0: string, 1: ?array}
     */
    public static function parse(string $raw): array
    {
        $raw = trim(self::utf8($raw));
        if ($raw === '') {
            return ['json', null];
        }
        if ($raw[0] === '<') {
            // The declaration names the encoding of the bytes as sent, not of this UTF-8 copy.
            $body = (string) preg_replace('/^<\?xml[^>]*\?>\s*/', '', $raw);
            $previous = libxml_use_internal_errors(true);
            $xml = simplexml_load_string($body, options: LIBXML_NONET | LIBXML_NOCDATA);
            if ($xml === false) {
                $xml = simplexml_load_string("<ZenithEvent>{$body}</ZenithEvent>", options: LIBXML_NONET | LIBXML_NOCDATA);
            }
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            $data = $xml === false ? null : json_decode((string) json_encode($xml), true);

            return ['xml', is_array($data) ? self::unwrap($data) : null];
        }
        $data = json_decode($raw, true);

        return ['json', is_array($data) ? self::unwrap($data) : null];
    }

    /**
     * {"EventMapNotification": {"Event": ..., "Data": ...}} => {"Event": ..., "Data": ...}:
     * a single outer element around the event is taken off, whatever its name.
     */
    public static function unwrap(array $payload): array
    {
        for ($i = 0; $i < 3 && count($payload) === 1; $i++) {
            $inner = reset($payload);
            if (! is_array($inner) || (! isset($inner['Event']) && ! isset($inner['Data']) && ! isset($inner['LoadBookingRequest']))) {
                break;
            }
            $payload = $inner;
        }

        return $payload;
    }

    /**
     * The booking reference: from the ready-to-use LoadBookingRequest when
     * there is one, else the first PNR code anywhere in the post.
     */
    public static function pnrOf(array $payload): ?string
    {
        $found = null;
        $walk = function (mixed $node, int $depth) use (&$walk, &$found): void {
            if ($found !== null || ! is_array($node) || $depth > 12) {
                return;
            }
            $unique = $node['UniqueID'] ?? null;
            if (is_array($unique) && in_array($unique['TypeCode'] ?? 'PnrCode', ['PnrCode', null], true) && is_string($unique['ID'] ?? null)) {
                $found = $unique['ID'];

                return;
            }
            foreach (['PnrCode', 'RecordLocator'] as $key) {
                if (is_string($node[$key] ?? null) && $node[$key] !== '') {
                    $found = $node[$key];

                    return;
                }
            }
            foreach ($node as $child) {
                $walk($child, $depth + 1);
            }
        };
        $walk($payload['LoadBookingRequest'] ?? null, 0);
        $walk($payload, 0);
        $pnr = $found === null ? '' : strtoupper(trim($found));

        return preg_match('/^[A-Z0-9]{5,8}$/', $pnr) ? $pnr : null;
    }

    // ------------------------------------------------------------ what changed

    /**
     * What the post says, in typed fields (zenith_events columns): the flight
     * as it was (a cancellation's flight; a time change's or transfer's Old
     * values), the flight as it is now (New values; none for a
     * cancellation), the reason, and the customer Zenith names in <Data>.
     * Times are the airport's local time, as Zenith gives them.
     *
     * @return array<string, ?string>
     */
    public static function details(array $payload): array
    {
        $payload = self::unwrap($payload);
        $ev = is_array($payload['Event'] ?? null) ? $payload['Event'] : $payload;
        $text = fn (mixed $v, int $max): ?string => is_scalar($v) && trim((string) $v) !== '' ? mb_substr(trim((string) $v), 0, $max) : null;
        $v = fn (string $key, int $max = 120): ?string => $text($ev[$key] ?? null, $max);
        $time = function (?string $t): ?string {
            if ($t === null || ! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2})?/', $t)) {
                return null;
            }
            try {
                return CarbonImmutable::parse(substr($t, 0, 19))->format('Y-m-d H:i:s');
            } catch (Throwable) {
                return null;
            }
        };
        // A time change gives the flight once and Old/New times; a transfer
        // gives Old/New of everything; a cancellation the flight once.
        $moved = $v('NewDepartureDate') !== null || $v('NewFlightNumber') !== null;
        $old = fn (string $f, int $max = 120) => $v("Old{$f}", $max) ?? $v($f, $max);
        $new = fn (string $f, int $max = 120) => $moved ? ($v("New{$f}", $max) ?? $v($f, $max)) : null;

        $data = is_array($payload['Data'] ?? null) ? $payload['Data'] : $payload;
        $customer = is_array($data['Customer'] ?? null) ? $data['Customer'] : [];
        $first = fn (mixed $list, string $item, string $field) => is_array($list) ? $text(($list[$item][$field] ?? $list[$item][0][$field] ?? null), 254) : null;

        return [
            'airline' => $old('AirlineDesignator', 3),
            'flight_number' => $old('FlightNumber', 8),
            'origin' => $old('OriginCode', 5),
            'destination' => $old('DestinationCode', 5),
            'departure_at' => $time($old('DepartureDate', 40)),
            'arrival_at' => $time($old('ArrivalDate', 40)),
            'equipment' => $old('EquipmentText'),
            'new_airline' => $new('AirlineDesignator', 3),
            'new_flight_number' => $new('FlightNumber', 8),
            'new_origin' => $new('OriginCode', 5),
            'new_destination' => $new('DestinationCode', 5),
            'new_departure_at' => $time($new('DepartureDate', 40)),
            'new_arrival_at' => $time($new('ArrivalDate', 40)),
            'new_equipment' => $new('EquipmentText'),
            'origin_terminal' => $v('OriginAirportTerminal', 20),
            'destination_terminal' => $v('DestinationAirportTerminal', 20),
            'segment_ref' => $v('RefSegment', 40),
            'reason' => $v('CancellationReason', 1000) ?? $v('TransferReason', 1000) ?? $v('ChangeReason', 1000),
            'customer_ref' => $text($customer['Ref'] ?? null, 40),
            'customer_name' => $text(trim(($text($customer['Firstname'] ?? null, 100) ?? '').' '.($text($customer['Surname'] ?? null, 100) ?? '')), 200),
            'customer_email' => $first($customer['Emails'] ?? null, 'CustomerEmail', 'Email'),
            'customer_phone' => ($phone = $first($customer['Phones'] ?? null, 'CustomerPhone', 'PhoneNumber')) === null ? null : mb_substr($phone, 0, 40),
        ];
    }

    /**
     * The flight before and after, from the post's details: a cancellation
     * names the flight; a time change and a transfer give its Old and New
     * values. Null when the post doesn't say enough.
     *
     * @return ?list<array{kind: string, before: ?array, after: ?array}>
     */
    public static function eventChange(string $event, array $payload): ?array
    {
        $d = self::details($payload);
        $iso = fn (?string $t) => $t === null ? null : str_replace(' ', 'T', $t);
        $flight = fn (string $p) => [
            'flightNumber' => ($d["{$p}airline"] ?? '').($d["{$p}flight_number"] ?? ''),
            'origin' => $d["{$p}origin"],
            'destination' => $d["{$p}destination"],
            'departure' => $iso($d["{$p}departure_at"]),
            'arrival' => $iso($d["{$p}arrival_at"]),
            'status' => null,
        ];
        $known = fn (array $f) => $f['flightNumber'] !== '' && $f['origin'] && $f['destination'] && $f['departure'];

        $change = match ($event) {
            'SegmentFlightCancelled' => ['kind' => 'cancelled', 'before' => $flight(''), 'after' => null],
            'FlightChange' => ['kind' => 'retimed', 'before' => $flight(''), 'after' => $flight('new_')],
            'FlightTransfer' => ['kind' => 'moved', 'before' => $flight(''), 'after' => $flight('new_')],
            default => null,
        };
        if ($change === null) {
            return null;
        }
        foreach ([$change['before'], $change['after']] as $f) {
            if ($f !== null && ! $known($f)) {
                return null;
            }
        }

        return [$change];
    }

    /**
     * Flights of the booking before and after, compared segment by segment:
     * cancelled, retimed (new departure or arrival), moved (another flight
     * number on the same segment) or added (a new segment, e.g. the flight
     * passengers were transferred to).
     *
     * @return list<array{kind: string, before: ?array, after: ?array}>
     */
    public static function changes(array $before, array $after): array
    {
        $old = self::segments($before);
        $new = self::segments($after);
        $out = [];
        foreach ($new as $ref => $n) {
            $o = $old[$ref] ?? null;
            if ($o === null) {
                if (! $n['cancelled']) {
                    $out[] = ['kind' => 'added', 'before' => null, 'after' => $n['flight']];
                }
            } elseif ($n['cancelled'] && ! $o['cancelled']) {
                $out[] = ['kind' => 'cancelled', 'before' => $o['flight'], 'after' => null];
            } elseif (! $n['cancelled'] && $o['flight']['flightNumber'] !== $n['flight']['flightNumber']) {
                $out[] = ['kind' => 'moved', 'before' => $o['flight'], 'after' => $n['flight']];
            } elseif (! $n['cancelled'] && ($o['flight']['departure'] !== $n['flight']['departure'] || $o['flight']['arrival'] !== $n['flight']['arrival'])) {
                $out[] = ['kind' => 'retimed', 'before' => $o['flight'], 'after' => $n['flight']];
            }
        }
        foreach ($old as $ref => $o) {
            if (! isset($new[$ref]) && ! $o['cancelled']) {
                $out[] = ['kind' => 'cancelled', 'before' => $o['flight'], 'after' => null];
            }
        }

        return $out;
    }

    /** @return array<string, array{flight: array, cancelled: bool}> */
    private static function segments(array $b): array
    {
        $out = [];
        foreach (Js::list($b['Segments'] ?? null) as $i => $s) {
            if (! is_array($s) || ! isset($s['OriginCode'], $s['DestinationCode'])) {
                continue;
            }
            $flight = BookingSummary::flight($s);
            $out[(string) ($s['Ref'] ?? "i{$i}")] = ['flight' => $flight, 'cancelled' => in_array($flight['status'], self::CANCELLED, true)];
        }

        return $out;
    }

    // ------------------------------------------------------------ the notice

    /** @return array{title: string, body: string} */
    public static function notice(string $event, string $pnr, array $changes): array
    {
        $lines = [];
        foreach ($changes as $c) {
            $lines[] = match ($c['kind']) {
                'retimed' => sprintf('%s now departs %s (was %s) and arrives %s.', self::route($c['after']), self::when($c['after']['departure']), self::when($c['before']['departure']), self::when($c['after']['arrival'])),
                'moved' => sprintf('%s on %s is now flight %s, departing %s.', self::route($c['before']), self::when($c['before']['departure']),
                    [$c['before']['origin'], $c['before']['destination']] === [$c['after']['origin'], $c['after']['destination']] ? $c['after']['flightNumber'] : self::route($c['after']),
                    self::when($c['after']['departure'])),
                'added' => sprintf('You are now on %s, departing %s.', self::route($c['after']), self::when($c['after']['departure'])),
                'cancelled' => sprintf('%s on %s has been cancelled.', self::route($c['before']), self::when($c['before']['departure'])),
                default => null,
            };
        }
        $lines = array_values(array_filter($lines));
        $detail = $lines ? implode(' ', $lines).' Times are local.' : null;

        return match ($event) {
            'FlightChange' => [
                'title' => 'Your flight time has changed',
                'body' => ($detail ?? "The time of a flight on booking {$pnr} has changed.")." Please check booking {$pnr} for your new times.",
            ],
            'SegmentFlightCancelled' => [
                'title' => 'Your flight has been cancelled',
                'body' => ($detail ?? "A flight on booking {$pnr} has been cancelled.")." Open booking {$pnr} to see your options, or contact Naoero Airlines.",
            ],
            'FlightTransfer' => [
                'title' => 'You have been moved to another flight',
                'body' => ($detail ?? "You have been moved to another flight on booking {$pnr}.")." Please check booking {$pnr} for your new flight.",
            ],
            default => [
                'title' => 'Your flight has changed',
                'body' => "A flight on booking {$pnr} has changed. Please check your booking.",
            ],
        };
    }

    /**
     * The flight a reminder is about: the one in the post when it names it
     * fully, else the booking's segment it refers to (RefSegment), else the
     * booking's next flight still held. False when that flight is cancelled
     * in the booking (no reminder then); null when it can't be told.
     */
    public static function reminderFlight(array $payload, array $booking, ?string $segmentRef): array|false|null
    {
        $segments = self::segments($booking);
        if ($segmentRef !== null && isset($segments[$segmentRef]) && $segments[$segmentRef]['cancelled']) {
            return false;
        }
        $d = self::details($payload);
        $iso = fn (?string $t) => $t === null ? null : str_replace(' ', 'T', $t);
        $posted = [
            'flightNumber' => ($d['airline'] ?? '').($d['flight_number'] ?? ''),
            'origin' => $d['origin'],
            'destination' => $d['destination'],
            'departure' => $iso($d['departure_at']),
            'arrival' => $iso($d['arrival_at']),
            'status' => null,
        ];
        if ($posted['flightNumber'] !== '' && $posted['origin'] && $posted['destination'] && $posted['departure']) {
            return $posted;
        }
        if ($segmentRef !== null && isset($segments[$segmentRef])) {
            return $segments[$segmentRef]['flight'];
        }
        $held = array_values(array_filter($segments, fn (array $s) => ! $s['cancelled']));
        if ($segments !== [] && $held === []) {
            return false;
        }
        usort($held, fn (array $a, array $b) => strcmp((string) $a['flight']['departure'], (string) $b['flight']['departure']));

        return $held[0]['flight'] ?? null;
    }

    /** @return array{title: string, body: string} */
    public static function reminderNotice(string $pnr, ?array $flight): array
    {
        $what = $flight === null
            ? "Your flight on booking {$pnr} is coming up soon."
            : sprintf('%s departs %s (local time).', self::route($flight), self::when($flight['departure']));

        return [
            'title' => 'Your flight is coming up',
            'body' => "{$what} Please have your travel documents ready and allow plenty of time at the airport. Open booking {$pnr} for your flight details.",
        ];
    }

    private static function route(array $f): string
    {
        return trim("{$f['flightNumber']} {$f['origin']}–{$f['destination']}");
    }

    private static function when(?string $local): string
    {
        if (! $local) {
            return 'at a new time';
        }
        try {
            return CarbonImmutable::parse($local)->format('D j M \a\t H:i');
        } catch (Throwable) {
            return $local;
        }
    }
}
