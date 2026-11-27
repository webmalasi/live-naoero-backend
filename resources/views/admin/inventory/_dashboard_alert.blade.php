{{--
    The dashboard's low-stock alert: merchandise that is sold out or at its
    low-stock level, for staff who can see the inventory.
--}}
@can('shop.inventory.view')
    @php
        $lowStock = \Illuminate\Support\Facades\Schema::hasTable('shop_product_variants')
            ? \Illuminate\Support\Facades\DB::table('shop_product_variants as v')->join('shop_products as p', 'p.id', '=', 'v.shop_product_id')
                ->where('v.is_active', true)->where('p.is_active', true)->where('v.track_inventory', true)
                ->whereRaw('v.on_hand - v.reserved <= coalesce(v.low_stock_threshold, ?)', [(int) config('naoero.shop.low_stock_threshold', 5)])
                ->orderByRaw('v.on_hand - v.reserved')->select('v.*', 'p.title as product_title')->get()
            : collect();
        $soldOut = $lowStock->filter(fn ($v) => \App\Services\Shop\Inventory::available($v) <= 0)->count();
    @endphp
    @if ($lowStock->isNotEmpty())
        <div class="rounded-2xl border {{ $soldOut ? 'border-rose-200 bg-rose-50' : 'border-amber-200 bg-amber-50' }} p-5" role="alert">
            <div class="flex flex-col md:flex-row md:items-center gap-3">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl {{ $soldOut ? 'bg-rose-600' : 'bg-amber-500' }} text-white text-lg">!</span>
                    <div>
                        <div class="font-extrabold {{ $soldOut ? 'text-rose-900' : 'text-amber-900' }}">
                            Shop stock is low: {{ $lowStock->count() }} item(s)@if ($soldOut), {{ $soldOut }} sold out @endif
                        </div>
                        <div class="text-xs {{ $soldOut ? 'text-rose-800' : 'text-amber-800' }}">
                            {{ $lowStock->take(4)->map(fn ($v) => \App\Services\Shop\Inventory::label($v->product_title, $v->title).' ('.\App\Services\Shop\Inventory::available($v).')')->implode(' · ') }}{{ $lowStock->count() > 4 ? ' · and '.($lowStock->count() - 4).' more' : '' }}
                        </div>
                    </div>
                </div>
                <div class="md:ml-auto flex gap-2">
                    <a href="{{ route('admin.inventory.stock', ['status' => $soldOut ? 'out_of_stock' : 'low_stock']) }}" class="px-3.5 py-2 rounded-xl bg-white border border-slate-200 text-xs font-bold text-navy">View stock</a>
                    @can('shop.inventory.purchasing')
                        <a href="{{ route('admin.inventory.purchase-orders.create', ['from' => 'suggestions']) }}" class="px-3.5 py-2 rounded-xl bg-navy text-white text-xs font-bold">Reorder</a>
                    @endcan
                </div>
            </div>
        </div>
    @endif
@endcan
