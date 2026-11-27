<?php

namespace Tests\Feature\Api\V1;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Flight events Zenith posts to us (EventMap: FlightChange,
 * SegmentFlightCancelled, FlightTransfer) become notices the customer
 * reads in the app and on the website.
 */
class ZenithEventsApiTest extends AppApiTestCase
{
    private const SECRET = 'webhook-secret-0123456789abcdef0123456789abcdef0123456789abcdef';

    private string $token;

    private array $booking;

    protected function setUp(): void
    {
        parent::setUp();
        config(['naoero.zenith_events.secret' => self::SECRET, 'naoero.zenith_events.allowed_ips' => []]);
        $this->token = $this->signUp()['accessToken'];

        // The customer's booking: ON 092 BNE-INU, 28 Oct 06:20-13:05.
        $this->booking = FakeZenith::fixture('CreateBooking')['Booking'];
        $this->booking['PnrInformation']['PnrCode'] = 'EVT123';
        $this->zenith->pnrBookings = ['EVT123' => $this->booking];
        $this->api('POST', 'bookings/find', ['pnr' => 'EVT123', 'lastName' => 'Testnywxts'], $this->token)->assertCreated();
    }

    /** What Zenith posts: event data with a ready-to-use LoadBookingRequest. */
    private static function body(string $pnr = 'EVT123'): array
    {
        return [
            'Customer' => ['Ref' => '123'],
            'LoadBookingRequest' => ['UniqueID' => ['TypeCode' => 'PnrCode', 'ID' => $pnr], 'Verification' => ['PassengerName' => 'TESTNYWXTS']],
        ];
    }

    private function postEvent(string $event, array|string $body, ?string $secret = null)
    {
        $raw = is_string($body) ? $body : json_encode($body);

        return $this->call('POST', '/api/v1/zenith/events/'.($secret ?? self::SECRET).'/'.$event, [], [], [], ['CONTENT_TYPE' => 'application/json'], $raw);
    }

    /** The booking's first flight set to the given one. */
    private function withFlight(array $b, string $number, string $from, string $to, string $departure, string $arrival, string $status = 'HK'): array
    {
        $s = &$b['Segments'][0];
        [$s['AirlineDesignator'], $s['FlightInfo']['FlightNumber'], $s['OriginCode'], $s['DestinationCode']] = ['ON', $number, $from, $to];
        [$s['FlightInfo']['DepartureDate'], $s['FlightInfo']['ArrivalDate'], $s['BookingClass']['StatusCode']] = [$departure, $arrival, $status];

        return $b;
    }

    /** The booking as Zenith returns it after the airline's change. */
    private function airlineChanges(callable $change): void
    {
        $b = $this->booking;
        $change($b['Segments'][0]);
        $this->zenith->pnrBookings = ['EVT123' => $b];
    }

    public function test_a_time_change_notifies_the_customer_with_the_new_times(): void
    {
        $this->airlineChanges(function (array &$s) {
            $s['FlightInfo']['DepartureDate'] = '2026-10-28T09:45:00';
            $s['FlightInfo']['ArrivalDate'] = '2026-10-28T16:30:00';
        });

        $this->postEvent('flight-change', self::body())->assertOk()->assertSee('OK');

        $this->assertDatabaseHas('zenith_events', ['event' => 'FlightChange', 'pnr' => 'EVT123', 'status' => 'processed', 'is_test' => false]);
        $list = $this->api('GET', 'me/notifications', token: $this->token)->assertOk();
        $list->assertJsonPath('unreadCount', 1)
            ->assertJsonPath('notifications.0.type', 'flight_time_change')
            ->assertJsonPath('notifications.0.pnr', 'EVT123')
            ->assertJsonPath('notifications.0.title', 'Your flight time has changed')
            ->assertJsonPath('notifications.0.changes.0.kind', 'retimed')
            ->assertJsonPath('notifications.0.changes.0.before.departure', '2026-10-28T06:20:00')
            ->assertJsonPath('notifications.0.changes.0.after.departure', '2026-10-28T09:45:00')
            ->assertJsonPath('notifications.0.read', false);
        $this->assertStringContainsString('ON092 BNE–INU now departs Wed 28 Oct at 09:45 (was Wed 28 Oct at 06:20)', $list->json('notifications.0.body'));

        // The saved booking has the new time too.
        $this->assertSame('2026-10-28T09:45:00', $this->api('GET', 'bookings', token: $this->token)->json('bookings.0.flights.0.departure'));

        // Read: the badge clears.
        $id = $list->json('notifications.0.id');
        $this->api('GET', 'me/notifications/unread-count', token: $this->token)->assertOk()->assertJsonPath('unreadCount', 1);
        $this->api('POST', "me/notifications/{$id}/read", token: $this->token)->assertOk()->assertJsonPath('unreadCount', 0);
        $this->api('GET', 'me/notifications', ['unread' => 1], $this->token)->assertJsonCount(0, 'notifications');
        $this->assertNotNull($this->api('GET', 'me/notifications', token: $this->token)->json('notifications.0.readAt'));
    }

    public function test_a_cancelled_flight_and_a_transfer_get_their_own_notices(): void
    {
        $this->airlineChanges(fn (array &$s) => $s['BookingClass']['StatusCode'] = 'HX');
        $this->postEvent('segment-flight-cancelled', self::body())->assertOk();

        $this->airlineChanges(function (array &$s) {
            $s['FlightInfo']['FlightNumber'] = '094';
            $s['FlightInfo']['DepartureDate'] = '2026-10-29T06:20:00';
        });
        $this->postEvent('flight-transfer', self::body() + ['Segment' => ['Ref' => '490318']])->assertOk();

        $list = $this->api('GET', 'me/notifications', token: $this->token)->assertOk()->assertJsonPath('unreadCount', 2);
        $byType = collect($list->json('notifications'))->keyBy('type');
        $this->assertSame('Your flight has been cancelled', $byType['flight_cancelled']['title']);
        $this->assertSame('cancelled', $byType['flight_cancelled']['changes'][0]['kind']);
        $this->assertStringContainsString('has been cancelled', $byType['flight_cancelled']['body']);
        $this->assertSame('You have been moved to another flight', $byType['flight_transfer']['title']);
        $this->assertSame('moved', $byType['flight_transfer']['changes'][0]['kind']);
        $this->assertStringContainsString('is now flight ON094, departing Thu 29 Oct at 06:20', $byType['flight_transfer']['body']);

        $this->api('POST', 'me/notifications/read-all', token: $this->token)->assertOk()->assertJsonPath('unreadCount', 0);
    }

    /** Zenith's reminder some hours before the flight, as XML with the flight in <Event>. */
    public function test_a_flight_reminder_tells_the_customer_their_flight_is_coming_up(): void
    {
        $xml = '<?xml version="1.0" encoding="utf-16" standalone="yes"?>'."\n"
            .'<Event><AirlineDesignator>ON</AirlineDesignator><FlightNumber>092</FlightNumber><OriginCode>BNE</OriginCode><DestinationCode>INU</DestinationCode>'
            .'<DepartureDate>2026-10-28T06:20:00</DepartureDate><ArrivalDate>2026-10-28T13:05:00</ArrivalDate><RefSegment>490318</RefSegment></Event>'."\n"
            .'<Data Format="Json" Source="CRM"><Customer><Ref>123</Ref></Customer><LoadBookingRequest><UniqueID><TypeCode>PnrCode</TypeCode><ID>EVT123</ID></UniqueID></LoadBookingRequest></Data>';

        $this->call('POST', '/api/v1/zenith/events/'.self::SECRET.'/flight-remindertest', [], [], [], ['CONTENT_TYPE' => 'application/xml'], $xml)->assertOk()->assertSee('OK');

        $this->assertDatabaseHas('zenith_events', ['event' => 'FlightReminder', 'pnr' => 'EVT123', 'status' => 'processed', 'is_test' => true, 'flight_number' => '092', 'segment_ref' => '490318']);
        $list = $this->api('GET', 'me/notifications', token: $this->token)->assertOk()
            ->assertJsonPath('unreadCount', 1)
            ->assertJsonPath('notifications.0.type', 'flight_reminder')
            ->assertJsonPath('notifications.0.category', 'flights')
            ->assertJsonPath('notifications.0.title', 'Your flight is coming up')
            ->assertJsonPath('notifications.0.changes', []);
        $this->assertStringContainsString('ON092 BNE–INU departs Wed 28 Oct at 06:20 (local time).', $list->json('notifications.0.body'));
        $this->assertStringContainsString('booking EVT123', $list->json('notifications.0.body'));
    }

    public function test_a_reminder_without_flight_details_uses_the_booking_and_skips_a_cancelled_flight(): void
    {
        // No <Event> details: the booking's flight is named.
        $this->postEvent('flight-reminder', self::body())->assertOk();
        $body = DB::table('customer_notifications')->where('type', 'flight_reminder')->value('body');
        $this->assertStringContainsString('ON092 BNE–INU departs Wed 28 Oct at 06:20', $body);

        // The flight was cancelled since: no reminder.
        $this->airlineChanges(fn (array &$s) => $s['BookingClass']['StatusCode'] = 'HX');
        $this->postEvent('flight-reminder', self::body() + ['Event' => ['RefSegment' => '490318']])->assertOk();
        $this->assertSame(1, DB::table('customer_notifications')->where('type', 'flight_reminder')->count());
        $this->assertStringContainsString('not reminded', (string) DB::table('zenith_events')->where('segment_ref', '490318')->value('detail'));
    }

    public function test_a_retried_post_is_stored_and_notified_once(): void
    {
        $this->airlineChanges(fn (array &$s) => $s['FlightInfo']['DepartureDate'] = '2026-10-28T07:00:00');
        $this->postEvent('flight-change', self::body())->assertOk();
        $this->postEvent('flight-change', self::body())->assertOk();

        $this->assertSame(1, DB::table('zenith_events')->count());
        $this->assertSame(1, DB::table('customer_notifications')->count());
    }

    /** A post without the right key is kept (answered 200) but never notifies anyone by itself. */
    public function test_a_post_with_a_wrong_or_missing_key_is_kept_unverified(): void
    {
        $this->airlineChanges(fn (array &$s) => $s['FlightInfo']['DepartureDate'] = '2026-10-28T07:00:00');
        $wrong = self::body();
        $this->postEvent('flight-change', $wrong, secret: str_repeat('0', 64))->assertOk()->assertSeeText('OK');
        $missing = self::body() + ['Note' => 'no key'];
        $this->call('POST', '/api/v1/zenith/events/flight-change', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($missing))->assertOk();
        config(['naoero.zenith_events.secret' => null]);
        $this->postEvent('flight-change', self::body() + ['Note' => 'not set'], secret: self::SECRET)->assertOk();
        config(['naoero.zenith_events.secret' => self::SECRET]);

        $events = DB::table('zenith_events')->orderBy('received_at')->get();
        $this->assertSame(['unverified', 'unverified', 'unverified'], $events->pluck('status')->all());
        $this->assertSame(['invalid', 'missing', 'not_configured'], $events->pluck('key_status')->all());
        $this->assertSame(['EVT123', 'EVT123', 'EVT123'], $events->pluck('pnr')->all(), 'read like any other post');
        $this->assertSame(0, DB::table('customer_notifications')->count(), 'no notice without the right key');
        $this->assertSame(['unverified', 'unverified', 'unverified'], DB::table('zenith_event_calls')->orderBy('received_at')->pluck('outcome')->all());

        // Zenith sending the same post with the right key verifies it.
        $this->postEvent('flight-change', $wrong)->assertOk();
        $first = DB::table('zenith_events')->where('id', $events[0]->id)->first();
        $this->assertSame(['processed', 'valid'], [$first->status, $first->key_status]);
        $this->assertSame(1, DB::table('customer_notifications')->count());

        // The key is never saved or logged.
        $this->assertSame(0, DB::table('zenith_event_calls')->where('path', 'like', '%'.self::SECRET.'%')->count());
        $this->assertSame('/api/v1/zenith/events/***/flight-change', DB::table('zenith_event_calls')->where('outcome', 'duplicate')->value('path'));
        $this->assertSame(0, DB::table('server_logs')->where('path', 'like', '%'.self::SECRET.'%')->orWhere('message', 'like', '%'.self::SECRET.'%')->count());
        $this->assertSame(0, DB::table('zenith_event_calls')->where('path', 'like', '%'.str_repeat('0', 64).'%')->count());
    }

    /** Preproduction as Zenith really sends it: "test" in lowercase, JSON inside EventMapNotification. */
    public function test_zenith_preproduction_json_with_a_lowercase_test_address_is_read(): void
    {
        $this->airlineChanges(fn (array &$s) => $s['FlightInfo']['DepartureDate'] = '2026-10-28T06:00:00');
        $post = ['EventMapNotification' => [
            'Event' => [
                'AirlineDesignator' => 'ON', 'FlightNumber' => '092', 'OriginCode' => 'BNE', 'DestinationCode' => 'INU',
                'NewDepartureDate' => '2026-10-28T06:00:00', 'OldDepartureDate' => '2026-10-28T06:20:00',
                'NewArrivalDate' => '2026-10-28T13:05:00', 'OldArrivalDate' => '2026-10-28T13:05:00',
                'NewEquipmentText' => 'Boeing 737-300 Passenger', 'OldEquipmentText' => 'Boeing 737-300 Passenger', 'RefSegment' => '490393',
            ],
            'Data' => [
                'Customer' => ['Ref' => '10027363', 'Firstname' => 'Robert', 'Surname' => 'Eoe', 'Emails' => ['CustomerEmail' => ['Email' => 'robert@example.com']]],
                'LoadBookingRequest' => ['UniqueID' => ['TypeCode' => 'PnrCode', 'ID' => 'EVT123'], 'Verification' => ['PassengerName' => 'TESTNYWXTS']],
            ],
        ]];
        $this->call('POST', '/api/v1/zenith/events/'.self::SECRET.'/flight-changetest', [], [], [], ['CONTENT_TYPE' => "application/json; encoding='utf-8'"], json_encode($post))->assertOk();

        $e = DB::table('zenith_events')->first();
        $this->assertSame(['FlightChange', true, 'processed', 'flight-changetest'], [$e->event, (bool) $e->is_test, $e->status, $e->address]);
        $this->assertSame(['ON', '092', 'BNE', 'INU'], [$e->airline, $e->flight_number, $e->origin, $e->destination]);
        $this->assertSame(['2026-10-28 06:20:00', '2026-10-28 06:00:00'], [$e->departure_at, $e->new_departure_at]);
        $this->assertSame(['10027363', 'Robert Eoe', 'robert@example.com'], [$e->customer_ref, $e->customer_name, $e->customer_email]);
        $call = DB::table('zenith_event_calls')->first();
        $this->assertSame(['FlightChange', true, 'stored', 200], [$call->event, (bool) $call->is_test, $call->outcome, (int) $call->response_status]);
        $n = DB::table('customer_notifications')->first();
        $this->assertSame('flight_time_change', $n->type);
    }

    /** Posts turned away before the fix are made into events from the saved calls. */
    public function test_posts_turned_away_before_are_recovered(): void
    {
        $this->airlineChanges(fn (array &$s) => $s['FlightInfo']['DepartureDate'] = '2026-10-28T06:00:00');
        $body = json_encode(['EventMapNotification' => ['Event' => ['FlightNumber' => '092', 'OriginCode' => 'BNE', 'DestinationCode' => 'INU', 'OldDepartureDate' => '2026-10-28T06:20:00', 'NewDepartureDate' => '2026-10-28T06:00:00'], 'Data' => self::body()]]);
        $call = fn (string $at, string $outcome, ?string $raw) => DB::table('zenith_event_calls')->insert([
            'id' => (string) Str::uuid(), 'received_at' => $at, 'is_test' => false, 'address' => 'flight-changetest', 'event' => null,
            'outcome' => $outcome, 'method' => 'POST', 'path' => '/api/v1/zenith/events/***/flight-changetest', 'ip' => '152.228.195.161',
            'request_headers' => '{}', 'request_bytes' => strlen((string) $raw), 'request_sha256' => hash('sha256', (string) $raw), 'request_body' => $raw,
            'response_status' => $outcome === 'unknown_event' ? 404 : 401, 'response_headers' => '{}', 'response_body' => '{"error":{}}', 'duration_ms' => 2,
        ]);
        $call('2026-10-15 07:00:00+00', 'unknown_event', $body);
        $call('2026-10-15 07:01:00+00', 'unknown_event', $body);   // Zenith retrying
        $call('2026-10-15 07:02:00+00', 'invalid_secret', null);   // empty: nothing to recover

        $this->artisan('naoero:zenith-events-recover', ['--dry-run' => true])->assertSuccessful();
        $this->assertSame(0, DB::table('zenith_events')->count());

        // Not Zenith's IP: left alone.
        $this->artisan('naoero:zenith-events-recover', ['--ip' => ['198.51.100.1']])->assertSuccessful();
        $this->assertSame(0, DB::table('zenith_events')->count());

        $this->artisan('naoero:zenith-events-recover', ['--ip' => ['152.228.195.161']])->assertSuccessful();
        $e = DB::table('zenith_events')->first();
        $this->assertSame(['FlightChange', true, 'processed', 'EVT123', '092'], [$e->event, (bool) $e->is_test, $e->status, $e->pnr, $e->flight_number]);
        $this->assertStringStartsWith('2026-10-15 07:00:00', (string) $e->received_at, 'kept when Zenith first sent it');
        $calls = DB::table('zenith_event_calls')->orderBy('received_at')->get();
        $this->assertSame(['recovered', 'recovered', 'invalid_secret'], $calls->pluck('outcome')->all());
        $this->assertSame([$e->id, $e->id, null], $calls->pluck('zenith_event_id')->all());
        $this->assertSame([404, 404, 401], $calls->pluck('response_status')->map(fn ($v) => (int) $v)->all(), 'what we answered then stays');
        $this->assertSame(['FlightChange', 'FlightChange', 'FlightChange'], $calls->pluck('event')->all());
        $this->assertSame(1, DB::table('customer_notifications')->count());

        // Again: nothing more.
        $this->artisan('naoero:zenith-events-recover')->assertSuccessful();
        $this->assertSame(1, DB::table('zenith_events')->count());
        $this->assertSame(1, DB::table('customer_notifications')->count());
    }

    /** Retries turned away, then one accepted: the event shows when and where Zenith first sent it. */
    public function test_recovered_calls_date_an_event_stored_later(): void
    {
        $this->airlineChanges(fn (array &$s) => $s['FlightInfo']['DepartureDate'] = '2026-10-28T06:00:00');
        $body = json_encode(self::body());
        DB::table('zenith_event_calls')->insert([
            'id' => (string) Str::uuid(), 'received_at' => '2026-10-15 07:00:00+00', 'is_test' => false, 'address' => 'flight-changetest',
            'outcome' => 'unknown_event', 'method' => 'POST', 'path' => '/api/v1/zenith/events/***/flight-changetest', 'ip' => '152.228.195.161',
            'request_headers' => '{}', 'request_bytes' => strlen($body), 'request_sha256' => hash('sha256', $body), 'request_body' => $body,
            'response_status' => 404, 'response_headers' => '{}', 'response_body' => '{}', 'duration_ms' => 2,
        ]);
        $this->postEvent('flight-change', $body)->assertOk(); // accepted at 08:00
        $this->assertSame(1, DB::table('customer_notifications')->count());

        $this->artisan('naoero:zenith-events-recover', ['--ip' => ['152.228.195.161']])->assertSuccessful();
        $e = DB::table('zenith_events')->first();
        $this->assertStringStartsWith('2026-10-15 07:00:00', (string) $e->received_at);
        $this->assertSame(['flight-changetest', true, 'processed'], [$e->address, (bool) $e->is_test, $e->status]);
        $this->assertSame(2, DB::table('zenith_event_calls')->where('zenith_event_id', $e->id)->count());
        $this->assertSame(1, DB::table('customer_notifications')->count(), 'not notified twice');
    }

    public function test_preproduction_xml_and_unknown_bookings_are_accepted(): void
    {
        $this->airlineChanges(fn (array &$s) => $s['FlightInfo']['DepartureDate'] = '2026-10-28T07:00:00');
        // Zenith adds "Test" in preproduction; the body may be XML.
        $xml = '<FlightChangeEvent><LoadBookingRequest><UniqueID><TypeCode>PnrCode</TypeCode><ID>EVT123</ID></UniqueID></LoadBookingRequest></FlightChangeEvent>';
        $this->postEvent('flight-changeTest', $xml)->assertOk();
        $this->assertDatabaseHas('zenith_events', ['event' => 'FlightChange', 'is_test' => true, 'format' => 'xml', 'pnr' => 'EVT123', 'status' => 'processed']);

        // A booking no customer of ours holds: answered 200, nothing to send.
        $this->postEvent('segment-flight-cancelled', self::body('ZZZ999'))->assertOk();
        $this->assertDatabaseHas('zenith_events', ['pnr' => 'ZZZ999', 'status' => 'ignored']);

        // Not JSON or XML: kept for a look, answered 200 so Zenith stops retrying.
        $this->postEvent('flight-transfer', 'nonsense')->assertOk();
        $this->assertDatabaseHas('zenith_events', ['event' => 'FlightTransfer', 'status' => 'failed']);
        $this->assertSame(1, DB::table('customer_notifications')->count());
    }

    /**
     * Zenith's own samples: an <Event> and a <Data> with no element around
     * them, declared utf-16. The notice tells the flight from the <Event>.
     */
    public function test_zenith_sample_events_are_read_in_utf16_and_utf8(): void
    {
        $sample = fn (string $event) => file_get_contents(__DIR__."/../../../Fixtures/Zenith/Events/{$event}.xml");
        $utf16 = fn (string $xml) => mb_convert_encoding($xml, 'UTF-16LE', 'UTF-8');
        $b = $this->booking;
        $cases = [
            // event, address, booking reference in the sample, body as sent, the booking before and after, title, text
            ['FlightChange', 'flight-change', '004DUX', "\xFF\xFE".$utf16($sample('FlightChange')),
                $this->withFlight($b, '411', 'EWR', 'DUS', '2027-04-28T21:00:00', '2027-04-29T09:50:00'),
                $this->withFlight($b, '411', 'EWR', 'DUS', '2027-04-28T22:00:00', '2027-04-29T09:50:00'),
                'Your flight time has changed', 'ON411 EWR–DUS now departs Wed 28 Apr at 22:00 (was Wed 28 Apr at 21:00) and arrives Thu 29 Apr at 09:50. Times are local.'],
            ['SegmentFlightCancelled', 'segment-flight-cancelledTest', '004DZ0', $sample('SegmentFlightCancelled'),
                $this->withFlight($b, '123', 'DUS', 'EWR', '2026-10-15T15:00:00', '2026-10-15T20:00:00'),
                $this->withFlight($b, '123', 'DUS', 'EWR', '2026-10-15T15:00:00', '2026-10-15T20:00:00', 'HX'),
                'Your flight has been cancelled', 'ON123 DUS–EWR on Thu 15 Oct at 15:00 has been cancelled.'],
            ['FlightTransfer', 'flight-transfer', '004DL6', $utf16($sample('FlightTransfer')),
                $this->withFlight($b, '300', 'MXP', 'EWR', '2026-12-06T12:05:00', '2026-12-06T15:40:00'),
                $this->withFlight($b, '300', 'MXP', 'EWR', '2026-12-10T12:05:00', '2026-12-10T15:40:00'),
                'You have been moved to another flight', 'ON300 MXP–EWR on Sun 6 Dec at 12:05 is now flight ON300, departing Thu 10 Dec at 12:05.'],
        ];
        foreach ($cases as [$event, $address, $pnr, $body, $before, $after, $title, $text]) {
            $before['PnrInformation']['PnrCode'] = $after['PnrInformation']['PnrCode'] = $pnr;
            $this->zenith->pnrBookings = [$pnr => $before];
            $this->api('POST', 'bookings/find', ['pnr' => $pnr, 'lastName' => 'Testnywxts'], $this->token)->assertCreated();
            $this->zenith->pnrBookings = [$pnr => $after];

            $this->postEvent($address, $body)->assertOk()->assertSee('OK');

            $row = DB::table('zenith_events')->where('event', $event)->first();
            $this->assertSame(['xml', $pnr, 'processed'], [$row->format, $row->pnr, $row->status], $event);
            $this->assertStringContainsString('<Event>', $row->raw);
            $n = DB::table('customer_notifications')->where('pnr', $pnr)->first();
            $this->assertSame($title, $n->title);
            $this->assertStringContainsString($text, $n->body);
        }

        $this->assertDatabaseHas('zenith_events', ['event' => 'SegmentFlightCancelled', 'is_test' => true]);

        // Everything the post says, in its own columns.
        $this->assertDatabaseHas('zenith_events', [
            'event' => 'FlightChange', 'airline' => 'ON', 'flight_number' => '411', 'origin' => 'EWR', 'destination' => 'DUS',
            'departure_at' => '2027-04-28 21:00:00', 'arrival_at' => '2027-04-29 09:50:00', 'equipment' => 'Airbus A321neo',
            'new_flight_number' => '411', 'new_departure_at' => '2027-04-28 22:00:00', 'new_arrival_at' => '2027-04-29 09:50:00',
            'origin_terminal' => 'TB', 'destination_terminal' => null, 'segment_ref' => '398386',
            'customer_ref' => '60182280', 'customer_name' => 'Firstname Lastname', 'customer_email' => 'test@test.com', 'customer_phone' => '+39675823956',
        ]);
        $this->assertDatabaseHas('zenith_events', [
            'event' => 'SegmentFlightCancelled', 'flight_number' => '123', 'origin' => 'DUS', 'destination' => 'EWR',
            'departure_at' => '2026-10-15 15:00:00', 'arrival_at' => '2026-10-15 20:00:00', 'new_flight_number' => null, 'new_departure_at' => null,
            'reason' => '-', 'customer_ref' => '60182202', 'customer_phone' => '+496785235456',
        ]);
        $this->assertDatabaseHas('zenith_events', [
            'event' => 'FlightTransfer', 'flight_number' => '300', 'origin' => 'MXP', 'departure_at' => '2026-12-06 12:05:00',
            'new_airline' => 'ON', 'new_flight_number' => '300', 'new_origin' => 'MXP', 'new_destination' => 'EWR',
            'new_departure_at' => '2026-12-10 12:05:00', 'new_arrival_at' => '2026-12-10 15:40:00', 'reason' => 'test', 'customer_ref' => '60148330',
        ]);
        $moved = collect($this->api('GET', 'me/notifications', token: $this->token)->json('notifications'))->firstWhere('pnr', '004DL6');
        $this->assertSame(['moved', '2026-12-06T12:05:00', '2026-12-10T12:05:00'], [$moved['changes'][0]['kind'], $moved['changes'][0]['before']['departure'], $moved['changes'][0]['after']['departure']]);
    }

    public function test_the_airline_being_unreachable_still_sends_the_notice(): void
    {
        $this->zenith->failures['LoadBooking'] = FakeZenith::error('', 'Service unavailable');
        $this->postEvent('segment-flight-cancelled', self::body())->assertOk();

        $this->assertDatabaseHas('zenith_events', ['event' => 'SegmentFlightCancelled', 'status' => 'processed']);
        $n = DB::table('customer_notifications')->first();
        $this->assertSame('flight_cancelled', $n->type);
        $this->assertSame('Your flight has been cancelled', $n->title);
        $this->assertStringContainsString('A flight on booking EVT123 has been cancelled.', $n->body);
    }

    public function test_unknown_addresses_and_other_ips_are_kept_without_notifying(): void
    {
        $this->postEvent('flight-delay', self::body())->assertOk();
        config(['naoero.zenith_events.allowed_ips' => ['203.0.113.7']]);
        $this->postEvent('flight-change', self::body())->assertOk();

        $events = DB::table('zenith_events')->orderBy('received_at')->get();
        $this->assertSame([['Unknown', 'ignored', 'flight-delay'], ['FlightChange', 'unverified', 'flight-change']],
            $events->map(fn ($e) => [$e->event, $e->status, $e->address])->all());
        $this->assertSame('ip_not_allowed', $events[1]->key_status);
        $this->assertSame(0, DB::table('customer_notifications')->count());
    }

    public function test_every_call_is_saved_with_the_response_we_sent(): void
    {
        $utf16 = "\xFF\xFE".mb_convert_encoding(file_get_contents(__DIR__.'/../../../Fixtures/Zenith/Events/FlightChange.xml'), 'UTF-16LE', 'UTF-8');
        $post = fn (string $event, string $raw, array $server = []) => $this->call('POST', '/api/v1/zenith/events/'.self::SECRET.'/'.$event.'?src=zenith', [], [], [],
            ['CONTENT_TYPE' => 'application/xml', 'HTTP_USER_AGENT' => 'Zenith EventMap', 'HTTP_AUTHORIZATION' => 'Bearer hidden', 'HTTP_COOKIE' => 'a=b', ...$server], $raw);

        $post('flight-changeTest', $utf16)->assertOk();
        $post('flight-changeTest', $utf16)->assertOk();                   // Zenith retrying
        $post('flight-delay', '<Event/>')->assertOk();                     // a misspelt address: kept
        $post('flight-transfer', str_repeat('x', 1_048_577))->assertOk();  // large: kept whole
        config(['naoero.zenith_events.allowed_ips' => ['203.0.113.7']]);
        $post('segment-flight-cancelled', '<Event/>')->assertOk();         // another IP: kept, unverified

        $calls = DB::table('zenith_event_calls')->orderBy('received_at')->get();
        $this->assertSame(['stored', 'duplicate', 'unknown_event', 'stored', 'unverified'], $calls->pluck('outcome')->all());
        $this->assertSame([200, 200, 200, 200, 200], $calls->pluck('response_status')->map(fn ($s) => (int) $s)->all());

        [$first, $retry] = [$calls[0], $calls[1]];
        $event = DB::table('zenith_events')->where('event', 'FlightChange')->value('id');
        $this->assertSame([$event, $event], [$first->zenith_event_id, $retry->zenith_event_id]);
        $this->assertSame(['FlightChange', 'flight-changeTest', true], [$first->event, $first->address, (bool) $first->is_test]);
        $this->assertSame(['POST', '/api/v1/zenith/events/***/flight-changeTest', 'src=zenith', 'Zenith EventMap', 'application/xml'],
            [$first->method, $first->path, $first->query_string, $first->user_agent, $first->content_type]);
        $this->assertSame('OK', $first->response_body);
        $this->assertSame(strlen($utf16), (int) $first->request_bytes);
        $this->assertSame(hash('sha256', $utf16), $first->request_sha256);
        $this->assertStringContainsString('<NewDepartureDate>2027-04-28T22:00:00</NewDepartureDate>', $first->request_body); // saved as UTF-8
        $this->assertNotNull($first->request_id);
        $this->assertGreaterThanOrEqual(0, (int) $first->duration_ms);

        $headers = json_decode($first->request_headers, true);
        $this->assertSame('Zenith EventMap', $headers['user-agent']);
        $this->assertArrayNotHasKey('authorization', $headers);
        $this->assertArrayNotHasKey('cookie', $headers);
        $response = json_decode($first->response_headers, true);
        $this->assertStringStartsWith('text/plain', $response['content-type']);
        $this->assertSame($first->request_id, $response['x-request-id']);

        // Kept to 64 KB here; the event keeps the whole body.
        $this->assertSame(65_536, strlen($calls[3]->request_body));
        $this->assertTrue((bool) $calls[3]->request_body_truncated);
        $this->assertSame(1_048_577, strlen((string) DB::table('zenith_events')->where('event', 'FlightTransfer')->value('raw')));
        $this->assertNull($calls[2]->event);
        $this->assertSame(4, DB::table('zenith_events')->count());
    }

    /** Zenith's real cancellation post (17 Nov 2026), for a booking and customer we don't have. */
    private static function zenithCancellation(string $pnr, string $ref, string $login): string
    {
        return json_encode(['EventMapNotification' => [
            'Event' => [
                'AirlineDesignator' => 'ON', 'FlightNumber' => '002', 'OriginCode' => 'BNE', 'DestinationCode' => 'INU',
                'DepartureDate' => '2027-01-25T12:30:00', 'ArrivalDate' => '2027-01-25T19:15:00',
                'EquipmentText' => 'Boeing 737-800 Passenger', 'CancellationReason' => 'Weather',
            ],
            'Data' => [
                'Customer' => ['Ref' => $ref, 'Login' => $login, 'TypeCode' => 'EndCustomer', 'Firstname' => 'Cedric', 'Surname' => 'REVEST',
                    'Emails' => ['CustomerEmail' => ['Email' => 'crevest@example.com']], 'Phones' => ['CustomerPhone' => ['PhoneNumber' => '0645787878']]],
                'LoadBookingRequest' => ['UniqueID' => ['TypeCode' => 'PnrCode', 'ID' => $pnr], 'Verification' => ['PassengerName' => 'REVEST', 'RefCustomer' => $ref]],
            ],
        ]]);
    }

    public function test_an_event_for_nobody_of_ours_is_ignored_with_the_reason_saved_and_logged(): void
    {
        $this->postEvent('segment-flight-cancelled', self::zenithCancellation('003PWE', '10000017', 'crtest'))->assertOk();

        $e = DB::table('zenith_events')->first();
        $this->assertSame(['SegmentFlightCancelled', 'ignored', '003PWE', 'Weather', '002'], [$e->event, $e->status, $e->pnr, $e->reason, $e->flight_number]);
        $this->assertSame('No customer of ours holds this booking. Zenith names customer crtest (ref 10000017), who has no account here.', $e->detail);
        $this->assertNotNull($e->processed_at);
        // Logged, so the log alone says what happened.
        $this->assertTrue(DB::table('server_logs')->where('source', 'zenith-event')->where('message', 'SegmentFlightCancelled ignored')->exists());
        $this->assertSame(0, DB::table('customer_notifications')->count());

        $this->artisan('naoero:zenith-events-status')->expectsOutputToContain('003PWE')->expectsOutputToContain('1 event(s). Queue: 0 waiting, 0 failed')->assertSuccessful();
    }

    public function test_a_customer_of_ours_named_by_zenith_gets_the_booking_and_the_notice(): void
    {
        // Booked elsewhere (the airline's site): not in their trips here.
        $customer = DB::table('customers')->first();
        $booking = $this->booking;
        $booking['PnrInformation']['PnrCode'] = 'ELS123';
        $this->zenith->pnrBookings['ELS123'] = $this->withFlight($booking, '002', 'BNE', 'INU', '2027-01-25T12:30:00', '2027-01-25T19:15:00', 'HX');
        $this->assertSame(0, DB::table('bookings')->where('pnr', 'ELS123')->count());

        $this->postEvent('segment-flight-cancelled', self::zenithCancellation('ELS123', $customer->zenith_customer_ref, strtoupper($customer->zenith_login)))->assertOk();

        $e = DB::table('zenith_events')->first();
        $this->assertSame('processed', $e->status);
        $this->assertStringContainsString('1 customer(s) notified. The booking was added', $e->detail);
        $added = DB::table('bookings')->where('pnr', 'ELS123')->first();
        $this->assertSame($customer->id, $added->customer_id);
        $n = DB::table('customer_notifications')->first();
        $this->assertSame([$customer->id, $added->id, 'flight_cancelled', 'ELS123'], [$n->customer_id, $n->booking_id, $n->type, $n->pnr]);

        // A reference that matches but a login that doesn't is not enough.
        $this->postEvent('segment-flight-cancelled', self::zenithCancellation('OTH999', $customer->zenith_customer_ref, 'someone.else'))->assertOk();
        $this->assertSame('ignored', DB::table('zenith_events')->where('pnr', 'OTH999')->value('status'));
    }

    public function test_customers_only_see_their_own_notices(): void
    {
        $this->airlineChanges(fn (array &$s) => $s['FlightInfo']['DepartureDate'] = '2026-10-28T07:00:00');
        $this->postEvent('flight-change', self::body())->assertOk();
        $id = DB::table('customer_notifications')->value('id');

        $other = $this->signUp('ben@example.com')['accessToken'];
        $this->api('GET', 'me/notifications', token: $other)->assertOk()->assertJsonCount(0, 'notifications')->assertJsonPath('unreadCount', 0);
        $this->api('POST', "me/notifications/{$id}/read", token: $other)->assertNotFound()->assertJsonPath('error.code', 'not_found');
        $this->api('GET', 'me/notifications')->assertUnauthorized();
        $this->assertNull(DB::table('customer_notifications')->value('read_at'));
    }
}
