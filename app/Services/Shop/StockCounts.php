<?php

namespace App\Services\Shop;

use App\Models\ActivityLog;
use App\Services\Support\Js;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Stock counts (stocktakes): staff count the shelves, and completing the
 * count sets each counted item's stock to what was found, with every
 * difference in the ledger. Items left blank are not changed. Sales during
 * the count are allowed: the difference is taken against stock at the
 * moment the count is applied.
 */
class StockCounts
{
    public const STATUSES = ['open' => 'In progress', 'completed' => 'Completed', 'cancelled' => 'Cancelled'];

    public function __construct(private readonly Inventory $inventory) {}

    private static function number(): string
    {
        do {
            $number = 'SC-'.now()->utc()->format('ymd').'-'.strtoupper(substr(bin2hex(random_bytes(2)), 0, 3));
        } while (DB::table('shop_stock_counts')->where('count_number', $number)->exists());

        return $number;
    }

    /** Starts a count of every tracked item, or of one category. */
    public function start(?string $category, ?string $notes, int $staffUserId): object
    {
        return DB::transaction(function () use ($category, $notes, $staffUserId) {
            $variants = DB::table('shop_product_variants as v')->join('shop_products as p', 'p.id', '=', 'v.shop_product_id')
                ->where('v.is_active', true)->where('v.track_inventory', true)
                ->when($category, fn ($q) => $q->where('p.category', $category))
                ->orderBy('p.title')->orderBy('v.sort_order')->orderBy('v.id')
                ->select('v.*', 'p.title as product_title')->get();
            if ($variants->isEmpty()) {
                throw new RuntimeException('There are no tracked items to count'.($category ? " in {$category}" : '').'.');
            }
            $id = DB::table('shop_stock_counts')->insertGetId([
                'count_number' => self::number(), 'status' => 'open', 'scope' => $category ?: 'All merchandise',
                'notes' => $notes, 'created_by' => $staffUserId, 'created_at' => Js::dbTime(), 'updated_at' => Js::dbTime(),
            ]);
            foreach ($variants as $v) {
                DB::table('shop_stock_count_items')->insert([
                    'stock_count_id' => $id, 'variant_id' => $v->id, 'sku' => $v->sku,
                    'title' => Inventory::label($v->product_title, $v->title), 'expected' => (int) $v->on_hand,
                ]);
            }
            $count = DB::table('shop_stock_counts')->find($id);
            $this->audit('created', $count, 'started for '.$count->scope.' ('.$variants->count().' items)');

            return $count;
        });
    }

    /**
     * Saves what was counted so far; blank means not counted yet.
     *
     * @param  array<int, int|string|null>  $counted  count item id => units
     */
    public function save(int $id, array $counted): int
    {
        return DB::transaction(function () use ($id, $counted) {
            $count = $this->lock($id);
            if ($count->status !== 'open') {
                throw new RuntimeException('This count is closed.');
            }
            $saved = 0;
            foreach (DB::table('shop_stock_count_items')->where('stock_count_id', $id)->get() as $item) {
                if (! array_key_exists($item->id, $counted)) {
                    continue;
                }
                $value = $counted[$item->id];
                $value = $value === null || $value === '' ? null : (int) $value;
                if ($value !== null && $value < 0) {
                    throw new RuntimeException("{$item->title}: a count can't be negative.");
                }
                DB::table('shop_stock_count_items')->where('id', $item->id)->update(['counted' => $value]);
                $saved += $value === null ? 0 : 1;
            }
            DB::table('shop_stock_counts')->where('id', $id)->update(['updated_at' => Js::dbTime()]);

            return $saved;
        });
    }

    /**
     * Applies the count: every counted item's stock becomes what was counted.
     *
     * @return array{applied: int, changed: int, units: int}
     */
    public function complete(int $id, int $staffUserId): array
    {
        return DB::transaction(function () use ($id, $staffUserId) {
            $count = $this->lock($id);
            if ($count->status !== 'open') {
                throw new RuntimeException('This count is closed.');
            }
            $items = DB::table('shop_stock_count_items')->where('stock_count_id', $id)->whereNotNull('counted')->whereNotNull('variant_id')->orderBy('variant_id')->get();
            if ($items->isEmpty()) {
                throw new RuntimeException('Nothing has been counted yet.');
            }
            $changed = 0;
            $units = 0;
            foreach ($items as $item) {
                $before = (int) $this->inventory->lock((int) $item->variant_id)->on_hand;
                $this->inventory->applyCount((int) $item->variant_id, (int) $item->counted, [
                    'reference_type' => 'stock_count', 'reference_id' => (string) $count->id, 'reference_label' => $count->count_number,
                ], $staffUserId);
                $variance = (int) $item->counted - $before;
                DB::table('shop_stock_count_items')->where('id', $item->id)->update(['on_hand_before' => $before, 'variance' => $variance]);
                if ($variance !== 0) {
                    $changed++;
                    $units += abs($variance);
                }
            }
            DB::table('shop_stock_counts')->where('id', $id)->update([
                'status' => 'completed', 'completed_by' => $staffUserId, 'completed_at' => Js::dbTime(), 'updated_at' => Js::dbTime(),
            ]);
            $this->audit('completed', $count, "applied {$items->count()} counted item(s), {$changed} different");

            return ['applied' => $items->count(), 'changed' => $changed, 'units' => $units];
        });
    }

    public function cancel(int $id, int $staffUserId): void
    {
        DB::transaction(function () use ($id) {
            $count = $this->lock($id);
            if ($count->status !== 'open') {
                throw new RuntimeException('This count is closed.');
            }
            DB::table('shop_stock_counts')->where('id', $id)->update(['status' => 'cancelled', 'updated_at' => Js::dbTime()]);
            $this->audit('cancelled', $count, 'cancelled: no stock changed');
        });
    }

    private function lock(int $id): object
    {
        return DB::table('shop_stock_counts')->where('id', $id)->lockForUpdate()->first() ?? throw new RuntimeException('Stock count not found.');
    }

    private function audit(string $action, object $count, string $description): void
    {
        try {
            ActivityLog::log($action, 'Shop Inventory', "{$count->count_number}: {$description}", ['stock_count' => $count->id]);
        } catch (Throwable) {
            // The audit log never blocks the change.
        }
    }
}
