<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ShopProduct;
use App\Services\Shop\Inventory;
use App\Services\Shop\ProductVariants;
use App\Services\Shop\Purchasing;
use App\Services\Shop\ShopCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Shop Inventory in the admin panel: what is in stock and what it is worth,
 * low and sold-out items, each product's sizes and variants, stock
 * adjustments, and the ledger of every movement. Purchase orders and stock
 * counts have their own controllers.
 */
class InventoryController extends Controller
{
    public function __construct(
        private readonly Inventory $inventory,
        private readonly ProductVariants $variants,
    ) {}

    public static function allow(Request $request, string $permission): void
    {
        abort_unless($request->user()?->can("shop.inventory.{$permission}"), 403, 'You do not have access to the shop inventory.');
    }

    /** Runs a staff action: its message, or what went wrong, back on the page. */
    public static function act(Request $request, string $permission, callable $action): RedirectResponse
    {
        self::allow($request, $permission);
        try {
            $message = $action($request->user()->id);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return back()->with('success', $message);
    }

    /** Every active variant of active merchandise, with its product. */
    private static function stockQuery()
    {
        return DB::table('shop_product_variants as v')->join('shop_products as p', 'p.id', '=', 'v.shop_product_id')
            ->where('p.type', '!=', 'gift_card')->whereNotIn('p.category', ['Gift Card', 'Gift Cards'])
            ->select('v.*', 'p.title as product_title', 'p.category', 'p.image', 'p.price as product_price', 'p.is_active as product_active');
    }

    // ------------------------------------------------------------ dashboard

    public function index(Request $request): View
    {
        self::allow($request, 'view');
        // Merchandise added straight to the database gets its variant here.
        $this->inventory->variantsFor(ShopProduct::query()->merch()->get());

        $rows = self::stockQuery()->where('v.is_active', true)->get();
        $tracked = $rows->where('track_inventory', true);
        $statuses = $rows->map(fn ($v) => Inventory::status($v));
        $kpis = [
            'skus' => $rows->count(),
            'products' => $rows->pluck('shop_product_id')->unique()->count(),
            'onHand' => (int) $tracked->sum('on_hand'),
            'reserved' => (int) $tracked->sum('reserved'),
            'available' => (int) $tracked->sum(fn ($v) => Inventory::available($v)),
            'costValue' => round($tracked->sum(fn ($v) => (int) $v->on_hand * (float) ($v->cost_price ?? 0)), 2),
            'retailValue' => round($tracked->sum(fn ($v) => (int) $v->on_hand * (float) ($v->price ?? $v->product_price)), 2),
            'noCost' => $tracked->filter(fn ($v) => $v->cost_price === null && $v->on_hand > 0)->count(),
            'low' => $statuses->filter(fn ($s) => $s === 'low_stock')->count(),
            'out' => $statuses->filter(fn ($s) => $s === 'out_of_stock')->count(),
            'untracked' => $statuses->filter(fn ($s) => $s === 'untracked')->count(),
        ];
        $attention = $rows->filter(fn ($v) => in_array(Inventory::status($v), ['low_stock', 'out_of_stock'], true))
            ->sortBy(fn ($v) => Inventory::available($v))->take(12)->values();
        $since = now()->subDays(30);
        $sold = DB::table('shop_inventory_movements')->where('type', 'sale')->where('created_at', '>=', $since)
            ->groupBy('variant_id', 'product_title', 'variant_title', 'sku')
            ->selectRaw('variant_id, product_title, variant_title, sku, -sum(on_hand_change) as units')
            ->orderByDesc('units')->limit(8)->get();

        return view('admin.inventory.index', [
            'kpis' => $kpis,
            'attention' => $attention,
            'bestSellers' => $sold,
            'unitsSold' => (int) DB::table('shop_inventory_movements')->where('type', 'sale')->where('created_at', '>=', $since)->sum(DB::raw('-on_hand_change')),
            'recent' => DB::table('shop_inventory_movements as m')->leftJoin('users as u', 'u.id', '=', 'm.staff_user_id')
                ->select('m.*', 'u.name as staff_name')->orderByDesc('m.created_at')->orderByDesc('m.id')->limit(12)->get(),
            'openPurchaseOrders' => DB::table('shop_purchase_orders')->whereIn('status', ['ordered', 'partially_received'])->count(),
            'draftPurchaseOrders' => DB::table('shop_purchase_orders')->where('status', 'draft')->count(),
            'openCounts' => DB::table('shop_stock_counts')->where('status', 'open')->count(),
            'waitingAlerts' => DB::table('shop_stock_alerts')->whereNull('notified_at')->count(),
            'suggestions' => count(Purchasing::suggestions()),
        ]);
    }

    // ------------------------------------------------------------ stock list

    public function stock(Request $request): View|StreamedResponse
    {
        self::allow($request, 'view');
        $this->inventory->variantsFor(ShopProduct::query()->merch()->get());
        $query = self::stockQuery();
        if ($request->input('show') !== 'all') {
            $query->where('v.is_active', true);
        }
        if ($category = $request->input('category')) {
            $query->where('p.category', $category);
        }
        if ($search = trim((string) $request->input('search'))) {
            $like = '%'.mb_strtolower($search).'%';
            $query->where(fn ($q) => $q->whereRaw('lower(p.title) like ?', [$like])->orWhereRaw('lower(v.sku) like ?', [$like])
                ->orWhereRaw('lower(coalesce(v.barcode, \'\')) like ?', [$like])->orWhereRaw('lower(coalesce(v.title, \'\')) like ?', [$like]));
        }
        $rows = $query->orderBy('p.title')->orderBy('v.sort_order')->orderBy('v.id')->get();
        if ($status = $request->input('status')) {
            $rows = $rows->filter(fn ($v) => Inventory::status($v) === $status)->values();
        }

        if ($request->input('export') === 'csv') {
            self::allow($request, 'export');

            return $this->exportStock($rows);
        }

        $page = max(1, (int) $request->input('page', 1));

        return view('admin.inventory.stock', [
            'rows' => new LengthAwarePaginator($rows->forPage($page, 50)->values(), $rows->count(), 50, $page, ['path' => $request->url(), 'query' => $request->query()]),
            'categories' => DB::table('shop_products')->where('type', '!=', 'gift_card')->whereNotIn('category', ['Gift Card', 'Gift Cards'])->distinct()->orderBy('category')->pluck('category'),
            'reasons' => Inventory::REASONS,
            'totals' => ['units' => $rows->where('track_inventory', true)->sum('on_hand'), 'items' => $rows->count()],
        ]);
    }

    private function exportStock($rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['SKU', 'Barcode', 'Product', 'Variant', 'Category', 'Status', 'On hand', 'Held for orders', 'Available', 'Low stock at', 'Reorder point', 'Reorder quantity', 'Unit cost', 'Stock value at cost', 'Price', 'Location', 'Tracked', 'Active']);
            foreach ($rows as $v) {
                $price = $v->price ?? $v->product_price;
                fputcsv($out, [
                    $v->sku, $v->barcode, $v->product_title, $v->title, $v->category, Inventory::status($v), $v->on_hand, $v->reserved, Inventory::available($v) ?? '',
                    Inventory::lowStockThreshold($v), $v->reorder_point, $v->reorder_quantity, $v->cost_price,
                    $v->cost_price === null ? '' : number_format((int) $v->on_hand * (float) $v->cost_price, 2, '.', ''),
                    $price, $v->bin_location, $v->track_inventory ? 'yes' : 'no', $v->is_active ? 'yes' : 'no',
                ]);
            }
            fclose($out);
        }, 'shop-stock-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    // ------------------------------------------------------------ one product

    public function product(Request $request, ShopProduct $product): View
    {
        self::allow($request, 'view');
        abort_if(ShopCatalog::isGiftCard($product), 404, 'Gift cards have no stock.');
        $variants = $this->inventory->variants($product);

        return view('admin.inventory.product', [
            'product' => $product->fresh(),
            'variants' => $variants,
            'reasons' => Inventory::REASONS,
            'movements' => DB::table('shop_inventory_movements as m')->leftJoin('users as u', 'u.id', '=', 'm.staff_user_id')
                ->where('m.shop_product_id', $product->id)->select('m.*', 'u.name as staff_name')
                ->orderByDesc('m.created_at')->orderByDesc('m.id')->limit(25)->get(),
            'waiting' => DB::table('shop_stock_alerts')->where('shop_product_id', $product->id)->whereNull('notified_at')->count(),
            'sold30' => (int) DB::table('shop_inventory_movements')->where('shop_product_id', $product->id)->where('type', 'sale')
                ->where('created_at', '>=', now()->subDays(30))->sum(DB::raw('-on_hand_change')),
        ]);
    }

    public function setOptions(Request $request, ShopProduct $product): RedirectResponse
    {
        $request->validate([
            'options' => ['nullable', 'array', 'max:'.ProductVariants::MAX_OPTIONS],
            'options.*.name' => ['nullable', 'string', 'max:30'],
            'options.*.values' => ['nullable', 'string', 'max:500'],
        ]);

        return self::act($request, 'manage', function (int $staff) use ($request, $product) {
            $result = $this->variants->setOptions($product, $request->input('options', []), $staff);

            return ProductVariants::clean($request->input('options', [])) === []
                ? 'Options removed: the product has one variant again.'
                : "Options saved: {$result['created']} new variant(s)".($result['deactivated'] ? ", {$result['deactivated']} switched off" : '').'.';
        });
    }

    public function updateVariant(Request $request, int $variant): RedirectResponse
    {
        $data = $request->validate([
            'sku' => ['required', 'string', 'max:60', 'regex:/^[A-Za-z0-9._\-\/]+$/'],
            'barcode' => ['nullable', 'string', 'max:60'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'cost_price' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'reorder_point' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'reorder_quantity' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'bin_location' => ['nullable', 'string', 'max:60'],
            'weight_grams' => ['nullable', 'integer', 'min:0', 'max:1000000'],
        ], ['sku.regex' => 'SKUs can use letters, numbers, dots, dashes and slashes.']);
        $data['track_inventory'] = $request->boolean('track_inventory');
        $data['is_active'] = $request->boolean('is_active');

        return self::act($request, 'manage', function (int $staff) use ($variant, $data) {
            $v = $this->variants->update($variant, $data, $staff);
            $this->audit("Updated variant {$v->sku}");

            return "Saved {$v->sku}.";
        });
    }

    public function deleteVariant(Request $request, int $variant): RedirectResponse
    {
        return self::act($request, 'manage', function (int $staff) use ($variant) {
            $message = $this->variants->delete($variant, $staff);
            $this->audit($message);

            return $message;
        });
    }

    public function adjust(Request $request, int $variant): RedirectResponse
    {
        $data = $request->validate([
            'mode' => ['required', Rule::in(['add', 'remove', 'set'])],
            'quantity' => ['required', 'integer', 'min:0', 'max:100000'],
            'reason' => ['required', Rule::in(array_keys(Inventory::REASONS))],
            'note' => ['nullable', 'string', 'max:300'],
        ]);

        return self::act($request, 'manage', function (int $staff) use ($variant, $data) {
            $v = $this->inventory->adjust($variant, $data['mode'], (int) $data['quantity'], $data['reason'], $data['note'] ?? null, $staff);
            $this->audit("Adjusted {$v->sku} ({$data['mode']} {$data['quantity']}, ".Inventory::REASONS[$data['reason']]."): now {$v->on_hand} on hand");

            return "{$v->sku}: now {$v->on_hand} on hand, ".Inventory::available($v).' available.';
        });
    }

    // ------------------------------------------------------------ ledger

    public function movements(Request $request): View|StreamedResponse
    {
        self::allow($request, 'view');
        $query = DB::table('shop_inventory_movements as m')->leftJoin('users as u', 'u.id', '=', 'm.staff_user_id')
            ->select('m.*', 'u.name as staff_name')->orderByDesc('m.created_at')->orderByDesc('m.id');
        if (($type = $request->input('type')) && isset(Inventory::TYPES[$type])) {
            $query->where('m.type', $type);
        }
        if (($reason = $request->input('reason')) && isset(Inventory::REASONS[$reason])) {
            $query->where('m.reason', $reason);
        }
        if ($variant = (int) $request->input('variant')) {
            $query->where('m.variant_id', $variant);
        }
        if ($from = $request->input('from')) {
            $query->where('m.created_at', '>=', $from.' 00:00:00');
        }
        if ($to = $request->input('to')) {
            $query->where('m.created_at', '<=', $to.' 23:59:59');
        }
        if ($search = trim((string) $request->input('search'))) {
            $like = '%'.mb_strtolower($search).'%';
            $query->where(fn ($q) => $q->whereRaw('lower(m.sku) like ?', [$like])->orWhereRaw('lower(m.product_title) like ?', [$like])
                ->orWhereRaw('lower(coalesce(m.reference_label, \'\')) like ?', [$like])->orWhereRaw('lower(coalesce(m.note, \'\')) like ?', [$like]));
        }

        if ($request->input('export') === 'csv') {
            self::allow($request, 'export');
            $rows = $query->limit(20000)->get();

            return response()->streamDownload(function () use ($rows) {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['When (UTC)', 'Type', 'SKU', 'Product', 'Variant', 'On hand change', 'Held change', 'On hand after', 'Held after', 'Unit cost', 'Reason', 'Reference', 'Note', 'Staff']);
                foreach ($rows as $m) {
                    fputcsv($out, [$m->created_at, Inventory::TYPES[$m->type] ?? $m->type, $m->sku, $m->product_title, $m->variant_title, $m->on_hand_change, $m->reserved_change,
                        $m->on_hand_after, $m->reserved_after, $m->unit_cost, Inventory::REASONS[$m->reason] ?? $m->reason, $m->reference_label, $m->note, $m->staff_name]);
                }
                fclose($out);
            }, 'shop-stock-movements-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
        }

        return view('admin.inventory.movements', [
            'movements' => $query->paginate(50)->withQueryString(),
            'types' => Inventory::TYPES,
            'reasons' => Inventory::REASONS,
        ]);
    }

    /** Customers waiting for sold-out items: what is wanted most. */
    public function alerts(Request $request): View
    {
        self::allow($request, 'view');

        return view('admin.inventory.alerts', [
            'demand' => DB::table('shop_stock_alerts as a')->join('shop_products as p', 'p.id', '=', 'a.shop_product_id')
                ->leftJoin('shop_product_variants as v', 'v.id', '=', 'a.variant_id')->whereNull('a.notified_at')
                ->groupBy('a.shop_product_id', 'a.variant_id', 'p.title', 'v.title', 'v.sku')
                ->selectRaw('a.shop_product_id, a.variant_id, p.title as product_title, v.title as variant_title, v.sku, count(*) as waiting, min(a.created_at) as since')
                ->orderByDesc('waiting')->get(),
            'recent' => DB::table('shop_stock_alerts as a')->join('shop_products as p', 'p.id', '=', 'a.shop_product_id')
                ->join('customers as c', 'c.id', '=', 'a.customer_id')->leftJoin('shop_product_variants as v', 'v.id', '=', 'a.variant_id')
                ->select('a.*', 'p.title as product_title', 'v.title as variant_title', 'c.zenith_login', 'c.email as customer_main_email', 'c.first_name', 'c.last_name')
                ->orderByDesc('a.created_at')->paginate(50),
        ]);
    }

    private function audit(string $description): void
    {
        try {
            ActivityLog::log('updated', 'Shop Inventory', $description);
        } catch (Throwable) {
            // The audit log never blocks the change.
        }
    }
}
