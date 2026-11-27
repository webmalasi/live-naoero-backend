<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Http\Requests\Api\V1\Shop\CheckShopCartRequest;
use App\Http\Requests\Api\V1\Shop\CreateShopOrderRequest;
use App\Services\Auth\AuthEvents;
use App\Services\Logging\ServerLog;
use App\Services\Shop\GiftCardIssuer;
use App\Services\Shop\ShopCatalog;
use App\Services\Shop\ShopCheckout;
use App\Services\Shop\ShopOrders;
use App\Services\Shop\StockAlerts;
use App\Services\Support\Js;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Services\Shop\ShopOperations;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Throwable;

/** Gift cards and merchandise: catalogue, cart check, orders, payment start, gift cards, back-in-stock alerts. */
class ShopController extends ApiController
{
    public function __construct(
        private readonly ShopCatalog $catalog,
        private readonly ShopOrders $orders,
        private readonly ShopCheckout $checkout,
        private readonly AuthEvents $events,
        private readonly StockAlerts $alerts,
    ) {}

    /** Everything on sale, with prices and stock from the admin panel. */
    public function products(): JsonResponse
    {
        return $this->json([
            'currency' => config('naoero.shop.currency', 'AUD'),
            'deliveryFee' => (float) config('naoero.shop.delivery_fee', 10),
            'products' => $this->catalog->catalogue(),
        ]);
    }

    public function product(string $id): JsonResponse
    {
        $product = $this->catalog->find($id) ?? throw new ApiException(404, 'product_not_found', 'This product is no longer available.');

        return $this->json(['product' => $this->catalog->presentWithVariants($product)]);
    }

    /**
     * Checks a cart before checkout: today's prices, and how many of each
     * line can be bought now. Nothing is reserved.
     */
    public function check(CheckShopCartRequest $request): JsonResponse
    {
        return $this->json($this->orders->check($request->items()));
    }

    /** Places an order (prices and stock checked here) for payment within 30 minutes. */
    public function store(CreateShopOrderRequest $request): JsonResponse
    {
        $customer = $this->customer($request);
        $meta = $this->meta($request);
        $this->events->enforceLimit('shop_order', $customer->zenith_login, $meta->ip, perLogin: 20, perIp: 40, windowMinutes: 60, countSuccesses: true);
        try {
            // The website sends X-Client-Platform: website; the app (or anything else) counts as the app.
            $channel = strtolower(trim((string) $request->header('X-Client-Platform'))) === 'website' ? 'website' : 'app';
            $order = $this->orders->create($customer, [...$request->order(), 'channel' => $channel]);
        } catch (Throwable $e) {
            $this->events->record('shop_order', false, $meta, $customer->id, $customer->zenith_login, $e instanceof ApiException ? $e->errorCode : 'error');
            throw $e;
        }
        $this->events->record('shop_order', true, $meta, $customer->id, $customer->zenith_login, "{$order['orderNumber']} {$order['currency']} {$order['total']}");

        return $this->json(['order' => $order], 201);
    }

    /**
     * The customer's order history (website and app orders alike): filtered,
     * sorted, paged, with account totals. With no parameters it returns the
     * newest 50, as it always has.
     */
    public function index(Request $request): JsonResponse
    {
        $customer = $this->customer($request);
        $filters = self::historyFilters($request);
        // Orders still within their payment window are checked at NAB first,
        // so an order paid a moment ago never shows as unpaid.
        $recent = DB::table('shop_orders')->where('customer_id', $customer->id)->where('status', 'pending_payment')
            ->where('expires_at', '>', Js::dbTime())->orderByDesc('created_at')->limit(3)->get();
        foreach ($recent as $order) {
            $this->checkout->reconcile($order);
        }

        return $this->json($this->orders->history($customer, $filters));
    }

    /** The order history's query parameters, validated. */
    private static function historyFilters(Request $request): array
    {
        $status = $request->query('status');
        $input = [
            ...$request->only(['type', 'channel', 'from', 'to', 'search', 'sort', 'page', 'perPage']),
            // ?status=paid,refunded or ?status[]=paid&status[]=refunded
            'status' => is_array($status) ? $status : array_values(array_filter(array_map('trim', explode(',', (string) $status)))),
        ];
        $validator = Validator::make($input, [
            'status' => ['array', 'max:6'],
            'status.*' => ['string', Rule::in(array_keys(ShopOrders::STATUS_LABELS))],
            'type' => ['nullable', Rule::in(['gift_card', 'merch'])],
            'channel' => ['nullable', Rule::in(['app', 'website'])],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'search' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', Rule::in(array_keys(ShopOrders::SORTS))],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'perPage' => ['nullable', 'integer', 'min:1', 'max:'.ShopOrders::HISTORY_MAX_PAGE_SIZE],
        ], [
            'status.*.in' => 'status must be one of: '.implode(', ', array_keys(ShopOrders::STATUS_LABELS)).'.',
            'type.in' => 'type must be gift_card or merch.',
            'channel.in' => 'channel must be app or website.',
            'date_format' => ':attribute must use the format YYYY-MM-DD.',
            'to.after_or_equal' => 'to must be on or after from.',
            'sort.in' => 'sort must be one of: '.implode(', ', array_keys(ShopOrders::SORTS)).'.',
            'integer' => ':attribute must be a whole number.',
            'page.min' => 'page must be 1 or more.',
            'perPage.min' => 'perPage must be between 1 and '.ShopOrders::HISTORY_MAX_PAGE_SIZE.'.',
            'perPage.max' => 'perPage must be between 1 and '.ShopOrders::HISTORY_MAX_PAGE_SIZE.'.',
            'search.max' => 'search can be at most 100 characters.',
        ]);
        if ($validator->fails()) {
            throw ApiException::invalid($validator->errors()->first());
        }

        return array_filter($validator->validated(), fn ($v) => $v !== null && $v !== '' && $v !== []);
    }

    /** One order. An unpaid one is first checked at NAB, so a payment is never missed. */
    public function show(Request $request, string $id): JsonResponse
    {
        $customer = $this->customer($request);
        $order = $this->orders->find($customer, $id);
        if ($order->status === 'pending_payment') {
            $this->checkout->reconcile($order);
            $order = $this->orders->find($customer, $id);
        }

        return $this->json(['order' => $this->orders->present($order)]);
    }

    /** Opens a payment for the order: returns our secure payment page for the in-app browser. */
    public function pay(Request $request, string $id): JsonResponse
    {
        $customer = $this->customer($request);
        $meta = $this->meta($request);
        $this->events->enforceLimit('shop_payment_start', $customer->zenith_login, $meta->ip, perLogin: 20, perIp: 40, windowMinutes: 60, countSuccesses: true);
        $order = $this->orders->find($customer, $id);
        // A website payment (it passes its order page to come back to, says
        // it is the website, or the order was placed there) needs
        // SHOP_RETURN_HOSTS; the app's payments don't.
        $website = filled($request->input('returnUrl'))
            || strtolower(trim((string) $request->header('X-Client-Platform'))) === 'website'
            || ($order->channel ?? 'app') === 'website';
        if ($website && ! ShopCheckout::websiteReady()) {
            app(ServerLog::class)->error('shop', 'Website card payment refused: SHOP_RETURN_HOSTS is not set in .env', ['order' => $order->order_number]);
            throw ShopCheckout::websiteNotConfigured();
        }
        // The website passes its order page so the customer lands back on it.
        $returnUrl = ShopCheckout::safeReturnUrl($request->input('returnUrl'));
        $payment = $this->checkout->start($order, $returnUrl);
        $this->events->record('shop_payment_start', true, $meta, $customer->id, $customer->zenith_login, "{$order->order_number}, attempt {$payment['paymentId']}");

        return $this->json(['payment' => $payment], 201);
    }

    /** Cancels an unpaid order (after checking no payment went through) and releases its stock. */
    public function cancel(Request $request, string $id): JsonResponse
    {
        $customer = $this->customer($request);
        $order = $this->orders->find($customer, $id);
        $this->checkout->reconcile($order, force: true);
        $order = $this->orders->find($customer, $id);
        // Paid, but not shipped yet: cancelled with a full refund to the card.
        if ($order->status !== 'pending_payment') {
            $reason = $request->json('reason');
            $lock = Cache::lock("shop-cancel:{$order->id}", 60);
            if (! $lock->get()) {
                throw new ApiException(409, 'cancel_in_progress', 'This order is already being cancelled.');
            }
            try {
                app(ShopOperations::class)->cancelPaid($customer, $order->id, is_string($reason) ? mb_substr(trim($reason), 0, 300) : null);
            } finally {
                $lock->release();
            }

            return $this->json(['order' => $this->orders->present($this->orders->find($customer, $id))]);
        }
        DB::transaction(function () use ($customer, $id) {
            $fresh = $this->orders->find($customer, $id, lock: true);
            if ($fresh->status !== 'pending_payment') {
                throw new ApiException(409, 'order_not_cancellable', $fresh->status === 'paid' ? 'This order is already paid.' : 'This order is already closed.');
            }
            ShopOrders::cancel($fresh, 'cancelled by the customer in the app', 'customer');
        });

        return $this->json(['order' => $this->orders->present($this->orders->find($customer, $id))]);
    }

    /** Every gift card the customer bought, newest first. */
    public function giftCards(Request $request): JsonResponse
    {
        $cards = DB::table('gift_card_orders')->where('customer_id', $this->customer($request)->id)
            ->orderByDesc('created_at')->orderByDesc('id')->limit(100)->get();

        return $this->json(['giftCards' => $cards->map(fn ($c) => GiftCardIssuer::present($c))->all()]);
    }

    /** Tells the customer when a sold-out product (or one size of it) is back. */
    public function createStockAlert(Request $request): JsonResponse
    {
        $data = Validator::make($request->json()->all(), [
            'productId' => ['required', 'regex:/^\d{1,18}$/'],
            'variantId' => ['nullable', 'regex:/^\d{1,18}$/'],
        ], ['regex' => ':attribute is not valid.', 'required' => ':attribute is required.']);
        if ($data->fails()) {
            throw ApiException::invalid($data->errors()->first());
        }
        $channel = strtolower(trim((string) $request->header('X-Client-Platform'))) === 'website' ? 'website' : 'app';
        $alert = $this->alerts->subscribe($this->customer($request), (string) $request->json('productId'), $request->json('variantId') === null ? null : (string) $request->json('variantId'), $channel);

        return $this->json(['alert' => $alert], 201);
    }

    /** Every back-in-stock alert the customer asked for, waiting ones first. */
    public function stockAlerts(Request $request): JsonResponse
    {
        return $this->json(['alerts' => $this->alerts->list($this->customer($request))]);
    }

    public function deleteStockAlert(Request $request, string $id): JsonResponse
    {
        $this->alerts->cancel($this->customer($request), $id);

        return $this->json(['ok' => true]);
    }
}
