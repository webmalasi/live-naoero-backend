<?php

namespace App\Services\Shop;

use App\Exceptions\ApiException;
use App\Models\ActivityLog;
use App\Services\Payments\NabException;
use App\Services\Payments\NabGateway;
use App\Services\Support\Js;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * What staff do with shop orders from the admin panel: refunds through NAB,
 * shipping, notes, cancelling unpaid orders, gift card resends and
 * cancellations, and checking an order with NAB. Every action is on the
 * order's timeline and in the admin activity log.
 */
class ShopOperations
{
    public function __construct(
        private readonly NabGateway $nab,
        private readonly ShopCheckout $checkout,
        private readonly GiftCardIssuer $giftCards,
        private readonly ShopEvents $events,
    ) {}

    private function audit(string $action, object $order, string $description, array $properties = []): void
    {
        try {
            ActivityLog::log($action, 'Shop Sales', "{$order->order_number}: {$description}", ['order' => $order->id, ...$properties]);
        } catch (Throwable) {
            // The admin audit log must never block the action.
        }
    }

    private static function order(string $id, bool $lock = false): object
    {
        return DB::table('shop_orders')->where('id', $id)->when($lock, fn ($q) => $q->lockForUpdate())->first()
            ?? throw new RuntimeException('Order not found.');
    }

    /** What can still be refunded on the order. */
    public static function refundable(object $order): float
    {
        return round(max(0, (float) $order->total - (float) $order->refunded_amount), 2);
    }

    // ------------------------------------------------------------ refunds

    /**
     * Refunds $amount of a paid order through NAB. A full refund cancels the
     * order's unused gift cards; a partial one can, on request.
     *
     * @return object the shop_refunds row
     */
    /**
     * @param  array<int, int>  $restock  order item id => units to put back in stock once refunded
     */
    public function refund(string $orderId, float $amount, string $reason, bool $cancelGiftCards, ?int $staffUserId, array $restock = []): object
    {
        return DB::transaction(function () use ($orderId, $amount, $reason, $cancelGiftCards, $staffUserId, $restock) {
            // Locked for the whole refund: two clicks never refund twice.
            $order = self::order($orderId, lock: true);
            if (! in_array($order->status, ['paid', 'partially_refunded', 'payment_review'], true) || ! $order->nab_transaction_id) {
                throw new RuntimeException('Only paid orders can be refunded.');
            }
            $amount = round($amount, 2);
            $refundable = self::refundable($order);
            if ($amount <= 0 || $amount > $refundable) {
                throw new RuntimeException(sprintf('The refund must be between %s 0.01 and %s %.2f.', $order->currency, $order->currency, $refundable));
            }
            $full = abs($amount - $refundable) < 0.005 && (float) $order->refunded_amount === 0.0;

            $id = (string) Str::uuid();
            DB::table('shop_refunds')->insert([
                'id' => $id, 'shop_order_id' => $order->id, 'order_number' => $order->order_number, 'amount' => number_format($amount, 2, '.', ''), 'currency' => $order->currency,
                'reason' => $reason, 'status' => 'pending', 'staff_user_id' => $staffUserId,
                'created_at' => Js::dbTime(), 'updated_at' => Js::dbTime(),
            ]);
            // Without a member of staff, the customer asked (cancelling their order).
            $source = $staffUserId ? 'staff' : 'customer';
            $this->events->record($order->id, 'refund_requested', 'pending', "Refund of {$order->currency} ".number_format($amount, 2)." requested: {$reason}", $source, $amount, ['refund' => $id], $staffUserId);

            try {
                $result = $this->nab->refund($order->nab_transaction_id, $order->order_number, $amount, $order->currency);
            } catch (NabException $e) {
                $result = ['ok' => false, 'id' => null, 'status' => null, 'reason' => $e->getMessage(), 'body' => null];
            }

            if (! $result['ok']) {
                DB::table('shop_refunds')->where('id', $id)->update([
                    'status' => 'failed', 'nab_status' => $result['status'], 'failure_reason' => mb_substr((string) $result['reason'], 0, 500),
                    'detail' => Js::encode(['response' => $result['body']]), 'updated_at' => Js::dbTime(),
                ]);
                $this->events->record($order->id, 'refund_failed', 'failed', "NAB refused the refund: {$result['reason']}", 'nab', $amount, ['refund' => $id], $staffUserId);
                $this->audit('refund_failed', $order, "refund of {$order->currency} {$amount} failed: {$result['reason']}", ['refund' => $id]);

                return DB::table('shop_refunds')->where('id', $id)->first();
            }

            $refunded = round((float) $order->refunded_amount + $amount, 2);
            $fullyRefunded = $refunded >= (float) $order->total - 0.005;
            DB::table('shop_orders')->where('id', $order->id)->update([
                'refunded_amount' => number_format($refunded, 2, '.', ''),
                'status' => $fullyRefunded ? 'refunded' : 'partially_refunded',
                'refunded_at' => Js::dbTime(),
                'updated_at' => Js::dbTime(),
            ]);
            $cancelled = ($full || $cancelGiftCards) ? $this->cancelUnusedGiftCards($order, "refund {$id}", $staffUserId) : 0;
            if (array_filter($restock)) {
                // The money is already back with the customer: a stock problem never undoes that.
                try {
                    app(Inventory::class)->restock($order, $restock, $staffUserId, "Refund {$order->currency} ".number_format($amount, 2).": {$reason}");
                } catch (RuntimeException $e) {
                    $this->events->record($order->id, 'stock_returned', 'warning', "Refunded, but not put back in stock: {$e->getMessage()}", 'staff', staffUserId: $staffUserId);
                }
            }
            DB::table('shop_refunds')->where('id', $id)->update([
                'status' => 'succeeded', 'nab_refund_id' => $result['id'], 'nab_status' => $result['status'], 'gift_cards_cancelled' => $cancelled,
                'detail' => Js::encode(['response' => $result['body']]), 'updated_at' => Js::dbTime(),
            ]);
            $this->events->record($order->id, 'refund_succeeded', 'refunded',
                "Refunded {$order->currency} ".number_format($amount, 2)." to the customer's card (NAB {$result['status']}, ref {$result['id']})".($cancelled ? ", {$cancelled} gift card(s) cancelled" : ''),
                'nab', $amount, ['refund' => $id, 'nabRefundId' => $result['id']], $staffUserId);
            $this->audit('refund', $order, "refunded {$order->currency} {$amount}", ['refund' => $id, 'nab' => $result['id']]);

            return DB::table('shop_refunds')->where('id', $id)->first();
        });
    }

    /** Cancels the order's gift cards that were never used. Must run in a transaction. */
    private function cancelUnusedGiftCards(object $order, string $why, ?int $staffUserId): int
    {
        $count = 0;
        $cards = DB::table('gift_card_orders')->where('shop_order_id', $order->id)->whereIn('status', ['issued', 'confirmed'])->get();
        foreach ($cards as $card) {
            if ((float) $card->balance_remaining < (float) $card->amount) {
                $this->events->record($order->id, 'gift_card_cancelled', 'warning', "Gift card {$card->voucher_code} was partly used and stays active ({$order->currency} {$card->balance_remaining} left)", 'staff', staffUserId: $staffUserId);

                continue;
            }
            DB::table('gift_card_orders')->where('id', $card->id)->update(['status' => 'cancelled', 'balance_remaining' => 0, 'updated_at' => now()]);
            $this->events->record($order->id, 'gift_card_cancelled', 'cancelled', "Gift card {$card->voucher_code} cancelled ({$why})", 'staff', $card->amount, ['giftCardId' => $card->id], $staffUserId);
            $count++;
        }

        return $count;
    }

    public function cancelGiftCard(int $cardId, string $reason, int $staffUserId): void
    {
        DB::transaction(function () use ($cardId, $reason, $staffUserId) {
            $card = DB::table('gift_card_orders')->where('id', $cardId)->lockForUpdate()->first() ?? throw new RuntimeException('Gift card not found.');
            if (! in_array($card->status, ['issued', 'confirmed'], true)) {
                throw new RuntimeException('This gift card is not active.');
            }
            DB::table('gift_card_orders')->where('id', $cardId)->update(['status' => 'cancelled', 'balance_remaining' => 0, 'updated_at' => now()]);
            if ($card->shop_order_id) {
                $order = self::order($card->shop_order_id);
                $this->events->record($order->id, 'gift_card_cancelled', 'cancelled', "Gift card {$card->voucher_code} cancelled: {$reason}", 'staff', $card->amount, ['giftCardId' => $cardId], $staffUserId);
                $this->audit('gift_card_cancelled', $order, "cancelled gift card {$card->voucher_code}: {$reason}");
            }
        });
    }

    public function resendGiftCard(int $cardId, int $staffUserId): bool
    {
        $card = DB::table('gift_card_orders')->where('id', $cardId)->first() ?? throw new RuntimeException('Gift card not found.');
        if (! in_array($card->status, ['issued', 'confirmed'], true)) {
            throw new RuntimeException('Only active gift cards can be resent.');
        }
        $sent = $this->giftCards->notify($cardId, $staffUserId);
        if ($card->shop_order_id) {
            $this->audit('gift_card_resent', self::order($card->shop_order_id), "resent gift card {$card->voucher_code}");
        }

        return $sent;
    }

    // ------------------------------------------------------------ fulfilment

    public function markShipped(string $orderId, string $carrier, ?string $tracking, ?string $trackingUrl, int $staffUserId): void
    {
        app(ShipmentTracking::class)->update($orderId, 'shipped', ['carrier' => $carrier, 'tracking_number' => $tracking, 'tracking_url' => $trackingUrl], $staffUserId);
    }

    public function markDelivered(string $orderId, int $staffUserId): void
    {
        app(ShipmentTracking::class)->update($orderId, 'delivered', [], $staffUserId);
    }

    // ------------------------------------------------------------ cancelling a paid order

    /**
     * Whether the customer can cancel the order now, and what they'd get
     * back. Unpaid orders are simply cancelled; a paid one is refunded in
     * full, as long as nothing has shipped and no gift card was used.
     *
     * @return array{allowed: bool, kind: ?string, refundAmount: ?float, reason: ?string}
     */
    public static function cancellation(object $order): array
    {
        $no = fn (string $why) => ['allowed' => false, 'kind' => null, 'refundAmount' => null, 'reason' => $why];
        if ($order->status === 'pending_payment') {
            return ['allowed' => true, 'kind' => 'unpaid', 'refundAmount' => 0.0, 'reason' => null];
        }
        if ($order->status !== 'paid') {
            return $no(match ($order->status) {
                'payment_review' => 'Your payment is being reviewed. Contact us if you want to cancel.',
                'partially_refunded' => 'Part of this order was refunded already. Contact us to change it.',
                default => 'This order is already closed.',
            });
        }
        if (! in_array($order->shipping_status, [null, 'processing', 'packed'], true)) {
            return $no('Your parcel has already shipped. You can ask for a return once it arrives.');
        }
        $used = DB::table('gift_card_orders')->where('shop_order_id', $order->id)
            ->where(fn ($q) => $q->whereNotIn('status', ['issued', 'confirmed'])->orWhereColumn('balance_remaining', '<', 'amount'))->exists();
        if ($used) {
            return $no('A gift card from this order has been used, so it can\'t be cancelled online. Contact us for help.');
        }

        return ['allowed' => true, 'kind' => 'refund', 'refundAmount' => self::refundable($order), 'reason' => null];
    }

    /**
     * The customer cancels a paid order before it ships: the card is refunded
     * in full through NAB, gift cards are cancelled and the merchandise goes
     * back in stock. If NAB refuses, nothing changes and our team is asked
     * to finish it (a help request is opened for the customer).
     */
    public function cancelPaid(object $customer, string $orderId, ?string $reason): object
    {
        $order = self::order($orderId);
        $check = self::cancellation($order);
        if (! $check['allowed'] || $check['kind'] !== 'refund') {
            throw new ApiException(409, 'order_not_cancellable', $check['reason'] ?? 'This order can\'t be cancelled.');
        }
        $why = 'Cancelled by the customer'.($reason ? ": {$reason}" : '');
        $restock = DB::table('shop_order_items')->where('shop_order_id', $order->id)->where('type', 'merch')->where('stock_state', 'sold')
            ->get()->mapWithKeys(fn ($i) => [$i->id => (int) $i->quantity - (int) $i->returned_quantity])->filter()->all();
        $refund = $this->refund($order->id, (float) $check['refundAmount'], $why, true, null, $restock);
        if ($refund->status !== 'succeeded') {
            try {
                app(ShopSupport::class)->create($customer, $order->id, 'cancellation',
                    "Please cancel order {$order->order_number}. The automatic refund failed ({$refund->failure_reason}).", 'app', 'system');
            } catch (Throwable) {
                // The customer is told either way.
            }
            throw new ApiException(502, 'refund_failed', "We couldn't refund your card automatically. Our team has been asked to cancel the order and will contact you.");
        }
        DB::table('shop_orders')->where('id', $order->id)->update([
            'shipping_status' => $order->shipping_status === null ? null : 'cancelled',
            'cancelled_at' => Js::dbTime(), 'cancel_reason' => $why, 'cancelled_by' => 'customer', 'updated_at' => Js::dbTime(),
        ]);
        $this->events->record($order->id, 'order_cancelled', 'cancelled', $why.' (refunded in full)', 'customer', $refund->amount, ['refund' => $refund->id, 'paid' => true]);
        $this->audit('cancelled', $order, 'cancelled by the customer, refunded in full');

        return self::order($order->id);
    }

    // ------------------------------------------------------------ other

    public function addNote(string $orderId, string $note, int $staffUserId): void
    {
        $order = self::order($orderId);
        $this->events->record($orderId, 'note_added', 'info', $note, 'staff', staffUserId: $staffUserId);
        $this->audit('note', $order, 'added a note');
    }

    /** Cancels an unpaid order, after making sure NAB didn't take a payment for it. */
    public function cancelUnpaid(string $orderId, string $reason, int $staffUserId): void
    {
        $this->checkout->reconcile(self::order($orderId), force: true);
        DB::transaction(function () use ($orderId, $reason, $staffUserId) {
            $order = self::order($orderId, lock: true);
            if ($order->status !== 'pending_payment') {
                throw new RuntimeException('Only unpaid orders can be cancelled; this one is '.str_replace('_', ' ', $order->status).'.');
            }
            ShopOrders::cancel($order, "cancelled by staff: {$reason}", 'staff', $staffUserId);
            $this->audit('cancelled', $order, "cancelled: {$reason}");
        });
    }

    /** Asks NAB whether a payment for the order went through. */
    public function reconcileNow(string $orderId, int $staffUserId): string
    {
        $before = self::order($orderId);
        $this->checkout->reconcile($before, force: true);
        $after = self::order($orderId);
        $message = $before->status === $after->status
            ? 'Checked with NAB: no change ('.str_replace('_', ' ', $after->status).')'
            : 'Checked with NAB: now '.str_replace('_', ' ', $after->status);
        $this->events->record($orderId, 'reconciled', 'info', $message, 'staff', staffUserId: $staffUserId);

        return $message;
    }
}
