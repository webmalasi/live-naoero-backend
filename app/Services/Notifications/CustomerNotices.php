<?php

namespace App\Services\Notifications;

use App\Jobs\SendPushNotification;
use App\Services\Logging\ServerLog;
use App\Services\Support\Js;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * The customer's inbox (customer_notifications), the same in the app and
 * on the website: flight changes from the airline, every step of their
 * bookings, and every step of their shop orders. Each notice has a
 * category to group by and an action saying what it opens.
 *
 * Sending never breaks the step it is about, and a step sent twice (a
 * retried request, a page reloaded) notifies once, by its dedupe key.
 * Each new notice is also pushed to the customer's phones.
 */
class CustomerNotices
{
    public const CATEGORIES = [
        'flights' => 'Flight updates',
        'bookings' => 'Bookings',
        'shop' => 'Shop orders',
        'account' => 'Account',
    ];

    /** Every notice type: its category and what the app opens for it. */
    public const TYPES = [
        // From the airline (Zenith flight events).
        'flight_time_change' => ['flights', 'booking'],
        'flight_cancelled' => ['flights', 'booking'],
        'flight_transfer' => ['flights', 'booking'],
        'flight_reminder' => ['flights', 'booking'],
        // Bookings made and managed by the customer.
        'booking_created' => ['bookings', 'booking'],
        'booking_confirmed' => ['bookings', 'booking'],
        'booking_payment_failed' => ['bookings', 'booking'],
        'booking_expired' => ['bookings', 'booking'],
        'booking_cancelled' => ['bookings', 'booking'],
        'booking_refunded' => ['bookings', 'booking'],
        'flights_cancelled' => ['bookings', 'booking'],
        'flight_change_held' => ['bookings', 'booking'],
        'flight_change_cancelled' => ['bookings', 'booking'],
        'flight_change_confirmed' => ['bookings', 'booking'],
        'trip_added' => ['bookings', 'booking'],
        'extras_added' => ['bookings', 'booking'],
        'passengers_updated' => ['bookings', 'booking'],
        'checked_in' => ['bookings', 'booking'],
        'checkin_undone' => ['bookings', 'booking'],
        'refund_request_received' => ['bookings', 'booking'],
        // Shop orders.
        'shop_order_placed' => ['shop', 'shop_order'],
        'shop_order_paid' => ['shop', 'shop_order'],
        'shop_payment_review' => ['shop', 'shop_order'],
        'shop_order_cancelled' => ['shop', 'shop_order'],
        'shop_order_expired' => ['shop', 'shop_order'],
        'shop_order_refunded' => ['shop', 'shop_order'],
        'shop_gift_card_issued' => ['shop', 'shop_order'],
        'shop_shipment_update' => ['shop', 'shop_order'],
        'shop_order_delivered' => ['shop', 'shop_order'],
        'shop_return_update' => ['shop', 'shop_return'],
        'shop_support_reply' => ['shop', 'support_request'],
        'shop_review_reply' => ['shop', 'product'],
        'back_in_stock' => ['shop', 'product'],
        // The account.
        'account_deletion_requested' => ['account', null],
        'account_deletion_declined' => ['account', null],
    ];

    public function __construct(private readonly ServerLog $log) {}

    public static function category(string $type): string
    {
        return self::TYPES[$type][0] ?? 'account';
    }

    /**
     * @param  array<string, mixed>  $data  what the notice is about (productId, returnId...); stored with it
     * @return bool whether a new notice was saved
     */
    public function send(
        string $customerId,
        string $type,
        string $title,
        string $body,
        array $data = [],
        ?string $pnr = null,
        ?string $shopOrderId = null,
        ?string $dedupeKey = null,
    ): bool {
        try {
            $bookingId = $pnr ? DB::table('bookings')->where('customer_id', $customerId)->where('pnr', strtoupper($pnr))->value('id') : null;
            $id = (string) Str::uuid();

            $saved = DB::transaction(fn () => DB::table('customer_notifications')->insertOrIgnore([
                'id' => $id,
                'customer_id' => $customerId,
                'booking_id' => $bookingId,
                'shop_order_id' => $shopOrderId,
                'type' => mb_substr($type, 0, 40),
                'category' => self::category($type),
                'pnr' => $pnr ? strtoupper($pnr) : null,
                'title' => mb_substr($title, 0, 200),
                'body' => mb_substr($body, 0, 2000),
                'data' => Js::encode($data ?: (object) []),
                'dedupe_key' => $dedupeKey === null ? null : mb_substr($dedupeKey, 0, 120),
                'created_at' => Js::dbTime(),
            ])) > 0;
        } catch (Throwable $e) {
            $this->log->warn('notifications', 'notice not saved', ['type' => $type, 'error' => $e->getMessage()]);

            return false;
        }
        if ($saved) {
            $this->push($id);
        }

        return $saved;
    }

    /** The phones get it too (SendPushNotification), once the notice is committed. */
    private function push(string $notificationId): void
    {
        try {
            SendPushNotification::dispatch($notificationId)->afterCommit();
        } catch (Throwable $e) {
            $this->log->warn('push', 'push not queued', ['notification' => $notificationId, 'error' => $e->getMessage()]);
        }
    }

    /**
     * What tapping the notice opens: {screen: booking, pnr} /
     * {screen: shop_order, orderId} / {screen: product, productId, variantId} /
     * {screen: shop_return, orderId, returnId} / {screen: support_request, requestId}.
     *
     * @return array<string, string>|null
     */
    public static function action(object $row, array $data): ?array
    {
        $screen = self::TYPES[$row->type][1] ?? null;

        return match ($screen) {
            'booking' => $row->pnr ? ['screen' => 'booking', 'pnr' => $row->pnr] : null,
            'shop_order' => ($row->shop_order_id ?? null) ? ['screen' => 'shop_order', 'orderId' => $row->shop_order_id] : null,
            'shop_return' => ($row->shop_order_id ?? null) ? array_filter(['screen' => 'shop_return', 'orderId' => $row->shop_order_id, 'returnId' => $data['returnId'] ?? null]) : null,
            'support_request' => isset($data['requestId']) ? array_filter(['screen' => 'support_request', 'requestId' => $data['requestId'], 'orderId' => $row->shop_order_id ?? null]) : null,
            'product' => isset($data['productId']) ? array_filter(['screen' => 'product', 'productId' => (string) $data['productId'], 'variantId' => isset($data['variantId']) ? (string) $data['variantId'] : null]) : null,
            default => null,
        };
    }
}
