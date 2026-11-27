<?php

namespace App\Services\Shop;

use App\Exceptions\ApiException;
use App\Models\ShopProduct;
use App\Services\Support\Js;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The shop's stock. Merchandise is stocked per variant (a size, a colour,
 * or the product itself when it has no options): what is on hand, and what
 * unpaid orders hold of it. Every change goes through here and is written
 * to the ledger (shop_inventory_movements) with who made it and why;
 * shop_products.stock_quantity is kept as what is still available to sell.
 *
 * An order reserves stock when it is placed, sells it when it is paid and
 * releases it when it is never paid; a refund can put it back in stock.
 */
class Inventory
{
    /** Movement types, as the admin panel shows them. */
    public const TYPES = [
        'opening' => 'Opening stock',
        'receipt' => 'Received',
        'adjustment' => 'Adjustment',
        'count' => 'Stock count',
        'reserve' => 'Held for an order',
        'release' => 'Released',
        'sale' => 'Sold',
        'return' => 'Returned to stock',
    ];

    /** Why staff change stock by hand. */
    public const REASONS = [
        'received' => 'Received (without a purchase order)',
        'found' => 'Found',
        'returned' => 'Customer return',
        'correction' => 'Correction',
        'damaged' => 'Damaged',
        'lost' => 'Lost or stolen',
        'expired' => 'Expired or obsolete',
        'sample' => 'Used as a sample or giveaway',
        'other' => 'Other',
    ];

    /** Customers only hear how many are left when it's this few. */
    public const SHOW_STOCK_LEFT_BELOW = 10;

    public function __construct(private readonly ShopEvents $events) {}

    public static function lowStockThreshold(object $variant): int
    {
        return $variant->low_stock_threshold ?? (int) config('naoero.shop.low_stock_threshold', 5);
    }

    /** What can still be sold; null when the variant never runs out. */
    public static function available(object $variant): ?int
    {
        return $variant->track_inventory ? max(0, (int) $variant->on_hand - (int) $variant->reserved) : null;
    }

    /** in_stock / low_stock / out_of_stock / untracked / inactive */
    public static function status(object $variant): string
    {
        if (! $variant->is_active) {
            return 'inactive';
        }
        $available = self::available($variant);

        return match (true) {
            $available === null => 'untracked',
            $available <= 0 => 'out_of_stock',
            $available <= self::lowStockThreshold($variant) => 'low_stock',
            default => 'in_stock',
        };
    }

    /** "Signature Hoodie" or "Signature Hoodie (M / Navy)". */
    public static function label(string $productTitle, ?string $variantTitle): string
    {
        return $variantTitle ? "{$productTitle} ({$variantTitle})" : $productTitle;
    }

    // ------------------------------------------------------------ variants

    /**
     * A product's variants, active first by their order. Merchandise that
     * has none yet (added straight to the database) gets its one variant
     * from its stock number.
     *
     * @return Collection<int, object>
     */
    public function variants(ShopProduct $product, bool $activeOnly = false): Collection
    {
        if (ShopCatalog::isGiftCard($product)) {
            return collect();
        }
        $variants = self::variantQuery($product->id, $activeOnly)->get();
        if ($variants->isEmpty() && ! DB::table('shop_product_variants')->where('shop_product_id', $product->id)->exists()) {
            $this->createDefaultVariant($product);
            $variants = self::variantQuery($product->id, $activeOnly)->get();
        }

        return $variants;
    }

    private static function variantQuery(int $productId, bool $activeOnly)
    {
        return DB::table('shop_product_variants')->where('shop_product_id', $productId)
            ->when($activeOnly, fn ($q) => $q->where('is_active', true))
            ->orderByDesc('is_active')->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Variants of many products in one query, grouped by product id.
     *
     * @param  iterable<ShopProduct>  $products
     * @return Collection<int, Collection<int, object>>
     */
    public function variantsFor(iterable $products, bool $activeOnly = true): Collection
    {
        $merch = collect($products)->reject(fn ($p) => ShopCatalog::isGiftCard($p));
        $grouped = DB::table('shop_product_variants')->whereIn('shop_product_id', $merch->pluck('id'))
            ->orderBy('sort_order')->orderBy('id')->get()->groupBy('shop_product_id');
        foreach ($merch as $product) {
            if (! $grouped->has($product->id)) {
                $this->createDefaultVariant($product);
                $grouped[$product->id] = self::variantQuery($product->id, false)->get();
            }
        }

        return $grouped->map(fn ($v) => $activeOnly ? $v->where('is_active', true)->values() : $v);
    }

    /** The variant for a product with no options: it carries the product's SKU and stock. */
    public function createDefaultVariant(ShopProduct $product, ?int $openingStock = null): ?object
    {
        if (ShopCatalog::isGiftCard($product)) {
            return null;
        }

        return DB::transaction(function () use ($product, $openingStock) {
            $existing = DB::table('shop_product_variants')->where('shop_product_id', $product->id)->where('is_default', true)->first();
            if ($existing) {
                return $existing;
            }
            $stock = $openingStock ?? $product->stock_quantity;
            $tracked = $stock !== null;
            $sku = self::uniqueSku($product->sku ?: 'NA-'.$product->id);
            $id = DB::table('shop_product_variants')->insertGetId([
                'shop_product_id' => $product->id, 'sku' => $sku, 'on_hand' => 0, 'reserved' => 0,
                'track_inventory' => $tracked, 'is_default' => true, 'is_active' => true, 'created_at' => Js::dbTime(), 'updated_at' => Js::dbTime(),
            ]);
            // The opening stock arrives through the ledger like any other.
            $variant = $this->write(DB::table('shop_product_variants')->find($id), $product->title, 'opening', $tracked ? max(0, (int) $stock) : 0, 0, [
                'note' => $tracked ? 'Opening stock' : 'Not tracked: never runs out',
            ]);
            self::syncProduct($product->id);

            return $variant;
        });
    }

    public static function uniqueSku(string $sku, ?int $ignoreVariantId = null): string
    {
        $base = mb_substr(strtoupper(trim($sku)), 0, 60);
        $candidate = $base;
        for ($n = 2; DB::table('shop_product_variants')->where('sku', $candidate)->when($ignoreVariantId, fn ($q) => $q->where('id', '!=', $ignoreVariantId))->exists(); $n++) {
            $candidate = "{$base}-{$n}";
        }

        return $candidate;
    }

    /**
     * The variant a customer picked: the one given, or the product's only
     * variant. A product with options needs one to be picked.
     */
    public function resolve(ShopProduct $product, ?string $variantId): object
    {
        $variants = $this->variants($product, activeOnly: true);
        if ($variantId === null || $variantId === '') {
            if (! empty($product->options) || $variants->count() !== 1) {
                throw new ApiException(400, 'variant_required', 'Choose a '.self::optionNames($product).' for '.$product->title.'.', extra: ['productId' => (string) $product->id]);
            }

            return $variants->first();
        }

        return $variants->firstWhere('id', (int) $variantId)
            ?? throw new ApiException(409, 'product_unavailable', "That option of {$product->title} is no longer available. Please review your cart.", extra: ['productId' => (string) $product->id, 'variantId' => $variantId]);
    }

    /** "size", "size and colour" */
    public static function optionNames(ShopProduct $product): string
    {
        $names = array_map(fn ($o) => mb_strtolower($o['name']), $product->options ?: []);

        return $names ? implode(' and ', $names) : 'option';
    }

    /** Keeps the product's stock number at what its variants can still sell. */
    public static function syncProduct(int $productId): void
    {
        $variants = DB::table('shop_product_variants')->where('shop_product_id', $productId)->where('is_active', true)->get();
        $tracked = $variants->where('track_inventory', true);
        $untracked = $variants->count() > $tracked->count();
        DB::table('shop_products')->where('id', $productId)->update([
            'stock_quantity' => $variants->isEmpty() || $untracked ? null : $tracked->sum(fn ($v) => max(0, $v->on_hand - $v->reserved)),
        ]);
    }

    // ------------------------------------------------------------ orders

    /**
     * Holds stock for a new order, all or nothing. Must run inside the
     * order's transaction.
     *
     * @param  array<int, int>  $quantities  variant id => quantity
     */
    public function reserve(array $quantities, string $orderId, string $orderNumber): void
    {
        ksort($quantities); // the same lock order everywhere: no deadlocks
        foreach ($quantities as $variantId => $quantity) {
            $variant = $this->lock($variantId);
            $product = DB::table('shop_products')->where('id', $variant->shop_product_id)->first(['id', 'title']);
            if (! $variant->track_inventory) {
                continue;
            }
            $available = self::available($variant);
            if ($available < $quantity) {
                $name = self::label($product->title, $variant->title);
                throw new ApiException(409, 'out_of_stock', $available > 0
                    ? "Only {$available} × {$name} left. Please lower the quantity."
                    : "{$name} is sold out.", extra: ['productId' => (string) $product->id, 'variantId' => (string) $variant->id, 'available' => $available]);
            }
            $this->write($variant, $product->title, 'reserve', 0, $quantity, self::orderRef($orderId, $orderNumber));
        }
    }

    /** The paid order's held stock leaves the shelf. Runs inside the payment's transaction. */
    public function sell(object $order): void
    {
        $sold = [];
        foreach ($this->orderLines($order, 'reserved') as $line) {
            $variant = $this->lock($line->variant_id);
            // A count may have left less on hand than was held; never below zero.
            $take = min((int) $line->quantity, (int) $variant->on_hand);
            $this->write($variant, $line->title, 'sale', -$take, -min((int) $line->quantity, (int) $variant->reserved), self::orderRef($order->id, $order->order_number));
            DB::table('shop_order_items')->where('id', $line->id)->update(['stock_state' => 'sold']);
            $sold[] = "{$line->quantity} × ".self::label($line->title, $line->variant_title);
        }
        if ($sold) {
            $this->events->record($order->id, 'stock_sold', 'info', 'Stock sold: '.implode(', ', $sold));
        }
    }

    /** An unpaid order's held stock goes back on sale. Runs inside a transaction. */
    public function release(object $order): void
    {
        $released = [];
        foreach ($this->orderLines($order, 'reserved') as $line) {
            $variant = $this->lock($line->variant_id);
            $this->write($variant, $line->title, 'release', 0, -min((int) $line->quantity, (int) $variant->reserved), self::orderRef($order->id, $order->order_number));
            DB::table('shop_order_items')->where('id', $line->id)->update(['stock_state' => 'released']);
            $released[] = "{$line->quantity} × ".self::label($line->title, $line->variant_title);
        }
        if ($released) {
            $this->events->record($order->id, 'stock_released', 'info', 'Stock back on sale: '.implode(', ', $released));
        }
    }

    /**
     * Puts refunded merchandise back in stock (sold lines), or releases what
     * an order under review still holds. Runs inside a transaction.
     *
     * @param  array<int, int>  $quantities  order item id => quantity
     * @return int units put back
     */
    public function restock(object $order, array $quantities, ?int $staffUserId = null, ?string $note = null): int
    {
        $total = 0;
        $done = [];
        foreach (DB::table('shop_order_items')->where('shop_order_id', $order->id)->where('type', 'merch')->whereNotNull('variant_id')->lockForUpdate()->orderBy('id')->get() as $line) {
            $quantity = (int) ($quantities[$line->id] ?? 0);
            if ($quantity <= 0) {
                continue;
            }
            if ($line->stock_state === 'reserved') {
                $this->release($order);

                continue;
            }
            if ($line->stock_state !== 'sold') {
                continue;
            }
            $left = (int) $line->quantity - (int) $line->returned_quantity;
            if ($quantity > $left) {
                throw new RuntimeException("Only {$left} × ".self::label($line->title, $line->variant_title).' can still be put back in stock.');
            }
            $variant = $this->lock($line->variant_id);
            if ($variant->track_inventory) {
                $this->write($variant, $line->title, 'return', $quantity, 0, [...self::orderRef($order->id, $order->order_number), 'reason' => 'returned', 'note' => $note, 'staff_user_id' => $staffUserId]);
            }
            DB::table('shop_order_items')->where('id', $line->id)->update(['returned_quantity' => (int) $line->returned_quantity + $quantity]);
            $total += $quantity;
            $done[] = "{$quantity} × ".self::label($line->title, $line->variant_title);
        }
        if ($done) {
            $this->events->record($order->id, 'stock_returned', 'info', 'Put back in stock: '.implode(', ', $done), 'staff', staffUserId: $staffUserId);
        }

        return $total;
    }

    /** @return Collection<int, object> */
    private function orderLines(object $order, string $state): Collection
    {
        return DB::table('shop_order_items')->where('shop_order_id', $order->id)->where('type', 'merch')
            ->where('stock_state', $state)->whereNotNull('variant_id')->lockForUpdate()->orderBy('variant_id')->get();
    }

    private static function orderRef(string $orderId, string $orderNumber): array
    {
        return ['reference_type' => 'order', 'reference_id' => $orderId, 'reference_label' => $orderNumber];
    }

    // ------------------------------------------------------------ exchanges

    /**
     * Holds stock for an exchange's replacement, like an order does. Runs
     * inside a transaction.
     *
     * @param  array{reference_type: string, reference_id: string, reference_label: string}  $reference
     */
    public function hold(int $variantId, int $quantity, array $reference, ?int $staffUserId = null): object
    {
        $variant = $this->lock($variantId);
        if (! $variant->track_inventory) {
            return $variant;
        }
        $available = self::available($variant);
        if ($available < $quantity || ! $variant->is_active) {
            throw new RuntimeException(self::label($this->productTitle($variant), $variant->title).": only {$available} available for the exchange.");
        }

        return $this->write($variant, $this->productTitle($variant), 'reserve', 0, $quantity, [...$reference, 'staff_user_id' => $staffUserId]);
    }

    /** Puts an exchange's held replacement back on sale. Runs inside a transaction. */
    public function releaseHold(int $variantId, int $quantity, array $reference, ?int $staffUserId = null): void
    {
        $variant = $this->lock($variantId);
        if ($variant->track_inventory) {
            $this->write($variant, $this->productTitle($variant), 'release', 0, -min($quantity, (int) $variant->reserved), [...$reference, 'staff_user_id' => $staffUserId]);
        }
    }

    /** The exchange's held replacement leaves the shelf. Runs inside a transaction. */
    public function sellHold(int $variantId, int $quantity, array $reference, ?int $staffUserId = null): void
    {
        $variant = $this->lock($variantId);
        if ($variant->track_inventory) {
            $this->write($variant, $this->productTitle($variant), 'sale', -min($quantity, (int) $variant->on_hand), -min($quantity, (int) $variant->reserved), [...$reference, 'staff_user_id' => $staffUserId]);
        }
    }

    // ------------------------------------------------------------ staff

    /**
     * Changes what is on hand by hand: by a number of units, or to a number.
     *
     * @param  'add'|'remove'|'set'  $mode
     */
    public function adjust(int $variantId, string $mode, int $quantity, string $reason, ?string $note, int $staffUserId): object
    {
        if (! isset(self::REASONS[$reason])) {
            throw new RuntimeException('Choose why the stock is changing.');
        }

        return DB::transaction(function () use ($variantId, $mode, $quantity, $reason, $note, $staffUserId) {
            $variant = $this->lock($variantId);
            if (! $variant->track_inventory) {
                throw new RuntimeException('This item is not tracked (it never runs out). Turn on stock tracking first.');
            }
            $change = match ($mode) {
                'add' => $quantity,
                'remove' => -$quantity,
                'set' => $quantity - (int) $variant->on_hand,
                default => throw new RuntimeException('Choose add, remove or set.'),
            };
            if ($change === 0) {
                throw new RuntimeException('That leaves the stock as it is.');
            }
            $after = (int) $variant->on_hand + $change;
            if ($after < 0) {
                throw new RuntimeException("Only {$variant->on_hand} on hand: you can't remove {$quantity}.");
            }
            if ($after < (int) $variant->reserved) {
                throw new RuntimeException("{$variant->reserved} are held for orders awaiting payment, so on hand can't go below {$variant->reserved}.");
            }

            return $this->write($variant, $this->productTitle($variant), 'adjustment', $change, 0, ['reason' => $reason, 'note' => $note, 'staff_user_id' => $staffUserId]);
        });
    }

    /**
     * Stock arriving from a supplier: on hand goes up and the average cost
     * takes in what it cost. Runs inside a transaction.
     */
    public function receive(int $variantId, int $quantity, ?float $unitCost, array $reference, int $staffUserId): object
    {
        $variant = $this->lock($variantId);
        if ($unitCost !== null && $variant->track_inventory) {
            $onHand = max(0, (int) $variant->on_hand);
            $current = $variant->cost_price === null ? $unitCost : (float) $variant->cost_price;
            $average = round(($current * $onHand + $unitCost * $quantity) / max(1, $onHand + $quantity), 2);
            DB::table('shop_product_variants')->where('id', $variantId)->update(['cost_price' => number_format($average, 2, '.', '')]);
            $variant->cost_price = $average;
        }
        if (! $variant->track_inventory) {
            return $variant;
        }

        return $this->write($variant, $this->productTitle($variant), 'receipt', $quantity, 0, [...$reference, 'unit_cost' => $unitCost, 'staff_user_id' => $staffUserId]);
    }

    /** A counted shelf: on hand becomes what was counted. Runs inside a transaction. */
    public function applyCount(int $variantId, int $counted, array $reference, int $staffUserId): object
    {
        $variant = $this->lock($variantId);
        $change = $counted - (int) $variant->on_hand;

        return $this->write($variant, $this->productTitle($variant), 'count', $change, 0, [...$reference, 'staff_user_id' => $staffUserId,
            'note' => $change === 0 ? 'Count matched' : sprintf('Counted %d, system had %d (%+d)', $counted, $variant->on_hand, $change)]);
    }

    // ------------------------------------------------------------ the ledger

    public function lock(int $variantId): object
    {
        return DB::table('shop_product_variants')->where('id', $variantId)->lockForUpdate()->first()
            ?? throw new RuntimeException('That stock item no longer exists.');
    }

    private function productTitle(object $variant): string
    {
        return (string) DB::table('shop_products')->where('id', $variant->shop_product_id)->value('title');
    }

    /** Applies a change to a (locked) variant and writes it to the ledger. */
    private function write(object $variant, string $productTitle, string $type, int $onHandChange, int $reservedChange, array $meta = []): object
    {
        $before = self::available($variant);
        $onHand = (int) $variant->on_hand + $onHandChange;
        $reserved = (int) $variant->reserved + $reservedChange;
        if ($onHand < 0 || $reserved < 0) {
            throw new RuntimeException('Stock cannot go below zero.');
        }
        DB::table('shop_product_variants')->where('id', $variant->id)->update(['on_hand' => $onHand, 'reserved' => $reserved, 'updated_at' => Js::dbTime()]);
        DB::table('shop_inventory_movements')->insert([
            'variant_id' => $variant->id,
            'shop_product_id' => $variant->shop_product_id,
            'sku' => $variant->sku,
            'product_title' => $productTitle,
            'variant_title' => $variant->title,
            'type' => $type,
            'on_hand_change' => $onHandChange,
            'reserved_change' => $reservedChange,
            'on_hand_after' => $onHand,
            'reserved_after' => $reserved,
            'unit_cost' => isset($meta['unit_cost']) ? number_format((float) $meta['unit_cost'], 2, '.', '') : null,
            'reason' => $meta['reason'] ?? null,
            'note' => isset($meta['note']) ? mb_substr((string) $meta['note'], 0, 500) : null,
            'reference_type' => $meta['reference_type'] ?? null,
            'reference_id' => $meta['reference_id'] ?? null,
            'reference_label' => $meta['reference_label'] ?? null,
            'staff_user_id' => $meta['staff_user_id'] ?? null,
            'created_at' => Js::dbTime(),
        ]);
        $variant->on_hand = $onHand;
        $variant->reserved = $reserved;
        self::syncProduct((int) $variant->shop_product_id);

        // Sold out until now: tell the customers waiting for it.
        if ($variant->track_inventory && $variant->is_active && $before === 0 && self::available($variant) > 0) {
            app(StockAlerts::class)->backInStock($variant);
        }

        return $variant;
    }
}
