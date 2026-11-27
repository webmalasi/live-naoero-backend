<?php

namespace Tests\Feature\Api\V1;

use App\Models\ShopProduct;
use App\Models\User;
use App\Services\Shop\ProductVariants;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Everything after a shop order: notices, invoice and receipt, delivery
 * tracking, cancelling a paid order, returns and exchanges, help
 * requests and reviews, for the customer and in the admin panel.
 */
class ShopAftercareTest extends AppApiTestCase
{
    private FakeNab $nab;

    private string $token;

    private User $admin;

    private ShopProduct $tee;

    /** @var array<string, int> size => variant id */
    private array $sizes;

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
        $this->tee = ShopProduct::create(['title' => 'Naoero Tee', 'category' => 'Apparel', 'type' => 'merch', 'price' => 30, 'currency' => 'AUD', 'sku' => 'TEE', 'stock_quantity' => 0, 'is_active' => true]);
        app(ProductVariants::class)->setOptions($this->tee, [['name' => 'Size', 'values' => 'S, M']], $this->admin->id);
        $this->sizes = DB::table('shop_product_variants')->where('shop_product_id', $this->tee->id)->where('is_active', true)->pluck('id', 'title')->map(fn ($v) => (int) $v)->all();
        foreach ($this->sizes as $id) {
            $this->actingAs($this->admin)->post(route('admin.inventory.variants.adjust', $id), ['mode' => 'set', 'quantity' => 8, 'reason' => 'received']);
        }
        $now = now();
        $this->giftCard = DB::table('shop_products')->insertGetId([
            'title' => 'AUD 100 Gift Card', 'slug' => 'aud-100-gift-card', 'category' => 'Gift Card', 'type' => 'gift_card', 'price' => 100,
            'currency' => 'AUD', 'stock_quantity' => 999, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    private function paidOrder(int $quantity = 2, bool $giftCard = false): array
    {
        $items = [['productId' => (string) $this->tee->id, 'variantId' => (string) $this->sizes['M'], 'quantity' => $quantity]];
        if ($giftCard) {
            $items[] = ['productId' => (string) $this->giftCard, 'quantity' => 1];
        }
        $order = $this->api('POST', 'shop/orders', ['items' => $items, 'shippingAddress' => ['name' => 'Ana Citizen', 'phone' => '+61400000001', 'line1' => '1 Main St', 'city' => 'Brisbane', 'postcode' => '4000', 'country' => 'AU']], $this->token)->assertCreated()->json('order');
        $payment = $this->api('POST', "shop/orders/{$order['id']}/pay", [], $this->token)->assertCreated()->json('payment');
        parse_str(parse_url($payment['payUrl'], PHP_URL_QUERY), $q);
        $tx = '70000000000000000000'.random_int(10, 99);
        $this->nab->sale($tx, $order['orderNumber'], number_format($order['total'], 2, '.', ''));
        $this->postJson(parse_url($payment['payUrl'], PHP_URL_PATH).'/complete', ['t' => $q['t'], 'completeResponse' => $this->nab->jwt(['id' => $tx, 'status' => 'AUTHORIZED'])])->assertOk();

        return $this->api('GET', "shop/orders/{$order['id']}", token: $this->token)->assertOk()->json('order');
    }

    private function deliver(array $order): void
    {
        $this->actingAs($this->admin)->post(route('admin.sales.ship', $order['id']), ['carrier' => 'Australia Post', 'tracking_number' => 'AP1'])->assertSessionHas('success');
        $this->actingAs($this->admin)->post(route('admin.sales.deliver', $order['id']))->assertSessionHas('success');
    }

    private function onHand(string $size): int
    {
        return (int) DB::table('shop_product_variants')->where('id', $this->sizes[$size])->value('on_hand');
    }

    private function noticeTypes(): array
    {
        return array_column($this->api('GET', 'me/notifications?category=shop&limit=100', token: $this->token)->json('notifications'), 'type');
    }

    public function test_a_paid_order_has_its_invoice_receipt_and_notices(): void
    {
        $order = $this->paidOrder(giftCard: true);
        $this->assertStringStartsWith('INV-', $order['invoiceNumber']);
        $this->assertStringStartsWith('RCT-', $order['receiptNumber']);
        $this->assertSame('processing', $order['delivery']['status']);
        $this->assertNotNull($order['delivery']['estimatedDelivery']);
        $this->assertSame(['refund', true], [$order['cancellation']['kind'], $order['canCancel']]);
        $this->assertContains('order_status', array_column($order['help']['topics'], 'code'));

        $invoice = $order['documents']['invoiceUrl'];
        $this->assertStringStartsWith('https://pay.test/api/v1/shop/orders/', $invoice);
        $this->get(substr($invoice, strlen('https://pay.test')))->assertOk()->assertSee('Invoice')->assertSee($order['invoiceNumber'])->assertSee('Naoero Tee')->assertSee('DEMO');
        $this->get(substr($order['documents']['receiptUrl'], strlen('https://pay.test')))->assertOk()->assertSee('Payment receipt')->assertSee('PAYMENT RECEIVED');
        $this->get("/api/v1/shop/orders/{$order['id']}/invoice")->assertForbidden();
        $this->actingAs($this->admin)->get(route('admin.sales.document', [$order['id'], 'invoice']))->assertOk()->assertSee($order['invoiceNumber']);

        $this->assertEqualsCanonicalizing(['shop_gift_card_issued', 'shop_order_paid', 'shop_order_placed'], $this->noticeTypes());
        $paid = collect($this->api('GET', 'me/notifications', token: $this->token)->json('notifications'))->firstWhere('type', 'shop_order_paid');
        $this->assertSame(['screen' => 'shop_order', 'orderId' => $order['id']], $paid['action']);
    }

    public function test_the_parcel_is_tracked_step_by_step(): void
    {
        $order = $this->paidOrder();
        $this->actingAs($this->admin)->post(route('admin.sales.shipment', $order['id']), ['status' => 'in_transit'])
            ->assertSessionHas('error', 'Enter the carrier.');
        $this->actingAs($this->admin)->post(route('admin.sales.shipment', $order['id']), ['status' => 'packed', 'location' => 'Yaren store'])->assertSessionHas('success');
        $this->actingAs($this->admin)->post(route('admin.sales.ship', $order['id']), ['carrier' => 'Australia Post', 'tracking_number' => 'AP123', 'tracking_url' => 'https://auspost.test/AP123'])->assertSessionHas('success');
        $this->actingAs($this->admin)->post(route('admin.sales.shipment', $order['id']), ['status' => 'in_transit', 'location' => 'Brisbane depot'])->assertSessionHas('success');
        $this->actingAs($this->admin)->post(route('admin.sales.shipment', $order['id']), ['status' => 'delivery_failed', 'message' => 'Nobody home'])->assertSessionHas('success');
        $this->actingAs($this->admin)->post(route('admin.sales.shipment', $order['id']), ['status' => 'out_for_delivery'])->assertSessionHas('success');
        $this->actingAs($this->admin)->post(route('admin.sales.shipment', $order['id']), ['status' => 'delivered'])->assertSessionHas('success');
        $this->actingAs($this->admin)->post(route('admin.sales.shipment', $order['id']), ['status' => 'in_transit'])->assertSessionHas('error');

        $delivery = $this->api('GET', "shop/orders/{$order['id']}/tracking", token: $this->token)->assertOk()->json('delivery');
        $this->assertSame('delivered', $delivery['status']);
        $this->assertSame([true, true, true, true, true, true], array_column($delivery['steps'], 'done'));
        $this->assertSame(['delivered', 'out_for_delivery', 'delivery_failed', 'in_transit', 'shipped', 'packed', 'processing'], array_column($delivery['events'], 'status'));
        $this->assertSame('Brisbane depot', $delivery['events'][3]['location']);
        $this->assertSame(['AP123', 'https://auspost.test/AP123', null], [$delivery['trackingNumber'], $delivery['trackingUrl'], $delivery['estimatedDelivery']]);

        $types = $this->noticeTypes();
        $this->assertSame(5, count(array_keys($types, 'shop_shipment_update')), 'packed, shipped, in transit, failed attempt, out for delivery');
        $this->assertContains('shop_order_delivered', $types);
        $this->actingAs($this->admin)->get(route('admin.sales.show', $order['id']))->assertOk()->assertSee('Brisbane depot')->assertSee($order['invoiceNumber']);

        // Another customer can't follow it.
        $this->api('GET', "shop/orders/{$order['id']}/tracking", token: $this->signUp('bo@example.com')['accessToken'])->assertNotFound();
    }

    public function test_a_paid_order_is_cancelled_with_a_full_refund_before_it_ships(): void
    {
        $order = $this->paidOrder(giftCard: true);
        $this->assertSame(6, $this->onHand('M'));
        $cancelled = $this->api('POST', "shop/orders/{$order['id']}/cancel", ['reason' => 'Ordered the wrong size'], $this->token)->assertOk()->json('order');

        $this->assertSame(['refunded', 'cancelled', 'customer', false], [$cancelled['status'], $cancelled['shippingStatus'], $cancelled['cancelledBy'], $cancelled['canCancel']]);
        $this->assertSame(170, $cancelled['refundedAmount']);
        $this->assertSame('170.00', $this->nab->refunds[0]['amount']);
        $this->assertSame(8, $this->onHand('M'), 'back in stock');
        $this->assertSame('cancelled', DB::table('gift_card_orders')->where('shop_order_id', $order['id'])->value('status'));
        $types = $this->noticeTypes();
        $this->assertContains('shop_order_cancelled', $types);
        $this->assertNotContains('shop_order_refunded', $types, 'one notice for the cancellation');

        $this->api('POST', "shop/orders/{$order['id']}/cancel", [], $this->token)->assertStatus(409)->assertJsonPath('error.code', 'order_not_cancellable');
        $shipped = $this->paidOrder();
        $this->actingAs($this->admin)->post(route('admin.sales.ship', $shipped['id']), ['carrier' => 'DHL']);
        $this->api('POST', "shop/orders/{$shipped['id']}/cancel", [], $this->token)->assertStatus(409)
            ->assertJsonPath('error.message', 'Your parcel has already shipped. You can ask for a return once it arrives.');
    }

    public function test_a_return_is_approved_received_restocked_and_refunded(): void
    {
        $order = $this->paidOrder();
        $line = $order['items'][0];
        $itemId = DB::table('shop_order_items')->where('shop_order_id', $order['id'])->value('id');
        $body = ['type' => 'return', 'reason' => 'changed_mind', 'items' => [['orderItemId' => (string) $itemId, 'quantity' => 1]]];
        $this->api('POST', "shop/orders/{$order['id']}/returns", $body, $this->token)->assertStatus(409)
            ->assertJsonPath('error.message', 'You can ask for a return once your order is delivered.');

        $this->deliver($order);
        $this->assertSame(2, $this->api('GET', "shop/orders/{$order['id']}", token: $this->token)->json('order.lines.0.returnableQuantity'));
        $this->api('POST', "shop/orders/{$order['id']}/returns", [...$body, 'items' => [['orderItemId' => (string) $itemId, 'quantity' => 3]]], $this->token)
            ->assertStatus(409)->assertJsonPath('error.message', 'You can return up to 2 × Naoero Tee (M).');
        $return = $this->api('POST', "shop/orders/{$order['id']}/returns", $body, $this->token)->assertCreated()->json('return');
        $this->assertSame(['requested', 30], [$return['status'], $return['value']]);
        $this->assertSame(1, $this->api('GET', "shop/orders/{$order['id']}", token: $this->token)->json('order.lines.0.returnableQuantity'));

        $this->actingAs($this->admin)->post(route('admin.sales.returns.refund', $return['id']), ['amount' => 30])->assertSessionHas('error', 'Receive the return before refunding it.');
        $this->actingAs($this->admin)->post(route('admin.sales.returns.approve', $return['id']), [])->assertSessionHas('success');
        $this->assertStringContainsString('send them to: Naoero Airlines', $this->api('GET', "me/returns/{$return['id']}", token: $this->token)->json('return.instructions'));
        $returnItem = DB::table('shop_return_items')->where('return_request_id', $return['id'])->value('id');
        $this->actingAs($this->admin)->post(route('admin.sales.returns.receive', $return['id']), ['items' => [$returnItem => ['condition' => 'unused', 'restock' => 1]]])->assertSessionHas('success');
        $this->assertSame(7, $this->onHand('M'), 'one back on the shelf');
        $this->actingAs($this->admin)->post(route('admin.sales.returns.refund', $return['id']), ['amount' => 30])->assertSessionHas('success');

        $done = $this->api('GET', "me/returns/{$return['id']}", token: $this->token)->assertOk()->json('return');
        $this->assertSame(['refunded', 30, false], [$done['status'], $done['refundAmount'], $done['canCancel']]);
        $this->assertSame('partially_refunded', DB::table('shop_orders')->where('id', $order['id'])->value('status'));
        $this->assertSame(4, count(array_keys($this->noticeTypes(), 'shop_return_update')), 'requested, approved, received, refunded');
        $this->actingAs($this->admin)->get(route('admin.sales.returns'))->assertOk()->assertSee($return['returnNumber']);
        $this->actingAs($this->admin)->get(route('admin.sales.returns.show', $return['id']))->assertOk()->assertSee('Naoero Tee (M)');
        $this->assertSame($line['title'], 'Naoero Tee');
    }

    public function test_an_exchange_holds_and_sends_the_replacement(): void
    {
        $order = $this->paidOrder(quantity: 1);
        $this->deliver($order);
        $itemId = DB::table('shop_order_items')->where('shop_order_id', $order['id'])->value('id');
        $this->api('POST', "shop/orders/{$order['id']}/returns", ['type' => 'exchange', 'reason' => 'wrong_size', 'items' => [['orderItemId' => (string) $itemId, 'quantity' => 1]]], $this->token)
            ->assertStatus(400)->assertJsonPath('error.message', 'Choose the size or option you want instead of Naoero Tee (M).');
        $exchange = $this->api('POST', "shop/orders/{$order['id']}/returns", ['type' => 'exchange', 'reason' => 'wrong_size',
            'items' => [['orderItemId' => (string) $itemId, 'quantity' => 1, 'exchangeVariantId' => (string) $this->sizes['S']]]], $this->token)->assertCreated()->json('return');
        $this->assertSame('Naoero Tee (S)', $exchange['items'][0]['exchangeFor']);

        $this->actingAs($this->admin)->post(route('admin.sales.returns.approve', $exchange['id']), ['instructions' => 'Post it to PO Box 1'])->assertSessionHas('success');
        $this->assertSame(1, (int) DB::table('shop_product_variants')->where('id', $this->sizes['S'])->value('reserved'), 'the S is held');
        $item = DB::table('shop_return_items')->where('return_request_id', $exchange['id'])->value('id');
        $this->actingAs($this->admin)->post(route('admin.sales.returns.receive', $exchange['id']), ['items' => [$item => ['condition' => 'opened', 'restock' => 1]]])->assertSessionHas('success');
        $this->actingAs($this->admin)->post(route('admin.sales.returns.exchange', $exchange['id']), ['carrier' => 'DHL', 'tracking_number' => 'D1'])->assertSessionHas('success');

        $this->assertSame([7, 0], [$this->onHand('S'), (int) DB::table('shop_product_variants')->where('id', $this->sizes['S'])->value('reserved')]);
        $this->assertSame(8, $this->onHand('M'), 'the M came back');
        $this->api('GET', "me/returns/{$exchange['id']}", token: $this->token)->assertJsonPath('return.status', 'exchanged')->assertJsonPath('return.replacement.trackingNumber', 'D1');
    }

    public function test_a_return_can_be_cancelled_and_releases_held_stock(): void
    {
        $order = $this->paidOrder(quantity: 1);
        $this->deliver($order);
        $itemId = DB::table('shop_order_items')->where('shop_order_id', $order['id'])->value('id');
        $exchange = $this->api('POST', "shop/orders/{$order['id']}/returns", ['type' => 'exchange', 'reason' => 'wrong_size',
            'items' => [['orderItemId' => (string) $itemId, 'quantity' => 1, 'exchangeVariantId' => (string) $this->sizes['S']]]], $this->token)->json('return');
        $this->actingAs($this->admin)->post(route('admin.sales.returns.approve', $exchange['id']));
        $this->api('POST', "me/returns/{$exchange['id']}/cancel", [], $this->token)->assertOk()->assertJsonPath('return.status', 'cancelled');
        $this->assertSame(0, (int) DB::table('shop_product_variants')->where('id', $this->sizes['S'])->value('reserved'));
        $this->api('POST', "me/returns/{$exchange['id']}/cancel", [], $this->token)->assertStatus(409);

        $this->travel(31)->days();
        $this->token = $this->api('POST', 'auth/login', ['email' => 'ana@example.com', 'password' => 'Passw0rd!'])->assertOk()->json('accessToken');
        $this->api('POST', "shop/orders/{$order['id']}/returns", ['type' => 'return', 'reason' => 'faulty', 'items' => [['orderItemId' => (string) $itemId, 'quantity' => 1]]], $this->token)
            ->assertStatus(409)->assertJsonPath('error.code', 'return_not_allowed');
    }

    public function test_help_requests_are_answered_in_the_admin_panel(): void
    {
        $order = $this->paidOrder();
        $request = $this->api('POST', 'me/support-requests', ['orderId' => $order['id'], 'topic' => 'delivery', 'message' => 'Can you deliver to my office instead?'], $this->token)
            ->assertCreated()->json('request');
        $this->assertSame(['open', 'Delivery or address ('.$order['orderNumber'].')'], [$request['status'], $request['subject']]);
        $this->api('POST', 'me/support-requests', ['topic' => 'nope', 'message' => 'Hello there'], $this->token)->assertStatus(400);

        $this->actingAs($this->admin)->get(route('admin.sales.support'))->assertOk()->assertSee($request['number']);
        $this->actingAs($this->admin)->get(route('admin.sales.support.show', $request['id']))->assertOk()->assertSee('deliver to my office');
        $this->actingAs($this->admin)->post(route('admin.sales.support.reply', $request['id']), ['message' => 'Yes, send us the address.'])->assertSessionHas('success');

        $thread = $this->api('GET', "me/support-requests/{$request['id']}", token: $this->token)->assertOk()->json('request');
        $this->assertSame(['answered', ['customer', 'staff']], [$thread['status'], array_column($thread['messages'], 'author')]);
        $this->assertContains('shop_support_reply', $this->noticeTypes());
        $this->api('POST', "me/support-requests/{$request['id']}/messages", ['message' => '1 Office Rd'], $this->token)->assertOk()->assertJsonPath('request.status', 'open');
        $this->api('POST', "me/support-requests/{$request['id']}/close", [], $this->token)->assertOk()->assertJsonPath('request.status', 'closed');
        $this->assertCount(1, $this->api('GET', "shop/orders/{$order['id']}", token: $this->token)->json('order.supportRequests'));
        $this->api('GET', "me/support-requests/{$request['id']}", token: $this->signUp('bo@example.com')['accessToken'])->assertNotFound();
    }

    public function test_reviews_come_from_buyers_and_set_the_rating(): void
    {
        $order = $this->paidOrder();
        $id = (string) $this->tee->id;
        $this->api('POST', "shop/products/{$id}/reviews", ['rating' => 5], $this->token)->assertStatus(403)
            ->assertJsonPath('error.message', 'You can review it once your order has shipped.');
        $this->deliver($order);
        $this->assertTrue($this->api('GET', "shop/orders/{$order['id']}", token: $this->token)->json('order.lines.0.canReview'));
        $this->api('POST', "shop/products/{$id}/reviews", ['rating' => 6], $this->token)->assertStatus(400);
        $review = $this->api('POST', "shop/products/{$id}/reviews", ['rating' => 4, 'title' => 'Comfy', 'body' => 'Fits well.'], $this->token)->assertCreated()->json('review');
        $this->assertSame(['M', 'Ana C.', true], [$review['variantTitle'], $review['reviewerName'], $review['verifiedPurchase']]);
        $this->api('POST', "shop/products/{$id}/reviews", ['rating' => 5], $this->token)->assertStatus(409)->assertJsonPath('error.code', 'already_reviewed');

        $public = $this->api('GET', "shop/products/{$id}/reviews")->assertOk()->json();
        $this->assertSame(['average' => 4, 'count' => 1], ['average' => $public['summary']['average'], 'count' => $public['summary']['count']]);
        $this->assertNull($public['canReview']);
        $this->assertSame($review['id'], $this->api('GET', "shop/products/{$id}/reviews", token: $this->token)->json('myReview.id'));
        $product = collect($this->api('GET', 'shop/products')->json('products'))->firstWhere('id', $id);
        $this->assertSame([4, 1], [$product['rating'], $product['reviewsCount']]);

        $this->api('PUT', "me/reviews/{$review['id']}", ['rating' => 2, 'body' => 'Shrank in the wash.'], $this->token)->assertOk()->assertJsonPath('review.rating', 2);
        $this->actingAs($this->admin)->post(route('admin.sales.reviews.reply', $review['id']), ['reply' => 'Sorry! Wash it cold.'])->assertSessionHas('success');
        $this->api('GET', "shop/products/{$id}/reviews")->assertJsonPath('reviews.0.reply.body', 'Sorry! Wash it cold.');
        $this->assertContains('shop_review_reply', $this->noticeTypes());

        $this->actingAs($this->admin)->post(route('admin.sales.reviews.moderate', $review['id']), ['status' => 'hidden', 'reason' => 'Test'])->assertSessionHas('success');
        $this->api('GET', "shop/products/{$id}/reviews")->assertJsonPath('summary.count', 0)->assertJsonCount(0, 'reviews');
        $this->actingAs($this->admin)->get(route('admin.sales.reviews'))->assertOk()->assertSee('Shrank in the wash.');
        $this->api('DELETE', "me/reviews/{$review['id']}", [], $this->token)->assertOk();
        $this->api('GET', 'me/reviews', token: $this->token)->assertJsonCount(0, 'reviews');
    }

    public function test_the_dashboard_warns_about_low_stock(): void
    {
        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()->assertDontSee('Shop stock is low');
        $this->actingAs($this->admin)->post(route('admin.inventory.variants.adjust', $this->sizes['S']), ['mode' => 'set', 'quantity' => 0, 'reason' => 'lost']);
        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()->assertSee('Shop stock is low: 1 item(s)')->assertSee('1 sold out')->assertSee('Naoero Tee (S) (0)');
    }

    public function test_the_help_details_are_public(): void
    {
        $this->api('GET', 'shop/help')->assertOk()->assertJsonPath('returnWindowDays', 30)->assertJsonPath('returnReasons.0.code', 'wrong_size');
    }
}
