<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\GiftCardOrder;
use App\Models\ShopBanner;
use App\Models\ShopProduct;
use App\Services\Shop\Inventory;
use App\Services\Shop\ShopCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ShopApiController extends Controller
{
    /**
     * Get Homepage Shop & Gift Cards section data (cards + quick recommendations).
     *
     * GET /api/shop/homepage
     */
    public function homepage(): JsonResponse
    {
        $banners = ShopBanner::query()
            ->active()
            ->get()
            ->map(function (ShopBanner $banner) {
                return [
                    'id' => $banner->id,
                    'title' => $banner->title,
                    'description' => $banner->description,
                    'cta' => $banner->cta,
                    'filter' => $banner->filter,
                    'link' => $banner->link,
                    'image' => $banner->image_url,
                ];
            });

        $recommended = ShopProduct::query()
            ->active()
            ->recommended()
            ->ordered()
            ->limit(3)
            ->get()
            ->map(fn (ShopProduct $p) => $this->formatProduct($p));

        return response()->json([
            'success' => true,
            'count' => $banners->count(),
            'data' => $banners,
            'recommended_preview' => $recommended,
        ]);
    }

    /**
     * List all shop products & gift cards with advanced filtering, search, and sorting.
     *
     * GET /api/shop/products
     */
    public function products(Request $request): JsonResponse
    {
        $query = ShopProduct::query()->active();

        // 1. Category filter
        if ($category = $request->query('category')) {
            if ($category !== 'All' && $category !== 'all') {
                if (in_array(strtolower($category), ['gift cards', 'gift card', 'gift_card', 'gift-cards', 'gift-card'])) {
                    $query->giftCards();
                } elseif (strtolower($category) === 'merch') {
                    $query->merch();
                } else {
                    $query->where('category', 'like', $category);
                }
            }
        }

        // 2. Type filter (gift_card, merch, apparel, drinkware, toys, caps, tshirts)
        if ($type = $request->query('type')) {
            if ($type === 'gift_card') {
                $query->giftCards();
            } else {
                $query->where('type', $type);
            }
        }

        // 3. Recommended filter (Recommended For U section on GiftCardDetail.jsx)
        if ($request->boolean('recommended') || $request->query('recommended') === '1') {
            $query->recommended();
        }

        // 4. Featured filter
        if ($request->boolean('featured') || $request->query('featured') === '1') {
            $query->featured();
        }

        // 5. Price range filter
        if ($minPrice = $request->query('min_price')) {
            $query->where('price', '>=', (float) $minPrice);
        }
        if ($maxPrice = $request->query('max_price')) {
            $query->where('price', '<=', (float) $maxPrice);
        }

        // 6. Keyword Search
        if ($search = trim($request->query('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('short_description', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('category', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        // 7. Sorting
        $sort = $request->query('sort', 'default');
        match ($sort) {
            'price_asc' => $query->orderBy('price', 'asc'),
            'price_desc' => $query->orderBy('price', 'desc'),
            'rating' => $query->orderBy('rating', 'desc')->orderBy('reviews_count', 'desc'),
            'newest' => $query->latest('id'),
            default => $query->ordered(),
        };

        // 8. Pagination or Limit
        $limit = $request->integer('limit');
        if ($limit > 0) {
            $products = $query->limit($limit)->get();
        } else {
            $products = $query->get();
        }

        $formatted = $products->map(fn (ShopProduct $p) => $this->formatProduct($p));

        return response()->json([
            'success' => true,
            'count' => $formatted->count(),
            'data' => $formatted,
        ]);
    }

    /**
     * Get distinct category pills, counts, and hero tiles.
     *
     * GET /api/shop/categories
     */
    public function categories(): JsonResponse
    {
        $categoryList = [
            'All',
            'Merch',
            'Gift Cards',
            'Caps',
            'Tshirts',
            'Apparel',
            'Drinkware',
            'Toys',
        ];

        $counts = [
            'All' => ShopProduct::active()->count(),
            'Merch' => ShopProduct::active()->merch()->count(),
            'Gift Cards' => ShopProduct::active()->giftCards()->count(),
            'Caps' => ShopProduct::active()->where('category', 'Caps')->count(),
            'Tshirts' => ShopProduct::active()->where('category', 'Tshirts')->count(),
            'Apparel' => ShopProduct::active()->where('category', 'Apparel')->count(),
            'Drinkware' => ShopProduct::active()->where('category', 'Drinkware')->count(),
            'Toys' => ShopProduct::active()->where('category', 'Toys')->count(),
        ];

        $tiles = [
            [
                'label' => 'Gift Cards',
                'category' => 'Gift Cards',
                'image' => asset('assets/home/giftcards.png'),
                'count' => $counts['Gift Cards'],
            ],
            [
                'label' => 'Merch',
                'category' => 'Merch',
                'image' => asset('assets/home/Nauruairline.png'),
                'count' => $counts['Merch'],
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => $categoryList,
            'counts' => $counts,
            'tiles' => $tiles,
        ]);
    }

    /**
     * Get single product or gift card detail by ID or Slug.
     *
     * GET /api/shop/products/{identifier}
     */
    public function show(string $identifier): JsonResponse
    {
        $product = ShopProduct::query()
            ->active()
            ->where(function ($q) use ($identifier) {
                if (is_numeric($identifier)) {
                    $q->where('id', (int) $identifier)->orWhere('slug', $identifier);
                } else {
                    $q->where('slug', $identifier);
                }
            })
            ->first();

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Shop product or gift card not found.',
            ], 404);
        }

        // Fetch related products in the same category
        $related = ShopProduct::query()
            ->active()
            ->where('id', '!=', $product->id)
            ->where(function ($q) use ($product) {
                $q->where('category', $product->category)
                  ->orWhere('type', $product->type);
            })
            ->limit(4)
            ->get()
            ->map(fn (ShopProduct $p) => $this->formatProduct($p));

        return response()->json([
            'success' => true,
            'data' => $this->formatProduct($product),
            'related' => $related,
        ]);
    }

    /**
     * Submit Gift Card purchase request / order inquiry.
     *
     * POST /api/gift-cards/order
     */
    public function submitOrder(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'buyer_name' => ['required', 'string', 'max:255'],
            'buyer_email' => ['required', 'email', 'max:255'],
            'buyer_phone' => ['nullable', 'string', 'max:50'],
            'recipient_name' => ['nullable', 'string', 'max:255'],
            'recipient_email' => ['nullable', 'email', 'max:255'],
            'amount' => ['required', 'numeric', 'min:10', 'max:50000'],
            'currency' => ['nullable', 'string', 'max:10'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:100'],
            'personal_message' => ['nullable', 'string', 'max:1000'],
            'delivery_type' => ['nullable', 'in:digital,postal'],
            'payment_method' => ['nullable', 'string', 'max:50'],
            'postal_address' => ['nullable', 'string', 'max:1000'],
            'shop_product_id' => ['nullable', 'exists:shop_products,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $validated['currency'] = $validated['currency'] ?? 'AUD';
        $validated['quantity'] = $validated['quantity'] ?? 1;
        $validated['delivery_type'] = $validated['delivery_type'] ?? 'digital';
        $validated['payment_method'] = $validated['payment_method'] ?? 'credit_card';
        $validated['status'] = 'pending';
        $validated['balance_remaining'] = $validated['amount'] * $validated['quantity'];

        $order = GiftCardOrder::create($validated);

        ActivityLog::log('created', 'shop', "New Gift Card Order #{$order->order_number} submitted by {$order->buyer_email} for {$order->formatted_amount}");

        return response()->json([
            'success' => true,
            'message' => "Thank you! Your gift card order #{$order->order_number} has been received. Our ticketing concierge will contact you with payment confirmation and voucher activation.",
            'data' => [
                'order_number' => $order->order_number,
                'voucher_code' => $order->voucher_code,
                'amount' => (float) $order->amount,
                'balance_remaining' => (float) $order->balance_remaining,
                'currency' => $order->currency,
                'formatted_amount' => $order->formatted_amount,
                'recipient_name' => $order->recipient_name ?: $order->buyer_name,
                'recipient_email' => $order->recipient_email ?: $order->buyer_email,
                'delivery_type' => $order->delivery_type,
                'status' => $order->status,
                'expires_at' => $order->expires_at ? $order->expires_at->toIso8601String() : null,
                'created_at' => $order->created_at->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Verify voucher code status and remaining balance.
     *
     * GET /api/gift-cards/verify/{voucher_code}
     */
    public function verifyVoucher(string $voucherCode): JsonResponse
    {
        $order = GiftCardOrder::where('voucher_code', strtoupper(trim($voucherCode)))->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'valid' => false,
                'message' => 'Invalid gift card voucher code.',
            ], 404);
        }

        $isExpired = $order->expires_at && $order->expires_at->isPast();
        $hasBalance = (float) $order->balance_remaining > 0;
        $isValid = in_array($order->status, ['confirmed', 'issued']) && !$isExpired && $hasBalance;

        return response()->json([
            'success' => true,
            'valid' => $isValid,
            'data' => [
                'voucher_code' => $order->voucher_code,
                'status' => $order->status,
                'amount' => (float) $order->amount,
                'balance_remaining' => (float) $order->balance_remaining,
                'currency' => $order->currency,
                'formatted_balance' => $order->formatted_balance,
                'recipient_name' => $order->recipient_name ?: $order->buyer_name,
                'expires_at' => $order->expires_at ? $order->expires_at->toDateString() : null,
                'is_expired' => $isExpired,
            ],
        ]);
    }

    /**
     * Format product object for JSON serialization.
     */
    private function formatProduct(ShopProduct $p): array
    {
        return [
            'id' => $p->id,
            'title' => $p->title,
            'slug' => $p->slug,
            'category' => $p->category,
            'type' => $p->type,
            'price' => (float) $p->price,
            'compareAtPrice' => $p->compare_at_price ? (float) $p->compare_at_price : null,
            'discountPercent' => $p->discount_percent,
            'currency' => $p->currency ?: 'AUD',
            'formattedPrice' => $p->formatted_price,
            'formattedCompareAtPrice' => $p->formatted_compare_at_price,
            'image' => $p->image_url,
            'shortDescription' => $p->short_description,
            'description' => $p->description,
            'sku' => $p->sku,
            'stockQuantity' => $p->stock_quantity,
            'isInStock' => $p->is_in_stock,
            // Sizes, colours...: what to pick, and each variant's price and stock.
            'options' => ShopCatalog::isGiftCard($p) ? [] : array_values($p->options ?: []),
            'variants' => app(Inventory::class)->variants($p, activeOnly: true)->map(fn ($v) => ShopCatalog::presentVariant($p, $v))->values()->all(),
            'rating' => (float) $p->rating,
            'reviewsCount' => (int) $p->reviews_count,
            'isRecommended' => $p->is_recommended,
            'isFeatured' => $p->is_featured,
            'isActive' => $p->is_active,
            'sortOrder' => $p->sort_order,
        ];
    }
}
