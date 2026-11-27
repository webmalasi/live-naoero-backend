<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Feature\Api\V1\AppApiTestCase;
use Tests\Feature\Api\V1\FakeNab;

/**
 * The Users Directory (public accounts), Flight Sales & Bookings, the logs
 * page, and where shop orders come from.
 */
class CustomerAdminTest extends AppApiTestCase
{
    private User $admin;

    private array $session;

    protected function setUp(): void
    {
        parent::setUp();
        (new FakeNab)->install();
        Mail::fake();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->create(['status' => 'active']);
        $this->admin->assignRole('Super Admin');
        $this->session = $this->signUp();
    }

    private function customerId(): string
    {
        return DB::table('customers')->where('zenith_login', 'ana@example.com')->value('id');
    }

    private function booking(): string
    {
        $id = (string) Str::uuid();
        DB::table('bookings')->insert([
            'id' => $id, 'customer_id' => $this->customerId(), 'zenith_environment' => 'Preprod', 'pnr' => '003PUX', 'status' => 'ticketed',
            'currency' => 'AUD', 'total_amount' => 1176.46, 'amount_due' => 0, 'time_limit' => now()->addDay(),
            'summary' => json_encode(['pnr' => '003PUX', 'flights' => [['flightNumber' => 'ON041', 'origin' => 'BNE', 'destination' => 'INU', 'departure' => '2026-10-26T21:40:00', 'arrival' => '2026-10-27T04:25:00', 'status' => 'HK']],
                'passengers' => [['ref' => 'P1', 'type' => 'AD', 'name' => 'Ana Citizen']], 'tickets' => [['passengerRef' => 'P1', 'passengerName' => 'Ana Citizen', 'number' => '9512100000001']], 'ticketPrintUrl' => null]),
            'ticket_numbers' => json_encode(['9512100000001']), 'zenith_booking' => '{}', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }

    public function test_the_admin_panel_warns_while_website_payments_are_not_set_up(): void
    {
        config(['naoero.shop.return_hosts' => []]);
        $this->actingAs($this->admin)->get(route('admin.customers.index'))->assertOk()
            ->assertSee('Website card payments are off')->assertSee('SHOP_RETURN_HOSTS=napacificbridge.info');

        config(['naoero.shop.return_hosts' => ['napacificbridge.info']]);
        $this->actingAs($this->admin)->get(route('admin.customers.index'))->assertOk()
            ->assertDontSee('Website card payments are off');
    }

    public function test_the_users_directory_lists_public_accounts_not_staff(): void
    {
        $this->booking();
        $this->actingAs($this->admin)->get(route('admin.customers.index'))->assertOk()
            ->assertSee('Users Directory')->assertSee('ana@example.com');
        $this->actingAs($this->admin)->get(route('admin.customers.index', ['search' => '003PUX']))->assertOk()->assertSee('ana@example.com');
        $this->actingAs($this->admin)->get(route('admin.customers.index', ['search' => 'nobody']))->assertOk()->assertDontSee('ana@example.com');
        $this->actingAs($this->admin)->get(route('admin.users.index'))->assertOk()->assertSee('Admin Users &amp; Staff', false);
        $csv = $this->actingAs($this->admin)->get(route('admin.customers.index', ['export' => 'csv']));
        $this->assertStringContainsString('ana@example.com', $csv->streamedContent());
    }

    public function test_a_user_profile_shows_everything_about_them(): void
    {
        $this->booking();
        $this->api('POST', 'logs/app', ['deviceId' => 'device-123456', 'platform' => 'android', 'entries' => [['level' => 'info', 'event' => 'screen.shop']]], $this->session['accessToken'])->assertOk();
        $png = "\x89PNG\r\n\x1A\n".random_bytes(32);
        $this->call('PUT', '/api/v1/me/avatar', [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer '.$this->session['accessToken']], $png)->assertOk();

        $page = $this->actingAs($this->admin)->get(route('admin.customers.show', $this->customerId()));
        $page->assertOk()->assertSee('Ana Citizen')->assertSee('003PUX')->assertSee('ON041')->assertSee('+61400000001')
            ->assertSee('Test phone')->assertSee('Signup')->assertSee('screen.shop')->assertSee('Full profile as Zenith holds it');
        $avatar = $this->actingAs($this->admin)->get(route('admin.customers.avatar', $this->customerId()));
        $avatar->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertSame($png, $avatar->getContent());
    }

    public function test_disabling_a_user_signs_them_out_and_blocks_sign_in(): void
    {
        $this->actingAs($this->admin)->patch(route('admin.customers.toggle-status', $this->customerId()), ['reason' => 'Fraud report'])->assertSessionHas('success');
        $this->api('GET', 'me', token: $this->session['accessToken'])->assertStatus(403)->assertJsonPath('error.code', 'account_disabled');
        $this->api('POST', 'auth/login', ['email' => 'ana@example.com', 'password' => 'Passw0rd!'])->assertStatus(403);
        $this->assertDatabaseHas('auth_events', ['event' => 'staff_disabled', 'customer_id' => $this->customerId()]);
        $this->assertDatabaseHas('activity_logs', ['module' => 'Customers', 'action' => 'staff_disabled']);

        $this->actingAs($this->admin)->patch(route('admin.customers.toggle-status', $this->customerId()), ['reason' => 'Cleared'])->assertSessionHas('success');
        $fresh = $this->api('POST', 'auth/login', ['email' => 'ana@example.com', 'password' => 'Passw0rd!'])->assertOk()->json();

        $this->actingAs($this->admin)->post(route('admin.customers.sign-out', $this->customerId()))->assertSessionHas('success');
        $this->api('GET', 'me', token: $fresh['accessToken'])->assertStatus(401)->assertJsonPath('error.code', 'session_expired');
    }

    public function test_flight_sales_and_bookings(): void
    {
        $id = $this->booking();
        $this->actingAs($this->admin)->get(route('admin.flight-bookings.index'))->assertOk()
            ->assertSee('Flight Sales &amp; Bookings', false)->assertSee('003PUX')->assertSee('$1,176.46');
        $this->actingAs($this->admin)->get(route('admin.flight-bookings.index', ['status' => 'pending_payment']))->assertOk()->assertDontSee('003PUX');
        $this->actingAs($this->admin)->get(route('admin.flight-bookings.show', $id))->assertOk()->assertSee('9512100000001')->assertSee('BNE')
            ->assertSee('No seats, special services or paid extras on this booking.')->assertSee('Booked with Naoero Airlines');
        DB::table('bookings')->where('id', $id)->update(['source' => 'reference', 'verify_surname' => 'CITIZEN', 'linked_at' => now()]);
        $this->actingAs($this->admin)->get(route('admin.flight-bookings.index'))->assertOk()->assertSee('Added by reference');
        $this->actingAs($this->admin)->get(route('admin.flight-bookings.show', $id))->assertOk()
            ->assertSee('Added with your booking reference')->assertSee('verified by passenger name CITIZEN');

        // Seats, requests and paid extras show with the airline's answer.
        $zb = json_decode(DB::table('bookings')->where('id', $id)->value('zenith_booking'), true);
        $zb['Segments'] = [['Ref' => '490318', 'OriginCode' => 'BNE', 'DestinationCode' => 'INU']];
        $zb['SpecialServices'][] = ['Code' => 'SEAT', 'RefPassenger' => '237560', 'RefSegment' => '490318', 'Text' => '12D', 'Status' => 'HK'];
        $zb['SpecialServices'][] = ['Code' => 'VGML', 'RefPassenger' => '237560', 'RefSegment' => '490318', 'Status' => 'NN'];
        $zb['FareInfo']['EMDTicketFares'][] = ['Ref' => 'EMD-1', 'Label' => '25KG Extra Baggage', 'AncillaryCode' => '10000189', 'RefPassenger' => '237560', 'RefSegment' => '490318', 'SaleCurrencyAmount' => ['TotalAmount' => 300]];
        DB::table('bookings')->where('id', $id)->update(['zenith_booking' => json_encode($zb)]);
        $this->actingAs($this->admin)->get(route('admin.flight-bookings.show', $id))->assertOk()
            ->assertSee('Seat 12D')->assertSee('Vegetarian meal (no dairy)')->assertSee('Requested')
            ->assertSee('25KG Extra Baggage')->assertSee('Awaiting payment')->assertSee('BNE → INU');
    }

    public function test_the_logs_page_has_api_app_and_sign_in_tabs(): void
    {
        $this->api('POST', 'logs/app', ['deviceId' => 'device-123456', 'entries' => [['level' => 'error', 'event' => 'payment.crash', 'message' => 'Oops']]], $this->session['accessToken']);
        $this->actingAs($this->admin)->get(route('admin.system-logs.index'))->assertOk()->assertSee('API, App &amp; Login Logs', false)->assertSee('POST /api/v1/auth/signup 201');
        $this->actingAs($this->admin)->get(route('admin.system-logs.index', ['tab' => 'app', 'level' => 'error']))->assertOk()->assertSee('payment.crash');
        $this->actingAs($this->admin)->get(route('admin.system-logs.index', ['tab' => 'logins']))->assertOk()->assertSee('ana@example.com');
    }

    public function test_staff_need_the_right_permissions(): void
    {
        $nobody = User::factory()->create(['status' => 'active']);
        $this->actingAs($nobody)->get(route('admin.customers.index'))->assertForbidden();
        $this->actingAs($nobody)->get(route('admin.flight-bookings.index'))->assertForbidden();
        $this->actingAs($nobody)->get(route('admin.system-logs.index'))->assertForbidden();
        $viewer = User::factory()->create(['status' => 'active']);
        $viewer->givePermissionTo(Permission::findOrCreate('customers.view', 'web'));
        $this->actingAs($viewer)->get(route('admin.customers.show', $this->customerId()))->assertOk()->assertDontSee('Disable account');
        $this->actingAs($viewer)->post(route('admin.customers.sign-out', $this->customerId()))->assertForbidden();
    }

    public function test_shop_orders_record_their_platform_and_image_paths(): void
    {
        $productId = DB::table('shop_products')->insertGetId([
            'title' => 'AUD 500 Gift Card', 'slug' => 'gc-500', 'category' => 'Gift Card', 'type' => 'gift_card', 'price' => 500, 'currency' => 'AUD',
            'image' => 'assets/giftcard/500aud.png', 'stock_quantity' => 999, 'is_active' => true, 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $body = ['items' => [['productId' => (string) $productId, 'quantity' => 1]]];
        $web = $this->api('POST', 'shop/orders', $body, $this->session['accessToken'], ['X-Client-Platform' => 'website'])->assertCreated()->json('order');
        $app = $this->api('POST', 'shop/orders', $body, $this->session['accessToken'])->assertCreated()->json('order');
        $this->assertSame(['website', 'app'], [$web['channel'], $app['channel']]);
        $this->assertSame('assets/giftcard/500aud.png', DB::table('shop_order_items')->where('shop_order_id', $web['id'])->value('image'));
        $this->assertStringEndsWith('/assets/giftcard/500aud.png', $web['items'][0]['image']);

        // Readable rows: what was bought and by whom, and the order number on every related row.
        $row = DB::table('shop_orders')->where('id', $web['id'])->first();
        $this->assertSame([(string) $productId, 'gift_card', '1 × AUD 500 Gift Card', 1, 'Ana Citizen', 'ana@example.com'],
            [$row->product_ids, $row->item_types, $row->item_titles, (int) $row->total_quantity, $row->customer_name, $row->customer_email]);
        $this->assertSame($web['orderNumber'], DB::table('shop_order_items')->where('shop_order_id', $web['id'])->value('order_number'));
        $this->assertSame([$web['orderNumber']], DB::table('shop_order_events')->where('shop_order_id', $web['id'])->distinct()->pluck('order_number')->all());

        $this->actingAs($this->admin)->get(route('admin.sales.index', ['channel' => 'website']))->assertOk()->assertSee($web['orderNumber'])->assertDontSee($app['orderNumber']);
        $this->actingAs($this->admin)->get(route('admin.sales.show', $web['id']))->assertOk()->assertSee('Placed on the website', false);
    }
}
