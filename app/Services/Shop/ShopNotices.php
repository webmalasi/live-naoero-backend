<?php

namespace App\Services\Shop;

use App\Services\Notifications\CustomerNotices;
use Illuminate\Support\Facades\DB;

/**
 * Turns the steps of a shop order (its timeline, ShopEvents) into notices
 * for the customer: placed, paid, cancelled, refunded, gift card sent,
 * every delivery step, and every step of a return. Steps only staff care
 * about (attempts, notes, checks with NAB) are not sent.
 */
class ShopNotices
{
    public function __construct(private readonly CustomerNotices $notices) {}

    /** @param  array<string, mixed>|null  $detail  the event's detail */
    public function forEvent(string $orderId, string $type, ?array $detail, float|int|string|null $amount): void
    {
        $o = DB::table('shop_orders')->where('id', $orderId)->first(['id', 'customer_id', 'order_number', 'currency', 'total', 'shipping_status', 'tracking_number', 'shipping_carrier', 'estimated_delivery']);
        if (! $o) {
            return;
        }
        $n = $o->order_number;
        $money = fn ($v) => "{$o->currency} ".number_format((float) $v, 2);
        $merch = $o->shipping_status !== null;
        $notice = match ($type) {
            'order_placed' => ['shop_order_placed', "Order {$n} placed", 'Pay '.$money($o->total).' within '.config('naoero.shop.payment_window_minutes', 30).' minutes to confirm it.', 'placed'],
            'payment_succeeded', 'payment_recovered' => ['shop_order_paid', "Payment received for {$n}",
                'Thank you! We received '.$money($o->total).'.'.($merch ? " We're preparing your parcel" : '').' Your invoice and receipt are in the order.', 'paid'],
            'payment_review' => ['shop_payment_review', "Payment under review ({$n})", "Your bank is reviewing the payment. We'll let you know once it is confirmed.", 'review'],
            'payment_after_close' => ['shop_payment_review', "Payment received after {$n} closed", 'Your payment arrived after the order had closed. Our team will contact you to refund or send it.', 'after_close'],
            'order_expired' => ['shop_order_expired', "Order {$n} expired", 'It was not paid in time, so it was cancelled. Nothing was charged.', 'expired'],
            'order_cancelled' => ['shop_order_cancelled', "Order {$n} cancelled",
                ! empty($detail['paid']) ? 'Your order is cancelled and '.$money($amount ?? 0).' is on its way back to your card (3 to 5 business days).' : 'Your order is cancelled. Nothing was charged.', 'cancelled'],
            'refund_succeeded' => $this->isCancellationRefund($detail) ? null : ['shop_order_refunded', "Refund for {$n}",
                $money($amount ?? 0).' is on its way back to your card. It usually shows in 3 to 5 business days.', 'refund:'.($detail['refund'] ?? '')],
            'gift_card_issued' => ['shop_gift_card_issued', "Your gift card from {$n} is ready", 'Find the code in My Orders & Gift Cards. We also emailed it to the recipient.', 'gift_card'],
            'shipped', 'delivered', 'shipment_update' => $this->delivery($o, $detail),
            'return_requested', 'return_approved', 'return_rejected', 'return_received', 'return_refunded', 'return_exchanged', 'return_cancelled' => $this->returnStep($o, $type, $detail, $money),
            default => null,
        };
        if (! $notice) {
            return;
        }
        [$noticeType, $title, $body, $key] = $notice;
        $data = array_filter(['returnId' => $detail['returnId'] ?? null, 'status' => $detail['status'] ?? null]);
        $this->notices->send($o->customer_id, $noticeType, $title, $body, $data, shopOrderId: $o->id, dedupeKey: "shop:{$o->id}:{$key}");
    }

    private function isCancellationRefund(?array $detail): bool
    {
        $reason = isset($detail['refund']) ? DB::table('shop_refunds')->where('id', $detail['refund'])->value('reason') : null;

        return is_string($reason) && str_starts_with($reason, 'Cancelled by the customer');
    }

    private function delivery(object $o, ?array $detail): ?array
    {
        $status = $detail['status'] ?? null;
        if (! $status) {
            return null;
        }
        $n = $o->order_number;
        $where = ! empty($detail['location']) ? " ({$detail['location']})" : '';
        [$title, $body] = match ($status) {
            'packed' => ["Order {$n} is packed", 'Your parcel is packed and waiting for the carrier.'],
            'shipped' => ["Order {$n} has shipped", 'Shipped with '.($detail['carrier'] ?? 'the carrier').(! empty($detail['tracking']) ? ", tracking {$detail['tracking']}" : '').'. Track it in the order.'],
            'in_transit' => ["Order {$n} is on its way", "Your parcel is in transit{$where}."],
            'out_for_delivery' => ["Order {$n} is out for delivery", 'Your parcel will arrive today.'],
            'delivered' => ["Order {$n} was delivered", 'Enjoy! You can review what you bought, or ask for a return within '.config('naoero.shop.return_window_days', 30).' days.'],
            'delivery_failed' => ["Delivery attempt for {$n}", "The carrier couldn't deliver your parcel{$where}. They will try again or leave a card."],
            'returned' => ["Order {$n} came back to us", 'The parcel was returned to us. We will contact you.'],
            default => [null, null],
        };

        return $title ? [$status === 'delivered' ? 'shop_order_delivered' : 'shop_shipment_update', $title, $body, 'delivery:'.($detail['shipmentEventId'] ?? $status)] : null;
    }

    private function returnStep(object $o, string $type, ?array $detail, callable $money): ?array
    {
        $id = $detail['returnId'] ?? null;
        $r = $id ? DB::table('shop_return_requests')->where('id', $id)->first() : null;
        if (! $r) {
            return null;
        }
        $kind = $r->type === 'exchange' ? 'exchange' : 'return';
        [$title, $body] = match ($type) {
            'return_requested' => ["We received your {$kind} request {$r->return_number}", "We'll check it and let you know how to send the items back."],
            'return_approved' => ["Your {$kind} {$r->return_number} is approved", $r->instructions ?: 'Send the items back to us.'],
            'return_rejected' => ["Your {$kind} {$r->return_number} wasn't accepted", $r->staff_note ?: 'Contact us if you have questions.'],
            'return_received' => ["We received your {$kind} {$r->return_number}", $kind === 'exchange' ? "We'll send your replacement soon." : "We'll refund you soon."],
            'return_refunded' => ["Refund for return {$r->return_number}", $money($r->refund_amount ?? 0).' is on its way back to your card (3 to 5 business days).'],
            'return_exchanged' => ["Your replacement is on its way ({$r->return_number})", 'Sent with '.($r->replacement_carrier ?? 'the carrier').($r->replacement_tracking ? ", tracking {$r->replacement_tracking}" : '').'.'],
            'return_cancelled' => ["Return {$r->return_number} cancelled", 'You cancelled the request. Nothing else is needed.'],
        };

        return ['shop_return_update', $title, $body, "return:{$r->id}:{$type}"];
    }
}
