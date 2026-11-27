<?php

namespace App\Services\Shop;

use App\Models\ActivityLog;
use App\Services\Support\Js;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Buying stock: purchase orders to suppliers, and receiving what arrives
 * (all at once or in parts) into stock at its cost.
 *
 * draft -> ordered -> partially_received -> received; a draft or an order
 * with nothing received yet can be cancelled.
 */
class Purchasing
{
    public const STATUSES = [
        'draft' => 'Draft',
        'ordered' => 'Ordered',
        'partially_received' => 'Partly received',
        'received' => 'Received',
        'cancelled' => 'Cancelled',
    ];

    public function __construct(private readonly Inventory $inventory) {}

    private static function number(): string
    {
        do {
            $number = 'PO-'.now()->utc()->format('ymd').'-'.strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
        } while (DB::table('shop_purchase_orders')->where('po_number', $number)->exists());

        return $number;
    }

    /**
     * @param  array{supplier_id?: ?int, supplier_reference?: ?string, expected_at?: ?string, shipping_cost?: ?float, notes?: ?string}  $data
     * @param  list<array{variant_id: int, quantity: int, unit_cost: ?float}>  $lines
     */
    public function create(array $data, array $lines, int $staffUserId): object
    {
        $lines = $this->lines($lines);

        return DB::transaction(function () use ($data, $lines, $staffUserId) {
            $id = DB::table('shop_purchase_orders')->insertGetId([
                'po_number' => self::number(),
                'supplier_id' => $data['supplier_id'] ?? null,
                'status' => 'draft',
                'currency' => (string) config('naoero.shop.currency', 'AUD'),
                'supplier_reference' => $data['supplier_reference'] ?? null,
                'expected_at' => $data['expected_at'] ?? null,
                'shipping_cost' => number_format((float) ($data['shipping_cost'] ?? 0), 2, '.', ''),
                'notes' => $data['notes'] ?? null,
                'created_by' => $staffUserId,
                'created_at' => Js::dbTime(), 'updated_at' => Js::dbTime(),
            ]);
            $this->replaceLines($id, $lines);
            $po = DB::table('shop_purchase_orders')->find($id);
            $this->audit('created', $po, 'created with '.count($lines).' line(s)');

            return $po;
        });
    }

    /** Edits a draft: its details and lines. */
    public function update(int $id, array $data, array $lines, int $staffUserId): object
    {
        $lines = $this->lines($lines);

        return DB::transaction(function () use ($id, $data, $lines) {
            $po = $this->lock($id);
            if ($po->status !== 'draft') {
                throw new RuntimeException('Only a draft can be edited; this purchase order is '.self::STATUSES[$po->status].'.');
            }
            DB::table('shop_purchase_orders')->where('id', $id)->update([
                'supplier_id' => $data['supplier_id'] ?? null,
                'supplier_reference' => $data['supplier_reference'] ?? null,
                'expected_at' => $data['expected_at'] ?? null,
                'shipping_cost' => number_format((float) ($data['shipping_cost'] ?? 0), 2, '.', ''),
                'notes' => $data['notes'] ?? null,
                'updated_at' => Js::dbTime(),
            ]);
            $this->replaceLines($id, $lines);
            $this->audit('updated', $po, 'draft edited');

            return DB::table('shop_purchase_orders')->find($id);
        });
    }

    public function markOrdered(int $id, int $staffUserId): void
    {
        DB::transaction(function () use ($id) {
            $po = $this->lock($id);
            if ($po->status !== 'draft') {
                throw new RuntimeException('This purchase order was already sent.');
            }
            if (! $po->supplier_id) {
                throw new RuntimeException('Choose a supplier before ordering.');
            }
            DB::table('shop_purchase_orders')->where('id', $id)->update(['status' => 'ordered', 'ordered_at' => Js::dbTime(), 'updated_at' => Js::dbTime()]);
            $this->audit('ordered', $po, 'marked as ordered');
        });
    }

    /**
     * Receives what arrived into stock, at each line's cost.
     *
     * @param  array<int, int>  $quantities  purchase order item id => quantity arriving now
     * @return int units received
     */
    public function receive(int $id, array $quantities, int $staffUserId): int
    {
        return DB::transaction(function () use ($id, $quantities, $staffUserId) {
            $po = $this->lock($id);
            if (! in_array($po->status, ['draft', 'ordered', 'partially_received'], true)) {
                throw new RuntimeException('This purchase order is '.mb_strtolower(self::STATUSES[$po->status]).': nothing more can be received.');
            }
            $items = DB::table('shop_purchase_order_items')->where('purchase_order_id', $id)->lockForUpdate()->orderBy('id')->get();
            $total = 0;
            foreach ($items as $item) {
                $quantity = (int) ($quantities[$item->id] ?? 0);
                if ($quantity <= 0) {
                    continue;
                }
                $outstanding = (int) $item->quantity_ordered - (int) $item->quantity_received;
                if ($quantity > $outstanding) {
                    throw new RuntimeException("{$item->title}: only {$outstanding} still to come, not {$quantity}.");
                }
                if (! $item->variant_id) {
                    throw new RuntimeException("{$item->title} was deleted from the shop: it can't be received into stock.");
                }
                $this->inventory->receive((int) $item->variant_id, $quantity, (float) $item->unit_cost, [
                    'reference_type' => 'purchase_order', 'reference_id' => (string) $po->id, 'reference_label' => $po->po_number,
                    'note' => $po->supplier_reference ? "Supplier ref {$po->supplier_reference}" : null,
                ], $staffUserId);
                DB::table('shop_purchase_order_items')->where('id', $item->id)->update(['quantity_received' => (int) $item->quantity_received + $quantity]);
                $total += $quantity;
            }
            if ($total === 0) {
                throw new RuntimeException('Enter how many of each item arrived.');
            }
            $complete = DB::table('shop_purchase_order_items')->where('purchase_order_id', $id)->whereColumn('quantity_received', '<', 'quantity_ordered')->doesntExist();
            DB::table('shop_purchase_orders')->where('id', $id)->update([
                'status' => $complete ? 'received' : 'partially_received',
                'ordered_at' => $po->ordered_at ?? Js::dbTime(),
                'received_at' => $complete ? Js::dbTime() : null,
                'updated_at' => Js::dbTime(),
            ]);
            $this->audit('received', $po, "received {$total} unit(s)".($complete ? ', complete' : ''));

            return $total;
        });
    }

    /** Closes an order that won't fully arrive: what came stays in stock. */
    public function close(int $id, int $staffUserId): void
    {
        DB::transaction(function () use ($id) {
            $po = $this->lock($id);
            if ($po->status === 'partially_received') {
                DB::table('shop_purchase_orders')->where('id', $id)->update(['status' => 'received', 'received_at' => Js::dbTime(), 'updated_at' => Js::dbTime()]);
                $this->audit('closed', $po, 'closed short: the rest will not arrive');

                return;
            }
            if (! in_array($po->status, ['draft', 'ordered'], true)) {
                throw new RuntimeException('This purchase order is already closed.');
            }
            DB::table('shop_purchase_orders')->where('id', $id)->update(['status' => 'cancelled', 'cancelled_at' => Js::dbTime(), 'updated_at' => Js::dbTime()]);
            $this->audit('cancelled', $po, 'cancelled');
        });
    }

    /**
     * What to buy: tracked variants at or below their reorder point (or low
     * stock threshold), with how many would bring them back up.
     *
     * @return list<array{variant: object, product_title: string, available: int, suggested: int}>
     */
    public static function suggestions(): array
    {
        $incoming = DB::table('shop_purchase_order_items as i')->join('shop_purchase_orders as p', 'p.id', '=', 'i.purchase_order_id')
            ->whereIn('p.status', ['draft', 'ordered', 'partially_received'])->groupBy('i.variant_id')
            ->selectRaw('i.variant_id, sum(i.quantity_ordered - i.quantity_received) as n')->pluck('n', 'variant_id');
        $rows = DB::table('shop_product_variants as v')->join('shop_products as p', 'p.id', '=', 'v.shop_product_id')
            ->where('v.is_active', true)->where('v.track_inventory', true)->where('p.is_active', true)
            ->orderBy('p.title')->orderBy('v.sort_order')->select('v.*', 'p.title as product_title')->get();
        $out = [];
        foreach ($rows as $v) {
            $available = Inventory::available($v);
            $point = $v->reorder_point ?? Inventory::lowStockThreshold($v);
            $coming = (int) ($incoming[$v->id] ?? 0);
            if ($available + $coming > $point) {
                continue;
            }
            $out[] = [
                'variant' => $v,
                'product_title' => $v->product_title,
                'available' => $available,
                'incoming' => $coming,
                'suggested' => max(1, $v->reorder_quantity ?? ($point * 2 - $available - $coming)),
            ];
        }

        return $out;
    }

    /**
     * @return list<array{variant_id: int, quantity: int, unit_cost: float, sku: string, title: string}>
     */
    private function lines(array $lines): array
    {
        $clean = [];
        foreach ($lines as $line) {
            $variantId = (int) ($line['variant_id'] ?? 0);
            $quantity = (int) ($line['quantity'] ?? 0);
            if ($variantId <= 0 || $quantity <= 0) {
                continue;
            }
            $variant = DB::table('shop_product_variants as v')->join('shop_products as p', 'p.id', '=', 'v.shop_product_id')
                ->where('v.id', $variantId)->select('v.*', 'p.title as product_title')->first()
                ?? throw new RuntimeException('One of the items no longer exists.');
            if (isset($clean[$variantId])) {
                $clean[$variantId]['quantity'] += $quantity;

                continue;
            }
            $cost = $line['unit_cost'] ?? null;
            $clean[$variantId] = [
                'variant_id' => $variantId,
                'quantity' => $quantity,
                'unit_cost' => round((float) ($cost === null || $cost === '' ? ($variant->cost_price ?? 0) : $cost), 2),
                'sku' => $variant->sku,
                'title' => Inventory::label($variant->product_title, $variant->title),
            ];
        }
        if ($clean === []) {
            throw new RuntimeException('Add at least one item with a quantity.');
        }

        return array_values($clean);
    }

    private function replaceLines(int $id, array $lines): void
    {
        DB::table('shop_purchase_order_items')->where('purchase_order_id', $id)->delete();
        foreach ($lines as $line) {
            DB::table('shop_purchase_order_items')->insert([
                'purchase_order_id' => $id, 'variant_id' => $line['variant_id'], 'sku' => $line['sku'], 'title' => $line['title'],
                'quantity_ordered' => $line['quantity'], 'quantity_received' => 0, 'unit_cost' => number_format($line['unit_cost'], 2, '.', ''),
            ]);
        }
    }

    private function lock(int $id): object
    {
        return DB::table('shop_purchase_orders')->where('id', $id)->lockForUpdate()->first() ?? throw new RuntimeException('Purchase order not found.');
    }

    private function audit(string $action, object $po, string $description): void
    {
        try {
            ActivityLog::log($action, 'Shop Inventory', "{$po->po_number}: {$description}", ['purchase_order' => $po->id]);
        } catch (Throwable) {
            // The audit log never blocks the change.
        }
    }
}
