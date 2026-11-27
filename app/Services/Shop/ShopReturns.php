<?php

namespace App\Services\Shop;

use App\Exceptions\ApiException;
use App\Models\ActivityLog;
use App\Services\Support\Js;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Returns and exchanges of delivered merchandise, item by item, within
 * the return window. The customer asks; staff approve (with how to send
 * it back) or reject; once the parcel is back staff record its condition,
 * put what can be sold again back in stock, and either refund the card
 * through NAB or send the replacement (held in stock from approval).
 *
 * requested -> approved -> received -> refunded | exchanged;
 * requested / approved -> rejected | cancelled (by the customer).
 * Every step is on the order's timeline and is a notice to the customer.
 */
class ShopReturns
{
    public const TYPES = ['return' => 'Return for a refund', 'exchange' => 'Exchange for another size or option'];

    public const REASONS = [
        'wrong_size' => 'Wrong size or fit',
        'changed_mind' => 'Changed my mind',
        'damaged' => 'Arrived damaged',
        'faulty' => 'Faulty',
        'wrong_item' => 'Wrong item sent',
        'not_as_described' => 'Not as described',
        'other' => 'Other',
    ];

    public const STATUSES = [
        'requested' => 'Requested',
        'approved' => 'Approved: send it back',
        'rejected' => 'Not accepted',
        'received' => 'Received by us',
        'refunded' => 'Refunded',
        'exchanged' => 'Replacement sent',
        'cancelled' => 'Cancelled',
    ];

    public const OPEN = ['requested', 'approved', 'received'];

    public const CONDITIONS = ['unused' => 'Unused, can be sold again', 'opened' => 'Opened, as new', 'damaged' => 'Damaged', 'faulty' => 'Faulty'];

    public function __construct(
        private readonly ShopEvents $events,
        private readonly Inventory $inventory,
        private readonly ShopOperations $operations,
    ) {}

    private static function number(): string
    {
        do {
            $number = 'RT-'.now()->utc()->format('ymd').'-'.strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
        } while (DB::table('shop_return_requests')->where('return_number', $number)->exists());

        return $number;
    }

    /**
     * Whether the order can be returned now, and how many of each line.
     *
     * @return array{allowed: bool, reason: ?string, until: ?string, items: array<int, int>}
     */
    public static function eligibility(object $order): array
    {
        $no = fn (string $why) => ['allowed' => false, 'reason' => $why, 'until' => null, 'items' => []];
        if (! in_array($order->status, ['paid', 'partially_refunded'], true) || $order->shipping_status === null) {
            return $no('Only paid orders with merchandise can be returned.');
        }
        if ($order->shipping_status !== 'delivered' || ! $order->delivered_at) {
            return $no('You can ask for a return once your order is delivered.');
        }
        $until = CarbonImmutable::parse($order->delivered_at)->addDays((int) config('naoero.shop.return_window_days', 30));
        if ($until->isPast()) {
            return $no('The '.config('naoero.shop.return_window_days', 30).'-day return window for this order has closed.');
        }
        $open = DB::table('shop_return_items as ri')->join('shop_return_requests as r', 'r.id', '=', 'ri.return_request_id')
            ->where('r.shop_order_id', $order->id)->whereIn('r.status', self::OPEN)
            ->groupBy('ri.order_item_id')->selectRaw('ri.order_item_id, sum(ri.quantity) as n')->pluck('n', 'order_item_id');
        $items = [];
        foreach (DB::table('shop_order_items')->where('shop_order_id', $order->id)->where('type', 'merch')->get() as $line) {
            $items[$line->id] = max(0, (int) $line->quantity - (int) $line->returned_quantity - (int) ($open[$line->id] ?? 0));
        }
        if (array_sum($items) === 0) {
            return ['allowed' => false, 'reason' => 'Everything in this order is already being returned.', 'until' => $until->toDateString(), 'items' => $items];
        }

        return ['allowed' => true, 'reason' => null, 'until' => $until->toDateString(), 'items' => $items];
    }

    /**
     * @param  array{type: string, reason: string, details?: ?string, items: list<array{orderItemId: string, quantity: int, exchangeVariantId?: ?string}>}  $input
     */
    public function create(object $customer, string $orderId, array $input, string $channel): array
    {
        if (! isset(self::TYPES[$input['type']])) {
            throw ApiException::invalid('type must be return or exchange.');
        }
        if (! isset(self::REASONS[$input['reason']])) {
            throw ApiException::invalid('reason must be one of: '.implode(', ', array_keys(self::REASONS)).'.');
        }

        return DB::transaction(function () use ($customer, $orderId, $input, $channel) {
            $order = (Str::isUuid($orderId) ? DB::table('shop_orders')->where('id', $orderId)->where('customer_id', $customer->id)->lockForUpdate()->first() : null)
                ?? throw ShopOrders::notFound();
            $eligible = self::eligibility($order);
            if (! $eligible['allowed']) {
                throw new ApiException(409, 'return_not_allowed', $eligible['reason']);
            }
            $lines = DB::table('shop_order_items')->where('shop_order_id', $order->id)->get()->keyBy('id');
            $rows = [];
            foreach ($input['items'] as $item) {
                $line = $lines[(int) $item['orderItemId']] ?? null;
                $quantity = (int) $item['quantity'];
                if (! $line || $line->type !== 'merch') {
                    throw ApiException::invalid('Choose items from this order.');
                }
                $left = $eligible['items'][$line->id] ?? 0;
                if ($quantity < 1 || $quantity > $left) {
                    throw new ApiException(409, 'return_not_allowed', $left > 0 ? "You can return up to {$left} × ".Inventory::label($line->title, $line->variant_title).'.' : Inventory::label($line->title, $line->variant_title).' is already being returned.');
                }
                $exchange = null;
                if ($input['type'] === 'exchange') {
                    $exchange = ctype_digit((string) ($item['exchangeVariantId'] ?? '')) ? DB::table('shop_product_variants')->where('id', (int) $item['exchangeVariantId'])
                        ->where('shop_product_id', $line->shop_product_id)->where('is_active', true)->first() : null;
                    if (! $exchange) {
                        throw ApiException::invalid('Choose the size or option you want instead of '.Inventory::label($line->title, $line->variant_title).'.');
                    }
                    if ((int) $exchange->id === (int) $line->variant_id) {
                        throw ApiException::invalid('Choose a different size or option to exchange for.');
                    }
                    $available = Inventory::available($exchange);
                    if ($available !== null && $available < $quantity) {
                        throw new ApiException(409, 'out_of_stock', Inventory::label($line->title, $exchange->title).($available > 0 ? " has only {$available} left." : ' is sold out.').' You can ask for a refund instead.',
                            extra: ['variantId' => (string) $exchange->id, 'available' => $available]);
                    }
                }
                $rows[] = [$line, $quantity, $exchange];
            }
            if (! $rows) {
                throw ApiException::invalid('Choose the items to return.');
            }

            $id = (string) Str::uuid();
            $number = self::number();
            DB::table('shop_return_requests')->insert([
                'id' => $id, 'return_number' => $number, 'shop_order_id' => $order->id, 'customer_id' => $customer->id,
                'type' => $input['type'], 'status' => 'requested', 'reason' => $input['reason'], 'details' => $input['details'] ?? null,
                'channel' => $channel, 'created_at' => Js::dbTime(), 'updated_at' => Js::dbTime(),
            ]);
            foreach ($rows as [$line, $quantity, $exchange]) {
                DB::table('shop_return_items')->insert([
                    'return_request_id' => $id, 'order_item_id' => $line->id, 'quantity' => $quantity,
                    'exchange_variant_id' => $exchange?->id, 'exchange_title' => $exchange ? Inventory::label($line->title, $exchange->title) : null,
                ]);
            }
            $what = implode(', ', array_map(fn ($r) => "{$r[1]} × ".Inventory::label($r[0]->title, $r[0]->variant_title).($r[2] ? ' for '.$r[2]->title : ''), $rows));
            $this->events->record($order->id, 'return_requested', 'pending', ucfirst($input['type'])." {$number} requested: {$what} (".self::REASONS[$input['reason']].')', 'customer',
                detail: ['returnId' => $id, 'status' => 'requested']);

            return $this->present(DB::table('shop_return_requests')->find($id));
        });
    }

    /** @return list<array<string, mixed>> */
    public function list(object $customer, ?string $orderId = null): array
    {
        return DB::table('shop_return_requests')->where('customer_id', $customer->id)
            ->when($orderId, fn ($q) => $q->where('shop_order_id', $orderId))
            ->orderByDesc('created_at')->limit(100)->get()->map(fn ($r) => $this->present($r))->all();
    }

    public function show(object $customer, string $id): array
    {
        return $this->present($this->owned($customer, $id));
    }

    public function cancel(object $customer, string $id): array
    {
        return DB::transaction(function () use ($customer, $id) {
            $r = $this->owned($customer, $id, lock: true);
            if (! in_array($r->status, ['requested', 'approved'], true)) {
                throw new ApiException(409, 'return_not_cancellable', $r->status === 'received' ? 'We already have the parcel: this return can no longer be cancelled.' : 'This return is already closed.');
            }
            $this->releaseReplacements($r, null);
            DB::table('shop_return_requests')->where('id', $r->id)->update(['status' => 'cancelled', 'completed_at' => Js::dbTime(), 'updated_at' => Js::dbTime()]);
            $this->events->record($r->shop_order_id, 'return_cancelled', 'cancelled', "Return {$r->return_number} cancelled by the customer", 'customer', detail: ['returnId' => $r->id, 'status' => 'cancelled']);

            return $this->present(DB::table('shop_return_requests')->find($r->id));
        });
    }

    // ------------------------------------------------------------ staff

    public function approve(string $id, ?string $instructions, int $staffUserId): void
    {
        DB::transaction(function () use ($id, $instructions, $staffUserId) {
            $r = $this->lock($id);
            if ($r->status !== 'requested') {
                throw new RuntimeException('Only a new request can be approved; this one is '.mb_strtolower(self::STATUSES[$r->status]).'.');
            }
            // The replacements are held now, so they're there when the parcel comes back.
            foreach (DB::table('shop_return_items')->where('return_request_id', $r->id)->whereNotNull('exchange_variant_id')->orderBy('exchange_variant_id')->get() as $item) {
                $this->inventory->hold((int) $item->exchange_variant_id, (int) $item->quantity, $this->ref($r), $staffUserId);
                DB::table('shop_return_items')->where('id', $item->id)->update(['replacement_state' => 'reserved']);
            }
            $instructions = trim((string) $instructions) ?: 'Pack the items securely with your order number '.DB::table('shop_orders')->where('id', $r->shop_order_id)->value('order_number').' and send them to: '.config('naoero.shop.seller.name').', '.config('naoero.shop.seller.address').'.';
            DB::table('shop_return_requests')->where('id', $r->id)->update(['status' => 'approved', 'instructions' => $instructions, 'handled_by' => $staffUserId, 'approved_at' => Js::dbTime(), 'updated_at' => Js::dbTime()]);
            $this->events->record($r->shop_order_id, 'return_approved', 'info', "Return {$r->return_number} approved: {$instructions}", 'staff', detail: ['returnId' => $r->id, 'status' => 'approved'], staffUserId: $staffUserId);
            $this->audit($r, 'approved');
        });
    }

    public function reject(string $id, string $note, int $staffUserId): void
    {
        DB::transaction(function () use ($id, $note, $staffUserId) {
            $r = $this->lock($id);
            if (! in_array($r->status, ['requested', 'approved'], true)) {
                throw new RuntimeException('This return can no longer be rejected.');
            }
            $this->releaseReplacements($r, $staffUserId);
            DB::table('shop_return_requests')->where('id', $r->id)->update(['status' => 'rejected', 'staff_note' => $note, 'handled_by' => $staffUserId, 'completed_at' => Js::dbTime(), 'updated_at' => Js::dbTime()]);
            $this->events->record($r->shop_order_id, 'return_rejected', 'declined', "Return {$r->return_number} not accepted: {$note}", 'staff', detail: ['returnId' => $r->id, 'status' => 'rejected'], staffUserId: $staffUserId);
            $this->audit($r, "rejected: {$note}");
        });
    }

    /**
     * The parcel is back: each item's condition, and whether it goes back on sale.
     *
     * @param  array<int, array{condition: string, restock?: bool}>  $items  return item id => what was found
     */
    public function receive(string $id, array $items, ?string $note, int $staffUserId): void
    {
        DB::transaction(function () use ($id, $items, $note, $staffUserId) {
            $r = $this->lock($id);
            if ($r->status !== 'approved') {
                throw new RuntimeException('Approve the return before receiving it.');
            }
            $order = DB::table('shop_orders')->where('id', $r->shop_order_id)->lockForUpdate()->first();
            $restock = [];
            $found = [];
            foreach (DB::table('shop_return_items')->where('return_request_id', $r->id)->get() as $item) {
                $condition = $items[$item->id]['condition'] ?? 'unused';
                if (! isset(self::CONDITIONS[$condition])) {
                    throw new RuntimeException('Choose the condition of every item.');
                }
                $back = (bool) ($items[$item->id]['restock'] ?? in_array($condition, ['unused', 'opened'], true));
                DB::table('shop_return_items')->where('id', $item->id)->update(['condition' => $condition, 'restocked' => $back]);
                $line = DB::table('shop_order_items')->find($item->order_item_id);
                $found[] = "{$item->quantity} × ".Inventory::label($line->title, $line->variant_title).': '.mb_strtolower(self::CONDITIONS[$condition]);
                if ($back) {
                    $restock[$item->order_item_id] = ($restock[$item->order_item_id] ?? 0) + (int) $item->quantity;
                } else {
                    // Not sold again, but counted as returned.
                    DB::table('shop_order_items')->where('id', $item->order_item_id)->increment('returned_quantity', (int) $item->quantity);
                }
            }
            if ($restock) {
                $this->inventory->restock($order, $restock, $staffUserId, "Return {$r->return_number}");
            }
            DB::table('shop_return_requests')->where('id', $r->id)->update([
                'status' => 'received', 'staff_note' => $note ?: $r->staff_note, 'handled_by' => $staffUserId, 'received_at' => Js::dbTime(), 'updated_at' => Js::dbTime(),
            ]);
            $this->events->record($r->shop_order_id, 'return_received', 'info', "Return {$r->return_number} received: ".implode('; ', $found), 'staff', detail: ['returnId' => $r->id, 'status' => 'received'], staffUserId: $staffUserId);
            $this->audit($r, 'received');
        });
    }

    /** What the returned items cost. */
    public static function value(object $r): float
    {
        return round((float) DB::table('shop_return_items as ri')->join('shop_order_items as i', 'i.id', '=', 'ri.order_item_id')
            ->where('ri.return_request_id', $r->id)->sum(DB::raw('ri.quantity * i.unit_price')), 2);
    }

    /** Refunds the returned items to the customer's card through NAB. */
    public function refund(string $id, ?float $amount, int $staffUserId): object
    {
        $r = DB::table('shop_return_requests')->where('id', $id)->first() ?? throw new RuntimeException('Return not found.');
        if ($r->status !== 'received') {
            throw new RuntimeException('Receive the return before refunding it.');
        }
        $amount = round($amount ?? self::value($r), 2);
        $refund = $this->operations->refund($r->shop_order_id, $amount, "Return {$r->return_number}", false, $staffUserId);
        if ($refund->status !== 'succeeded') {
            throw new RuntimeException("NAB refused the refund: {$refund->failure_reason}");
        }
        DB::table('shop_return_requests')->where('id', $r->id)->update([
            'status' => 'refunded', 'refund_amount' => number_format($amount, 2, '.', ''), 'refund_id' => $refund->id,
            'handled_by' => $staffUserId, 'completed_at' => Js::dbTime(), 'updated_at' => Js::dbTime(),
        ]);
        $this->events->record($r->shop_order_id, 'return_refunded', 'refunded', "Return {$r->return_number} refunded: {$refund->currency} ".number_format($amount, 2), 'staff', $amount,
            ['returnId' => $r->id, 'status' => 'refunded'], $staffUserId);
        $this->audit($r, "refunded {$amount}");

        return $refund;
    }

    /** Sends the replacement: its held stock is sold. */
    public function completeExchange(string $id, string $carrier, ?string $tracking, int $staffUserId): void
    {
        DB::transaction(function () use ($id, $carrier, $tracking, $staffUserId) {
            $r = $this->lock($id);
            if ($r->type !== 'exchange' || $r->status !== 'received') {
                throw new RuntimeException('Receive the exchange before sending the replacement.');
            }
            foreach (DB::table('shop_return_items')->where('return_request_id', $r->id)->where('replacement_state', 'reserved')->orderBy('exchange_variant_id')->get() as $item) {
                $this->inventory->sellHold((int) $item->exchange_variant_id, (int) $item->quantity, $this->ref($r), $staffUserId);
                DB::table('shop_return_items')->where('id', $item->id)->update(['replacement_state' => 'sold']);
            }
            DB::table('shop_return_requests')->where('id', $r->id)->update([
                'status' => 'exchanged', 'replacement_carrier' => $carrier, 'replacement_tracking' => $tracking,
                'handled_by' => $staffUserId, 'completed_at' => Js::dbTime(), 'updated_at' => Js::dbTime(),
            ]);
            $this->events->record($r->shop_order_id, 'return_exchanged', 'success', "Exchange {$r->return_number}: replacement sent with {$carrier}".($tracking ? ", tracking {$tracking}" : ''), 'staff',
                detail: ['returnId' => $r->id, 'status' => 'exchanged', 'carrier' => $carrier, 'tracking' => $tracking], staffUserId: $staffUserId);
            $this->audit($r, "replacement sent with {$carrier}");
        });
    }

    private function releaseReplacements(object $r, ?int $staffUserId): void
    {
        foreach (DB::table('shop_return_items')->where('return_request_id', $r->id)->where('replacement_state', 'reserved')->get() as $item) {
            $this->inventory->releaseHold((int) $item->exchange_variant_id, (int) $item->quantity, $this->ref($r), $staffUserId);
            DB::table('shop_return_items')->where('id', $item->id)->update(['replacement_state' => 'released']);
        }
    }

    private function ref(object $r): array
    {
        return ['reference_type' => 'return', 'reference_id' => $r->id, 'reference_label' => $r->return_number];
    }

    private function lock(string $id): object
    {
        return DB::table('shop_return_requests')->where('id', $id)->lockForUpdate()->first() ?? throw new RuntimeException('Return not found.');
    }

    private function owned(object $customer, string $id, bool $lock = false): object
    {
        return (Str::isUuid($id) ? DB::table('shop_return_requests')->where('id', $id)->where('customer_id', $customer->id)->when($lock, fn ($q) => $q->lockForUpdate())->first() : null)
            ?? throw new ApiException(404, 'return_not_found', "We couldn't find that return on your account.");
    }

    /** @return array<string, mixed> */
    public function present(object $r): array
    {
        $items = DB::table('shop_return_items as ri')->join('shop_order_items as i', 'i.id', '=', 'ri.order_item_id')
            ->where('ri.return_request_id', $r->id)->orderBy('ri.id')
            ->get(['ri.*', 'i.title', 'i.variant_title', 'i.image', 'i.unit_price', 'i.shop_product_id']);

        return [
            'id' => $r->id,
            'returnNumber' => $r->return_number,
            'orderId' => $r->shop_order_id,
            'orderNumber' => DB::table('shop_orders')->where('id', $r->shop_order_id)->value('order_number'),
            'type' => $r->type,
            'typeLabel' => self::TYPES[$r->type] ?? $r->type,
            'status' => $r->status,
            'statusLabel' => self::STATUSES[$r->status] ?? $r->status,
            'reason' => $r->reason,
            'reasonLabel' => self::REASONS[$r->reason] ?? $r->reason,
            'details' => $r->details,
            'instructions' => $r->instructions,
            'note' => in_array($r->status, ['rejected', 'received', 'refunded', 'exchanged'], true) ? $r->staff_note : null,
            'items' => $items->map(fn ($i) => [
                'orderItemId' => (string) $i->order_item_id,
                'title' => Inventory::label($i->title, $i->variant_title),
                'image' => ShopCatalog::imageUrl($i->image),
                'quantity' => (int) $i->quantity,
                'unitPrice' => Js::round2($i->unit_price),
                'exchangeFor' => $i->exchange_title,
                'condition' => $i->condition,
            ])->all(),
            'value' => Js::round2(self::value($r)),
            'refundAmount' => $r->refund_amount === null ? null : Js::round2($r->refund_amount),
            'replacement' => $r->replacement_carrier ? ['carrier' => $r->replacement_carrier, 'trackingNumber' => $r->replacement_tracking] : null,
            'canCancel' => in_array($r->status, ['requested', 'approved'], true),
            'createdAt' => Js::iso($r->created_at),
            'approvedAt' => Js::iso($r->approved_at),
            'receivedAt' => Js::iso($r->received_at),
            'completedAt' => Js::iso($r->completed_at),
        ];
    }

    private function audit(object $r, string $what): void
    {
        try {
            ActivityLog::log('updated', 'Shop Sales', "{$r->return_number}: {$what}", ['return' => $r->id]);
        } catch (Throwable) {
            // The audit log never blocks the step.
        }
    }
}
