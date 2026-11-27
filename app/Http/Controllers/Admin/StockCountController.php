<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Shop\StockCounts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

/** Stock counts (stocktakes) in the admin panel: count the shelves, then apply. */
class StockCountController extends Controller
{
    public function __construct(private readonly StockCounts $counts) {}

    public function index(Request $request): View
    {
        InventoryController::allow($request, 'view');

        return view('admin.inventory.counts.index', [
            'counts' => DB::table('shop_stock_counts as c')->leftJoin('users as u', 'u.id', '=', 'c.created_by')
                ->select('c.*', 'u.name as created_by_name')
                ->selectSub(fn ($q) => $q->from('shop_stock_count_items')->whereColumn('stock_count_id', 'c.id')->selectRaw('count(*)'), 'items')
                ->selectSub(fn ($q) => $q->from('shop_stock_count_items')->whereColumn('stock_count_id', 'c.id')->whereNotNull('counted')->selectRaw('count(*)'), 'counted_items')
                ->selectSub(fn ($q) => $q->from('shop_stock_count_items')->whereColumn('stock_count_id', 'c.id')->where('variance', '!=', 0)->selectRaw('count(*)'), 'differences')
                ->orderByDesc('c.created_at')->paginate(25),
            'categories' => DB::table('shop_products')->where('type', '!=', 'gift_card')->whereNotIn('category', ['Gift Card', 'Gift Cards'])->distinct()->orderBy('category')->pluck('category'),
            'statuses' => StockCounts::STATUSES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        InventoryController::allow($request, 'count');
        $data = $request->validate(['category' => ['nullable', 'string', 'max:100'], 'notes' => ['nullable', 'string', 'max:500']]);
        try {
            $count = $this->counts->start($data['category'] ?? null, $data['notes'] ?? null, $request->user()->id);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.inventory.counts.show', $count->id)->with('success', "Count {$count->count_number} started.");
    }

    public function show(Request $request, int $count): View
    {
        InventoryController::allow($request, 'view');
        $row = DB::table('shop_stock_counts as c')->leftJoin('users as u', 'u.id', '=', 'c.created_by')->leftJoin('users as d', 'd.id', '=', 'c.completed_by')
            ->where('c.id', $count)->select('c.*', 'u.name as created_by_name', 'd.name as completed_by_name')->first();
        abort_unless($row, 404);

        return view('admin.inventory.counts.show', [
            'count' => $row,
            'items' => DB::table('shop_stock_count_items as i')->leftJoin('shop_product_variants as v', 'v.id', '=', 'i.variant_id')
                ->where('i.stock_count_id', $count)->orderBy('i.id')
                ->select('i.*', 'v.on_hand as on_hand_now', 'v.bin_location', 'v.barcode')->get(),
            'statuses' => StockCounts::STATUSES,
        ]);
    }

    public function save(Request $request, int $count): RedirectResponse
    {
        $data = $request->validate(['counted' => ['required', 'array'], 'counted.*' => ['nullable', 'integer', 'min:0', 'max:1000000']]);

        return InventoryController::act($request, 'count', function () use ($count, $data, $request) {
            $saved = $this->counts->save($count, $data['counted']);
            if ($request->input('then') === 'complete') {
                $result = $this->counts->complete($count, $request->user()->id);

                return "Count applied: {$result['applied']} item(s) counted, {$result['changed']} different ({$result['units']} unit(s) corrected).";
            }

            return "Saved {$saved} counted item(s). Apply the count when you're done.";
        });
    }

    public function cancel(Request $request, int $count): RedirectResponse
    {
        return InventoryController::act($request, 'count', function (int $staff) use ($count) {
            $this->counts->cancel($count, $staff);

            return 'Count cancelled: no stock was changed.';
        });
    }
}
