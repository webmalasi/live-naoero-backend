<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * What customers can now do on their own through the airline's key:
 * cancel (unpaid) or refund to a voucher (paid), see their vouchers, buy
 * services sold on their own, undo a check-in (when allowed), see which
 * flights were flown; plus the seat calendar, value-code lists and a health
 * check that pings Zenith.
 */
class SelfServiceApiTest extends AppApiTestCase
{
    private string $token;

    private object $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->token = $this->signUp()['accessToken'];
        $this->customer = DB::table('customers')->where('zenith_login', 'ana@example.com')->first();
    }

    /** A booking on the customer's account, as Zenith holds it. */
    private function hold(array $booking): string
    {
        $pnr = $booking['PnrInformation']['PnrCode'];
        $booking['PnrInformation']['RefCustomer'] = $this->customer->zenith_customer_ref;
        $this->zenith->pnrBookings[$pnr] = $booking;
        DB::table('bookings')->insert([
            'id' => (string) Str::uuid(), 'customer_id' => $this->customer->id, 'zenith_environment' => $this->customer->zenith_environment,
            'pnr' => $pnr, 'status' => 'pending_payment', 'summary' => '{}', 'ticket_numbers' => '[]', 'zenith_booking' => '{}',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $pnr;
    }

    private static function ticketed(): array
    {
        return FakeZenith::fixture('LoadBookingTicketed')['Booking'];
    }

    /** The ticketed booking's two flights, held but not paid yet. */
    private static function unpaidTwoFlights(): array
    {
        $b = self::ticketed();
        $b['TicketInfo']['ETTickets'] = [];
        $b['MiscInfo']['ContainsIssuableTickets'] = true;
        $b['MiscInfo']['CancelInfo'] = ['CanCancel' => true, 'CanVoid' => false, 'CanRefund' => false, 'CanRequestRefund' => false];
        $b['PnrInformation']['TimeLimit'] = '2026-10-20T10:00:00';

        return $b;
    }

    // ------------------------------------------------------------ cancel

    public function test_an_unpaid_booking_is_cancelled_whole_or_flight_by_flight(): void
    {
        $pnr = $this->hold(self::unpaidTwoFlights());

        $options = $this->api('GET', "bookings/{$pnr}/cancel", token: $this->token)->assertOk()
            ->assertJsonPath('canCancel', true)->assertJsonPath('canRefund', false)
            ->assertJsonPath('modes', ['booking', 'flights'])
            ->assertJsonPath('flights.0.segmentRef', '490338')->assertJsonPath('flights.1.segmentRef', '490339')
            ->assertJsonPath('refund', null);
        $this->assertStringContainsString('nothing is charged or refunded', $options->json('note'));

        // Confirmation required; an unknown flight is refused.
        $this->api('POST', "bookings/{$pnr}/cancel", ['mode' => 'flights', 'segmentRefs' => ['490339']], $this->token)->assertStatus(400);
        $this->api('POST', "bookings/{$pnr}/cancel", ['mode' => 'flights', 'segmentRefs' => ['999'], 'confirm' => true], $this->token)
            ->assertStatus(409)->assertJsonPath('error.code', 'flight_not_cancellable');
        $this->api('POST', "bookings/{$pnr}/cancel", ['mode' => 'refund', 'expectedRefund' => 0, 'confirm' => true], $this->token)
            ->assertStatus(409)->assertJsonPath('error.code', 'nothing_to_refund');

        // One flight, then the rest.
        $this->api('POST', "bookings/{$pnr}/cancel", ['mode' => 'flights', 'segmentRefs' => ['490339'], 'confirm' => true], $this->token)->assertOk()
            ->assertJsonPath('cancellation.status', 'done')->assertJsonPath('cancellation.mode', 'flights')
            ->assertJsonPath('booking.status', 'pending_payment')->assertJsonCount(1, 'booking.flights');
        $this->assertSame(['490339'], $this->zenith->calls('Cancel')[0]['CancelSettings']['CancelSegmentSettings']['SegmentReferencesToCancel']);

        $done = $this->api('POST', "bookings/{$pnr}/cancel", ['mode' => 'booking', 'confirm' => true], $this->token)->assertOk()
            ->assertJsonPath('booking.status', 'cancelled')->assertJsonPath('message', 'Your booking is cancelled.');
        // All that is left: an empty list cancels every flight.
        $this->assertSame([], $this->zenith->calls('Cancel')[1]['CancelSettings']['CancelSegmentSettings']['SegmentReferencesToCancel']);
        $this->assertSame(['RefCustomer' => $this->customer->zenith_customer_ref], $this->zenith->calls('Cancel')[1]['Verification']);
        $this->assertSame(2, DB::table('booking_cancellations')->where('pnr', $pnr)->where('status', 'done')->count());
        $this->assertDatabaseHas('customer_notifications', ['customer_id' => $this->customer->id, 'pnr' => $pnr, 'type' => 'booking_cancelled']);

        $this->api('GET', "bookings/{$pnr}/cancel", token: $this->token)->assertOk()
            ->assertJsonPath('canCancel', false)->assertJsonPath('unavailableReason', 'This booking is already cancelled.')
            ->assertJsonCount(2, 'history');
        $this->api('POST', "bookings/{$pnr}/cancel", ['mode' => 'booking', 'confirm' => true], $this->token)->assertStatus(409)->assertJsonPath('error.code', 'not_cancellable');
        $this->assertIsString($done->json('cancellation.id'));
    }

    public function test_a_paid_booking_is_refunded_to_a_voucher_for_the_amount_shown(): void
    {
        $pnr = $this->hold(self::ticketed());
        $this->zenith->refunds = ['1232415191663' => 412.5];
        // Once the airline lets the key refund to vouchers (the settings are cached).
        $this->zenith->refundFopCodes = ['MCO'];
        Cache::flush();

        $quote = $this->api('GET', "bookings/{$pnr}/cancel", token: $this->token)->assertOk()
            ->assertJsonPath('canRefund', true)->assertJsonPath('modes', ['refund'])
            ->assertJsonPath('refund.currency', 'AUD')->assertJsonPath('refund.refundAmount', 412.5)
            ->assertJsonPath('refund.penaltyAmount', 50)->assertJsonPath('refund.method', 'voucher')
            ->assertJsonPath('refund.tickets.0.ticketNumber', '1232415191663')->assertJsonPath('refund.tickets.0.passengerName', 'Anna Tester')
            ->assertJsonPath('refund.penalties.0.label', 'Cancellation penalty')
            ->assertJsonCount(2, 'refund.flights');
        $this->assertStringContainsString('not back to your card', $quote->json('note'));

        // The amount must be the one shown; a cancel without a refund is refused.
        $this->api('POST', "bookings/{$pnr}/cancel", ['mode' => 'refund', 'confirm' => true], $this->token)->assertStatus(400);
        $this->api('POST', "bookings/{$pnr}/cancel", ['mode' => 'booking', 'confirm' => true], $this->token)->assertStatus(409)->assertJsonPath('error.code', 'refund_required');
        $this->api('POST', "bookings/{$pnr}/cancel", ['mode' => 'refund', 'expectedRefund' => 500, 'confirm' => true], $this->token)
            ->assertStatus(409)->assertJsonPath('error.code', 'refund_changed')->assertJsonPath('error.refund.refundAmount', 412.5);
        $this->assertSame([], $this->zenith->calls('Cancel'));

        $done = $this->api('POST', "bookings/{$pnr}/cancel", ['mode' => 'refund', 'expectedRefund' => 412.5, 'confirm' => true], $this->token)->assertOk()
            ->assertJsonPath('booking.status', 'cancelled')
            ->assertJsonPath('cancellation.refundAmount', 412.5)->assertJsonPath('cancellation.refundMethod', 'voucher')
            ->assertJsonPath('vouchers.0.amount', 412.5)->assertJsonPath('vouchers.0.usable', true);
        $this->assertStringContainsString('412.50 AUD has been added to a credit voucher', $done->json('message'));
        $fop = $this->zenith->calls('Cancel')[0]['CancelSettings']['RefundSettings']['RefundFops'][0];
        $this->assertSame(['Code' => 'MCO', 'Amount' => 412.5, 'MCOInfo' => []], $fop);
        $this->assertDatabaseHas('booking_cancellations', ['pnr' => $pnr, 'mode' => 'refund', 'status' => 'done', 'refund_amount' => 412.5, 'penalty_amount' => 50, 'currency' => 'AUD']);
        $this->assertDatabaseHas('customer_notifications', ['pnr' => $pnr, 'type' => 'booking_refunded']);

        // The voucher on the account.
        $this->api('GET', 'me/vouchers', token: $this->token)->assertOk()
            ->assertJsonPath('vouchers.0.number', '99900000000')->assertJsonPath('vouchers.0.statusLabel', 'Available')
            ->assertJsonPath('vouchers.0.expiresAt', '2027-11-04T00:00:00.000Z')
            ->assertJsonPath('totals', [['currency' => 'AUD', 'amount' => 412.5]]);
        $this->assertSame(['RefCustomer' => $this->customer->zenith_customer_ref, 'Login' => 'ana@example.com'], $this->zenith->calls('LoadMCOTickets')[0]['CustomerReference']);

        // Staff see it on the booking in the admin panel.
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('Super Admin');
        $id = DB::table('bookings')->where('pnr', $pnr)->value('id');
        $this->actingAs($admin)->get(route('admin.flight-bookings.show', $id))->assertOk()
            ->assertSee('Cancellations by the customer (1)')->assertSee('Refund to voucher')->assertSee('412.50 AUD');
    }

    /** The key as it is today: RefundFopCodes empty, so no refund is offered or tried. */
    public function test_no_refund_while_the_key_cannot_refund_to_a_voucher(): void
    {
        $pnr = $this->hold(self::ticketed());
        $this->api('GET', "bookings/{$pnr}/cancel", token: $this->token)->assertOk()
            ->assertJsonPath('canRefund', false)->assertJsonPath('modes', [])->assertJsonPath('refund', null)
            ->assertJsonPath('ticketValue.amount', 1613.16)
            ->assertJsonPath('unavailableReason', 'Refunds to a credit voucher are not available online yet. You can send a refund request instead.');
        $this->api('POST', "bookings/{$pnr}/cancel", ['mode' => 'refund', 'expectedRefund' => 300, 'confirm' => true], $this->token)
            ->assertStatus(409)->assertJsonPath('error.code', 'refund_not_enabled');
        $this->assertSame([], $this->zenith->calls('Cancel'));
        $this->assertSame([], $this->zenith->calls('PrepareCancel'));

        // Forced on by the server setting.
        config(['naoero.refund_to_voucher' => 'on']);
        $this->api('GET', "bookings/{$pnr}/cancel", token: $this->token)->assertOk()->assertJsonPath('modes', ['refund']);
    }

    /**
     * Paid booking, "cancel flights only": the seats are released, the
     * tickets stay open and keep their value for a change or a refund.
     */
    public function test_cancel_flights_only_keeps_the_ticket_value(): void
    {
        $pnr = $this->hold(self::ticketed());
        $this->api('POST', "bookings/{$pnr}/cancel", ['mode' => 'keep_ticket', 'confirm' => true], $this->token)
            ->assertForbidden()->assertJsonPath('error.code', 'keep_ticket_not_offered');

        config(['naoero.allow_keep_ticket_cancel' => true]);
        $options = $this->api('GET', "bookings/{$pnr}/cancel", token: $this->token)->assertOk()
            ->assertJsonPath('modes', ['keep_ticket'])->assertJsonPath('canCancel', true)->assertJsonPath('canRefund', false)
            ->assertJsonCount(2, 'flights')->assertJsonPath('ticketValue.amount', 1613.16)->assertJsonPath('keptTickets', null);
        $this->assertStringContainsString('your tickets keep their value', $options->json('note'));
        $this->api('POST', "bookings/{$pnr}/cancel", ['mode' => 'booking', 'confirm' => true], $this->token)
            ->assertStatus(409)->assertJsonPath('error.code', 'refund_required');

        $done = $this->api('POST', "bookings/{$pnr}/cancel", ['mode' => 'keep_ticket', 'confirm' => true], $this->token)->assertOk()
            ->assertJsonPath('cancellation.mode', 'keep_ticket')->assertJsonPath('cancellation.refundAmount', null)
            ->assertJsonPath('booking.status', 'cancelled')
            ->assertJsonPath('booking.keptTickets.amount', 1613.16)->assertJsonPath('booking.keptTickets.currency', 'AUD')
            ->assertJsonPath('booking.keptTickets.tickets', ['1232415191663'])
            ->assertJsonPath('booking.keptTickets.canChange', true)->assertJsonPath('booking.keptTickets.canRefund', true);
        $this->assertStringContainsString('Your tickets (1,613.16 AUD) stay valid', $done->json('message'));
        $cancel = $this->zenith->calls('Cancel')[0]['CancelSettings'];
        $this->assertSame(['CancelSegmentSettings' => ['SegmentReferencesToCancel' => []]], $cancel);
        $this->assertDatabaseHas('customer_notifications', ['pnr' => $pnr, 'type' => 'flights_cancelled']);
        $this->assertDatabaseHas('booking_cancellations', ['pnr' => $pnr, 'mode' => 'keep_ticket', 'status' => 'done']);

        // The tickets can still be changed to another flight...
        $this->api('GET', "bookings/{$pnr}/change", token: $this->token)->assertOk()->assertJsonPath('canChange', true);
        // ...and refunded once the key allows it; there is nothing left to cancel.
        $this->api('GET', "bookings/{$pnr}/cancel", token: $this->token)->assertOk()
            ->assertJsonPath('modes', [])->assertJsonPath('keptTickets.amount', 1613.16)
            ->assertJsonPath('unavailableReason', 'Your flights are already cancelled and your tickets keep their value: you can change to another flight. Refunds to a credit voucher are not available online yet. You can send a refund request instead.');
        $this->api('POST', "bookings/{$pnr}/cancel", ['mode' => 'keep_ticket', 'confirm' => true], $this->token)->assertStatus(409);
        $this->zenith->refundFopCodes = ['MCO'];
        Cache::flush();
        $this->api('GET', "bookings/{$pnr}/cancel", token: $this->token)->assertOk()->assertJsonPath('modes', ['refund'])->assertJsonPath('canRefund', true);
    }

    public function test_cancel_one_flight_of_a_paid_booking_keeping_the_ticket(): void
    {
        config(['naoero.allow_keep_ticket_cancel' => true]);
        $pnr = $this->hold(self::ticketed());
        $this->api('POST', "bookings/{$pnr}/cancel", ['mode' => 'keep_ticket', 'segmentRefs' => ['nope'], 'confirm' => true], $this->token)
            ->assertStatus(409)->assertJsonPath('error.code', 'flight_not_cancellable');
        $this->api('POST', "bookings/{$pnr}/cancel", ['mode' => 'keep_ticket', 'segmentRefs' => ['490339'], 'confirm' => true], $this->token)->assertOk()
            ->assertJsonPath('booking.status', 'ticketed')->assertJsonPath('booking.keptTickets', null)
            ->assertJsonPath('message', 'The flights you chose are cancelled. Your tickets stay valid for a flight change or a refund.');
        $this->assertSame(['490339'], $this->zenith->calls('Cancel')[0]['CancelSettings']['CancelSegmentSettings']['SegmentReferencesToCancel']);
    }

    public function test_tickets_the_fare_rules_do_not_refund_and_an_airline_that_does_not_answer(): void
    {
        $b = self::ticketed();
        $b['MiscInfo']['CancelInfo']['CanRefund'] = false;
        $pnr = $this->hold($b);
        $this->api('GET', "bookings/{$pnr}/cancel", token: $this->token)->assertOk()
            ->assertJsonPath('canRefund', false)->assertJsonPath('modes', [])
            ->assertJsonPath('unavailableReason', 'These tickets cannot be refunded online. You can send a refund request instead.');

        // No answer to Cancel, and nothing was cancelled: a clean failure, recorded.
        $unpaid = self::unpaidTwoFlights();
        $unpaid['PnrInformation']['PnrCode'] = 'NOANS1';
        $pnr = $this->hold($unpaid);
        $this->zenith->failures['Cancel'] = 'down';
        $this->api('POST', "bookings/{$pnr}/cancel", ['mode' => 'booking', 'confirm' => true], $this->token)
            ->assertStatus(503)->assertJsonPath('error.code', 'unavailable');
        $this->assertDatabaseHas('booking_cancellations', ['pnr' => $pnr, 'status' => 'failed', 'error_code' => 'no_answer']);

        // Someone else's booking is not reachable.
        $other = $this->signUp('ben@example.com')['accessToken'];
        $this->api('GET', "bookings/{$pnr}/cancel", token: $other)->assertNotFound();
    }

    public function test_one_cancellation_at_a_time(): void
    {
        $pnr = $this->hold(self::unpaidTwoFlights());
        $lock = Cache::lock("naoero:cancel:{$this->customer->zenith_environment}:{$pnr}", 120);
        $lock->get();
        $this->api('POST', "bookings/{$pnr}/cancel", ['mode' => 'booking', 'confirm' => true], $this->token)
            ->assertStatus(409)->assertJsonPath('error.code', 'cancel_in_progress');
        $lock->release();
        $this->assertSame([], $this->zenith->calls('Cancel'));
    }

    // ------------------------------------------------------------ vouchers

    public function test_vouchers_are_the_customers_own(): void
    {
        $ref = $this->customer->zenith_customer_ref;
        $this->zenith->mcos[$ref] = [
            ['DocumentNumber' => '111', 'Amount' => 80, 'Currency' => 'AUD', 'StatusCode' => 'Issued', 'ExpiryDateGMT' => '2027-01-01T00:00:00'],
            ['DocumentNumber' => '222', 'Amount' => 20, 'Currency' => 'AUD', 'StatusCode' => 'Issued', 'ExpiryDateGMT' => '2026-01-01T00:00:00'],
            ['DocumentNumber' => '333', 'Amount' => 0, 'Currency' => 'USD', 'StatusCode' => 'Used', 'ExpiryDateGMT' => null],
        ];
        $list = $this->api('GET', 'me/vouchers', token: $this->token)->assertOk()->assertJsonCount(3, 'vouchers');
        $this->assertSame(['111', '222', '333'], array_column($list->json('vouchers'), 'number'));
        $this->assertSame(['Available', 'Expired', 'Used'], array_column($list->json('vouchers'), 'statusLabel'));
        $list->assertJsonPath('totals', [['currency' => 'AUD', 'amount' => 80]]);
        $this->api('GET', 'me/vouchers', ['usable' => 1, 'currency' => 'aud'], $this->token)->assertOk();
        $this->assertTrue($this->zenith->calls('LoadMCOTickets')[1]['UsableOnly']);
        $this->assertSame('AUD', $this->zenith->calls('LoadMCOTickets')[1]['CurrencyCode']);

        $other = $this->signUp('ben@example.com')['accessToken'];
        $this->api('GET', 'me/vouchers', token: $other)->assertOk()->assertJsonCount(0, 'vouchers');
        $this->api('GET', 'me/vouchers')->assertUnauthorized();
    }

    // ------------------------------------------------------------ services

    public function test_services_on_their_own_are_listed_bought_and_paid_like_a_booking(): void
    {
        $this->zenith->pnrBookings = ['KEEP01' => self::ticketed()];
        $catalog = $this->api('GET', 'services')->assertOk()
            ->assertJsonPath('currency', 'AUD')
            ->assertJsonPath('services.0.ref', '10000084')->assertJsonPath('services.0.name', 'Island Pass')
            ->assertJsonPath('services.0.price', 199)->assertJsonPath('services.0.maxQuantity', 5)
            ->assertJsonPath('services.0.description', '10 one-way travel credits.');
        $this->assertStringNotContainsString('<script>', $catalog->json('services.0.descriptionHtml'));
        $this->assertContains('AUD', $catalog->json('currencies'));
        $this->api('GET', 'services', ['currency' => 'XYZ'])->assertStatus(400);

        $this->api('POST', 'services/purchase', ['ref' => '10000084', 'quantity' => 2])->assertUnauthorized();
        $this->api('POST', 'services/purchase', ['ref' => '10000084', 'quantity' => 6], $this->token)->assertStatus(400);
        $this->api('POST', 'services/purchase', ['ref' => 'nope'], $this->token)->assertStatus(409)->assertJsonPath('error.code', 'service_unavailable');

        $bought = $this->api('POST', 'services/purchase', ['ref' => '10000084', 'quantity' => 2], $this->token)->assertCreated()
            ->assertJsonPath('booking.kind', 'services')->assertJsonPath('booking.status', 'pending_payment')
            ->assertJsonPath('booking.amountDue', 398)->assertJsonPath('booking.currency', 'AUD')->assertJsonPath('booking.timeLimit', null)
            ->assertJsonCount(2, 'booking.services')->assertJsonPath('booking.services.0.label', 'Island Pass');
        $pnr = $bought->json('booking.pnr');
        $buy = $this->zenith->calls('BuyServices')[0];
        $this->assertSame($this->customer->zenith_customer_ref, $buy['RefCustomer']);
        $this->assertCount(2, $buy['EMDTicketFares']);
        $this->assertSame(['ServiceTypeCode' => 'ECoupon', 'ServiceCode' => '10000084', 'Ref' => '10000084'], $buy['EMDTicketFares'][0]);

        // It is in the trips and is paid like any booking.
        $this->assertContains($pnr, array_column($this->api('GET', 'bookings', token: $this->token)->json('bookings'), 'pnr'));
        $this->api('POST', "bookings/{$pnr}/payments", [], $this->token)->assertCreated();
        // Services can't be cancelled online.
        $this->api('GET', "bookings/{$pnr}/cancel", token: $this->token)->assertOk()->assertJsonPath('canCancel', false)
            ->assertJsonPath('unavailableReason', 'Services cannot be cancelled online. Please contact Naoero Airlines.');
    }

    // ------------------------------------------------------------ undo check-in

    public function test_undoing_a_check_in_is_off_unless_allowed_and_must_be_confirmed(): void
    {
        $pnr = $this->hold(self::ticketed());
        $this->zenith->booking = self::ticketed();
        $checkin = FakeZenith::fixture('PrepareCheckin');
        foreach ($checkin['CheckinTargets'][0]['ETTicketCheckinList'] as &$tc) {
            foreach ($tc['ETCouponCheckinList'] as &$cc) {
                $cc['CheckinStatusCode'] = 'CheckedIn';
            }
        }
        unset($tc, $cc);
        $this->zenith->checkin = $checkin;

        $this->api('GET', "bookings/{$pnr}/check-in", token: $this->token)->assertOk()->assertJsonPath('undo.available', false);
        $this->api('POST', "bookings/{$pnr}/check-in/undo", ['group' => 0, 'confirm' => true], $this->token)->assertForbidden()->assertJsonPath('error.code', 'uncheck_not_offered');

        config(['naoero.allow_uncheck' => true]);
        $this->api('GET', "bookings/{$pnr}/check-in", token: $this->token)->assertOk()->assertJsonPath('undo.available', true);
        $this->api('POST', "bookings/{$pnr}/check-in/undo", ['group' => 0], $this->token)->assertStatus(400)->assertJsonPath('error.code', 'confirmation_required');
        $this->api('POST', "bookings/{$pnr}/check-in/undo", ['group' => 7, 'confirm' => true], $this->token)->assertStatus(409);
        $this->assertSame([], $this->zenith->calls('Uncheck'));

        $this->api('POST', "bookings/{$pnr}/check-in/undo", ['group' => 0, 'confirm' => true], $this->token)->assertOk()
            ->assertJsonPath('booking.status', 'cancelled');
        $targets = $this->zenith->calls('Uncheck')[0]['ETTicketTargets'];
        $this->assertSame($checkin['CheckinTargets'][0]['ETTicketCheckinList'][0]['DocumentNumber'], $targets[0]['DocumentNumber']);
        $this->assertNotEmpty($targets[0]['CouponOrders']);
    }

    // ------------------------------------------------------------ flown

    public function test_flown_flights_are_read_for_this_booking_only(): void
    {
        $b = self::ticketed();
        $ticket = $b['TicketInfo']['ETTickets'][0]['DocumentNumber'];
        $b['TicketInfo']['ETTickets'][0]['Coupons'][0]['SegmentSold']['FlightInfo']['DepartureDate'] = '2026-10-05T12:30:00';
        $b['TicketInfo']['ETTickets'][0]['Coupons'][1]['SegmentSold']['FlightInfo']['DepartureDate'] = '2026-10-13T21:00:00';
        $pnr = $this->hold($b);
        $this->zenith->flown = [
            ['ETDocumentNumber' => $ticket, 'CouponOrder' => 1, 'DepartureDate' => '2026-10-05T12:30:00', 'FareBasisCode' => 'KOW', 'CabinClassCode' => 'Y', 'Firstname' => 'Pax', 'Surname' => 'Tester'],
            // Another traveller on the same flight: never shown.
            ['ETDocumentNumber' => '9999999999999', 'CouponOrder' => 1, 'DepartureDate' => '2026-10-05T12:30:00', 'Firstname' => 'Someone', 'Surname' => 'Else'],
        ];

        $res = $this->api('GET', "bookings/{$pnr}/flown", token: $this->token)->assertOk()
            ->assertJsonPath('flownCount', 1)->assertJsonCount(2, 'coupons')
            ->assertJsonPath('coupons.0.flown', true)->assertJsonPath('coupons.0.fareBasis', 'KOW')->assertJsonPath('coupons.0.passengerName', 'Anna Tester')
            ->assertJsonPath('coupons.1.flown', false)->assertJsonPath('checkedUntil', '2026-10-13');
        $this->assertStringNotContainsString('Someone', $res->getContent());
        // Two windows (Zenith allows 7 days a call).
        $calls = $this->zenith->calls('FlownCoupons');
        $this->assertSame(['2026-10-05T00:00:00', '2026-10-05T23:59:59'], [$calls[0]['StartFlightDate'], $calls[0]['EndFlightDate']]);
        $this->assertSame('2026-10-13T00:00:00', $calls[1]['StartFlightDate']);
        // Cached: no new calls.
        $this->api('GET', "bookings/{$pnr}/flown", token: $this->token)->assertOk()->assertJsonPath('flownCount', 1);
        $this->assertCount(2, $this->zenith->calls('FlownCoupons'));
    }

    // ------------------------------------------------------------ calendar, reference, health

    public function test_the_seat_calendar_for_a_month(): void
    {
        $this->zenith->calendar = ['BNE-INU' => ['2026-11-02' => 'HK', '2026-11-09' => 'HL', '2026-11-16' => 'Full', '2026-12-01' => 'HK']];

        $this->api('GET', 'flight-calendar', ['origin' => 'bne', 'destination' => 'INU', 'month' => '2026-11'])->assertOk()
            ->assertJsonPath('origin', 'BNE')->assertJsonPath('month', '2026-11')
            ->assertJsonPath('days', [
                ['date' => '2026-11-02', 'status' => 'available', 'code' => 'HK'],
                ['date' => '2026-11-09', 'status' => 'waitlist', 'code' => 'HL'],
                ['date' => '2026-11-16', 'status' => 'full', 'code' => 'Full'],
            ]);
        $q = $this->zenith->calls('Calendar')[0]['OriginDestinationQueries'][0];
        $this->assertSame(['BNE', 'INU', '2026-11-01T00:00:00', '2026-11-30T00:00:00'], [$q['OriginCode'], $q['DestinationCode'], $q['StartDate'], $q['EndDate']]);

        // This month starts today, not on the 1st.
        $this->api('GET', 'flight-calendar', ['origin' => 'BNE', 'destination' => 'INU', 'month' => '2026-10'])->assertOk();
        $this->assertSame('2026-10-15T00:00:00', $this->zenith->calls('Calendar')[1]['OriginDestinationQueries'][0]['StartDate']);

        $this->api('GET', 'flight-calendar', ['origin' => 'BNE', 'destination' => 'CXI', 'month' => '2026-11'])->assertNotFound()->assertJsonPath('error.code', 'route_not_served');
        $this->api('GET', 'flight-calendar', ['origin' => 'BNE', 'destination' => 'INU', 'month' => '2026-09'])->assertStatus(400);
        $this->api('GET', 'flight-calendar', ['origin' => 'BNE', 'destination' => 'INU', 'month' => '11/2026'])->assertStatus(400);
        $this->api('GET', 'flight-calendar', ['origin' => 'BNE', 'destination' => 'BNE', 'month' => '2026-11'])->assertStatus(400);
    }

    public function test_value_code_lists_and_one_list(): void
    {
        $this->api('GET', 'reference/value-code-lists')->assertOk()->assertJsonPath('lists', ['Airport', 'CabinClass', 'MCOStatusCode', 'WebClass']);
        $airports = $this->api('GET', 'reference/value-codes/Airport')->assertOk()->assertJsonPath('name', 'Airport');
        $this->assertNotEmpty($airports->json('codes'));
        $this->assertArrayHasKey('code', $airports->json('codes.0'));
        $this->api('GET', 'reference/value-codes/NoSuchList')->assertNotFound();
        $this->api('GET', 'reference/value-codes/bad-name!')->assertStatus(400);
    }

    public function test_health_says_whether_zenith_answers(): void
    {
        $this->api('GET', 'health')->assertOk()->assertJsonPath('zenith.reachable', true)->assertJsonPath('zenith.serverTimeUtc', '2026-11-04T10:00:00.000Z');
        $this->assertSame('naoero-health', $this->zenith->calls('Ping')[0]['StringTest']);
        // At most once a minute.
        $this->api('GET', 'health')->assertOk();
        $this->assertCount(1, $this->zenith->calls('Ping'));

        Cache::store(config('naoero.cache_store'))->forget('naoero:zenith:ping');
        $this->zenith->failures['Ping'] = 'down';
        $this->api('GET', 'health')->assertOk()->assertJsonPath('ok', true)->assertJsonPath('zenith.reachable', false);
    }
}
