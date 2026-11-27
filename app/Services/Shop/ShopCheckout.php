<?php

namespace App\Services\Shop;

use App\Exceptions\ApiException;
use App\Services\Logging\ServerLog;
use App\Services\Payments\NabException;
use App\Services\Payments\NabGateway;
use App\Services\Support\Js;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Paying for a shop order with NAB Gateway Unified Checkout, inside the app:
 *
 *  1. start(): the app asks to pay; we open a payment attempt with a
 *     one-time secret and return our own https payment page for it.
 *  2. page(): the page opens a Unified Checkout session for the order's
 *     amount (from our database, never from the app), with 3-D Secure.
 *     The card is typed into NAB's fields; it never reaches us.
 *  3. complete(): NAB completes the payment and the page sends us its
 *     result. We never trust it: the transaction is looked up at NAB and
 *     must match the order's reference, amount and currency before the
 *     order is marked paid and its gift cards are issued (once).
 *  4. reconcile(): if the page never reported back (app closed, network
 *     lost), the order's reference is searched at NAB, so a payment that
 *     went through is never lost; unpaid orders expire and release stock.
 */
class ShopCheckout
{
    public function __construct(
        private readonly NabGateway $nab,
        private readonly GiftCardIssuer $giftCards,
        private readonly ShopEvents $events,
        private readonly ServerLog $log,
    ) {}

    /** Set while reconcile() applies a payment found at NAB. */
    private bool $recovering = false;

    public static function origin(): string
    {
        return (string) config('naoero.shop.pay_origin');
    }

    private static function unavailable(): ApiException
    {
        return new ApiException(503, 'payment_unavailable', 'Card payments are temporarily unavailable. Please try again shortly.');
    }

    // ------------------------------------------------------------ 1. start

    /**
     * Website card payments need the website's address in SHOP_RETURN_HOSTS
     * (.env), so customers can be sent back to their order. The app does not
     * use it.
     */
    public static function websiteReady(): bool
    {
        return config('naoero.shop.return_hosts') !== [];
    }

    /** Refuses a website payment while SHOP_RETURN_HOSTS is not set. */
    public static function websiteNotConfigured(): ApiException
    {
        return new ApiException(503, 'website_payments_not_configured',
            'Card payments on the website are not available right now. Please try again later, or pay in the Naoero Airlines app.');
    }

    /**
     * The website's page to come back to after paying, if it is https on an
     * allowed host; anything else is refused, so the page never sends
     * customers somewhere unknown.
     */
    public static function safeReturnUrl(mixed $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }
        if (! self::websiteReady()) {
            throw self::websiteNotConfigured();
        }
        $parts = is_string($url) && strlen($url) <= 1000 ? parse_url($url) : false;
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (! $parts || ($parts['scheme'] ?? '') !== 'https' || isset($parts['user']) || ! in_array($host, config('naoero.shop.return_hosts'), true)) {
            throw new ApiException(400, 'return_url_not_allowed', 'returnUrl must be an https page on an allowed website.');
        }

        return $url;
    }

    /** Placeholders a return address can use for the order it was opened for. */
    public const RETURN_PLACEHOLDERS = ['{order}', '{id}', '{status}'];

    /**
     * Where the finished page takes the customer: that order's page on the
     * website. The address given when paying (else the configured order
     * page) may hold {order} (order number), {id} (order id) and {status};
     * one without placeholders gets ?order=&id=&status= added instead.
     * Null: nowhere to go (the app closes the page itself).
     */
    public static function returnTarget(?object $attempt, ?object $order, string $status): ?string
    {
        $url = ($attempt->return_url ?? null) ?: (string) config('naoero.shop.order_page_url');
        if (! $order || $url === '') {
            return null;
        }
        $values = [rawurlencode($order->order_number), rawurlencode($order->id), rawurlencode($status)];
        if (str_contains($url, '{')) {
            return str_replace(self::RETURN_PLACEHOLDERS, $values, $url);
        }
        $fragment = '';
        if (($hash = strpos($url, '#')) !== false) {
            [$url, $fragment] = [substr($url, 0, $hash), substr($url, $hash)];
        }

        return $url.(str_contains($url, '?') ? '&' : '?').http_build_query(['order' => $order->order_number, 'id' => $order->id, 'status' => $status]).$fragment;
    }

    /** @return array{paymentId: string, payUrl: string, returnUrlPrefix: string, expiresAt: string} */
    public function start(object $order, ?string $returnUrl = null): array
    {
        if ($order->status !== 'pending_payment') {
            throw new ApiException(409, $order->status === 'paid' ? 'already_paid' : 'order_closed',
                $order->status === 'paid' ? 'This order is already paid.' : 'This order can no longer be paid. Please place a new order.');
        }
        $expires = CarbonImmutable::parse($order->expires_at);
        if ($expires->isPast()) {
            throw new ApiException(409, 'order_expired', 'This order has expired. Please place it again.');
        }
        if (! NabGateway::configured() || ! str_starts_with(self::origin(), 'https://')) {
            throw self::unavailable();
        }

        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $id = (string) Str::uuid();
        DB::table('shop_payment_attempts')->insert([
            'id' => $id,
            'shop_order_id' => $order->id,
            'order_number' => $order->order_number,
            'token_hash' => hash('sha256', $token),
            'token_expires_at' => Js::dbTime($expires),
            'status' => 'started',
            'amount' => number_format((float) $order->total, 2, '.', ''),
            'return_url' => $returnUrl,
            'created_at' => Js::dbTime(),
            'updated_at' => Js::dbTime(),
        ]);
        $base = self::origin()."/api/v1/shop/checkout/{$id}";
        $this->events->record($order->id, 'payment_started', 'info', 'Customer started paying by card (attempt '.substr($id, 0, 8).')', 'customer', $order->total, ['attempt' => $id]);

        return [
            'paymentId' => $id,
            'payUrl' => "{$base}?t={$token}",
            // The page sends the app here when it's done; the app closes it.
            'returnUrlPrefix' => "{$base}/done",
            'expiresAt' => Js::iso($expires),
        ];
    }

    /**
     * The attempt and order for a page request, after checking its secret.
     *
     * @return array{0: object, 1: object}
     */
    public function attempt(string $attemptId, string $token, bool $lockOrder = false): array
    {
        $attempt = Str::isUuid($attemptId) ? DB::table('shop_payment_attempts')->where('id', $attemptId)->first() : null;
        if (! $attempt || ! hash_equals($attempt->token_hash, hash('sha256', $token))) {
            throw new ApiException(404, 'payment_not_found', 'This payment link is not valid.');
        }
        $order = DB::table('shop_orders')->where('id', $attempt->shop_order_id)->when($lockOrder, fn ($q) => $q->lockForUpdate())->first();

        return [$attempt, $order];
    }

    // ------------------------------------------------------------ 2. page

    /** What the payment page needs: the order summary and a Unified Checkout session. */
    public function page(object $attempt, object $order): array
    {
        $summary = $this->summary($order);
        if ($order->status !== 'pending_payment' || CarbonImmutable::parse($attempt->token_expires_at)->isPast()) {
            return ['state' => $order->status === 'paid' ? 'paid' : 'closed', 'order' => $summary];
        }

        $customer = DB::table('customers')->where('id', $order->customer_id)->first(['first_name', 'last_name']);
        $body = [
            'clientVersion' => config('naoero.nab.client_version', '0.31'),
            'targetOrigins' => [self::origin()],
            'allowedCardNetworks' => array_values(array_filter(config('naoero.nab.card_networks'))),
            'allowedPaymentTypes' => array_values(array_filter(config('naoero.nab.payment_types'))),
            'country' => 'AU',
            'locale' => 'en_AU',
            'captureMandate' => [
                'billingType' => 'PARTIAL',
                'requestEmail' => false,
                'requestPhone' => false,
                'requestShipping' => false,
                'showAcceptedNetworkIcons' => true,
            ],
            // Card payment completed by Unified Checkout, with 3-D Secure:
            // gift cards are a high-fraud product.
            'completeMandate' => ['type' => 'CAPTURE', 'consumerAuthentication' => true],
            'clientReferenceInformation' => ['code' => $order->order_number],
            'orderInformation' => [
                'amountDetails' => ['totalAmount' => number_format((float) $order->total, 2, '.', ''), 'currency' => $order->currency],
                'billTo' => array_filter([
                    'email' => $order->contact_email,
                    'firstName' => $customer->first_name ?? null,
                    'lastName' => $customer->last_name ?? null,
                ]),
            ],
        ];
        try {
            $session = $this->nab->captureContext($body);
        } catch (NabException $e) {
            $this->log->error('shop', 'checkout session failed', ['order' => $order->order_number, 'error' => $e->getMessage()]);
            $this->events->record($order->id, 'payment_failed', 'failed', "NAB could not open the card form: {$e->getMessage()}", 'nab', detail: ['attempt' => $attempt->id]);
            throw self::unavailable();
        }
        $this->events->record($order->id, 'payment_page_opened', 'info', 'Secure card form opened (3-D Secure on)', 'customer', detail: ['attempt' => $attempt->id]);

        return ['state' => 'ready', 'order' => $summary, ...$session];
    }

    private function summary(object $order): array
    {
        $items = DB::table('shop_order_items')->where('shop_order_id', $order->id)->orderBy('id')->get();

        return [
            'orderNumber' => $order->order_number,
            'currency' => $order->currency,
            'items' => $items->map(fn ($i) => ['title' => $i->title, 'quantity' => (int) $i->quantity, 'lineTotal' => Js::round2($i->line_total), 'type' => $i->type, 'image' => ShopCatalog::imageUrl($i->image)])->all(),
            'itemsTotal' => Js::round2($order->items_total),
            'deliveryFee' => Js::round2($order->delivery_fee),
            'total' => Js::round2($order->total),
            'email' => $order->contact_email,
        ];
    }

    // ------------------------------------------------------------ 3. complete

    /**
     * Records what Unified Checkout returned for the attempt.
     *
     * @return array{status: string, message: string}
     */
    public function complete(string $attemptId, string $token, string $completeResponse): array
    {
        [$attempt] = $this->attempt($attemptId, $token);

        // Which transaction the page is talking about. The response is a
        // gateway JWT; its claims are only a pointer: NAB itself is asked.
        $claims = [];
        try {
            $claims = $this->nab->verifyJwt($completeResponse);
        } catch (Throwable) {
            $parts = explode('.', $completeResponse);
            if (count($parts) === 3) {
                $claims = json_decode((string) base64_decode(strtr($parts[1], '-_', '+/')), true) ?: [];
            }
        }
        $transactionId = (string) ($claims['id'] ?? $claims['transactionId'] ?? ($claims['details']['id'] ?? ''));
        if (! preg_match('/^[A-Za-z0-9]{6,40}$/', $transactionId)) {
            $reported = strtoupper((string) ($claims['status'] ?? ''));
            $this->setAttempt($attempt->id, 'declined', $reported ?: 'no transaction');

            return ['status' => 'declined', 'message' => 'The payment was not completed. Please check your card details and try again.'];
        }

        // Newly made transactions take a few seconds to be searchable.
        $transaction = null;
        for ($i = 0; $i < 7 && ! $transaction; $i++) {
            if ($i > 0) {
                sleep(2);
            }
            try {
                $transaction = $this->nab->transaction($transactionId);
            } catch (NabException $e) {
                $this->log->warn('shop', 'transaction lookup failed', ['error' => $e->getMessage()]);
            }
        }
        if (! $transaction) {
            DB::table('shop_payment_attempts')->where('id', $attempt->id)->update(['nab_transaction_id' => $transactionId, 'updated_at' => Js::dbTime()]);
            $this->events->record($attempt->shop_order_id, 'payment_pending', 'pending', "Card form finished; NAB has not confirmed transaction {$transactionId} yet", 'nab', detail: ['attempt' => $attempt->id, 'transaction' => $transactionId]);

            // reconcile() picks it up from the order's reference.
            return ['status' => 'pending', 'message' => "We're confirming your payment with the bank. This can take a minute."];
        }

        return $this->apply($attempt->id, $transaction);
    }

    /**
     * Decides what a gateway transaction means for the order and records it.
     *
     * @return array{status: string, message: string}
     */
    public function apply(string $attemptId, array $transaction): array
    {
        return DB::transaction(function () use ($attemptId, $transaction) {
            $attempt = DB::table('shop_payment_attempts')->where('id', $attemptId)->first();
            $order = DB::table('shop_orders')->where('id', $attempt->shop_order_id)->lockForUpdate()->first();
            $verdict = self::verdict($transaction, $order);
            $txId = (string) ($transaction['id'] ?? '');

            if (in_array($order->status, ['paid', 'partially_refunded', 'refunded'], true)) {
                // Already settled by another attempt or by reconciliation.
                return $order->nab_transaction_id === $txId
                    ? ['status' => 'paid', 'message' => 'Payment received. Thank you!']
                    : ['status' => 'paid', 'message' => 'This order was already paid.'];
            }

            if ($verdict['status'] === 'paid' && $order->status !== 'pending_payment') {
                // Paid after the order was cancelled: keep the record for a
                // refund by staff; the customer is told clearly.
                $this->setAttempt($attemptId, 'review', 'paid after the order closed', $txId, $verdict, event: false);
                DB::table('shop_orders')->where('id', $order->id)->update(['status' => 'payment_review', 'nab_transaction_id' => $txId, 'payment_details' => Js::encode($verdict['card']), 'updated_at' => Js::dbTime()]);
                $this->log->error('shop', 'payment after order closed', ['order' => $order->order_number, 'transaction' => $txId]);
                $this->events->record($order->id, 'payment_after_close', 'warning', "Paid {$order->currency} {$order->total} after the order had closed: refund or fulfil it", 'nab', $order->total, ['transaction' => $txId, 'card' => $verdict['card']]);

                return ['status' => 'review', 'message' => 'Your payment was received after this order closed. Our team will contact you.'];
            }

            if ($verdict['status'] === 'paid') {
                $this->markPaid($order, $attemptId, $txId, $verdict);

                return ['status' => 'paid', 'message' => 'Payment received. Thank you!'];
            }
            if ($verdict['status'] === 'review') {
                $this->setAttempt($attemptId, 'review', $verdict['reason'], $txId, $verdict);
                DB::table('shop_orders')->where('id', $order->id)->update(['status' => 'payment_review', 'nab_transaction_id' => $txId, 'payment_details' => Js::encode($verdict['card']), 'updated_at' => Js::dbTime()]);

                return ['status' => 'review', 'message' => "Your payment is being reviewed by the bank. We'll email you once it's confirmed."];
            }

            $this->setAttempt($attemptId, 'declined', $verdict['reason'], $txId, $verdict);

            return ['status' => 'declined', 'message' => 'Your card was declined. Please try another card.'];
        });
    }

    private function markPaid(object $order, string $attemptId, string $txId, array $verdict): void
    {
        $hasMerch = DB::table('shop_order_items')->where('shop_order_id', $order->id)->where('type', 'merch')->exists();
        DB::table('shop_orders')->where('id', $order->id)->update([
            'status' => 'paid',
            'shipping_status' => $hasMerch ? 'processing' : null,
            'nab_transaction_id' => $txId,
            'payment_details' => Js::encode($verdict['card']),
            'paid_at' => Js::dbTime(),
            'updated_at' => Js::dbTime(),
        ]);
        $this->setAttempt($attemptId, 'paid', null, $txId, $verdict);
        // The stock held for the order is now sold.
        app(Inventory::class)->sell($order);
        ShopDocuments::assign($order);
        if ($hasMerch) {
            app(ShipmentTracking::class)->start($order);
        }
        $customer = DB::table('customers')->where('id', $order->customer_id)->first(['id']);
        $issued = $this->giftCards->issue($order, $customer);
        $this->log->info('shop', 'order paid', ['order' => $order->order_number, 'transaction' => $txId, 'giftCards' => $issued]);
    }

    /** Records an attempt's outcome, and the matching event on the order's timeline. */
    private function setAttempt(string $id, string $status, ?string $reason, ?string $txId = null, ?array $detail = null, bool $event = true): void
    {
        $card = $detail['card'] ?? [];
        DB::table('shop_payment_attempts')->where('id', $id)->update(array_filter([
            'status' => $status,
            'reason' => $reason === null ? null : mb_substr($reason, 0, 300),
            'nab_transaction_id' => $txId ?: null,
            'detail' => $detail === null ? null : Js::encode($detail),
            'card_brand' => $card['brand'] ?? null,
            'card_last4' => $card['last4'] ?? null,
            'updated_at' => Js::dbTime(),
        ], fn ($v) => $v !== null));
        if (! $event) {
            return;
        }
        $attempt = DB::table('shop_payment_attempts')->where('id', $id)->first(['shop_order_id']);
        $order = DB::table('shop_orders')->where('id', $attempt->shop_order_id)->first(['total', 'currency']);
        $cardLabel = isset($card['brand']) ? trim($card['brand'].(isset($card['last4']) ? " •••• {$card['last4']}" : '')) : 'card';
        $secure = isset($card['threeDSecure']) ? ", 3-D Secure ECI {$card['threeDSecure']}" : '';
        [$type, $outcome, $message] = match ($status) {
            'paid' => [$this->recovering ? 'payment_recovered' : 'payment_succeeded', 'success',
                ($this->recovering ? 'Payment found at NAB and confirmed' : 'Payment confirmed by NAB')." ({$order->currency} {$order->total}, {$cardLabel}{$secure})"],
            'declined' => ['payment_declined', 'declined', "Payment declined ({$cardLabel}): ".($reason ?? 'no reason given')],
            'review' => ['payment_review', 'review', "Held for review by NAB fraud screening ({$cardLabel})"],
            'failed' => ['payment_failed', 'failed', 'Payment failed: '.($reason ?? 'unknown error')],
            default => ['payment_'.$status, 'info', "Payment {$status}"],
        };
        $this->events->record($attempt->shop_order_id, $type, $outcome, $message, 'nab', $status === 'paid' ? $order->total : null,
            ['attempt' => $id, 'transaction' => $txId, 'reason' => $reason, 'card' => $card ?: null]);
    }

    /**
     * Whether a gateway transaction pays this order. It must carry the
     * order's reference, amount and currency, with a successful
     * authorisation and capture, and not be held by fraud screening.
     *
     * @return array{status: string, reason: ?string, card: array}
     */
    public static function verdict(array $t, object $order): array
    {
        $apps = [];
        foreach ((array) ($t['applicationInformation']['applications'] ?? []) as $a) {
            $apps[$a['name'] ?? ''] = (string) ($a['reasonCode'] ?? '');
        }
        $card = $t['paymentInformation']['card'] ?? [];
        $brand = match ((string) ($card['type'] ?? '')) {
            '001' => 'Visa', '002' => 'Mastercard', '003' => 'American Express',
            default => match ((string) ($t['paymentInformation']['paymentType']['method'] ?? '')) {
                'VI' => 'Visa', 'MC', 'MA' => 'Mastercard', 'AX' => 'American Express', default => 'Card',
            },
        };
        $summary = [
            'method' => 'card',
            'brand' => $brand,
            'last4' => isset($card['suffix']) ? substr((string) $card['suffix'], -4) : null,
            'transactionId' => $t['id'] ?? null,
            'threeDSecure' => isset($t['consumerAuthenticationInformation']['eciRaw']) ? (string) $t['consumerAuthenticationInformation']['eciRaw'] : null,
        ];
        $result = fn (string $status, ?string $reason) => ['status' => $status, 'reason' => $reason, 'card' => $summary];

        if (($t['clientReferenceInformation']['code'] ?? null) !== $order->order_number) {
            return $result('declined', 'reference does not match the order');
        }
        $amount = $t['orderInformation']['amountDetails']['totalAmount'] ?? null;
        $currency = $t['orderInformation']['amountDetails']['currency'] ?? null;
        if (! is_numeric($amount) || (int) round($amount * 100) !== (int) round((float) $order->total * 100) || $currency !== $order->currency) {
            return $result('declined', "amount {$amount} {$currency} does not match the order");
        }
        $auth = $apps['ics_auth'] ?? null;
        $decision = $apps['ics_decision'] ?? null;
        if ($decision === '480') {
            return $result('review', 'held for review by fraud screening');
        }
        if ($auth !== '100' || ($decision !== null && $decision !== '100')) {
            return $result('declined', 'authorisation '.($auth ?? 'missing').', decision '.($decision ?? 'none'));
        }
        if (isset($apps['ics_bill']) && $apps['ics_bill'] !== '100') {
            return $result('declined', "capture {$apps['ics_bill']}");
        }

        return $result('paid', null);
    }

    // ------------------------------------------------------------ 4. reconcile

    /**
     * For an unpaid order with payment attempts: asks NAB whether a payment
     * for its reference went through (at most every 30 seconds per order).
     */
    public function reconcile(object $order, bool $force = false): void
    {
        if ($order->status !== 'pending_payment' || ! NabGateway::configured()) {
            return;
        }
        $attempt = DB::table('shop_payment_attempts')->where('shop_order_id', $order->id)->orderByDesc('created_at')->first();
        if (! $attempt) {
            return;
        }
        if (! $force && $order->reconciled_at && CarbonImmutable::parse($order->reconciled_at)->gt(now()->subSeconds(30))) {
            return;
        }
        DB::table('shop_orders')->where('id', $order->id)->update(['reconciled_at' => Js::dbTime()]);

        try {
            $found = $this->nab->searchByReference($order->order_number);
        } catch (NabException $e) {
            $this->log->warn('shop', 'reconcile search failed', ['order' => $order->order_number, 'error' => $e->getMessage()]);

            return;
        }
        foreach ($found as $summary) {
            if (! isset($summary['id']) || self::verdict($summary, $order)['status'] !== 'paid') {
                continue;
            }
            // The summary is enough to know; the full record confirms it.
            $transaction = $this->nab->transaction((string) $summary['id']) ?? $summary;
            $this->recovering = true;
            try {
                $outcome = $this->apply($attempt->id, $transaction);
            } finally {
                $this->recovering = false;
            }
            $this->log->info('shop', 'reconciled payment', ['order' => $order->order_number, 'outcome' => $outcome['status']]);

            return;
        }
    }

    /** Cancels unpaid orders past their payment window (after a last check at NAB). */
    public function expireStale(): int
    {
        $expired = 0;
        $orders = DB::table('shop_orders')->where('status', 'pending_payment')->where('expires_at', '<', Js::dbTime())->limit(100)->get();
        foreach ($orders as $order) {
            $this->reconcile($order, force: true);
            DB::transaction(function () use ($order, &$expired) {
                $fresh = DB::table('shop_orders')->where('id', $order->id)->lockForUpdate()->first();
                if ($fresh->status === 'pending_payment') {
                    ShopOrders::cancel($fresh, 'not paid in time');
                    $expired++;
                }
            });
        }

        return $expired;
    }
}
