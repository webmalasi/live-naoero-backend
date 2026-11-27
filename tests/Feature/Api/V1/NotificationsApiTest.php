<?php

namespace Tests\Feature\Api\V1;

use App\Services\Notifications\CustomerNotices;
use Illuminate\Support\Facades\DB;

/**
 * The customer's inbox: notices from every part of the project, grouped
 * by category, each saying what it opens; reading, clearing and paging.
 */
class NotificationsApiTest extends AppApiTestCase
{
    private const ADULT = 'Traveler_Type_1_Index_0';

    private const CHILD = 'Traveler_Type_2_Index_1';

    private const INFANT = 'Traveler_Type_3_Index_2';

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->token = $this->signUp()['accessToken'];
    }

    private function customerId(): string
    {
        return (string) DB::table('customers')->value('id');
    }

    private function passengers(): array
    {
        $doc = ['docType' => 'PP', 'docNumber' => 'PA1234567', 'nationality' => 'AU', 'docIssueCountry' => 'AU', 'docExpiry' => '2031-01-01'];

        return [
            ['ref' => self::ADULT, 'values' => ['title' => 'MR', 'firstName' => 'John', 'lastName' => 'Citizen', 'dateOfBirth' => '1985-03-14', 'mobilePhone' => '+61 400 000 001', ...$doc]],
            ['ref' => self::CHILD, 'values' => ['firstName' => 'Amy', 'lastName' => 'Citizen', 'gender' => 'F', 'dateOfBirth' => '2018-05-05', ...$doc, 'docNumber' => 'PB1234567']],
            ['ref' => self::INFANT, 'values' => ['firstName' => 'Ben', 'lastName' => 'Citizen', 'gender' => 'M', 'dateOfBirth' => '2026-01-10', ...$doc, 'docNumber' => 'PC1234567']],
        ];
    }

    public function test_a_booking_notifies_each_step_once(): void
    {
        $draft = $this->api('POST', 'bookings/prepare', ['offerRef' => 'OFFER-1', 'itineraryRef' => 'IT_0'], $this->token)->assertOk()->json('draftId');
        $this->api('POST', 'bookings', ['draftId' => $draft, 'passengers' => $this->passengers()], $this->token)->assertCreated();
        $payment = $this->api('POST', 'bookings/003PUX/payments', [], $this->token)->assertCreated()->json('paymentId');
        $this->api('POST', "bookings/003PUX/payments/{$payment}", ['outcome' => 'decline'], $this->token)->assertOk();

        $paid = FakeZenith::fixture('CreateBooking')['Booking'];
        $paid['TicketInfo']['ETTickets'] = [['DocumentNumber' => '9512100000001', 'RefPassenger' => '237560']];
        $paid['MiscInfo']['ContainsIssuableTickets'] = false;
        $this->zenith->booking = $paid;
        $this->api('POST', "bookings/003PUX/payments/{$payment}", ['outcome' => 'accept'], $this->token)->assertOk()->assertJsonPath('booking.status', 'ticketed');
        // Reading the booking again never notifies twice.
        $this->api('GET', 'bookings/003PUX', token: $this->token)->assertOk();
        $this->api('POST', "bookings/003PUX/payments/{$payment}", ['outcome' => 'accept'], $this->token)->assertOk();

        $list = $this->api('GET', 'me/notifications', token: $this->token)->assertOk()->json();
        $this->assertEqualsCanonicalizing(['booking_confirmed', 'booking_payment_failed', 'booking_created'], array_column($list['notifications'], 'type'));
        $this->assertSame(['bookings', 'bookings', 'bookings'], array_column($list['notifications'], 'category'));
        $confirmed = collect($list['notifications'])->firstWhere('type', 'booking_confirmed');
        $this->assertSame(['screen' => 'booking', 'pnr' => '003PUX'], $confirmed['action']);
        $this->assertSame('Booking 003PUX is confirmed', $confirmed['title']);
        $this->assertSame(3, $list['unreadCount']);
        $this->assertSame(['flights' => 0, 'bookings' => 3, 'shop' => 0, 'account' => 0], $list['unreadByCategory']);
    }

    public function test_the_inbox_filters_reads_deletes_and_pages(): void
    {
        $notices = app(CustomerNotices::class);
        $id = $this->customerId();
        $notices->send($id, 'flight_time_change', 'Your flight time changed', 'ON 101 now leaves at 09:30.', pnr: '003PUX');
        $this->travel(1)->minutes();
        $notices->send($id, 'shop_order_paid', 'Payment received', 'Thanks!', dedupeKey: 'x');
        $this->assertFalse($notices->send($id, 'shop_order_paid', 'Payment received', 'Thanks!', dedupeKey: 'x'), 'the same step notifies once');
        $this->travel(1)->minutes();
        $notices->send($id, 'back_in_stock', 'Back in stock: Tee', 'It is back.', ['productId' => '7', 'variantId' => '12']);

        $this->api('GET', 'me/notifications', token: $this->token)->assertOk()->assertJsonCount(3, 'notifications')
            ->assertJsonPath('notifications.0.action', ['screen' => 'product', 'productId' => '7', 'variantId' => '12'])
            ->assertJsonPath('unreadByCategory.shop', 2)->assertJsonPath('unreadByCategory.flights', 1);
        $shop = $this->api('GET', 'me/notifications?category=shop', token: $this->token)->assertOk()->json('notifications');
        $this->assertSame(['back_in_stock', 'shop_order_paid'], array_column($shop, 'type'));
        $this->api('GET', 'me/notifications?category=nope', token: $this->token)->assertStatus(400);

        $page = $this->api('GET', 'me/notifications?limit=2', token: $this->token)->assertOk()->assertJsonPath('hasMore', true)->json();
        $this->api('GET', 'me/notifications?limit=2&before='.urlencode($page['nextBefore']), token: $this->token)
            ->assertOk()->assertJsonPath('notifications.0.type', 'flight_time_change')->assertJsonPath('hasMore', false);

        $this->api('POST', 'me/notifications/read-all?category=shop', [], $this->token)->assertOk()->assertJsonPath('unreadCount', 1);
        $flight = $this->api('GET', 'me/notifications?unread=1', token: $this->token)->json('notifications.0.id');
        $this->api('POST', "me/notifications/{$flight}/read", [], $this->token)->assertOk()->assertJsonPath('unreadCount', 0);

        $this->api('DELETE', "me/notifications/{$flight}", [], $this->token)->assertOk();
        $this->api('DELETE', "me/notifications/{$flight}", [], $this->token)->assertNotFound();
        $this->api('DELETE', 'me/notifications/read', [], $this->token)->assertOk()->assertJsonPath('deleted', 2);
        $this->api('GET', 'me/notifications', token: $this->token)->assertJsonCount(0, 'notifications');

        // Only the customer's own notices.
        $other = $this->signUp('bo@example.com')['accessToken'];
        $this->api('GET', 'me/notifications', token: $other)->assertJsonCount(0, 'notifications');
    }
}
