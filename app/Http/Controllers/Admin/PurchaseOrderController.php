<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Services\Shop\Inventory;
use App\Services\Shop\Purchasing;
use App\Services\Support\Js;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

/**
 * Buying stock in the admin panel: suppliers, purchase orders (by hand or
 * from what is running low), and receiving deliveries into stock.
 */
class PurchaseOrderController extends Controller
{
    public function __construct(private readonly Purchasing $purchasing) {}

    // ------------------------------------------------------------ suppliers

    public function suppliers(Request $request): View
    {
        InventoryController::allow($request, 'view');

        return view('admin.inventory.suppliers', [
            'suppliers' => DB::table('shop_suppliers as s')
                ->selectSub(fn ($q) => $q->from('shop_purchase_orders')->whereColumn('supplier_id', 's.id')->selectRaw('count(*)'), 'orders')
                ->selectSub(fn ($q) => $q->from('shop_purchase_orders')->whereColumn('supplier_id', 's.id')->whereIn('status', ['ordered', 'partially_received'])->selectRaw('count(*)'), 'open_orders')
                ->addSelect('s.*')->orderByDesc('s.is_active')->orderBy('s.name')->get(),
        ]);
    }

    private static function supplierData(Request $request): array
    {
        return [
            ...$request->validate([
                'name' => ['required', 'string', 'max:120'],
                'contact_name' => ['nullable', 'string', 'max:120'],
                'email' => ['nullable', 'email', 'max:254'],
                'phone' => ['nullable', 'string', 'max:40'],
                'website' => ['nullable', 'url', 'max:200'],
                'address' => ['nullable', 'string', 'max:500'],
                'lead_time_days' => ['nullable', 'integer', 'min:0', 'max:365'],
                'notes' => ['nullable', 'string', 'max:1000'],
            ]),
            'is_active' => $request->boolean('is_active', true),
            'updated_at' => Js::dbTime(),
        ];
    }

    public function storeSupplier(Request $request): RedirectResponse
    {
        InventoryController::allow($request, 'purchasing');
        $data = self::supplierData($request);
        DB::table('shop_suppliers')->insert([...$data, 'currency' => (string) config('naoero.shop.currency', 'AUD'), 'created_at' => Js::dbTime()]);
        $this->audit("Added supplier {$data['name']}");

        return back()->with('success', "Supplier {$data['name']} added.");
    }

    public function updateSupplier(Request $request, int $supplier): RedirectResponse
    {
        InventoryController::allow($request, 'purchasing');
        abort_unless(DB::table('shop_suppliers')->where('id', $supplier)->exists(), 404);
        $data = self::supplierData($request);
        DB::table('shop_suppliers')->where('id', $supplier)->update($data);
        $this->audit("Updated supplier {$data['name']}");

        return back()->with('success', "Supplier {$data['name']} saved.");
    }

    // ------------------------------------------------------------ purchase orders

    public function index(Request $request): View
    {
        InventoryController::allow($request, 'view');
        $query = DB::table('shop_purchase_orders as po')->leftJoin('shop_suppliers as s', 's.id', '=', 'po.supplier_id')
            ->select('po.*', 's.name as supplier_name')
            ->selectSub(fn ($q) => $q->from('shop_purchase_order_items')->whereColumn('purchase_order_id', 'po.id')->selectRaw('coalesce(sum(quantity_ordered), 0)'), 'units_ordered')
            ->selectSub(fn ($q) => $q->from('shop_purchase_order_items')->whereColumn('purchase_order_id', 'po.id')->selectRaw('coalesce(sum(quantity_received), 0)'), 'units_received')
            ->selectSub(fn ($q) => $q->from('shop_purchase_order_items')->whereColumn('purchase_order_id', 'po.id')->selectRaw('coalesce(sum(quantity_ordered * unit_cost), 0)'), 'items_cost')
            ->orderByDesc('po.created_at')->orderByDesc('po.id');
        if (($status = $request->input('status')) && isset(Purchasing::STATUSES[$status])) {
            $query->where('po.status', $status);
        }
        if ($supplier = (int) $request->input('supplier')) {
            $query->where('po.supplier_id', $supplier);
        }
        if ($search = trim((string) $request->input('search'))) {
            $like = '%'.mb_strtolower($search).'%';
            $query->where(fn ($q) => $q->whereRaw('lower(po.po_number) like ?', [$like])->orWhereRaw('lower(coalesce(po.supplier_reference, \'\')) like ?', [$like]));
        }

        return view('admin.inventory.purchase_orders.index', [
            'orders' => $query->paginate(25)->withQueryString(),
            'statuses' => Purchasing::STATUSES,
            'suppliers' => DB::table('shop_suppliers')->orderBy('name')->get(['id', 'name']),
            'counts' => DB::table('shop_purchase_orders')->groupBy('status')->selectRaw('status, count(*) as n')->pluck('n', 'status'),
        ]);
    }

    /** A new purchase order; ?from=suggestions fills it with what is running low. */
    public function create(Request $request): View
    {
        InventoryController::allow($request, 'purchasing');
        $lines = [];
        if ($request->input('from') === 'suggestions') {
            foreach (Purchasing::suggestions() as $s) {
                $lines[] = ['variant_id' => $s['variant']->id, 'quantity' => $s['suggested'], 'unit_cost' => $s['variant']->cost_price];
            }
        }

        return view('admin.inventory.purchase_orders.form', [
            'po' => null,
            'lines' => $lines,
            'catalogue' => self::catalogue(),
            'suppliers' => DB::table('shop_suppliers')->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'suggestions' => Purchasing::suggestions(),
        ]);
    }

    /** Everything that can be bought, for the line picker. */
    private static function catalogue()
    {
        return DB::table('shop_product_variants as v')->join('shop_products as p', 'p.id', '=', 'v.shop_product_id')
            ->where('v.is_active', true)->orderBy('p.title')->orderBy('v.sort_order')
            ->get(['v.id', 'v.sku', 'v.title', 'v.cost_price', 'v.on_hand', 'v.reserved', 'v.track_inventory', 'p.title as product_title'])
            ->map(fn ($v) => ['id' => $v->id, 'label' => Inventory::label($v->product_title, $v->title)." · {$v->sku}", 'cost' => $v->cost_price, 'available' => Inventory::available($v)]);
    }

    private static function input(Request $request): array
    {
        $data = $request->validate([
            'supplier_id' => ['nullable', 'integer', Rule::exists('shop_suppliers', 'id')],
            'supplier_reference' => ['nullable', 'string', 'max:80'],
            'expected_at' => ['nullable', 'date'],
            'shipping_cost' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'max:200'],
            'lines.*.variant_id' => ['nullable', 'integer'],
            'lines.*.quantity' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'lines.*.unit_cost' => ['nullable', 'numeric', 'min:0', 'max:100000'],
        ]);
        $lines = $data['lines'];
        unset($data['lines']);

        return [$data, $lines];
    }

    public function store(Request $request): RedirectResponse
    {
        InventoryController::allow($request, 'purchasing');
        [$data, $lines] = self::input($request);
        try {
            $po = $this->purchasing->create($data, $lines, $request->user()->id);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return redirect()->route('admin.inventory.purchase-orders.show', $po->id)->with('success', "Purchase order {$po->po_number} saved as a draft.");
    }

    public function show(Request $request, int $po): View
    {
        InventoryController::allow($request, 'view');
        $order = DB::table('shop_purchase_orders as po')->leftJoin('shop_suppliers as s', 's.id', '=', 'po.supplier_id')
            ->leftJoin('users as u', 'u.id', '=', 'po.created_by')->where('po.id', $po)
            ->select('po.*', 's.name as supplier_name', 's.email as supplier_email', 's.phone as supplier_phone', 's.contact_name as supplier_contact', 'u.name as created_by_name')->first();
        abort_unless($order, 404);

        return view('admin.inventory.purchase_orders.show', [
            'po' => $order,
            'items' => DB::table('shop_purchase_order_items')->where('purchase_order_id', $po)->orderBy('id')->get(),
            'receipts' => DB::table('shop_inventory_movements as m')->leftJoin('users as u', 'u.id', '=', 'm.staff_user_id')
                ->where('m.reference_type', 'purchase_order')->where('m.reference_id', (string) $po)
                ->select('m.*', 'u.name as staff_name')->orderByDesc('m.created_at')->get(),
            'statuses' => Purchasing::STATUSES,
        ]);
    }

    public function edit(Request $request, int $po): View
    {
        InventoryController::allow($request, 'purchasing');
        $order = DB::table('shop_purchase_orders')->find($po);
        abort_unless($order, 404);
        abort_unless($order->status === 'draft', 409, 'Only a draft can be edited.');

        return view('admin.inventory.purchase_orders.form', [
            'po' => $order,
            'lines' => DB::table('shop_purchase_order_items')->where('purchase_order_id', $po)->orderBy('id')->get()
                ->map(fn ($i) => ['variant_id' => $i->variant_id, 'quantity' => $i->quantity_ordered, 'unit_cost' => $i->unit_cost])->all(),
            'catalogue' => self::catalogue(),
            'suppliers' => DB::table('shop_suppliers')->where('is_active', true)->orWhere('id', $order->supplier_id)->orderBy('name')->get(['id', 'name']),
            'suggestions' => [],
        ]);
    }

    public function update(Request $request, int $po): RedirectResponse
    {
        InventoryController::allow($request, 'purchasing');
        [$data, $lines] = self::input($request);
        try {
            $order = $this->purchasing->update($po, $data, $lines, $request->user()->id);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return redirect()->route('admin.inventory.purchase-orders.show', $order->id)->with('success', "{$order->po_number} saved.");
    }

    public function markOrdered(Request $request, int $po): RedirectResponse
    {
        return InventoryController::act($request, 'purchasing', function (int $staff) use ($po) {
            $this->purchasing->markOrdered($po, $staff);

            return 'Marked as ordered from the supplier.';
        });
    }

    public function receive(Request $request, int $po): RedirectResponse
    {
        $data = $request->validate(['receive' => ['required', 'array'], 'receive.*' => ['nullable', 'integer', 'min:0', 'max:100000']]);

        return InventoryController::act($request, 'purchasing', function (int $staff) use ($po, $data) {
            $units = $this->purchasing->receive($po, array_map('intval', array_filter($data['receive'], fn ($v) => $v !== null)), $staff);

            return "Received {$units} unit(s) into stock.";
        });
    }

    public function close(Request $request, int $po): RedirectResponse
    {
        return InventoryController::act($request, 'purchasing', function (int $staff) use ($po) {
            $this->purchasing->close($po, $staff);

            return 'Purchase order closed.';
        });
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
