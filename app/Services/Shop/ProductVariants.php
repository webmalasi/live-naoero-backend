<?php

namespace App\Services\Shop;

use App\Models\ActivityLog;
use App\Models\ShopProduct;
use App\Services\Support\Js;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * A product's options (Size: S, M, L; Colour: Navy, White) and the
 * variants made from them, each with its own SKU, price, cost and stock.
 * A variant with history is never deleted, only switched off, so the
 * ledger and past orders keep pointing at it.
 */
class ProductVariants
{
    public const MAX_OPTIONS = 3;

    public const MAX_VARIANTS = 100;

    public function __construct(private readonly Inventory $inventory) {}

    /**
     * Sets the product's options and makes a variant for every combination:
     * existing variants keep their stock (the product's only variant becomes
     * the first combination); combinations no longer offered are switched off.
     *
     * @param  list<array{name: string, values: list<string>}>  $options
     * @return array{created: int, deactivated: int}
     */
    public function setOptions(ShopProduct $product, array $options, int $staffUserId): array
    {
        if (ShopCatalog::isGiftCard($product)) {
            throw new RuntimeException('Gift cards have no sizes or colours.');
        }
        $options = self::clean($options);
        $combos = self::combinations($options);
        if (count($combos) > self::MAX_VARIANTS) {
            throw new RuntimeException('That makes '.count($combos).' variants; the most is '.self::MAX_VARIANTS.'.');
        }

        $result = DB::transaction(function () use ($product, $options, $combos) {
            $this->inventory->variants($product); // its first variant, if it has none yet
            $variants = DB::table('shop_product_variants')->where('shop_product_id', $product->id)->lockForUpdate()->orderBy('sort_order')->orderBy('id')->get();
            $created = 0;
            $used = [];

            if ($options === []) {
                // Back to one variant: the first active one, if the others are empty.
                $keep = $variants->firstWhere('is_active', true) ?? $variants->first();
                foreach ($variants as $v) {
                    if ($v->id !== $keep->id && $v->is_active && ((int) $v->on_hand > 0 || (int) $v->reserved > 0)) {
                        throw new RuntimeException("{$v->title} still has stock. Move or remove it before removing the options.");
                    }
                }
                DB::table('shop_product_variants')->where('shop_product_id', $product->id)->where('id', '!=', $keep->id)
                    ->update(['is_active' => false, 'is_default' => false, 'updated_at' => Js::dbTime()]);
                DB::table('shop_product_variants')->where('id', $keep->id)->update(['title' => null, 'option_values' => null, 'is_active' => true, 'is_default' => true, 'updated_at' => Js::dbTime()]);
                DB::table('shop_products')->where('id', $product->id)->update(['options' => null]);
                Inventory::syncProduct($product->id);

                return ['created' => 0, 'deactivated' => $variants->where('is_active', true)->count() - 1];
            }

            foreach ($combos as $i => $values) {
                $title = implode(' / ', $values);
                // The variant for exactly these options, else one whose options
                // are part of them (an option was added), else the bare variant.
                $match = $variants->first(fn ($v) => ! in_array($v->id, $used, true) && self::values($v) === $values)
                    ?? $variants->first(fn ($v) => ! in_array($v->id, $used, true) && self::values($v) !== [] && array_intersect_assoc(self::values($v), $values) === self::values($v))
                    ?? ($i === 0 ? $variants->first(fn ($v) => ! in_array($v->id, $used, true) && self::values($v) === []) : null);
                if ($match) {
                    DB::table('shop_product_variants')->where('id', $match->id)->update([
                        'title' => $title, 'option_values' => Js::encode($values), 'is_active' => true, 'is_default' => false, 'sort_order' => $i, 'updated_at' => Js::dbTime(),
                    ]);
                    $used[] = $match->id;

                    continue;
                }
                $id = DB::table('shop_product_variants')->insertGetId([
                    'shop_product_id' => $product->id,
                    'sku' => Inventory::uniqueSku(($product->sku ?: 'NA-'.$product->id).'-'.implode('-', array_map(fn ($v) => preg_replace('/[^A-Z0-9]+/', '', mb_strtoupper($v)) ?: 'X', $values))),
                    'title' => $title, 'option_values' => Js::encode($values), 'on_hand' => 0, 'reserved' => 0,
                    'track_inventory' => true, 'is_default' => false, 'is_active' => true, 'sort_order' => $i,
                    'created_at' => Js::dbTime(), 'updated_at' => Js::dbTime(),
                ]);
                $used[] = $id;
                $created++;
            }

            $retired = $variants->filter(fn ($v) => $v->is_active && ! in_array($v->id, $used, true));
            foreach ($retired as $v) {
                if ((int) $v->reserved > 0) {
                    throw new RuntimeException("{$v->title} is held for orders awaiting payment; it can be removed once they are paid or cancelled.");
                }
            }
            DB::table('shop_product_variants')->whereIn('id', $retired->pluck('id'))->update(['is_active' => false, 'is_default' => false, 'updated_at' => Js::dbTime()]);
            DB::table('shop_products')->where('id', $product->id)->update(['options' => Js::encode($options)]);
            Inventory::syncProduct($product->id);

            return ['created' => $created, 'deactivated' => $retired->count()];
        });
        $this->audit($product, 'set options '.(json_encode($options) ?: '').": {$result['created']} variant(s) added, {$result['deactivated']} switched off");

        return $result;
    }

    /**
     * @param  array{sku?: string, barcode?: ?string, price?: ?float, cost_price?: ?float, low_stock_threshold?: ?int, reorder_point?: ?int, reorder_quantity?: ?int, bin_location?: ?string, weight_grams?: ?int, track_inventory?: bool, is_active?: bool}  $data
     */
    public function update(int $variantId, array $data, int $staffUserId): object
    {
        return DB::transaction(function () use ($variantId, $data) {
            $variant = $this->inventory->lock($variantId);
            if (isset($data['sku'])) {
                $sku = mb_strtoupper(trim($data['sku']));
                if (DB::table('shop_product_variants')->where('sku', $sku)->where('id', '!=', $variantId)->exists()) {
                    throw new RuntimeException("SKU {$sku} is already used by another item.");
                }
                $data['sku'] = $sku;
            }
            if (array_key_exists('track_inventory', $data) && ! $data['track_inventory'] && (int) $variant->reserved > 0) {
                throw new RuntimeException('Stock is held for orders awaiting payment: tracking can be turned off once they are paid or cancelled.');
            }
            if (array_key_exists('is_active', $data) && ! $data['is_active']) {
                if ((int) $variant->reserved > 0) {
                    throw new RuntimeException('Stock is held for orders awaiting payment: it can be switched off once they are paid or cancelled.');
                }
                if (DB::table('shop_product_variants')->where('shop_product_id', $variant->shop_product_id)->where('is_active', true)->where('id', '!=', $variantId)->doesntExist()) {
                    throw new RuntimeException('A product needs at least one active variant. Switch the product off instead.');
                }
            }
            foreach (['price', 'cost_price'] as $money) {
                if (array_key_exists($money, $data) && $data[$money] !== null) {
                    $data[$money] = number_format((float) $data[$money], 2, '.', '');
                }
            }
            DB::table('shop_product_variants')->where('id', $variantId)->update([...$data, 'updated_at' => Js::dbTime()]);
            Inventory::syncProduct((int) $variant->shop_product_id);

            return DB::table('shop_product_variants')->find($variantId);
        });
    }

    /** Deletes a variant that was never stocked or sold; otherwise it is switched off. */
    public function delete(int $variantId, int $staffUserId): string
    {
        $variant = DB::table('shop_product_variants')->find($variantId) ?? throw new RuntimeException('That variant no longer exists.');
        $used = (int) $variant->on_hand > 0 || (int) $variant->reserved > 0
            || DB::table('shop_order_items')->where('variant_id', $variantId)->exists()
            || DB::table('shop_purchase_order_items')->where('variant_id', $variantId)->exists()
            || DB::table('shop_inventory_movements')->where('variant_id', $variantId)->where(fn ($q) => $q->where('on_hand_change', '!=', 0)->orWhere('reserved_change', '!=', 0))->exists();
        if ($used) {
            $this->update($variantId, ['is_active' => false], $staffUserId);

            return 'This variant has stock or history, so it was switched off instead of deleted.';
        }
        if (DB::table('shop_product_variants')->where('shop_product_id', $variant->shop_product_id)->where('is_active', true)->where('id', '!=', $variantId)->doesntExist()) {
            throw new RuntimeException('A product needs at least one active variant.');
        }
        DB::table('shop_product_variants')->where('id', $variantId)->delete();
        Inventory::syncProduct((int) $variant->shop_product_id);

        return "Variant {$variant->sku} deleted.";
    }

    /** @return array<string, string> option name => value */
    public static function values(object $variant): array
    {
        return $variant->option_values ? (json_decode($variant->option_values, true) ?: []) : [];
    }

    /**
     * Options as typed in the form: trimmed, without blanks or repeats.
     *
     * @return list<array{name: string, values: list<string>}>
     */
    public static function clean(array $options): array
    {
        $clean = [];
        $names = [];
        foreach ($options as $option) {
            $name = trim((string) ($option['name'] ?? ''));
            $raw = is_array($option['values'] ?? null) ? $option['values'] : explode(',', (string) ($option['values'] ?? ''));
            $values = [];
            foreach ($raw as $value) {
                $value = mb_substr(trim((string) $value), 0, 40);
                if ($value !== '' && ! in_array(mb_strtolower($value), array_map('mb_strtolower', $values), true)) {
                    $values[] = $value;
                }
            }
            if ($name === '' && $values === []) {
                continue;
            }
            if ($name === '' || $values === []) {
                throw new RuntimeException('Give every option a name and at least one value.');
            }
            if (in_array(mb_strtolower($name), $names, true)) {
                throw new RuntimeException("The option {$name} is listed twice.");
            }
            $names[] = mb_strtolower($name);
            $clean[] = ['name' => mb_substr($name, 0, 30), 'values' => $values];
        }
        if (count($clean) > self::MAX_OPTIONS) {
            throw new RuntimeException('A product can have at most '.self::MAX_OPTIONS.' options.');
        }

        return $clean;
    }

    /**
     * Every combination of the options' values, in order.
     *
     * @return list<array<string, string>>
     */
    public static function combinations(array $options): array
    {
        $combos = [[]];
        foreach ($options as $option) {
            $next = [];
            foreach ($combos as $combo) {
                foreach ($option['values'] as $value) {
                    $next[] = [...$combo, $option['name'] => $value];
                }
            }
            $combos = $next;
        }

        return $options === [] ? [] : $combos;
    }

    private function audit(ShopProduct $product, string $description): void
    {
        try {
            ActivityLog::log('updated', 'Shop Inventory', "Product #{$product->id} '{$product->title}': {$description}");
        } catch (Throwable) {
            // The audit log never blocks the change.
        }
    }
}
