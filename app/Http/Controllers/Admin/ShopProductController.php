<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\DropdownOption;
use App\Models\GiftCardOrder;
use App\Models\ShopBanner;
use App\Models\ShopProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ShopProductController extends Controller
{
    /**
     * Display a listing of shop products, gift cards & homepage banner settings.
     */
    public function index(Request $request): View|StreamedResponse
    {
        $query = ShopProduct::query();

        // 1. Category Filter
        if ($cat = $request->input('category')) {
            if ($cat !== 'All') {
                if (in_array(strtolower($cat), ['gift cards', 'gift card'])) {
                    $query->giftCards();
                } elseif (strtolower($cat) === 'merch') {
                    $query->merch();
                } else {
                    $query->where('category', $cat);
                }
            }
        }

        // 2. Type Filter
        if ($type = $request->input('type')) {
            if ($type === 'gift_card') {
                $query->giftCards();
            } elseif ($type !== 'all') {
                $query->where('type', $type);
            }
        }

        // 3. Stock Status Filter
        if ($stockStatus = $request->input('stock_status')) {
            if ($stockStatus === 'low_stock') {
                $query->where('stock_quantity', '<=', 10)->where('stock_quantity', '>', 0);
            } elseif ($stockStatus === 'out_of_stock') {
                $query->where('stock_quantity', '<=', 0);
            } elseif ($stockStatus === 'in_stock') {
                $query->where(function ($q) {
                    $q->where('stock_quantity', '>', 0)->orWhereNull('stock_quantity');
                });
            }
        }

        // 4. Recommended Filter
        if ($request->filled('recommended') && $request->input('recommended') !== 'all') {
            $query->where('is_recommended', $request->boolean('recommended'));
        }

        // 5. Active Filter
        if ($request->filled('active') && $request->input('active') !== 'all') {
            $query->where('is_active', $request->boolean('active'));
        }

        // 6. Search Filter
        if ($search = trim($request->input('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhere('short_description', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('category', 'like', "%{$search}%");
            });
        }

        // 7. Sorting
        $sort = $request->input('sort', 'default');
        match ($sort) {
            'price_asc' => $query->orderBy('price', 'asc'),
            'price_desc' => $query->orderBy('price', 'desc'),
            'stock_asc' => $query->orderBy('stock_quantity', 'asc'),
            'title_asc' => $query->orderBy('title', 'asc'),
            'newest' => $query->latest('id'),
            default => $query->ordered(),
        };

        // Export to CSV
        if ($request->input('export') === 'csv') {
            return $this->exportProductsCsv($query->get());
        }

        $products = $query->paginate(15)->withQueryString();

        // Stats KPI Calculations
        $allProducts = ShopProduct::all();
        $totalCount = $allProducts->count();
        $merchCount = $allProducts->filter(fn ($p) => $p->type === 'merch' || $p->category !== 'Gift Card')->count();
        $giftCardCount = $allProducts->filter(fn ($p) => $p->type === 'gift_card' || $p->category === 'Gift Card')->count();
        $recommendedCount = $allProducts->where('is_recommended', true)->count();
        $totalStockValue = $allProducts->sum(fn ($p) => (float) $p->price * ((int) $p->stock_quantity ?: 1));
        
        $ordersCount = GiftCardOrder::count();
        $pendingOrdersCount = GiftCardOrder::pending()->count();
        $totalRevenue = GiftCardOrder::whereIn('status', ['confirmed', 'issued', 'redeemed'])->sum('amount');

        // Homepage Banners (ShopSection.jsx)
        $banners = ShopBanner::ordered()->get();

        $categories = array_merge(['All'], DropdownOption::getValues('shop_categories'));

        return view('admin.shop.index', compact(
            'products',
            'banners',
            'categories',
            'totalCount',
            'merchCount',
            'giftCardCount',
            'recommendedCount',
            'totalStockValue',
            'ordersCount',
            'pendingOrdersCount',
            'totalRevenue'
        ));
    }

    /**
     * Show form to create a new product / gift card.
     */
    public function create(): View
    {
        $categories = DropdownOption::getValues('shop_categories');
        $types = DropdownOption::getValues('shop_types');
        $currencies = DropdownOption::getGroup('currencies');
        return view('admin.shop.create', compact('categories', 'types', 'currencies'));
    }

    /**
     * Store a newly created shop product.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:100'],
            'type' => ['required', 'string', 'max:50'],
            'price' => ['required', 'numeric', 'min:0'],
            'compare_at_price' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'sku' => ['nullable', 'string', 'max:50'],
            'stock_quantity' => ['nullable', 'integer', 'min:0'],
            'rating' => ['nullable', 'numeric', 'min:1', 'max:5'],
            'reviews_count' => ['nullable', 'integer', 'min:0'],
            'is_recommended' => ['nullable', 'boolean'],
            'is_featured' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
            'image' => ['nullable', 'string', 'max:500'],
            'image_file' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp,svg', 'max:5120'],
        ]);

        $data = $validated;
        $data['currency'] = $validated['currency'] ?: 'AUD';
        $data['is_recommended'] = $request->boolean('is_recommended');
        $data['is_featured'] = $request->boolean('is_featured');
        $data['is_active'] = $request->boolean('is_active', true);
        $data['sort_order'] = (int) ($validated['sort_order'] ?? 0);
        // Opening stock: after this, stock only changes through the inventory.
        $data['stock_quantity'] = isset($validated['stock_quantity']) ? (int) $validated['stock_quantity'] : 0;
        $data['rating'] = isset($validated['rating']) ? (float) $validated['rating'] : 5.0;
        $data['reviews_count'] = isset($validated['reviews_count']) ? (int) $validated['reviews_count'] : 0;

        // Handle uploaded image file
        if ($request->hasFile('image_file')) {
            $path = $request->file('image_file')->store('shop', 'public');
            $data['image'] = Storage::url($path);
        }

        $product = ShopProduct::create($data);

        ActivityLog::log('created', 'shop', "Created shop product #{$product->id}: '{$product->title}' ({$product->formatted_price})");

        return redirect()->route('admin.shop.index')->with('success', "Product '{$product->title}' created successfully.");
    }

    /**
     * Show form to edit a product.
     */
    public function edit(ShopProduct $product): View
    {
        $categories = DropdownOption::getValues('shop_categories');
        $types = DropdownOption::getValues('shop_types');
        $currencies = DropdownOption::getGroup('currencies');
        return view('admin.shop.edit', compact('product', 'categories', 'types', 'currencies'));
    }

    /**
     * Update specified product.
     */
    public function update(Request $request, ShopProduct $product): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:100'],
            'type' => ['required', 'string', 'max:50'],
            'price' => ['required', 'numeric', 'min:0'],
            'compare_at_price' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'sku' => ['nullable', 'string', 'max:50'],
            'stock_quantity' => ['nullable', 'integer', 'min:0'],
            'rating' => ['nullable', 'numeric', 'min:1', 'max:5'],
            'reviews_count' => ['nullable', 'integer', 'min:0'],
            'is_recommended' => ['nullable', 'boolean'],
            'is_featured' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
            'image' => ['nullable', 'string', 'max:500'],
            'image_file' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp,svg', 'max:5120'],
        ]);

        $data = $validated;
        $data['currency'] = $validated['currency'] ?: 'AUD';
        $data['is_recommended'] = $request->boolean('is_recommended');
        $data['is_featured'] = $request->boolean('is_featured');
        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = (int) ($validated['sort_order'] ?? 0);
        // Stock is changed in Inventory (adjustments, deliveries, counts), never here.
        unset($data['stock_quantity']);

        if ($request->hasFile('image_file')) {
            $path = $request->file('image_file')->store('shop', 'public');
            $data['image'] = Storage::url($path);
        }

        $product->update($data);

        ActivityLog::log('updated', 'shop', "Updated shop product #{$product->id}: '{$product->title}'");

        return redirect()->route('admin.shop.index')->with('success', "Product '{$product->title}' updated successfully.");
    }

    /**
     * Delete product.
     */
    public function destroy(ShopProduct $product): RedirectResponse
    {
        $title = $product->title;
        $product->delete();

        ActivityLog::log('deleted', 'shop', "Deleted shop product: '{$title}'");

        return redirect()->route('admin.shop.index')->with('success', "Product '{$title}' deleted.");
    }

    /**
     * Toggle active status.
     */
    public function toggleActive(Request $request, ShopProduct $product): JsonResponse|RedirectResponse
    {
        $product->is_active = ! $product->is_active;
        $product->save();

        $status = $product->is_active ? 'activated' : 'deactivated';
        ActivityLog::log('updated', 'shop', "Product #{$product->id} {$status}");

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'is_active' => $product->is_active,
                'message' => "Product {$status} successfully.",
            ]);
        }

        return back()->with('success', "Product {$status} successfully.");
    }

    /**
     * Toggle recommended flag (Recommended For U section).
     */
    public function toggleRecommended(Request $request, ShopProduct $product): JsonResponse|RedirectResponse
    {
        $product->is_recommended = ! $product->is_recommended;
        $product->save();

        $msg = $product->is_recommended ? 'marked as Recommended' : 'removed from Recommended';
        ActivityLog::log('updated', 'shop', "Product #{$product->id} {$msg}");

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'is_recommended' => $product->is_recommended,
                'message' => "Product {$msg}.",
            ]);
        }

        return back()->with('success', "Product {$msg}.");
    }

    /**
     * Toggle featured flag.
     */
    public function toggleFeatured(Request $request, ShopProduct $product): JsonResponse|RedirectResponse
    {
        $product->is_featured = ! $product->is_featured;
        $product->save();

        $msg = $product->is_featured ? 'marked as Featured' : 'removed from Featured';
        ActivityLog::log('updated', 'shop', "Product #{$product->id} {$msg}");

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'is_featured' => $product->is_featured,
                'message' => "Product {$msg}.",
            ]);
        }

        return back()->with('success', "Product {$msg}.");
    }

    /**
     * Update homepage shop banner cards (ShopSection.jsx).
     */
    public function updateBanners(Request $request): RedirectResponse
    {
        $bannersData = $request->input('banners', []);

        foreach ($bannersData as $id => $fields) {
            $banner = ShopBanner::find($id);
            if ($banner) {
                $banner->update([
                    'title' => $fields['title'] ?? $banner->title,
                    'description' => $fields['description'] ?? $banner->description,
                    'cta' => $fields['cta'] ?? $banner->cta,
                    'filter' => $fields['filter'] ?? $banner->filter,
                    'link' => $fields['link'] ?? $banner->link,
                    'is_active' => isset($fields['is_active']),
                ]);
            }
        }

        ActivityLog::log('updated', 'shop', 'Updated homepage Shop & Gift Cards banner cards');

        return back()->with('success', 'Homepage Shop & Gift Cards promotional cards updated successfully.');
    }

    /**
     * View gift card orders and purchase inquiries.
     */
    public function orders(Request $request): View|StreamedResponse
    {
        $query = GiftCardOrder::with('product')->latest();

        if ($status = $request->input('status')) {
            if ($status !== 'all') {
                $query->where('status', $status);
            }
        }

        if ($delivery = $request->input('delivery_type')) {
            if ($delivery !== 'all') {
                $query->where('delivery_type', $delivery);
            }
        }

        if ($search = trim($request->input('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('buyer_name', 'like', "%{$search}%")
                  ->orWhere('buyer_email', 'like', "%{$search}%")
                  ->orWhere('order_number', 'like', "%{$search}%")
                  ->orWhere('voucher_code', 'like', "%{$search}%")
                  ->orWhere('recipient_name', 'like', "%{$search}%");
            });
        }

        if ($request->input('export') === 'csv') {
            return $this->exportOrdersCsv($query->get());
        }

        $orders = $query->paginate(20)->withQueryString();
        $totalOrders = GiftCardOrder::count();
        $pendingCount = GiftCardOrder::pending()->count();
        $confirmedCount = GiftCardOrder::where('status', 'confirmed')->count();
        $issuedCount = GiftCardOrder::where('status', 'issued')->count();
        $totalRevenue = GiftCardOrder::whereIn('status', ['confirmed', 'issued', 'redeemed'])->sum('amount');

        return view('admin.shop.orders', compact(
            'orders',
            'totalOrders',
            'pendingCount',
            'confirmedCount',
            'issuedCount',
            'totalRevenue'
        ));
    }

    /**
     * Update gift card order status, voucher balance, and admin notes.
     */
    public function updateOrderStatus(Request $request, GiftCardOrder $order): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:pending,confirmed,issued,redeemed,cancelled'],
            'balance_remaining' => ['nullable', 'numeric', 'min:0'],
            'admin_notes' => ['nullable', 'string'],
        ]);

        $order->update($validated);

        ActivityLog::log('updated', 'shop', "Updated Gift Card Order #{$order->order_number} to status '{$order->status}'");

        return back()->with('success', "Order #{$order->order_number} updated to status '{$order->status}'.");
    }

    /**
     * Regenerate unique voucher code for an order.
     */
    public function regenerateVoucher(GiftCardOrder $order): RedirectResponse
    {
        $denom = (int) $order->amount;
        $prefix = $denom > 0 ? "ON-{$denom}" : 'ON-GC';
        $newCode = $prefix . '-' . strtoupper(Str::random(4)) . '-' . strtoupper(Str::random(4));

        $order->update(['voucher_code' => $newCode]);

        ActivityLog::log('updated', 'shop', "Regenerated voucher code for Order #{$order->order_number}: '{$newCode}'");

        return back()->with('success', "New voucher code '{$newCode}' generated for Order #{$order->order_number}.");
    }

    /**
     * Export products catalog to CSV.
     */
    private function exportProductsCsv($products): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="naoero_shop_catalog_' . date('Y-m-d') . '.csv"',
        ];

        return response()->stream(function () use ($products) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'SKU', 'Title', 'Category', 'Type', 'Price AUD', 'Compare Price AUD', 'Stock', 'Rating', 'Recommended', 'Featured', 'Active', 'Created At']);

            foreach ($products as $p) {
                fputcsv($handle, [
                    $p->id,
                    $p->sku,
                    $p->title,
                    $p->category,
                    $p->type,
                    $p->price,
                    $p->compare_at_price,
                    $p->stock_quantity,
                    $p->rating,
                    $p->is_recommended ? 'YES' : 'NO',
                    $p->is_featured ? 'YES' : 'NO',
                    $p->is_active ? 'ACTIVE' : 'INACTIVE',
                    $p->created_at->toIso8601String(),
                ]);
            }
            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Export gift card orders to CSV.
     */
    private function exportOrdersCsv($orders): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="naoero_gift_card_orders_' . date('Y-m-d') . '.csv"',
        ];

        return response()->stream(function () use ($orders) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Order #', 'Voucher Code', 'Buyer Name', 'Buyer Email', 'Buyer Phone', 'Recipient Name', 'Recipient Email', 'Amount AUD', 'Balance AUD', 'Delivery', 'Status', 'Date']);

            foreach ($orders as $o) {
                fputcsv($handle, [
                    $o->order_number,
                    $o->voucher_code,
                    $o->buyer_name,
                    $o->buyer_email,
                    $o->buyer_phone,
                    $o->recipient_name,
                    $o->recipient_email,
                    $o->amount,
                    $o->balance_remaining,
                    $o->delivery_type,
                    strtoupper($o->status),
                    $o->created_at->toIso8601String(),
                ]);
            }
            fclose($handle);
        }, 200, $headers);
    }
}
