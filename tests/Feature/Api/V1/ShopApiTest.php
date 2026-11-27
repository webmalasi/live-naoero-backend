<?php

namespace Tests\Feature\Api\V1;

use App\Mail\GiftCardIssued;
use App\Services\Shop\ShopCheckout;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class ShopApiTest extends AppApiTestCase
{
    private FakeNab $nab;

    private string $token;

    private int $hoodie;

    private int $giftCard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->nab = (new FakeNab)->install();
        Mail::fake();
        $this->token = $this->signUp()['accessToken'];
        $now = now();
        $this->hoodie = DB::table('shop_products')->insertGetId([
            'title' => 'Signature Hoodie', 'slug' => 'signature-hoodie', 'category' => 'Apparel', 'type' => 'merch',
            'price' => 58, 'currency' => 'AUD', 'image' => 'assets/giftcard/hoodie.png', 'stock_quantity' => 3,
            'is_active' => true, 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $this->giftCard = DB::table('shop_products')->insertGetId([
            'title' => 'AUD 500 Gift Card', 'slug' => 'aud-500-gift-card', 'category' => 'Gift Card', 'type' => 'gift_card',
            'price' => 500, 'currency' => 'AUD', 'image' => 'assets/giftcard/500aud.png', 'stock_quantity' => 999,
            'is_active' => true, 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    private function address(): array
    {
        return ['name' => 'Ana Citizen', 'phone' => '+61 400 000 001', 'line1' => '1 Main St', 'city' => 'Brisbane', 'postcode' => '4000', 'country' => 'au'];
    }

    private function order(array $items, ?array $address = null): array
    {
        return $this->api('POST', 'shop/orders', array_filter(['items' => $items, 'shippingAddress' => $address]), $this->token)->assertCreated()->json('order');
    }

    /** Pays an order end to end through the payment page. */
    private function pay(array $order, ?string $amount = null): array
    {
        $payment = $this->api('POST', "shop/orders/{$order['id']}/pay", [], $this->token)->assertCreated()->json('payment');
        parse_str(parse_url($payment['payUrl'], PHP_URL_QUERY), $query);
        $this->nab->sale('7000000000000000000001', $order['orderNumber'], $amount ?? number_format($order['total'], 2, '.', ''));
        $result = $this->postJson(parse_url($payment['payUrl'], PHP_URL_PATH).'/complete', [
            't' => $query['t'],
            'completeResponse' => $this->nab->jwt(['id' => '7000000000000000000001', 'status' => 'AUTHORIZED']),
        ])->assertOk()->json();

        return [$payment, $result, $query['t']];
    }

    public function test_products_come_from_the_shop_catalogue(): void
    {
        $products = $this->api('GET', 'shop/products')->assertOk()->assertJsonPath('deliveryFee', 10)->json('products');
        $this->assertSame(['Signature Hoodie', 'AUD 500 Gift Card'], array_column($products, 'title'));
        $this->assertSame(['merch', 'gift_card'], array_column($products, 'type'));
        $this->assertSame(3, $products[0]['stockLeft']);
        $this->assertStringEndsWith('assets/giftcard/hoodie.png', $products[0]['image']);
    }

    public function test_prices_come_from_the_server_and_merch_needs_an_address(): void
    {
        $this->api('POST', 'shop/orders', ['items' => [['productId' => (string) $this->hoodie, 'quantity' => 1, 'price' => 1]]], $this->token)->assertStatus(400);
        $this->api('POST', 'shop/orders', ['items' => [['productId' => (string) $this->hoodie, 'quantity' => 1]]], $this->token)
            ->assertStatus(400)->assertJsonPath('error.message', 'Add a delivery address for your merchandise.');

        $order = $this->order([['productId' => (string) $this->hoodie, 'quantity' => 2], ['productId' => (string) $this->giftCard, 'quantity' => 1]], $this->address());
        $this->assertSame(616, $order['itemsTotal']);
        $this->assertSame(10, $order['deliveryFee']);
        $this->assertSame(626, $order['total']);
        $this->assertSame('AU', $order['shippingAddress']['country']);
        $this->assertTrue($order['canPay']);
        $this->assertSame(1, DB::table('shop_products')->where('id', $this->hoodie)->value('stock_quantity'));

        $giftOnly = $this->order([['productId' => (string) $this->giftCard, 'quantity' => 1]]);
        $this->assertSame(0, $giftOnly['deliveryFee']);
    }

    public function test_stock_is_reserved_and_sold_out_items_are_refused(): void
    {
        $this->order([['productId' => (string) $this->hoodie, 'quantity' => 3]], $this->address());
        $this->api('POST', 'shop/orders', ['items' => [['productId' => (string) $this->hoodie, 'quantity' => 1]], 'shippingAddress' => $this->address()], $this->token)
            ->assertStatus(409)->assertJsonPath('error.code', 'out_of_stock')->assertJsonPath('error.message', 'Signature Hoodie is sold out.');
    }

    public function test_shop_orders_need_a_signed_in_customer(): void
    {
        $this->api('POST', 'shop/orders', ['items' => [['productId' => (string) $this->giftCard, 'quantity' => 1]]])->assertStatus(401);
        $this->api('GET', 'me/gift-cards')->assertStatus(401);
    }

    public function test_the_payment_page_opens_a_3ds_checkout_for_the_order_amount(): void
    {
        $order = $this->order([['productId' => (string) $this->giftCard, 'quantity' => 1, 'gift' => ['recipientName' => 'Bo', 'recipientEmail' => 'Bo@Example.com', 'message' => 'Happy travels']]]);
        $payment = $this->api('POST', "shop/orders/{$order['id']}/pay", [], $this->token)->assertCreated()->json('payment');
        $this->assertStringStartsWith('https://pay.test/api/v1/shop/checkout/', $payment['payUrl']);

        $page = $this->get(parse_url($payment['payUrl'], PHP_URL_PATH).'?'.parse_url($payment['payUrl'], PHP_URL_QUERY));
        $page->assertOk()->assertSee('Secure checkout')->assertSee($order['orderNumber'])->assertSee('$500.00')
            ->assertSee('SecureAcceptance.js')->assertHeader('Referrer-Policy', 'no-referrer');

        $context = collect($this->nab->calls)->first(fn ($c) => $c[1] === '/up/v1/capture-contexts')[2];
        $this->assertSame(['type' => 'CAPTURE', 'consumerAuthentication' => true], $context['completeMandate']);
        $this->assertSame('500.00', $context['orderInformation']['amountDetails']['totalAmount']);
        $this->assertSame($order['orderNumber'], $context['clientReferenceInformation']['code']);
        $this->assertSame(['https://pay.test'], $context['targetOrigins']);

        $this->get(parse_url($payment['payUrl'], PHP_URL_PATH).'?t=wrong')->assertOk()->assertSee('This payment link is not valid.');
        $this->assertStringNotContainsString(parse_url($payment['payUrl'], PHP_URL_QUERY), DB::table('server_logs')->get()->toJson());
    }

    public function test_a_confirmed_payment_issues_each_gift_card_once(): void
    {
        $order = $this->order([['productId' => (string) $this->giftCard, 'quantity' => 2, 'gift' => ['recipientName' => 'Bo', 'recipientEmail' => 'bo@example.com', 'message' => 'Happy travels']]]);
        [$payment, $result, $t] = $this->pay($order);
        $this->assertSame('paid', $result['status']);
        $this->assertStringEndsWith('/done?status=paid', $result['redirect']);

        $paid = $this->api('GET', "shop/orders/{$order['id']}", token: $this->token)->assertOk()->json('order');
        $this->assertSame('paid', $paid['status']);
        $this->assertSame(['method' => 'card', 'brand' => 'Visa', 'last4' => '1091', 'transactionId' => '7000000000000000000001', 'threeDSecure' => '05'], $paid['payment']);
        $this->assertCount(2, $paid['giftCards']);
        $this->assertMatchesRegularExpression('/^ON-500-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $paid['giftCards'][0]['code']);
        $this->assertNotSame($paid['giftCards'][0]['code'], $paid['giftCards'][1]['code']);
        $this->assertSame('Happy travels', $paid['giftCards'][0]['message']);
        Mail::assertQueued(GiftCardIssued::class, 2);

        // The same result again changes nothing.
        $this->postJson(parse_url($payment['payUrl'], PHP_URL_PATH).'/complete', ['t' => $t, 'completeResponse' => $this->nab->jwt(['id' => '7000000000000000000001'])])
            ->assertOk()->assertJsonPath('status', 'paid');
        $this->assertSame(2, DB::table('gift_card_orders')->count());
        $this->api('POST', "shop/orders/{$order['id']}/pay", [], $this->token)->assertStatus(409)->assertJsonPath('error.code', 'already_paid');

        // The website's voucher check knows the card.
        $this->getJson('/api/gift-cards/verify/'.$paid['giftCards'][0]['code'])->assertOk()->assertJsonPath('valid', true);
        $this->api('GET', 'me/gift-cards', token: $this->token)->assertOk()->assertJsonCount(2, 'giftCards');
    }

    public function test_the_finished_page_takes_website_customers_back_to_their_order(): void
    {
        config(['naoero.shop.return_hosts' => ['naoero-airlines.com']]);
        $order = $this->order([['productId' => (string) $this->giftCard, 'quantity' => 1]]);
        $pay = "shop/orders/{$order['id']}/pay";

        // Only https pages on the allowed websites.
        foreach (['https://evil.example/orders', 'http://naoero-airlines.com/orders', 'https://naoero-airlines.com.evil.example/x', 'javascript:alert(1)'] as $bad) {
            $this->api('POST', $pay, ['returnUrl' => $bad], $this->token)->assertStatus(400)->assertJsonPath('error.code', 'return_url_not_allowed');
        }

        $payment = $this->api('POST', $pay, ['returnUrl' => 'https://naoero-airlines.com/account/orders?tab=shop'], $this->token)->assertCreated()->json('payment');
        parse_str(parse_url($payment['payUrl'], PHP_URL_QUERY), $query);
        $this->nab->sale('7000000000000000000002', $order['orderNumber'], '500.00');
        $result = $this->postJson(parse_url($payment['payUrl'], PHP_URL_PATH).'/complete', [
            't' => $query['t'], 'completeResponse' => $this->nab->jwt(['id' => '7000000000000000000002', 'status' => 'AUTHORIZED']),
        ])->assertOk()->json();

        $target = "https://naoero-airlines.com/account/orders?tab=shop&amp;order={$order['orderNumber']}&amp;id={$order['id']}&amp;status=paid";
        $this->get(parse_url($result['redirect'], PHP_URL_PATH).'?status=paid')->assertOk()
            ->assertSee('Payment successful')->assertSee("Order {$order['orderNumber']}")
            ->assertSee('content="5;url='.$target.'"', false)->assertSee('Taking you to your order in 5 seconds');

        // The page's own word can't claim a payment our records don't have.
        $unpaid = $this->order([['productId' => (string) $this->giftCard, 'quantity' => 1]]);
        $other = $this->api('POST', "shop/orders/{$unpaid['id']}/pay", [], $this->token)->assertCreated()->json('payment');
        $this->get(parse_url($other['returnUrlPrefix'], PHP_URL_PATH).'?status=paid')->assertOk()
            ->assertSee('Confirming your payment')->assertDontSee('Payment successful')->assertSee('You can close this page');

        // Without a returnUrl, the configured order page is used.
        config(['naoero.shop.order_page_url' => 'https://naoero-airlines.com/orders/{order}']);
        $this->get(parse_url($other['returnUrlPrefix'], PHP_URL_PATH).'?status=declined')->assertOk()
            ->assertSee('Payment declined')->assertSee("https://naoero-airlines.com/orders/{$unpaid['orderNumber']}", false);

        // The website's own page for this order, built from placeholders.
        $third = $this->order([['productId' => (string) $this->giftCard, 'quantity' => 1]]);
        $link = $this->api('POST', "shop/orders/{$third['id']}/pay", ['returnUrl' => 'https://naoero-airlines.com/account/orders/{order}?id={id}&result={status}'], $this->token)->assertCreated()->json('payment');
        $this->get(parse_url($link['returnUrlPrefix'], PHP_URL_PATH).'?status=cancelled')->assertOk()->assertSee('Payment cancelled')
            ->assertSee("https://naoero-airlines.com/account/orders/{$third['orderNumber']}?id={$third['id']}&amp;result=cancelled", false);

        // An unknown attempt just says it's done.
        $this->get('/api/v1/shop/checkout/not-an-id/done')->assertOk()->assertSee('You can close this page');
    }

    public function test_website_payments_are_refused_until_the_website_address_is_set_up(): void
    {
        config(['naoero.shop.return_hosts' => []]);
        $order = $this->order([['productId' => (string) $this->giftCard, 'quantity' => 1]]);
        $pay = "shop/orders/{$order['id']}/pay";
        $back = ['returnUrl' => 'https://napacificbridge.info/order-success/?id={id}'];

        // The website sends its order page to come back to.
        $this->api('POST', $pay, $back, $this->token)
            ->assertStatus(503)->assertJsonPath('error.code', 'website_payments_not_configured')
            ->assertJsonPath('error.message', 'Card payments on the website are not available right now. Please try again later, or pay in the Naoero Airlines app.');
        $this->assertSame(0, DB::table('shop_payment_attempts')->count());
        $this->assertTrue(DB::table('server_logs')->where('level', 'error')->where('message', 'like', '%SHOP_RETURN_HOSTS%')->exists());

        // The app is not affected.
        $this->api('POST', $pay, [], $this->token)->assertCreated();

        // Once set, the website can pay.
        config(['naoero.shop.return_hosts' => ['napacificbridge.info']]);
        $this->api('POST', $pay, $back, $this->token)->assertCreated();
    }

    public function test_the_order_history_filters_sorts_pages_and_totals_the_customers_orders(): void
    {
        $giftCard = fn () => $this->order([['productId' => (string) $this->giftCard, 'quantity' => 1]]);
        $paid = $giftCard();
        $this->pay($paid);
        $merch = $this->api('POST', 'shop/orders', ['items' => [['productId' => (string) $this->hoodie, 'quantity' => 1]], 'shippingAddress' => $this->address()], $this->token, ['X-Client-Platform' => 'website'])->assertCreated()->json('order');
        $cancelled = $giftCard();
        $this->api('POST', "shop/orders/{$cancelled['id']}/cancel", [], $this->token)->assertOk();
        foreach ([$paid['id'] => '2026-10-01 09:00:00', $merch['id'] => '2026-10-10 09:00:00', $cancelled['id'] => '2026-10-20 09:00:00'] as $id => $at) {
            DB::table('shop_orders')->where('id', $id)->update(['created_at' => $at]);
        }
        $numbers = fn (string $query = '') => array_column($this->api('GET', 'shop/orders'.($query ? "?{$query}" : ''), token: $this->token)->assertOk()->json('orders'), 'orderNumber');

        // Everything, newest first, with totals for the account.
        $all = $this->api('GET', 'shop/orders', token: $this->token)->assertOk();
        $this->assertSame([$cancelled['orderNumber'], $merch['orderNumber'], $paid['orderNumber']], array_column($all->json('orders'), 'orderNumber'));
        $all->assertJsonPath('pagination', ['page' => 1, 'perPage' => 50, 'total' => 3, 'totalPages' => 1, 'hasMore' => false])
            ->assertJsonPath('summary.totalOrders', 3)
            ->assertJsonPath('summary.byStatus', ['pending_payment' => 1, 'paid' => 1, 'payment_review' => 0, 'partially_refunded' => 0, 'refunded' => 0, 'cancelled' => 1])
            ->assertJsonPath('summary.paidOrders', 1)
            ->assertJsonPath('summary.awaitingPayment', 1)
            ->assertJsonPath('summary.totalSpent', 500)
            ->assertJsonPath('summary.totalRefunded', 0)
            ->assertJsonPath('summary.currency', 'AUD')
            ->assertJsonPath('summary.giftCards', 1)
            ->assertJsonPath('summary.activeGiftCards', 1)
            ->assertJsonPath('filters.sort', 'newest');
        $first = $all->json('orders.2');
        $this->assertSame(['Paid', '1 × AUD 500 Gift Card', 1], [$first['statusLabel'], $first['itemsSummary'], $first['itemCount']]);
        $this->assertCount(1, $first['giftCards']);
        $this->assertSame('website', $all->json('orders.1.channel'));

        // Filters.
        $this->assertSame([$paid['orderNumber']], $numbers('status=paid'));
        $this->assertSame([$cancelled['orderNumber'], $paid['orderNumber']], $numbers('status=paid,cancelled'));
        $this->assertSame([$cancelled['orderNumber'], $paid['orderNumber']], $numbers('status[]=paid&status[]=cancelled'));
        $this->assertSame([$merch['orderNumber']], $numbers('type=merch'));
        $this->assertSame([$merch['orderNumber']], $numbers('channel=website'));
        $this->assertSame([$merch['orderNumber']], $numbers('search=hoodie'));
        $this->assertSame([$paid['orderNumber']], $numbers('search='.strtolower($paid['orderNumber'])));
        $this->assertSame([$merch['orderNumber']], $numbers('from=2026-10-05&to=2026-10-15'));
        $this->assertSame([$cancelled['orderNumber']], $numbers('from=2026-10-20&to=2026-10-20'));
        $this->assertSame([], $numbers('search=nothing-like-this'));

        // Sorting and pages.
        $this->assertSame([$paid['orderNumber'], $merch['orderNumber'], $cancelled['orderNumber']], $numbers('sort=oldest'));
        $this->assertSame($merch['orderNumber'], $numbers('sort=total_desc')[2]);
        $this->assertSame($merch['orderNumber'], $numbers('sort=total_asc')[0]);
        $this->api('GET', 'shop/orders?perPage=2', token: $this->token)->assertOk()->assertJsonCount(2, 'orders')
            ->assertJsonPath('pagination', ['page' => 1, 'perPage' => 2, 'total' => 3, 'totalPages' => 2, 'hasMore' => true]);
        $this->api('GET', 'shop/orders?perPage=2&page=2', token: $this->token)->assertOk()->assertJsonCount(1, 'orders')
            ->assertJsonPath('orders.0.orderNumber', $paid['orderNumber'])->assertJsonPath('pagination.hasMore', false);
        $this->api('GET', 'shop/orders?perPage=2&page=9', token: $this->token)->assertOk()->assertJsonCount(0, 'orders')->assertJsonPath('pagination.total', 3);
        // A filtered list still reports the whole account's totals.
        $this->api('GET', 'shop/orders?status=cancelled', token: $this->token)->assertOk()->assertJsonPath('pagination.total', 1)->assertJsonPath('summary.totalOrders', 3);

        // Bad parameters are refused with a readable message.
        foreach ([
            'status=shipped' => 'status must be one of',
            'type=ticket' => 'type must be gift_card or merch.',
            'channel=phone' => 'channel must be app or website.',
            'from=21-10-2026' => 'from must use the format YYYY-MM-DD.',
            'from=2026-10-20&to=2026-10-01' => 'to must be on or after from.',
            'sort=cheapest' => 'sort must be one of',
            'perPage=500' => 'perPage must be between 1 and 100.',
            'page=0' => 'page must be 1 or more.',
            'page=two' => 'page must be a whole number.',
        ] as $query => $message) {
            $this->assertStringContainsString($message, $this->api('GET', "shop/orders?{$query}", token: $this->token)->assertStatus(400)->assertJsonPath('error.code', 'invalid_request')->json('error.message'), $query);
        }

        // Only ever the signed-in customer's own orders.
        $other = $this->signUp('bo@example.com')['accessToken'];
        $this->api('GET', 'shop/orders', token: $other)->assertOk()->assertJsonCount(0, 'orders')
            ->assertJsonPath('summary.totalOrders', 0)->assertJsonPath('summary.totalSpent', 0)->assertJsonPath('summary.lastOrderAt', null);
        $this->api('GET', "shop/orders?search={$paid['orderNumber']}", token: $other)->assertOk()->assertJsonCount(0, 'orders');
        $this->api('GET', 'shop/orders')->assertUnauthorized();
    }

    public function test_a_payment_that_does_not_match_the_order_is_refused(): void
    {
        $order = $this->order([['productId' => (string) $this->giftCard, 'quantity' => 1]]);
        [, $result] = $this->pay($order, amount: '5.00');
        $this->assertSame('declined', $result['status']);
        $this->api('GET', "shop/orders/{$order['id']}", token: $this->token)->assertJsonPath('order.status', 'pending_payment')->assertJsonPath('order.giftCards', []);
        $this->assertDatabaseHas('shop_payment_attempts', ['status' => 'declined']);
    }

    public function test_declines_and_fraud_reviews_are_not_paid(): void
    {
        $order = (object) ['order_number' => 'NS-1', 'total' => '50.00', 'currency' => 'AUD'];
        $t = $this->nab->sale('1', 'NS-1', '50.00', codes: ['ics_auth' => '481']);
        $this->assertSame('declined', ShopCheckout::verdict($t, $order)['status']);
        $t = $this->nab->sale('2', 'NS-1', '50.00', codes: ['ics_decision' => '480']);
        $this->assertSame('review', ShopCheckout::verdict($t, $order)['status']);
        $t = $this->nab->sale('3', 'NS-1', '50.00', 'USD');
        $this->assertSame('declined', ShopCheckout::verdict($t, $order)['status']);
        $t = $this->nab->sale('4', 'NS-1', '50');
        $this->assertSame('paid', ShopCheckout::verdict($t, $order)['status']);
    }

    public function test_a_payment_whose_result_was_lost_is_found_at_nab(): void
    {
        $order = $this->order([['productId' => (string) $this->giftCard, 'quantity' => 1]]);
        $this->api('POST', "shop/orders/{$order['id']}/pay", [], $this->token)->assertCreated();
        // Paid at NAB, but the app was closed before the page reported back.
        $this->nab->sale('7000000000000000000009', $order['orderNumber'], '500.00');

        $this->api('GET', "shop/orders/{$order['id']}", token: $this->token)->assertOk()
            ->assertJsonPath('order.status', 'paid')->assertJsonCount(1, 'order.giftCards');
    }

    public function test_unpaid_orders_expire_and_release_stock(): void
    {
        $order = $this->order([['productId' => (string) $this->hoodie, 'quantity' => 2]], $this->address());
        $this->api('POST', "shop/orders/{$order['id']}/pay", [], $this->token)->assertCreated();
        $this->assertSame(1, DB::table('shop_products')->where('id', $this->hoodie)->value('stock_quantity'));

        $this->travel(31)->minutes();
        Artisan::call('naoero:shop-reconcile');
        $this->api('GET', "shop/orders/{$order['id']}", token: $this->token)->assertJsonPath('order.status', 'cancelled')->assertJsonPath('order.canPay', false);
        $this->assertSame(3, DB::table('shop_products')->where('id', $this->hoodie)->value('stock_quantity'));
    }

    public function test_customers_can_cancel_unpaid_orders_only(): void
    {
        $order = $this->order([['productId' => (string) $this->hoodie, 'quantity' => 1]], $this->address());
        $this->api('POST', "shop/orders/{$order['id']}/cancel", [], $this->token)->assertOk()->assertJsonPath('order.status', 'cancelled');
        $this->assertSame(3, DB::table('shop_products')->where('id', $this->hoodie)->value('stock_quantity'));
        $this->api('POST', "shop/orders/{$order['id']}/cancel", [], $this->token)->assertStatus(409);

        $other = $this->signUp('bo@example.com')['accessToken'];
        $this->api('GET', "shop/orders/{$order['id']}", token: $other)->assertNotFound();
        $this->api('GET', 'shop/orders', token: $this->token)->assertOk()->assertJsonCount(1, 'orders');
    }
}
