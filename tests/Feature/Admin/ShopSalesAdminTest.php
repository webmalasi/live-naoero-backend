<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Tests\Feature\Api\V1\AppApiTestCase;
use Tests\Feature\Api\V1\FakeNab;

/**
 * The shop's records and the admin panel's sales pages: every step of an
 * order on its tagged timeline, refunds through NAB, shipping, gift cards,
 * and who may do what.
 */
class ShopSalesAdminTest extends AppApiTestCase
{
    private FakeNab $nab;

    private string $token;

    private User $admin;

    private int $hoodie;

    private int $giftCard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->nab = (new FakeNab)->install();
        Mail::fake();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->create(['status' => 'active']);
        $this->admin->assignRole('Super Admin');
        $this->token = $this->signUp()['accessToken'];
        $now = now();
        $this->hoodie = DB::table('shop_products')->insertGetId([
            'title' => 'Signature Hoodie', 'slug' => 'signature-hoodie', 'category' => 'Apparel', 'type' => 'merch', 'price' => 58,
            'currency' => 'AUD', 'stock_quantity' => 5, 'is_active' => true, 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $this->giftCard = DB::table('shop_products')->insertGetId([
            'title' => 'AUD 500 Gift Card', 'slug' => 'aud-500-gift-card', 'category' => 'Gift Card', 'type' => 'gift_card', 'price' => 500,
            'currency' => 'AUD', 'stock_quantity' => 999, 'is_active' => true, 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    private function staff(array $permissions): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->givePermissionTo(array_map(fn ($p) => Permission::findOrCreate($p, 'web'), $permissions));

        return $user;
    }

    /** An order (one hoodie and one gift card for Bo) paid through the payment page. */
    private function paidOrder(bool $merch = true, string $status = 'AUTHORIZED', array $codes = []): array
    {
        $items = [['productId' => (string) $this->giftCard, 'quantity' => 1, 'gift' => ['recipientName' => 'Bo', 'recipientEmail' => 'bo@example.com']]];
        if ($merch) {
            $items[] = ['productId' => (string) $this->hoodie, 'quantity' => 1];
        }
        $order = $this->api('POST', 'shop/orders', array_filter([
            'items' => $items,
            'shippingAddress' => $merch ? ['name' => 'Ana', 'phone' => '+61400000001', 'line1' => '1 Main St', 'city' => 'Brisbane', 'postcode' => '4000', 'country' => 'AU'] : null,
        ]), $this->token)->assertCreated()->json('order');
        $payment = $this->api('POST', "shop/orders/{$order['id']}/pay", [], $this->token)->assertCreated()->json('payment');
        parse_str(parse_url($payment['payUrl'], PHP_URL_QUERY), $q);
        $this->get(parse_url($payment['payUrl'], PHP_URL_PATH).'?t='.$q['t'])->assertOk();
        $tx = '70000000000000000000'.random_int(10, 99);
        $this->nab->sale($tx, $order['orderNumber'], number_format($order['total'], 2, '.', ''), codes: $codes);
        $this->postJson(parse_url($payment['payUrl'], PHP_URL_PATH).'/complete', ['t' => $q['t'], 'completeResponse' => $this->nab->jwt(['id' => $tx, 'status' => $status])])->assertOk();

        return $order;
    }

    private function events(string $orderId): array
    {
        return DB::table('shop_order_events')->where('shop_order_id', $orderId)->orderBy('id')->get(['type', 'outcome', 'source'])
            ->map(fn ($e) => "{$e->type}:{$e->outcome}")->all();
    }

    public function test_every_step_of_a_paid_order_is_recorded_with_its_outcome(): void
    {
        $order = $this->paidOrder();
        $this->assertSame([
            'order_placed:info',
            'stock_reserved:info',
            'payment_started:info',
            'payment_page_opened:info',
            'payment_succeeded:success',
            'stock_sold:info',
            'gift_card_issued:success',
            'gift_card_emailed:success',
        ], $this->events($order['id']));
        $attempt = DB::table('shop_payment_attempts')->where('shop_order_id', $order['id'])->first();
        $this->assertSame(['paid', 'Visa', '1091', '568.00'], [$attempt->status, $attempt->card_brand, $attempt->card_last4, number_format($attempt->amount, 2, '.', '')]);
    }

    public function test_declines_reviews_and_expiry_are_tagged(): void
    {
        $declined = $this->paidOrder(merch: false, codes: ['ics_auth' => '481']);
        $this->assertContains('payment_declined:declined', $this->events($declined['id']));

        $review = $this->paidOrder(merch: false, codes: ['ics_decision' => '480']);
        $this->assertContains('payment_review:review', $this->events($review['id']));

        $this->travel(31)->minutes();
        $this->artisan('naoero:shop-reconcile')->assertSuccessful();
        $this->assertContains('order_expired:cancelled', $this->events($declined['id']));
    }

    public function test_the_admin_sees_sales_orders_payments_and_activity(): void
    {
        $order = $this->paidOrder();
        $this->actingAs($this->admin)->get(route('admin.sales.index'))->assertOk()
            ->assertSee('Shop Sales &amp; Orders', false)->assertSee('App', false)->assertSee($order['orderNumber'])->assertSee('$568.00')->assertSee('To ship');
        $this->actingAs($this->admin)->get(route('admin.sales.index', ['search' => $order['orderNumber']]))->assertOk()->assertSee($order['orderNumber']);
        $this->actingAs($this->admin)->get(route('admin.sales.show', $order['id']))->assertOk()
            ->assertSee('Payment succeeded')->assertSee('Gift card issued')->assertSee('Visa •••• 1091')->assertSee('Mark as shipped');
        $this->actingAs($this->admin)->get(route('admin.sales.payments', ['outcome' => 'paid']))->assertOk()->assertSee($order['orderNumber']);
        $this->actingAs($this->admin)->get(route('admin.sales.activity', ['outcome' => 'success']))->assertOk()->assertSee('Payment confirmed by NAB');

        $csv = $this->actingAs($this->admin)->get(route('admin.sales.index', ['export' => 'csv']));
        $csv->assertOk();
        $this->assertStringContainsString($order['orderNumber'], $csv->streamedContent());
        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()->assertSee('Shop Sales &amp; Orders', false);
    }

    public function test_a_full_refund_goes_through_nab_and_cancels_the_unused_gift_cards(): void
    {
        $order = $this->paidOrder();
        $this->actingAs($this->admin)->post(route('admin.sales.refund', $order['id']), ['amount' => '568.00', 'reason' => 'Customer asked'])
            ->assertSessionHas('success');

        $this->assertSame([['payment' => DB::table('shop_orders')->where('id', $order['id'])->value('nab_transaction_id'), 'amount' => '568.00', 'currency' => 'AUD']], $this->nab->refunds);
        $row = DB::table('shop_orders')->where('id', $order['id'])->first();
        $this->assertSame(['refunded', '568.00'], [$row->status, number_format($row->refunded_amount, 2, '.', '')]);
        $this->assertSame('cancelled', DB::table('gift_card_orders')->where('shop_order_id', $order['id'])->value('status'));
        $this->assertDatabaseHas('shop_refunds', ['shop_order_id' => $order['id'], 'status' => 'succeeded', 'gift_cards_cancelled' => 1, 'staff_user_id' => $this->admin->id]);
        $events = $this->events($order['id']);
        $this->assertContains('refund_requested:pending', $events);
        $this->assertContains('refund_succeeded:refunded', $events);
        $this->assertContains('gift_card_cancelled:cancelled', $events);
        $this->assertDatabaseHas('activity_logs', ['module' => 'Shop Sales', 'action' => 'refund']);

        // Nothing left to refund; the customer sees the refund.
        $this->actingAs($this->admin)->post(route('admin.sales.refund', $order['id']), ['amount' => '1.00', 'reason' => 'Again'])->assertSessionHas('error');
        $this->api('GET', "shop/orders/{$order['id']}", token: $this->token)->assertJsonPath('order.status', 'refunded')->assertJsonPath('order.refundedAmount', 568);
    }

    public function test_partial_refunds_add_up_and_cannot_exceed_the_total(): void
    {
        $order = $this->paidOrder();
        $this->actingAs($this->admin)->post(route('admin.sales.refund', $order['id']), ['amount' => '10', 'reason' => 'Delivery fee back'])->assertSessionHas('success');
        $this->assertSame('partially_refunded', DB::table('shop_orders')->where('id', $order['id'])->value('status'));
        $this->assertSame('issued', DB::table('gift_card_orders')->where('shop_order_id', $order['id'])->value('status'), 'a partial refund keeps gift cards');

        $this->actingAs($this->admin)->post(route('admin.sales.refund', $order['id']), ['amount' => '559', 'reason' => 'Too much'])->assertSessionHas('error');
        $this->actingAs($this->admin)->post(route('admin.sales.refund', $order['id']), ['amount' => '558', 'reason' => 'The rest'])->assertSessionHas('success');
        $this->assertSame('refunded', DB::table('shop_orders')->where('id', $order['id'])->value('status'));
    }

    public function test_a_refund_nab_refuses_is_recorded_as_failed(): void
    {
        $order = $this->paidOrder();
        $this->nab->refuseRefunds = true;
        $this->actingAs($this->admin)->post(route('admin.sales.refund', $order['id']), ['amount' => '58', 'reason' => 'Damaged'])
            ->assertSessionHas('error');
        $this->assertDatabaseHas('shop_refunds', ['shop_order_id' => $order['id'], 'status' => 'failed']);
        $this->assertContains('refund_failed:failed', $this->events($order['id']));
        $this->assertSame('paid', DB::table('shop_orders')->where('id', $order['id'])->value('status'));
    }

    public function test_shipping_notes_gift_cards_and_cancelling(): void
    {
        $order = $this->paidOrder();
        $this->actingAs($this->admin)->post(route('admin.sales.ship', $order['id']), ['carrier' => 'Australia Post', 'tracking_number' => 'AP123'])->assertSessionHas('success');
        $this->actingAs($this->admin)->post(route('admin.sales.deliver', $order['id']))->assertSessionHas('success');
        $this->actingAs($this->admin)->post(route('admin.sales.note', $order['id']), ['note' => 'Left at the front desk'])->assertSessionHas('success');
        $this->api('GET', "shop/orders/{$order['id']}", token: $this->token)
            ->assertJsonPath('order.shippingStatus', 'delivered')->assertJsonPath('order.tracking.number', 'AP123');

        $card = DB::table('gift_card_orders')->where('shop_order_id', $order['id'])->first();
        $this->actingAs($this->admin)->post(route('admin.sales.gift-cards.resend', $card->id))->assertSessionHas('success');
        $this->actingAs($this->admin)->post(route('admin.sales.gift-cards.cancel', $card->id), ['reason' => 'Lost'])->assertSessionHas('success');
        $events = $this->events($order['id']);
        foreach (['shipped:success', 'delivered:success', 'note_added:info', 'gift_card_cancelled:cancelled'] as $e) {
            $this->assertContains($e, $events);
        }

        $unpaid = $this->api('POST', 'shop/orders', ['items' => [['productId' => (string) $this->hoodie, 'quantity' => 2]],
            'shippingAddress' => ['name' => 'Ana', 'phone' => '+61400000001', 'line1' => '1 Main St', 'city' => 'Brisbane', 'postcode' => '4000', 'country' => 'AU']], $this->token)->json('order');
        $this->actingAs($this->admin)->post(route('admin.sales.cancel', $unpaid['id']), ['reason' => 'Duplicate'])->assertSessionHas('success');
        $this->assertContains('order_cancelled:cancelled', $this->events($unpaid['id']));
        $this->assertSame(4, DB::table('shop_products')->where('id', $this->hoodie)->value('stock_quantity'));
    }

    public function test_staff_permissions_are_enforced(): void
    {
        $order = $this->paidOrder();
        $nobody = User::factory()->create(['status' => 'active']);
        $this->actingAs($nobody)->get(route('admin.sales.index'))->assertForbidden();

        $support = $this->staff(['shop.sales.view', 'shop.sales.manage']);
        $this->actingAs($support)->get(route('admin.sales.show', $order['id']))->assertOk()->assertDontSee('↩ Refund');
        $this->actingAs($support)->post(route('admin.sales.refund', $order['id']), ['amount' => '1', 'reason' => 'Try'])->assertForbidden();
        $this->actingAs($support)->get(route('admin.sales.index', ['export' => 'csv']))->assertForbidden();
        $this->actingAs($support)->post(route('admin.sales.note', $order['id']), ['note' => 'Called the customer'])->assertSessionHas('success');
        $this->assertSame([], $this->nab->refunds);
    }
}
