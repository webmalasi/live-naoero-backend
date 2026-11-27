<?php

namespace App\Services\Shop;

use App\Services\Logging\ServerLog;
use App\Services\Support\Js;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * The timeline of every shop order (shop_order_events): each step, who
 * took it, and its outcome tag. Staff read it in the admin panel; it is
 * never edited. Recording never breaks the step being recorded.
 */
class ShopEvents
{
    /** Outcome tags, as the admin panel shows them. */
    public const OUTCOMES = [
        'success' => 'Success',
        'declined' => 'Declined',
        'failed' => 'Failed',
        'review' => 'Under review',
        'pending' => 'Pending',
        'cancelled' => 'Cancelled',
        'refunded' => 'Refunded',
        'info' => 'Info',
        'warning' => 'Warning',
    ];

    /** Every event type, with a label for the admin panel. */
    public const TYPES = [
        'order_placed' => 'Order placed',
        'stock_reserved' => 'Stock reserved',
        'stock_released' => 'Stock released',
        'stock_sold' => 'Stock sold',
        'stock_returned' => 'Put back in stock',
        'payment_started' => 'Payment started',
        'payment_page_opened' => 'Payment page opened',
        'payment_pending' => 'Payment awaiting confirmation',
        'payment_succeeded' => 'Payment succeeded',
        'payment_recovered' => 'Payment recovered from NAB',
        'payment_declined' => 'Payment declined',
        'payment_review' => 'Payment held for review',
        'payment_failed' => 'Payment failed',
        'payment_after_close' => 'Paid after the order closed',
        'gift_card_issued' => 'Gift card issued',
        'gift_card_emailed' => 'Gift card email sent',
        'gift_card_cancelled' => 'Gift card cancelled',
        'order_cancelled' => 'Order cancelled',
        'order_expired' => 'Order expired',
        'refund_requested' => 'Refund requested',
        'refund_succeeded' => 'Refund succeeded',
        'refund_failed' => 'Refund failed',
        'shipped' => 'Shipped',
        'shipment_update' => 'Delivery update',
        'delivered' => 'Delivered',
        'support_requested' => 'Help requested',
        'support_replied' => 'Help request answered',
        'return_requested' => 'Return requested',
        'return_approved' => 'Return approved',
        'return_rejected' => 'Return not accepted',
        'return_received' => 'Return received',
        'return_refunded' => 'Return refunded',
        'return_exchanged' => 'Replacement sent',
        'return_cancelled' => 'Return cancelled',
        'note_added' => 'Note added',
        'reconciled' => 'Checked with NAB',
    ];

    public function __construct(private readonly ServerLog $log) {}

    /**
     * @param  'customer'|'system'|'nab'|'staff'  $source
     */
    public function record(
        string $orderId,
        string $type,
        string $outcome,
        string $message,
        string $source = 'system',
        float|int|string|null $amount = null,
        ?array $detail = null,
        ?int $staffUserId = null,
    ): void {
        $this->log->log(
            in_array($outcome, ['failed', 'warning'], true) ? 'warn' : 'info',
            'shop',
            "{$type}: {$message}",
            detail: ['order' => $orderId, 'outcome' => $outcome, 'source' => $source, 'amount' => $amount],
        );
        try {
            // Its own savepoint: a failed insert must not undo the step itself.
            DB::transaction(fn () => DB::table('shop_order_events')->insert([
                'shop_order_id' => $orderId,
                'order_number' => DB::table('shop_orders')->where('id', $orderId)->value('order_number'),
                'type' => $type,
                'outcome' => $outcome,
                'source' => $source,
                'staff_user_id' => $staffUserId,
                'message' => mb_substr($message, 0, 1000),
                'amount' => $amount === null ? null : number_format((float) $amount, 2, '.', ''),
                'detail' => $detail === null ? null : Js::encode(ServerLog::redact($detail)),
                'created_at' => Js::dbTime(),
            ]));
        } catch (Throwable $e) {
            $this->log->error('shop', "could not record event {$type}", ['order' => $orderId, 'error' => $e->getMessage()]);
        }
        // The steps the customer cares about are notices in their inbox.
        try {
            // Its own savepoint too: a failure must not undo the step.
            DB::transaction(fn () => app(ShopNotices::class)->forEvent($orderId, $type, $detail, $amount));
        } catch (Throwable $e) {
            $this->log->warn('shop', "could not notify the customer of {$type}", ['order' => $orderId, 'error' => $e->getMessage()]);
        }
    }

    /** The order's timeline, oldest first. */
    public static function timeline(string $orderId): array
    {
        return DB::table('shop_order_events as e')
            ->leftJoin('users as u', 'u.id', '=', 'e.staff_user_id')
            ->where('e.shop_order_id', $orderId)
            ->orderBy('e.created_at')->orderBy('e.id')
            ->get(['e.*', 'u.name as staff_name'])
            ->map(fn ($e) => [
                'type' => $e->type,
                'label' => self::TYPES[$e->type] ?? ucfirst(str_replace('_', ' ', $e->type)),
                'outcome' => $e->outcome,
                'source' => $e->source,
                'staff' => $e->staff_name,
                'message' => $e->message,
                'amount' => $e->amount === null ? null : (float) $e->amount,
                'detail' => $e->detail ? json_decode($e->detail, true) : null,
                'at' => $e->created_at,
            ])->all();
    }
}
