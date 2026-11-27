<?php

namespace App\Services\Shop;

use App\Exceptions\ApiException;
use App\Models\ShopProduct;
use App\Services\Support\Js;
use App\Services\Support\Phones;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Shop orders. Every price comes from shop_products (or the variant's own
 * price) at the moment the order is placed, never from the app; merchandise
 * stock is reserved then (see Inventory), sold when the order is paid and
 * released if it never is.
 */
class ShopOrders
{
    // No 0/O or 1/I: order numbers get read out over the phone.
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /** Every order status, with how customers see it. */
    public const STATUS_LABELS = [
        'pending_payment' => 'Awaiting payment',
        'paid' => 'Paid',
        'payment_review' => 'Payment under review',
        'partially_refunded' => 'Partly refunded',
        'refunded' => 'Refunded',
        'cancelled' => 'Cancelled',
    ];

    /** Statuses where the customer's money was taken. */
    public const PAID_STATUSES = ['paid', 'partially_refunded', 'refunded'];

    /** Order history sort orders: [column, direction]. */
    public const SORTS = [
        'newest' => ['created_at', 'desc'],
        'oldest' => ['created_at', 'asc'],
        'total_desc' => ['total', 'desc'],
        'total_asc' => ['total', 'asc'],
    ];

    public const HISTORY_PAGE_SIZE = 50;

    public const HISTORY_MAX_PAGE_SIZE = 100;

    public function __construct(
        private readonly ShopCatalog $catalog,
        private readonly Inventory $inventory,
    ) {}

    private static function orderNumber(): string
    {
        $code = '';
        for ($i = 0; $i < 6; $i++) {
            $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return 'NS-'.now()->utc()->format('ymd').'-'.$code;
    }

    private static function money(float|int|string $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    /**
     * @param  array{items: list<array{productId: string, variantId?: string|null, quantity: int, gift?: array|null}>, shippingAddress?: array|null, contactEmail?: string|null}  $input
     */
    public function create(object $customer, array $input): array
    {
        $currency = config('naoero.shop.currency', 'AUD');
        $lines = [];
        $merchQuantities = [];
        foreach ($input['items'] as $i => $item) {
            [$product, $variant] = $this->resolveLine($item, $currency);
            $giftCard = $variant === null;
            $quantity = (int) $item['quantity'];
            if (! $giftCard) {
                $merchQuantities[$variant->id] = ($merchQuantities[$variant->id] ?? 0) + $quantity;
            }
            $gift = null;
            if ($giftCard) {
                $g = is_array($item['gift'] ?? null) ? $item['gift'] : [];
                $gift = array_filter([
                    'recipientName' => Js::text($g['recipientName'] ?? null),
                    'recipientEmail' => Js::text($g['recipientEmail'] ?? null) ? strtolower(trim($g['recipientEmail'])) : null,
                    'message' => Js::text($g['message'] ?? null),
                ], fn ($v) => $v !== null) ?: null;
            }
            $lines[] = [
                'product' => $product,
                'variant' => $variant,
                'name' => Inventory::label($product->title, $variant?->title),
                'type' => $giftCard ? 'gift_card' : 'merch',
                'quantity' => $quantity,
                'unit' => (float) ($variant->price ?? $product->price),
                'gift' => $gift,
            ];
        }

        $hasMerch = $merchQuantities !== [];
        $address = $input['shippingAddress'] ?? null;
        if ($hasMerch && ! is_array($address)) {
            throw ApiException::invalid('Add a delivery address for your merchandise.');
        }
        $itemsTotal = array_sum(array_map(fn ($l) => round($l['unit'] * $l['quantity'], 2), $lines));
        $deliveryFee = $hasMerch ? (float) config('naoero.shop.delivery_fee', 10) : 0.0;
        $total = round($itemsTotal + $deliveryFee, 2);
        if ($total <= 0) {
            throw ApiException::invalid('There is nothing to pay for.');
        }

        $contact = Js::text($input['contactEmail'] ?? null) ? strtolower(trim($input['contactEmail'])) : ($customer->email ?? $customer->zenith_login);
        $id = (string) Str::uuid();
        $now = Js::dbTime();

        $orderNumber = self::orderNumber();
        DB::transaction(function () use ($id, $orderNumber, $input, $customer, $lines, $merchQuantities, $hasMerch, $address, $itemsTotal, $deliveryFee, $total, $currency, $contact, $now) {
            // Reserve merchandise stock; refuse if any line can't be met.
            $this->inventory->reserve($merchQuantities, $id, $orderNumber);

            DB::table('shop_orders')->insert([
                'id' => $id,
                'order_number' => $orderNumber,
                'customer_id' => $customer->id,
                'status' => 'pending_payment',
                'shipping_status' => null,
                'currency' => $currency,
                'items_total' => self::money($itemsTotal),
                'delivery_fee' => self::money($deliveryFee),
                'total' => self::money($total),
                'contact_email' => $contact,
                // What was bought and by whom, readable without joins.
                'product_ids' => implode(', ', array_unique(array_map(fn ($l) => $l['product']->id, $lines))),
                'item_types' => implode(', ', array_unique(array_map(fn ($l) => $l['type'], $lines))),
                'item_titles' => implode(', ', array_map(fn ($l) => "{$l['quantity']} × {$l['name']}", $lines)),
                'total_quantity' => array_sum(array_map(fn ($l) => $l['quantity'], $lines)),
                'customer_name' => trim((string) DB::table('customers')->where('id', $customer->id)->selectRaw("trim(coalesce(first_name, '') || ' ' || coalesce(last_name, '')) as n")->value('n')) ?: null,
                'customer_email' => $customer->email ?? $customer->zenith_login,
                'channel' => in_array($input['channel'] ?? 'app', ['app', 'website'], true) ? ($input['channel'] ?? 'app') : 'app',
                'shipping_address' => $hasMerch ? Js::encode($address) : null,
                // The courier's contact number, in E.164 with its country.
                'contact_phone' => $hasMerch ? (Phones::parse($address['phone'] ?? null, $address['phoneCountry'] ?? $address['country'] ?? null)['e164'] ?? null) : null,
                'contact_phone_country' => $hasMerch ? (Phones::parse($address['phone'] ?? null, $address['phoneCountry'] ?? $address['country'] ?? null)['country'] ?? null) : null,
                'expires_at' => Js::dbTime(now()->addMinutes((int) config('naoero.shop.payment_window_minutes', 30))),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            foreach ($lines as $line) {
                DB::table('shop_order_items')->insert([
                    'shop_order_id' => $id,
                    'order_number' => $orderNumber,
                    'shop_product_id' => $line['product']->id,
                    'variant_id' => $line['variant']?->id,
                    'variant_title' => $line['variant']?->title,
                    'sku' => $line['variant']->sku ?? $line['product']->sku,
                    'stock_state' => $line['variant']?->track_inventory ? 'reserved' : null,
                    'type' => $line['type'],
                    'title' => $line['product']->title,
                    'image' => ShopCatalog::imagePath($line['product']),
                    'unit_price' => self::money($line['unit']),
                    'quantity' => $line['quantity'],
                    'line_total' => self::money($line['unit'] * $line['quantity']),
                    'gift' => $line['gift'] ? Js::encode($line['gift']) : null,
                ]);
            }
        });

        $events = app(ShopEvents::class);
        $platform = ($input['channel'] ?? 'app') === 'website' ? 'on the website' : 'in the app';
        $events->record($id, 'order_placed', 'info', "Order {$orderNumber} placed {$platform}: ".implode(', ', array_map(
            fn ($l) => "{$l['quantity']} × {$l['name']}", $lines,
        ))." ({$currency} ".self::money($total).')', 'customer', $total, [
            'items' => array_map(fn ($l) => ['productId' => $l['product']->id, 'variantId' => $l['variant']?->id, 'title' => $l['name'], 'quantity' => $l['quantity'], 'unitPrice' => $l['unit'], 'gift' => $l['gift'] !== null], $lines),
            'deliveryFee' => $deliveryFee,
            'channel' => $input['channel'] ?? 'app',
        ]);
        if ($merchQuantities !== []) {
            $events->record($id, 'stock_reserved', 'info', 'Merchandise stock reserved for '.(int) config('naoero.shop.payment_window_minutes', 30).' minutes', 'system', detail: ['variants' => $merchQuantities]);
        }

        return $this->present($this->find($customer, $id));
    }

    /** The customer's own order, or a 404. */
    public function find(object $customer, string $id, bool $lock = false): object
    {
        if (! Str::isUuid($id)) {
            throw self::notFound();
        }

        return DB::table('shop_orders')->where('id', $id)->where('customer_id', $customer->id)
            ->when($lock, fn ($q) => $q->lockForUpdate())->first() ?? throw self::notFound();
    }

    public static function notFound(): ApiException
    {
        return new ApiException(404, 'order_not_found', "We couldn't find that order on your account.");
    }

    /** @return list<array<string, mixed>> */
    public function list(object $customer): array
    {
        return $this->history($customer)['orders'];
    }

    /**
     * The customer's order history, for an order history page: their orders
     * filtered, sorted and one page at a time, with totals for the whole
     * account. Only ever the customer's own orders.
     *
     * @param  array{status?: list<string>, type?: string, channel?: string, from?: string, to?: string, search?: string, sort?: string, page?: int, perPage?: int}  $filters  already validated
     * @return array{orders: list<array>, pagination: array, filters: array, summary: array}
     */
    public function history(object $customer, array $filters = []): array
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = min(self::HISTORY_MAX_PAGE_SIZE, max(1, (int) ($filters['perPage'] ?? self::HISTORY_PAGE_SIZE)));
        $sort = isset(self::SORTS[$filters['sort'] ?? '']) ? $filters['sort'] : 'newest';
        [$column, $direction] = self::SORTS[$sort];

        $query = DB::table('shop_orders as o')->where('o.customer_id', $customer->id);
        if (! empty($filters['status'])) {
            $query->whereIn('o.status', $filters['status']);
        }
        if (! empty($filters['type'])) {
            $query->whereExists(fn ($q) => $q->from('shop_order_items as i')->whereColumn('i.shop_order_id', 'o.id')->where('i.type', $filters['type']));
        }
        if (! empty($filters['channel'])) {
            $query->where('o.channel', $filters['channel']);
        }
        if (! empty($filters['from'])) {
            $query->where('o.created_at', '>=', Js::dbTime(CarbonImmutable::parse($filters['from'], 'UTC')->startOfDay()));
        }
        if (! empty($filters['to'])) {
            $query->where('o.created_at', '<', Js::dbTime(CarbonImmutable::parse($filters['to'], 'UTC')->addDay()->startOfDay()));
        }
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $like = '%'.mb_strtolower($search).'%';
            $query->where(fn ($q) => $q->whereRaw('lower(o.order_number) like ?', [$like])
                ->orWhereExists(fn ($i) => $i->from('shop_order_items as i')->whereColumn('i.shop_order_id', 'o.id')->whereRaw('lower(i.title) like ?', [$like])));
        }

        $total = (clone $query)->count();
        $rows = $query->orderBy("o.{$column}", $direction)->orderBy('o.id', $direction)
            ->forPage($page, $perPage)->get(['o.*']);

        // Every order's items and gift cards in two queries, not two per order.
        $ids = $rows->pluck('id')->all();
        $items = $ids ? DB::table('shop_order_items')->whereIn('shop_order_id', $ids)->orderBy('id')->get()->groupBy('shop_order_id') : collect();
        $cards = $ids ? DB::table('gift_card_orders')->whereIn('shop_order_id', $ids)->orderBy('id')->get()->groupBy('shop_order_id') : collect();

        return [
            'orders' => $rows->map(fn ($o) => $this->present($o, $items->get($o->id, collect()), $cards->get($o->id, collect()), full: false))->all(),
            'pagination' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'totalPages' => (int) ceil($total / $perPage),
                'hasMore' => $page * $perPage < $total,
            ],
            'filters' => [
                'status' => array_values($filters['status'] ?? []),
                'type' => $filters['type'] ?? null,
                'channel' => $filters['channel'] ?? null,
                'from' => $filters['from'] ?? null,
                'to' => $filters['to'] ?? null,
                'search' => $search === '' ? null : $search,
                'sort' => $sort,
            ],
            'summary' => $this->summary($customer),
        ];
    }

    /** Totals over all of the customer's orders (filters don't apply). */
    public function summary(object $customer): array
    {
        $byStatus = array_fill_keys(array_keys(self::STATUS_LABELS), 0);
        $rows = DB::table('shop_orders')->where('customer_id', $customer->id)->groupBy('status')
            ->get(['status', DB::raw('count(*) as n'), DB::raw('coalesce(sum(total), 0) as total'), DB::raw('coalesce(sum(refunded_amount), 0) as refunded')]);
        $spent = 0.0;
        $refunded = 0.0;
        foreach ($rows as $r) {
            $byStatus[$r->status] = (int) $r->n;
            if (in_array($r->status, self::PAID_STATUSES, true)) {
                $spent += (float) $r->total;
                $refunded += (float) $r->refunded;
            }
        }
        $dates = DB::table('shop_orders')->where('customer_id', $customer->id)->selectRaw('min(created_at) as first_at, max(created_at) as last_at')->first();
        $cards = DB::table('gift_card_orders as g')->join('shop_orders as o', 'o.id', '=', 'g.shop_order_id')->where('o.customer_id', $customer->id);

        return [
            'totalOrders' => array_sum($byStatus),
            'byStatus' => $byStatus,
            'paidOrders' => array_sum(array_intersect_key($byStatus, array_flip(self::PAID_STATUSES))),
            'awaitingPayment' => $byStatus['pending_payment'],
            // What the customer paid, less what was refunded.
            'totalSpent' => Js::round2($spent - $refunded),
            'totalRefunded' => Js::round2($refunded),
            'currency' => (string) config('naoero.shop.currency', 'AUD'),
            'giftCards' => (clone $cards)->count(),
            'activeGiftCards' => (clone $cards)->whereIn('g.status', ['issued', 'confirmed'])->count(),
            'firstOrderAt' => Js::iso($dates->first_at ?? null),
            'lastOrderAt' => Js::iso($dates->last_at ?? null),
        ];
    }

    /** Puts reserved merchandise back on sale. Must run inside a transaction. */
    public static function releaseStock(object $order): void
    {
        app(Inventory::class)->release($order);
    }

    /**
     * The product and variant a cart line asks for (no variant for a gift
     * card), or why it can't be bought.
     *
     * @param  array{productId: string, variantId?: string|null}  $item
     * @return array{0: ShopProduct, 1: object|null}
     */
    private function resolveLine(array $item, string $currency): array
    {
        $product = $this->catalog->find((string) $item['productId'])
            ?? throw new ApiException(409, 'product_unavailable', 'An item in your cart is no longer available. Please review your cart.', extra: ['productId' => (string) $item['productId']]);
        if (($product->currency ?: 'AUD') !== $currency) {
            throw new ApiException(409, 'product_unavailable', "{$product->title} can't be bought in the app.", extra: ['productId' => (string) $product->id]);
        }

        return [$product, ShopCatalog::isGiftCard($product) ? null : $this->inventory->resolve($product, $item['variantId'] ?? null)];
    }

    /**
     * Checks a cart before checkout without placing an order: today's price
     * and how many can be bought of every line, and the totals.
     *
     * @param  list<array{productId: string, variantId?: string|null, quantity: int}>  $items
     * @return array<string, mixed>
     */
    public function check(array $items): array
    {
        $currency = (string) config('naoero.shop.currency', 'AUD');
        $max = (int) config('naoero.shop.max_quantity', 10);
        $key = fn (array $item) => ($item['variantId'] ?? null) ? 'v'.$item['variantId'] : 'p'.$item['productId'];
        $wanted = [];
        foreach ($items as $item) {
            $wanted[$key($item)] = ($wanted[$key($item)] ?? 0) + (int) $item['quantity'];
        }

        $lines = [];
        $itemsTotal = 0.0;
        $hasMerch = false;
        foreach ($items as $item) {
            $quantity = (int) $item['quantity'];
            $line = ['productId' => (string) $item['productId'], 'variantId' => ($item['variantId'] ?? null) ? (string) $item['variantId'] : null, 'quantity' => $quantity];
            try {
                [$product, $variant] = $this->resolveLine($item, $currency);
            } catch (ApiException $e) {
                $lines[] = [...$line, 'ok' => false, 'problem' => $e->errorCode, 'message' => $e->getMessage(), 'title' => null,
                    'unitPrice' => null, 'available' => 0, 'maxQuantity' => 0, 'lineTotal' => 0];

                continue;
            }
            $available = $variant ? Inventory::available($variant) : null;
            $limit = max(0, min($max, $available ?? PHP_INT_MAX));
            $name = Inventory::label($product->title, $variant?->title);
            $problem = match (true) {
                $limit === 0 => ['out_of_stock', "{$name} is sold out."],
                $wanted[$key($item)] > $limit => ['not_enough_stock', $limit < $max ? "Only {$limit} × {$name} left." : "You can buy at most {$max} of one item."],
                default => null,
            };
            $unit = (float) ($variant->price ?? $product->price);
            $lineTotal = round($unit * min($quantity, $limit), 2);
            $itemsTotal += $lineTotal;
            $hasMerch = $hasMerch || ($variant !== null && $limit > 0);
            $lines[] = [
                ...$line,
                'variantId' => $variant ? (string) $variant->id : null,
                'ok' => $problem === null,
                'problem' => $problem[0] ?? null,
                'message' => $problem[1] ?? null,
                'title' => $name,
                'unitPrice' => Js::round2($unit),
                'available' => $available,
                'maxQuantity' => $limit,
                'lineTotal' => Js::round2($lineTotal),
            ];
        }
        $deliveryFee = $hasMerch ? (float) config('naoero.shop.delivery_fee', 10) : 0.0;

        return [
            'ok' => collect($lines)->every(fn ($l) => $l['ok']),
            'currency' => $currency,
            'lines' => $lines,
            // What the cart costs with every line cut to what can be bought.
            'itemsTotal' => Js::round2($itemsTotal),
            'deliveryFee' => Js::round2($deliveryFee),
            'total' => Js::round2($itemsTotal + $deliveryFee),
        ];
    }

    /**
     * Cancels an unpaid order. Must run inside a transaction, with the order
     * row locked.
     *
     * @param  'customer'|'system'|'staff'  $source
     */
    public static function cancel(object $order, string $reason, string $source = 'system', ?int $staffUserId = null): void
    {
        if ($order->status !== 'pending_payment') {
            return;
        }
        $expired = $reason === 'not paid in time';
        app(ShopEvents::class)->record($order->id, $expired ? 'order_expired' : 'order_cancelled', 'cancelled',
            $expired ? 'Not paid within 30 minutes: cancelled automatically' : 'Cancelled: '.$reason, $source, staffUserId: $staffUserId);
        self::releaseStock($order);
        DB::table('shop_orders')->where('id', $order->id)->update([
            'status' => 'cancelled', 'cancelled_at' => Js::dbTime(), 'cancel_reason' => $reason, 'cancelled_by' => $source, 'updated_at' => Js::dbTime(),
        ]);
        DB::table('shop_payment_attempts')->where('shop_order_id', $order->id)->where('status', 'started')
            ->update(['status' => 'cancelled', 'updated_at' => Js::dbTime()]);
    }

    /** @return array<string, mixed> */
    public function present(object $o, ?Collection $items = null, ?Collection $cards = null, bool $full = true): array
    {
        $items ??= DB::table('shop_order_items')->where('shop_order_id', $o->id)->orderBy('id')->get();
        $cards ??= DB::table('gift_card_orders')->where('shop_order_id', $o->id)->orderBy('id')->get();
        $payment = $o->payment_details ? json_decode($o->payment_details, true) : null;
        $expires = CarbonImmutable::parse($o->expires_at);

        return [
            'id' => $o->id,
            'orderNumber' => $o->order_number,
            'status' => $o->status,
            'statusLabel' => self::STATUS_LABELS[$o->status] ?? ucfirst(str_replace('_', ' ', $o->status)),
            'shippingStatus' => $o->shipping_status,
            // At a glance, for lists: "1 × AUD 500 Gift Card, 2 × Signature Hoodie".
            'itemsSummary' => $items->map(fn ($i) => "{$i->quantity} × ".Inventory::label($i->title, $i->variant_title ?? null))->implode(', '),
            'itemCount' => (int) $items->sum('quantity'),
            'currency' => $o->currency,
            'itemsTotal' => Js::round2($o->items_total),
            'deliveryFee' => Js::round2($o->delivery_fee),
            'total' => Js::round2($o->total),
            'contactEmail' => $o->contact_email,
            'channel' => $o->channel ?? 'app',
            'shippingAddress' => $o->shipping_address ? json_decode($o->shipping_address, true) : null,
            'items' => $items->map(fn ($i) => [
                'productId' => $i->shop_product_id === null ? null : (string) $i->shop_product_id,
                'variantId' => ($i->variant_id ?? null) === null ? null : (string) $i->variant_id,
                'variantTitle' => $i->variant_title ?? null,
                'sku' => $i->sku ?? null,
                'type' => $i->type,
                'title' => $i->title,
                'image' => ShopCatalog::imageUrl($i->image),
                'unitPrice' => Js::round2($i->unit_price),
                'quantity' => (int) $i->quantity,
                'lineTotal' => Js::round2($i->line_total),
                'gift' => $i->gift ? json_decode($i->gift, true) : null,
            ])->all(),
            'giftCards' => $cards->map(fn ($c) => GiftCardIssuer::present($c))->all(),
            'payment' => $payment,
            'canPay' => $o->status === 'pending_payment' && $expires->isFuture(),
            'createdAt' => Js::iso($o->created_at),
            'expiresAt' => Js::iso($o->expires_at),
            'paidAt' => Js::iso($o->paid_at),
            'refundedAmount' => Js::round2($o->refunded_amount ?? 0),
            'refundedAt' => Js::iso($o->refunded_at ?? null),
            'tracking' => ($o->shipped_at ?? null) ? [
                'carrier' => $o->shipping_carrier,
                'number' => $o->tracking_number,
                'url' => $o->tracking_url,
                'shippedAt' => Js::iso($o->shipped_at),
                'deliveredAt' => Js::iso($o->delivered_at),
            ] : null,
            'cancelledAt' => Js::iso($o->cancelled_at),
            'cancelledBy' => $o->cancelled_by ?? null,
            'cancelReason' => $o->cancel_reason ?? null,
            'shippingStatusLabel' => $o->shipping_status ? (ShipmentTracking::STATUSES[$o->shipping_status] ?? $o->shipping_status) : null,
            'invoiceNumber' => $o->invoice_number ?? null,
            'receiptNumber' => $o->receipt_number ?? null,
            ...($full ? $this->aftercare($o, $items) : []),
        ];
    }

    /**
     * What one order's page needs beyond the list: delivery tracking, the
     * invoice and receipt, cancelling, returns, reviews and help.
     *
     * @return array<string, mixed>
     */
    private function aftercare(object $o, Collection $items): array
    {
        $customer = (object) ['id' => $o->customer_id];
        $returns = app(ShopReturns::class);
        $eligible = ShopReturns::eligibility($o);
        $reviewed = DB::table('shop_product_reviews')->where('customer_id', $o->customer_id)
            ->whereIn('shop_product_id', $items->pluck('shop_product_id')->filter())->get()->keyBy('shop_product_id');
        $canReview = in_array($o->status, ['paid', 'partially_refunded'], true);
        $shipped = in_array($o->shipping_status, ['shipped', 'in_transit', 'out_for_delivery', 'delivered', 'delivery_failed'], true);
        $cancellation = ShopOperations::cancellation($o);

        return [
            'delivery' => ShipmentTracking::present($o),
            'documents' => ShopDocuments::links($o),
            'cancellation' => $cancellation,
            'canCancel' => $cancellation['allowed'],
            'returnPolicy' => [
                'allowed' => $eligible['allowed'],
                'reason' => $eligible['reason'],
                'until' => $eligible['until'],
                'windowDays' => (int) config('naoero.shop.return_window_days', 30),
            ],
            // Per order line: how many can still be returned, and its review.
            'lines' => $items->map(fn ($i) => [
                'orderItemId' => (string) $i->id,
                'productId' => $i->shop_product_id === null ? null : (string) $i->shop_product_id,
                'returnableQuantity' => $eligible['items'][$i->id] ?? 0,
                'returnedQuantity' => (int) ($i->returned_quantity ?? 0),
                'canReview' => $canReview && $i->shop_product_id !== null && ! isset($reviewed[$i->shop_product_id]) && ($i->type === 'gift_card' || $shipped),
                'reviewId' => isset($reviewed[$i->shop_product_id]) ? $reviewed[$i->shop_product_id]->id : null,
                'rating' => isset($reviewed[$i->shop_product_id]) ? (int) $reviewed[$i->shop_product_id]->rating : null,
            ])->values()->all(),
            'returns' => $returns->list($customer, $o->id),
            'supportRequests' => app(ShopSupport::class)->list($customer, $o->id),
            'help' => self::help(),
        ];
    }

    /** How to reach the shop team. */
    public static function help(): array
    {
        return [
            'email' => config('naoero.shop.seller.email'),
            'phone' => config('naoero.shop.seller.phone'),
            'hours' => config('naoero.shop.help_hours'),
            'topics' => collect(ShopSupport::TOPICS)->map(fn ($label, $code) => ['code' => $code, 'label' => $label])->values()->all(),
        ];
    }
}
