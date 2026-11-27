<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Feature\Api\V1\AppApiTestCase;

/**
 * Refund requests: customers ask from the app or website for a ticketed
 * booking (tickets are non-refundable); staff decide in the admin panel.
 */
class RefundRequestTest extends AppApiTestCase
{
    private User $admin;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->create(['status' => 'active']);
        $this->admin->assignRole('Super Admin');
        $this->token = $this->signUp()['accessToken'];
    }

    private function booking(string $status = 'ticketed', string $pnr = '003PUX', string $email = 'ana@example.com'): string
    {
        $id = (string) Str::uuid();
        DB::table('bookings')->insert([
            'id' => $id, 'customer_id' => DB::table('customers')->where('zenith_login', $email)->value('id'), 'zenith_environment' => 'Preprod',
            'pnr' => $pnr, 'status' => $status, 'currency' => 'AUD', 'total_amount' => 1176.46, 'amount_due' => 0, 'time_limit' => now()->addDay(),
            'summary' => json_encode(['pnr' => $pnr, 'flights' => [['flightNumber' => 'ON041', 'origin' => 'BNE', 'destination' => 'INU', 'departure' => '2026-11-26T21:40:00', 'arrival' => '2026-11-27T04:25:00', 'status' => 'HK']],
                'passengers' => [['ref' => 'P1', 'type' => 'AD', 'name' => 'Ana Citizen'], ['ref' => 'P2', 'type' => 'CHD', 'name' => 'Amy Citizen']]]),
            'ticket_numbers' => json_encode(['9512100000001']), 'zenith_booking' => '{}', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }

    private function request(array $overrides = []): array
    {
        return [
            'reason' => 'medical',
            'details' => 'I was admitted to hospital the day before the flight and could not travel.',
            'passengerRefs' => ['P1'],
            'contactPhone' => '+61 400 123 456',
            'acceptPolicy' => true,
            ...$overrides,
        ];
    }

    public function test_a_customer_sends_a_refund_request_for_a_ticketed_booking(): void
    {
        $this->booking();
        $form = $this->api('GET', 'bookings/003pux/refund-requests', token: $this->token)->assertOk();
        $form->assertJsonPath('canRequest', true)->assertJsonPath('requests', [])
            ->assertJsonPath('passengers.1.name', 'Amy Citizen')
            ->assertJsonPath('reasons.0', ['code' => 'medical', 'label' => 'Illness or a medical reason']);
        $this->assertStringContainsString('non-refundable', $form->json('policy'));

        $sent = $this->api('POST', 'bookings/003PUX/refund-requests', $this->request(), $this->token, ['X-Client-Platform' => 'website'])->assertCreated();
        $reference = $sent->json('request.reference');
        $this->assertMatchesRegularExpression('/^RR-\d{6}-[A-Z2-9]{6}$/', $reference);
        $sent->assertJsonPath('request.status', 'submitted')->assertJsonPath('request.statusLabel', 'Submitted')
            ->assertJsonPath('request.passengers', ['Ana Citizen'])->assertJsonPath('request.reasonLabel', 'Illness or a medical reason');
        $this->assertStringContainsString($reference, $sent->json('message'));
        $this->assertDatabaseHas('refund_requests', ['reference' => $reference, 'pnr' => '003PUX', 'channel' => 'website', 'customer_email' => 'ana@example.com']);

        // One open request per booking.
        $this->api('POST', 'bookings/003PUX/refund-requests', $this->request(), $this->token)
            ->assertStatus(409)->assertJsonPath('error.code', 'refund_request_open');
        $this->api('GET', 'bookings/003PUX/refund-requests', token: $this->token)->assertOk()
            ->assertJsonPath('canRequest', false)->assertJsonPath('requests.0.reference', $reference)
            ->assertJsonMissingPath('requests.0.staffNote');
    }

    public function test_requests_are_checked(): void
    {
        $this->booking();
        $this->api('POST', 'bookings/003PUX/refund-requests', $this->request(['acceptPolicy' => false]), $this->token)->assertStatus(400);
        $this->api('POST', 'bookings/003PUX/refund-requests', $this->request(['details' => 'Too short']), $this->token)->assertStatus(400);
        $this->api('POST', 'bookings/003PUX/refund-requests', $this->request(['reason' => 'bored']), $this->token)->assertStatus(400);
        $this->api('POST', 'bookings/003PUX/refund-requests', $this->request(['passengerRefs' => ['P9']]), $this->token)->assertStatus(400);
        $this->api('POST', 'bookings/003PUX/refund-requests', $this->request(['amount' => 5]), $this->token)->assertStatus(400);

        // Only paid, ticketed bookings; only the customer's own.
        $this->booking('pending_payment', '003PUY');
        $this->api('POST', 'bookings/003PUY/refund-requests', $this->request(), $this->token)
            ->assertStatus(409)->assertJsonPath('error.code', 'not_ticketed');
        $other = $this->signUp('bo@example.com')['accessToken'];
        $this->api('GET', 'bookings/003PUX/refund-requests', token: $other)->assertNotFound();
        $this->api('POST', 'bookings/003PUX/refund-requests', $this->request(), $other)->assertNotFound();
        $this->api('GET', 'bookings/003PUX/refund-requests')->assertUnauthorized();
        $this->assertDatabaseCount('refund_requests', 0);
    }

    public function test_staff_review_and_reply(): void
    {
        $bookingId = $this->booking();
        $reference = $this->api('POST', 'bookings/003PUX/refund-requests', $this->request(), $this->token)->assertCreated()->json('request.reference');
        $id = DB::table('refund_requests')->where('reference', $reference)->value('id');

        $this->actingAs($this->admin)->get(route('admin.refund-requests.index'))->assertOk()
            ->assertSee($reference)->assertSee('003PUX')->assertSee('Waiting for a decision');
        $this->actingAs($this->admin)->get(route('admin.refund-requests.index', ['search' => '003PUX']))->assertOk()->assertSee($reference);
        $this->actingAs($this->admin)->get(route('admin.refund-requests.index', ['status' => 'approved']))->assertOk()->assertDontSee($reference);
        $this->actingAs($this->admin)->get(route('admin.refund-requests.show', $id))->assertOk()
            ->assertSee('admitted to hospital')->assertSee('+61 400 123 456')->assertSee(route('admin.flight-bookings.show', $bookingId), false);

        // Approving or declining needs a reply for the customer.
        $this->actingAs($this->admin)->post(route('admin.refund-requests.update', $id), ['status' => 'declined'])->assertSessionHasErrors('customer_message');
        $this->actingAs($this->admin)->post(route('admin.refund-requests.update', $id), [
            'status' => 'declined', 'customer_message' => 'Your fare is non-refundable, but we have extended your ticket for 12 months.', 'staff_note' => 'Checked with ops',
        ])->assertRedirect(route('admin.refund-requests.show', $id))->assertSessionHas('success');
        $this->assertDatabaseHas('refund_requests', ['id' => $id, 'status' => 'declined', 'handled_by' => $this->admin->id]);
        $this->assertNotNull(DB::table('refund_requests')->where('id', $id)->value('decided_at'));
        $this->assertDatabaseHas('activity_logs', ['module' => 'refund_requests']);

        // The customer sees the decision and reply, never the internal note; a new request is possible again.
        $view = $this->api('GET', 'bookings/003PUX/refund-requests', token: $this->token)->assertOk();
        $view->assertJsonPath('requests.0.status', 'declined')->assertJsonPath('requests.0.customerMessage', 'Your fare is non-refundable, but we have extended your ticket for 12 months.')
            ->assertJsonPath('canRequest', true);
        $this->assertStringNotContainsString('Checked with ops', $view->getContent());
    }

    public function test_viewing_and_deciding_need_permissions(): void
    {
        $this->booking();
        $reference = $this->api('POST', 'bookings/003PUX/refund-requests', $this->request(), $this->token)->json('request.reference');
        $id = DB::table('refund_requests')->where('reference', $reference)->value('id');

        $analyst = User::factory()->create(['status' => 'active']);
        $analyst->assignRole('Data Analyst'); // bookings.view, not bookings.manage
        $this->actingAs($analyst)->get(route('admin.refund-requests.show', $id))->assertOk()->assertSee('needs the bookings.manage permission');
        $this->actingAs($analyst)->post(route('admin.refund-requests.update', $id), ['status' => 'approved', 'customer_message' => 'Yes'])->assertForbidden();

        $nobody = User::factory()->create(['status' => 'active']);
        $this->actingAs($nobody)->get(route('admin.refund-requests.index'))->assertForbidden();
        $this->assertDatabaseHas('refund_requests', ['id' => $id, 'status' => 'submitted']);
    }
}
