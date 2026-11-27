<?php

namespace App\Services\Shop;

use App\Exceptions\ApiException;
use App\Services\Logging\ServerLog;
use App\Services\Notifications\CustomerNotices;
use App\Services\Support\Js;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * "Tell me when it's back": customers waiting for sold-out merchandise.
 * When it is back on sale each gets a notice in the app and on the
 * website (customer_notifications, type back_in_stock), once.
 */
class StockAlerts
{
    public function __construct(
        private readonly ShopCatalog $catalog,
        private readonly Inventory $inventory,
        private readonly ServerLog $log,
    ) {}

    /** Waits for a sold-out product (or one of its options) to come back. */
    public function subscribe(object $customer, string $productId, ?string $variantId, string $channel): array
    {
        $product = $this->catalog->find($productId) ?? throw new ApiException(404, 'product_not_found', 'This product is no longer available.');
        if (ShopCatalog::isGiftCard($product)) {
            throw new ApiException(409, 'in_stock', 'Gift cards never sell out.');
        }
        $variants = $this->inventory->variants($product, activeOnly: true);
        $variant = null;
        if ($variantId !== null && $variantId !== '') {
            $variant = $variants->firstWhere('id', (int) $variantId)
                ?? throw new ApiException(404, 'product_not_found', 'That option is no longer available.', extra: ['variantId' => $variantId]);
            $waitingFor = collect([$variant]);
        } else {
            $waitingFor = $variants;
        }
        // Something to buy now: no need to wait.
        if ($waitingFor->contains(fn ($v) => Inventory::available($v) !== 0)) {
            throw new ApiException(409, 'in_stock', 'This is in stock: you can order it now.');
        }

        $existing = DB::table('shop_stock_alerts')->where('customer_id', $customer->id)->where('shop_product_id', $product->id)
            ->where('variant_id', $variant?->id)->whereNull('notified_at')->first();
        if (! $existing) {
            $id = (string) Str::uuid();
            DB::table('shop_stock_alerts')->insert([
                'id' => $id, 'customer_id' => $customer->id, 'shop_product_id' => $product->id, 'variant_id' => $variant?->id,
                'channel' => $channel, 'created_at' => Js::dbTime(),
            ]);
            $existing = DB::table('shop_stock_alerts')->find($id);
        }

        return $this->present($existing);
    }

    /** @return list<array<string, mixed>> */
    public function list(object $customer): array
    {
        return DB::table('shop_stock_alerts')->where('customer_id', $customer->id)
            ->orderByRaw('notified_at is not null')->orderByDesc('created_at')->limit(100)->get()
            ->map(fn ($a) => $this->present($a))->all();
    }

    public function cancel(object $customer, string $id): void
    {
        $deleted = Str::isUuid($id) ? DB::table('shop_stock_alerts')->where('id', $id)->where('customer_id', $customer->id)->delete() : 0;
        if (! $deleted) {
            throw new ApiException(404, 'alert_not_found', "We couldn't find that alert on your account.");
        }
    }

    /** The variant is on sale again: tell everyone waiting for it, or for any of the product's options. */
    public function backInStock(object $variant): void
    {
        try {
            $product = DB::table('shop_products')->where('id', $variant->shop_product_id)->first(['id', 'title', 'is_active']);
            if (! $product?->is_active) {
                return;
            }
            $alerts = DB::table('shop_stock_alerts')->where('shop_product_id', $product->id)->whereNull('notified_at')
                ->where(fn ($q) => $q->whereNull('variant_id')->orWhere('variant_id', $variant->id))->limit(500)->get();
            $name = Inventory::label($product->title, $variant->title);
            foreach ($alerts as $alert) {
                app(CustomerNotices::class)->send($alert->customer_id, 'back_in_stock', "Back in stock: {$name}",
                    "{$name} is back in the Naoero Shop. Order soon, stock is limited.",
                    ['productId' => (string) $product->id, 'variantId' => (string) $variant->id], dedupeKey: "back_in_stock:{$alert->id}");
                DB::table('shop_stock_alerts')->where('id', $alert->id)->update(['notified_at' => Js::dbTime()]);
            }
            if ($alerts->isNotEmpty()) {
                $this->log->info('shop', 'back in stock notices', ['product' => $product->id, 'variant' => $variant->id, 'customers' => $alerts->count()]);
            }
        } catch (Throwable $e) {
            // A notice must never block the stock change.
            $this->log->warn('shop', 'back in stock notices failed', ['variant' => $variant->id, 'error' => $e->getMessage()]);
        }
    }

    /** @return array<string, mixed> */
    private function present(object $a): array
    {
        $product = DB::table('shop_products')->where('id', $a->shop_product_id)->first(['id', 'title', 'image']);
        $variant = $a->variant_id ? DB::table('shop_product_variants')->find($a->variant_id) : null;

        return [
            'id' => $a->id,
            'productId' => (string) $a->shop_product_id,
            'variantId' => $a->variant_id === null ? null : (string) $a->variant_id,
            'title' => Inventory::label($product->title ?? '', $variant?->title),
            'image' => $product?->image ? ShopCatalog::imageUrl(ltrim($product->image, '/')) : null,
            'status' => $a->notified_at ? 'notified' : 'waiting',
            'createdAt' => Js::iso($a->created_at),
            'notifiedAt' => Js::iso($a->notified_at),
        ];
    }
}
