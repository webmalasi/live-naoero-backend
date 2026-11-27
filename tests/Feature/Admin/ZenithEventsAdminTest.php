<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Api\V1\AppApiTestCase;
use Tests\Feature\Api\V1\FakeZenith;

/**
 * Zenith Flight Events in the admin panel: every post Zenith made to our
 * event addresses, what we answered and the notices it sent, production and
 * preproduction apart.
 */
class ZenithEventsAdminTest extends AppApiTestCase
{
    private const SECRET = 'webhook-secret-0123456789abcdef0123456789abcdef0123456789abcdef';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['naoero.zenith_events.secret' => self::SECRET, 'naoero.zenith_events.allowed_ips' => []]);
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->create(['status' => 'active']);
        $this->admin->assignRole('Super Admin');

        // A customer holding EVT123, whose flight the airline then retimes.
        $token = $this->signUp()['accessToken'];
        $booking = FakeZenith::fixture('CreateBooking')['Booking'];
        $booking['PnrInformation']['PnrCode'] = 'EVT123';
        $this->zenith->pnrBookings = ['EVT123' => $booking];
        $this->api('POST', 'bookings/find', ['pnr' => 'EVT123', 'lastName' => 'Testnywxts'], $token)->assertCreated();
        $booking['Segments'][0]['FlightInfo']['DepartureDate'] = '2026-10-28T09:45:00';
        $this->zenith->pnrBookings = ['EVT123' => $booking];
    }

    private function postEvent(string $event, string $raw)
    {
        return $this->call('POST', '/api/v1/zenith/events/'.self::SECRET.'/'.$event, [], [], [], ['CONTENT_TYPE' => 'application/xml'], $raw);
    }

    private static function body(string $pnr): string
    {
        return '<Event><AirlineDesignator>ON</AirlineDesignator><FlightNumber>092</FlightNumber></Event>'
            ."<Data><LoadBookingRequest><UniqueID><TypeCode>PnrCode</TypeCode><ID>{$pnr}</ID></UniqueID></LoadBookingRequest></Data>";
    }

    public function test_production_and_preproduction_posts_are_shown_apart(): void
    {
        $this->postEvent('flight-change', self::body('EVT123'))->assertOk();
        $this->postEvent('segment-flight-cancelledTest', self::body('PRE456'))->assertOk();
        // Kept and answered 200 too: not from an allowed IP, and a misspelt address.
        config(['naoero.zenith_events.allowed_ips' => ['203.0.113.7']]);
        $this->postEvent('flight-transfer', self::body('EVT123'))->assertOk();
        config(['naoero.zenith_events.allowed_ips' => []]);
        $this->postEvent('flight-delay', self::body('EVT123'))->assertOk();

        // All, the default: both environments, each row tagged.
        $this->actingAs($this->admin)->get(route('admin.zenith-events.index'))->assertOk()
            ->assertSee('EVT123')->assertSee('PRE456')->assertSee('PRE-PROD')->assertSee('PROD');

        $this->actingAs($this->admin)->get(route('admin.zenith-events.index', ['env' => 'production']))->assertOk()
            ->assertSee('Pre-Production')
            ->assertSee('/api/v1/zenith/events/••••cdef/flight-change', false)
            ->assertSee(self::SECRET, false) // in what Copy gives
            ->assertDontSee('flight-changeTest')
            ->assertDontSee('is not set on the server')
            ->assertSee('EVT123')->assertDontSee('PRE456')
            ->assertSee('Processed')
            ->assertSee('POST /api/v1/zenith/events/***/flight-transfer', false)
            ->assertSee('Saved, key not accepted (unverified)')
            ->assertSee('It came from an IP address not in ZENITH_EVENTS_IPS.')
            ->assertSee('POST /api/v1/zenith/events/***/flight-delay', false)
            ->assertSee('Saved, unknown address (not acted on)')
            ->assertDontSee('HTTP 404')->assertDontSee('HTTP 403');

        $this->actingAs($this->admin)->get(route('admin.zenith-events.index', ['env' => 'production', 'outcome' => 'unknown_event']))->assertOk()
            ->assertSee('POST /api/v1/zenith/events/***/flight-delay', false)->assertDontSee('POST /api/v1/zenith/events/***/flight-transfer', false);

        $this->actingAs($this->admin)->get(route('admin.zenith-events.index', ['env' => 'preproduction']))->assertOk()
            ->assertSee('/api/v1/zenith/events/••••cdef/segment-flight-cancelledtest', false)
            ->assertSee('PRE456')->assertDontSee('EVT123')
            ->assertSee('No customer of ours');

        $this->actingAs($this->admin)->get(route('admin.zenith-events.index', ['env' => 'production', 'search' => 'evt123', 'status' => 'failed']))->assertOk()->assertDontSee('View post');

        // Without the key on the server, the page says so.
        config(['naoero.zenith_events.secret' => null]);
        $this->actingAs($this->admin)->get(route('admin.zenith-events.index'))->assertOk()->assertSee('ZENITH_WEBHOOK_SECRET is not set on the server');
    }

    public function test_a_post_shows_as_received_with_our_answer_and_its_notices(): void
    {
        $this->postEvent('flight-change', self::body('EVT123'))->assertOk();
        $id = DB::table('zenith_events')->value('id');

        $this->actingAs($this->admin)->get(route('admin.zenith-events.show', $id))->assertOk()
            ->assertSee('HTTP 200 OK')
            ->assertSee('&lt;FlightNumber&gt;092&lt;/FlightNumber&gt;', false)
            ->assertSee('Your flight time has changed')
            ->assertSee('ON092 BNE–INU now departs Wed 28 Oct at 09:45')
            ->assertSee('1 customer(s) notified.')
            ->assertSee('first post')
            ->assertDontSee('Process again');

        // The call: what came in and what we sent back.
        $call = DB::table('zenith_event_calls')->where('zenith_event_id', $id)->value('id');
        $this->actingAs($this->admin)->get(route('admin.zenith-events.call', $call))->assertOk()
            ->assertSee('POST /api/v1/zenith/events/***/flight-change', false)
            ->assertSee('Request headers')->assertSee('content-type')->assertSee('application/xml')
            ->assertSee('&lt;ID&gt;EVT123&lt;/ID&gt;', false)
            ->assertSee('Response body we sent')->assertSee('text/plain; charset=utf-8')
            ->assertSee('View the stored event');
    }

    public function test_a_failed_post_can_be_processed_again(): void
    {
        // An event whose processing failed (e.g. a lost queue job).
        $this->postEvent('flight-change', self::body('EVT123'))->assertOk();
        $id = DB::table('zenith_events')->value('id');
        DB::table('customer_notifications')->delete();
        DB::table('zenith_events')->where('id', $id)->update(['status' => 'failed', 'detail' => 'Worker stopped']);
        $this->actingAs($this->admin)->get(route('admin.zenith-events.show', $id))->assertOk()->assertSee('Process again');

        $this->actingAs($this->admin)->from(route('admin.zenith-events.show', $id))->post(route('admin.zenith-events.retry', $id))
            ->assertRedirect(route('admin.zenith-events.show', $id))->assertSessionHas('success');
        $this->assertDatabaseHas('zenith_events', ['id' => $id, 'status' => 'processed']);
        $this->assertSame(1, DB::table('customer_notifications')->count());

        // Processed: not again.
        $this->actingAs($this->admin)->post(route('admin.zenith-events.retry', $id))->assertSessionHas('error');
    }

    public function test_an_unverified_post_is_shown_and_staff_can_process_it(): void
    {
        $this->call('POST', '/api/v1/zenith/events/'.str_repeat('0', 64).'/flight-changetest', [], [], [], ['CONTENT_TYPE' => 'application/xml'], self::body('EVT123'))->assertOk();
        $id = DB::table('zenith_events')->value('id');
        $this->assertSame(0, DB::table('customer_notifications')->count());

        $this->actingAs($this->admin)->get(route('admin.zenith-events.index', ['env' => 'preproduction', 'status' => 'unverified']))->assertOk()
            ->assertSee('Unverified (key not accepted)')->assertSee('flight-changetest')->assertSee('The webhook key in the address is wrong.');
        $this->actingAs($this->admin)->get(route('admin.zenith-events.show', $id))->assertOk()
            ->assertSee('I checked it: process')->assertSee('no customer is notified unless you process it');
        $this->actingAs($this->admin)->get(route('admin.zenith-events.call', DB::table('zenith_event_calls')->value('id')))->assertOk()
            ->assertSee('Saved, key not accepted (unverified)');

        $response = $this->actingAs($this->admin)->post(route('admin.zenith-events.retry', $id));
        $this->assertNull(session('error'), (string) session('error'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('zenith_events', ['id' => $id, 'status' => 'processed']);
        $this->assertSame(1, DB::table('customer_notifications')->count());
    }

    /** Zenith sending a post to "...test" and again to the plain address: the calls stay with their event. */
    public function test_calls_are_listed_with_their_event(): void
    {
        $this->postEvent('flight-changetest', self::body('EVT123'))->assertOk();
        $this->postEvent('flight-change', self::body('EVT123'))->assertOk();
        $this->assertSame(1, DB::table('zenith_events')->count());
        $this->assertSame([false, true], DB::table('zenith_event_calls')->orderBy('address')->pluck('is_test')->map(fn ($v) => (bool) $v)->all());

        $this->actingAs($this->admin)->get(route('admin.zenith-events.index', ['env' => 'preproduction']))->assertOk()
            ->assertSee('EVT123')
            ->assertSee('POST /api/v1/zenith/events/***/flight-changetest', false)
            ->assertSee('POST /api/v1/zenith/events/***/flight-change<', false);
        $this->actingAs($this->admin)->get(route('admin.zenith-events.index', ['env' => 'production']))->assertOk()
            ->assertDontSee('EVT123')
            ->assertDontSee('POST /api/v1/zenith/events/***/flight-change', false);
        $retry = DB::table('zenith_event_calls')->where('is_test', false)->value('id');
        $this->actingAs($this->admin)->get(route('admin.zenith-events.call', $retry))->assertOk()->assertSee('Pre-Production');
    }

    public function test_staff_without_log_access_are_refused(): void
    {
        $this->postEvent('flight-change', self::body('EVT123'))->assertOk();
        $id = DB::table('zenith_events')->value('id');
        $nobody = User::factory()->create(['status' => 'active']);

        $this->actingAs($nobody)->get(route('admin.zenith-events.index'))->assertForbidden();
        $this->actingAs($nobody)->get(route('admin.zenith-events.show', $id))->assertForbidden();
        $this->actingAs($nobody)->post(route('admin.zenith-events.retry', $id))->assertForbidden();
        $this->actingAs($nobody)->get(route('admin.zenith-events.call', DB::table('zenith_event_calls')->value('id')))->assertForbidden();
    }
}
