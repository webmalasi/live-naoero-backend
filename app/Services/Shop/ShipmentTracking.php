<?php

namespace App\Services\Shop;

use App\Models\ActivityLog;
use App\Services\Support\Js;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Where a merchandise order's parcel is: every delivery step staff record
 * (packed, shipped, in transit, out for delivery, delivered, a failed
 * attempt), with where and when, shown to the customer as a tracking
 * timeline and written to the order's timeline.
 */
class ShipmentTracking
{
    public const STATUSES = [
        'processing' => 'Preparing your order',
        'packed' => 'Packed',
        'shipped' => 'Shipped',
        'in_transit' => 'In transit',
        'out_for_delivery' => 'Out for delivery',
        'delivered' => 'Delivered',
        'delivery_failed' => 'Delivery attempt failed',
        'cancelled' => 'Cancelled',
        'returned' => 'Returned to us',
    ];

    /** The usual journey, in order; the customer sees these as steps. */
    public const FLOW = ['processing', 'packed', 'shipped', 'in_transit', 'out_for_delivery', 'delivered'];

    /** What staff can record next, from each status. */
    private const NEXT = [
        'processing' => ['packed', 'shipped', 'in_transit', 'out_for_delivery', 'delivered'],
        'packed' => ['shipped', 'in_transit', 'out_for_delivery', 'delivered'],
        'shipped' => ['in_transit', 'out_for_delivery', 'delivered', 'delivery_failed', 'returned'],
        'in_transit' => ['in_transit', 'out_for_delivery', 'delivered', 'delivery_failed', 'returned'],
        'out_for_delivery' => ['out_for_delivery', 'delivered', 'delivery_failed', 'returned'],
        'delivery_failed' => ['in_transit', 'out_for_delivery', 'delivered', 'returned'],
        'delivered' => [],
        'cancelled' => [],
        'returned' => [],
    ];

    public function __construct(private readonly ShopEvents $events) {}

    /** @return list<string> */
    public static function nextStatuses(?string $status): array
    {
        return $status === null ? [] : self::NEXT[$status] ?? [];
    }

    /** A paid order with merchandise starts its journey. Runs inside the payment's transaction. */
    public function start(object $order): void
    {
        if (DB::table('shop_shipment_events')->where('shop_order_id', $order->id)->exists()) {
            return;
        }
        $days = (int) config('naoero.shop.delivery_days', 10);
        DB::table('shop_orders')->where('id', $order->id)->update(['estimated_delivery' => now()->addDays($days)->toDateString()]);
        DB::table('shop_shipment_events')->insert([
            'shop_order_id' => $order->id, 'status' => 'processing', 'message' => "Order received: we're preparing your parcel.",
            'source' => 'system', 'occurred_at' => Js::dbTime(), 'created_at' => Js::dbTime(),
        ]);
    }

    /**
     * Records a delivery step.
     *
     * @param  array{message?: ?string, location?: ?string, carrier?: ?string, tracking_number?: ?string, tracking_url?: ?string, estimated_delivery?: ?string, occurred_at?: ?string}  $data
     */
    public function update(string $orderId, string $status, array $data, ?int $staffUserId): object
    {
        if (! isset(self::STATUSES[$status]) || in_array($status, ['processing', 'cancelled'], true)) {
            throw new RuntimeException('Choose a delivery step.');
        }

        return DB::transaction(function () use ($orderId, $status, $data, $staffUserId) {
            $order = DB::table('shop_orders')->where('id', $orderId)->lockForUpdate()->first() ?? throw new RuntimeException('Order not found.');
            if (! in_array($order->status, ['paid', 'partially_refunded'], true) || $order->shipping_status === null) {
                throw new RuntimeException('Only paid orders with merchandise can be shipped.');
            }
            if (! in_array($status, self::nextStatuses($order->shipping_status), true)) {
                throw new RuntimeException('This order is '.mb_strtolower(self::STATUSES[$order->shipping_status]).': it can\'t be marked '.mb_strtolower(self::STATUSES[$status]).'.');
            }
            $carrier = ($data['carrier'] ?? null) ?: $order->shipping_carrier;
            if (in_array($status, ['shipped', 'in_transit', 'out_for_delivery'], true) && ! $carrier) {
                throw new RuntimeException('Enter the carrier.');
            }
            $at = isset($data['occurred_at']) && $data['occurred_at'] ? CarbonImmutable::parse($data['occurred_at']) : CarbonImmutable::now();
            if ($at->isFuture()) {
                $at = CarbonImmutable::now();
            }
            $fields = ['shipping_status' => $status, 'updated_at' => Js::dbTime()];
            if ($carrier) {
                $fields['shipping_carrier'] = $carrier;
            }
            foreach (['tracking_number', 'tracking_url'] as $k) {
                if (! empty($data[$k])) {
                    $fields[$k] = $data[$k];
                }
            }
            if (! empty($data['estimated_delivery'])) {
                $fields['estimated_delivery'] = CarbonImmutable::parse($data['estimated_delivery'])->toDateString();
            }
            if (in_array($status, ['shipped', 'in_transit', 'out_for_delivery', 'delivered'], true) && ! $order->shipped_at) {
                $fields['shipped_at'] = Js::dbTime($at);
            }
            if ($status === 'delivered') {
                $fields['delivered_at'] = Js::dbTime($at);
            }
            DB::table('shop_orders')->where('id', $orderId)->update($fields);

            $tracking = $fields['tracking_number'] ?? $order->tracking_number;
            $message = trim((string) ($data['message'] ?? '')) ?: match ($status) {
                'packed' => 'Your parcel is packed and waiting for the carrier.',
                'shipped' => "Shipped with {$carrier}".($tracking ? ", tracking {$tracking}" : '').'.',
                'in_transit' => 'Your parcel is on its way.',
                'out_for_delivery' => 'Your parcel is out for delivery today.',
                'delivered' => 'Delivered. Enjoy!',
                'delivery_failed' => 'The carrier could not deliver your parcel. They will try again or leave a card.',
                'returned' => 'The parcel came back to us. We will contact you.',
            };
            $eventId = DB::table('shop_shipment_events')->insertGetId([
                'shop_order_id' => $orderId, 'status' => $status, 'message' => mb_substr($message, 0, 500),
                'location' => $data['location'] ?? null, 'carrier' => $carrier, 'tracking_number' => $tracking,
                'source' => $staffUserId ? 'staff' : 'system', 'staff_user_id' => $staffUserId,
                'occurred_at' => Js::dbTime($at), 'created_at' => Js::dbTime(),
            ]);
            $type = match ($status) {
                'shipped' => 'shipped',
                'delivered' => 'delivered',
                default => 'shipment_update',
            };
            $outcome = match ($status) {
                'delivery_failed', 'returned' => 'warning',
                'shipped', 'delivered' => 'success',
                default => 'info',
            };
            $this->events->record($orderId, $type, $outcome, self::STATUSES[$status].': '.$message.(! empty($data['location']) ? " ({$data['location']})" : ''),
                $staffUserId ? 'staff' : 'system', detail: ['status' => $status, 'shipmentEventId' => $eventId, 'carrier' => $carrier, 'tracking' => $tracking, 'url' => $fields['tracking_url'] ?? $order->tracking_url, 'location' => $data['location'] ?? null], staffUserId: $staffUserId);
            try {
                ActivityLog::log($type, 'Shop Sales', "{$order->order_number}: delivery ".self::STATUSES[$status], ['order' => $orderId]);
            } catch (Throwable) {
                // The audit log never blocks the step.
            }

            return DB::table('shop_orders')->find($orderId);
        });
    }

    /**
     * The order's delivery as the customer follows it (null without merchandise).
     *
     * @return array<string, mixed>|null
     */
    public static function present(object $o, bool $withEvents = true): ?array
    {
        $status = $o->shipping_status ?? null;
        if ($status === null) {
            return null;
        }
        $events = $withEvents ? DB::table('shop_shipment_events')->where('shop_order_id', $o->id)->orderBy('occurred_at')->orderBy('id')->get() : collect();
        $reached = $events->groupBy('status')->map(fn ($e) => $e->first()->occurred_at);
        $position = array_search(in_array($status, ['delivery_failed', 'returned'], true) ? 'out_for_delivery' : $status, self::FLOW, true);

        return [
            'status' => $status,
            'label' => self::STATUSES[$status] ?? $status,
            'carrier' => $o->shipping_carrier ?? null,
            'trackingNumber' => $o->tracking_number ?? null,
            'trackingUrl' => $o->tracking_url ?? null,
            'estimatedDelivery' => in_array($status, ['delivered', 'cancelled', 'returned'], true) ? null : ($o->estimated_delivery ?? null),
            'shippedAt' => Js::iso($o->shipped_at ?? null),
            'deliveredAt' => Js::iso($o->delivered_at ?? null),
            // The usual steps, ticked as reached.
            'steps' => $status === 'cancelled' ? [] : array_map(fn ($s, $i) => [
                'status' => $s,
                'label' => self::STATUSES[$s],
                'done' => $position !== false && $i <= $position && ! ($s === 'delivered' && $status !== 'delivered'),
                'current' => $s === $status,
                'at' => Js::iso($reached[$s] ?? null),
            ], self::FLOW, array_keys(self::FLOW)),
            // Everything that happened, newest first.
            'events' => $events->reverse()->values()->map(fn ($e) => [
                'status' => $e->status,
                'label' => self::STATUSES[$e->status] ?? $e->status,
                'message' => $e->message,
                'location' => $e->location,
                'at' => Js::iso($e->occurred_at),
            ])->all(),
        ];
    }
}
