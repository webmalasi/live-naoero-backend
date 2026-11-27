<?php

namespace App\Services\Shop;

use App\Models\ShopProduct;
use App\Services\Support\Js;
use Illuminate\Support\Collection;

/**
 * The shop's products (the website's shop_products), as the app receives
 * them, with their variants (sizes, colours) and what is in stock of
 * each. Prices, stock and images are managed by staff in the admin panel.
 */
class ShopCatalog
{
    public function __construct(private readonly Inventory $inventory) {}

    public static function isGiftCard(ShopProduct $p): bool
    {
        return $p->type === 'gift_card' || in_array($p->category, ['Gift Card', 'Gift Cards'], true);
    }

    /**
     * How a product image is stored on an order line: a path such as
     * "assets/giftcard/hoodie.png" (our own files), so it is shown from
     * whatever address the page is served on; other sites keep their URL.
     */
    public static function imagePath(ShopProduct $p): ?string
    {
        $url = $p->image_url;
        if (! $url) {
            return null;
        }
        if (! str_starts_with($url, 'http')) {
            return ltrim($url, '/');
        }
        // Our own hosts, current and earlier (the backend moved from the nip.io
        // name and the bare IP to api.napacificbridge.info).
        $ours = [parse_url((string) config('app.url'), PHP_URL_HOST), parse_url((string) config('naoero.shop.pay_origin'), PHP_URL_HOST),
            'api.napacificbridge.info', 'naoero.37.60.224.111.nip.io', '37.60.224.111'];

        return in_array(parse_url($url, PHP_URL_HOST), array_filter($ours), true) ? ltrim((string) parse_url($url, PHP_URL_PATH), '/') : $url;
    }

    /** A stored image (path or URL) as a URL for the current request. */
    public static function imageUrl(?string $stored): ?string
    {
        if (! $stored) {
            return null;
        }

        return str_starts_with($stored, 'http') ? $stored : asset($stored);
    }

    /** Merchandise with a stock count; gift cards are unlimited. */
    public static function tracksStock(ShopProduct $p): bool
    {
        return ! self::isGiftCard($p) && $p->stock_quantity !== null;
    }

    /**
     * @param  Collection<int, object>|null  $variants  the product's active variants (merchandise)
     * @return array<string, mixed>
     */
    public static function present(ShopProduct $p, ?Collection $variants = null): array
    {
        $giftCard = self::isGiftCard($p);
        $variants = $giftCard ? collect() : ($variants ?? collect());
        $compare = $p->compare_at_price !== null && (float) $p->compare_at_price > (float) $p->price ? Js::round2($p->compare_at_price) : null;
        $options = $giftCard ? [] : array_values($p->options ?: []);

        if ($variants->isNotEmpty()) {
            $available = $variants->map(fn ($v) => Inventory::available($v));
            $unlimited = $available->containsStrict(null);
            $left = (int) $available->filter(fn ($a) => $a !== null)->sum();
            $inStock = $unlimited || $left > 0;
            $stockLeft = ! $unlimited && $left <= Inventory::SHOW_STOCK_LEFT_BELOW ? $left : null;
        } else {
            $inStock = $giftCard || $p->stock_quantity === null || $p->stock_quantity > 0;
            $stockLeft = self::tracksStock($p) && $p->stock_quantity <= Inventory::SHOW_STOCK_LEFT_BELOW ? max(0, (int) $p->stock_quantity) : null;
        }
        $prices = $variants->map(fn ($v) => (float) ($v->price ?? $p->price));

        return [
            'id' => (string) $p->id,
            'type' => $giftCard ? 'gift_card' : 'merch',
            'category' => $p->category,
            'title' => $p->title,
            'slug' => $p->slug,
            'sku' => $p->sku,
            'price' => Js::round2($p->price),
            // Lowest and highest variant price, when options cost different amounts.
            'priceRange' => $prices->unique()->count() > 1 ? ['min' => Js::round2($prices->min()), 'max' => Js::round2($prices->max())] : null,
            'compareAtPrice' => $compare,
            'currency' => $p->currency ?: 'AUD',
            'image' => $p->image_url,
            'shortDescription' => $p->short_description,
            'description' => $p->description,
            'inStock' => $inStock,
            // Only low stock is worth telling the customer about.
            'stockLeft' => $stockLeft,
            'hasVariants' => $options !== [],
            // [{"name": "Size", "values": ["S", "M", "L"]}]
            'options' => $options,
            'variants' => $variants->map(fn ($v) => self::presentVariant($p, $v))->values()->all(),
            'isRecommended' => (bool) $p->is_recommended,
            'isFeatured' => (bool) $p->is_featured,
            // From published reviews only; none yet: no rating.
            'rating' => (int) $p->reviews_count > 0 && $p->rating !== null ? Js::round2($p->rating) : null,
            'reviewsCount' => (int) $p->reviews_count,
        ];
    }

    /** @return array<string, mixed> */
    public static function presentVariant(ShopProduct $p, object $v): array
    {
        $available = Inventory::available($v);
        $price = (float) ($v->price ?? $p->price);
        $compare = $p->compare_at_price !== null && (float) $p->compare_at_price > $price ? Js::round2($p->compare_at_price) : null;

        return [
            'id' => (string) $v->id,
            'sku' => $v->sku,
            'title' => $v->title,
            'options' => ProductVariants::values($v),
            'price' => Js::round2($price),
            'compareAtPrice' => $compare,
            'inStock' => $available === null || $available > 0,
            'stockLeft' => $available !== null && $available <= Inventory::SHOW_STOCK_LEFT_BELOW ? $available : null,
            // The most one order can take of it.
            'maxQuantity' => min((int) config('naoero.shop.max_quantity', 10), $available ?? PHP_INT_MAX),
        ];
    }

    /** @return Collection<int, ShopProduct> */
    public function products(): Collection
    {
        return ShopProduct::query()->active()->ordered()->get();
    }

    /**
     * Everything on sale, with each product's variants and stock.
     *
     * @return list<array<string, mixed>>
     */
    public function catalogue(): array
    {
        $products = $this->products();
        $variants = $this->inventory->variantsFor($products);

        return $products->map(fn ($p) => self::present($p, $variants->get($p->id)))->values()->all();
    }

    /** @return array<string, mixed> */
    public function presentWithVariants(ShopProduct $product): array
    {
        return self::present($product, $this->inventory->variants($product, activeOnly: true));
    }

    public function find(string $id): ?ShopProduct
    {
        return ctype_digit($id) ? ShopProduct::query()->active()->find((int) $id) : null;
    }
}
