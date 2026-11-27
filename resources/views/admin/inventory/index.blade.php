@extends('layouts.admin')

@section('title', 'Shop Inventory')

@section('content')
@php
    $money = fn ($v) => '$'.number_format((float) $v, 2);
    $when = fn ($v) => $v ? \Carbon\Carbon::parse($v)->timezone(config('app.timezone'))->format('j M, H:i') : '—';
    $types = \App\Services\Shop\Inventory::TYPES;
@endphp
<div class="max-w-7xl mx-auto space-y-6">
    @include('admin.inventory._nav', [
        'crumb' => 'Overview',
        'title' => 'Shop Inventory',
        'subtitle' => 'Merchandise stock across every size and variant: what is on the shelf, what unpaid orders hold, and what needs buying.',
    ])

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        @foreach ([
            ['Units on hand', number_format($kpis['onHand']), $kpis['skus'].' SKUs across '.$kpis['products'].' products', 'text-navy'],
            ['Available to sell', number_format($kpis['available']), number_format($kpis['reserved']).' held for unpaid orders', 'text-emerald-700'],
            ['Stock value at cost', $money($kpis['costValue']), $kpis['noCost'] ? $kpis['noCost'].' SKU(s) have no cost yet' : 'Average cost × on hand', 'text-navy'],
            ['Stock value at retail', $money($kpis['retailValue']), 'Selling price × on hand', 'text-navy'],
        ] as [$label, $value, $hint, $color])
            <div class="naoero-card p-4">
                <div class="text-[11px] font-bold uppercase tracking-wider text-slate-500">{{ $label }}</div>
                <div class="text-2xl font-extrabold mt-1 {{ $color }}">{{ $value }}</div>
                <div class="text-[11px] text-slate-500 mt-1">{{ $hint }}</div>
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-2 md:grid-cols-6 gap-3">
        @foreach ([
            ['Low stock', $kpis['low'], route('admin.inventory.stock', ['status' => 'low_stock']), 'text-amber-700'],
            ['Sold out', $kpis['out'], route('admin.inventory.stock', ['status' => 'out_of_stock']), 'text-rose-700'],
            ['To reorder', $suggestions, route('admin.inventory.purchase-orders.create', ['from' => 'suggestions']), 'text-navy'],
            ['Purchase orders open', $openPurchaseOrders.($draftPurchaseOrders ? " (+{$draftPurchaseOrders} draft)" : ''), route('admin.inventory.purchase-orders.index'), 'text-navy'],
            ['Counts in progress', $openCounts, route('admin.inventory.counts.index'), 'text-navy'],
            ['Customers waiting', $waitingAlerts, route('admin.inventory.alerts'), 'text-purple-700'],
        ] as [$label, $value, $href, $color])
            <a href="{{ $href }}" class="naoero-card p-3 hover:shadow-md transition-shadow">
                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">{{ $label }}</div>
                <div class="text-lg font-extrabold {{ $color }}">{{ $value }}</div>
            </a>
        @endforeach
    </div>

    <div class="grid lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 naoero-card overflow-hidden">
            <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between">
                <span class="font-extrabold text-navy text-sm">Needs attention</span>
                @can('shop.inventory.purchasing')
                    @if ($suggestions)
                        <a href="{{ route('admin.inventory.purchase-orders.create', ['from' => 'suggestions']) }}" class="px-3 py-1.5 rounded-lg bg-accent text-navy text-xs font-bold">Reorder {{ $suggestions }} item(s) →</a>
                    @endif
                @endcan
            </div>
            <table class="min-w-full text-sm">
                <tbody class="divide-y divide-slate-100">
                    @forelse ($attention as $v)
                        <tr>
                            <td class="px-4 py-2.5">
                                <a href="{{ route('admin.inventory.product', $v->shop_product_id) }}" class="font-bold text-navy hover:underline">{{ \App\Services\Shop\Inventory::label($v->product_title, $v->title) }}</a>
                                <div class="text-[11px] font-mono text-slate-400">{{ $v->sku }}</div>
                            </td>
                            <td class="px-4 py-2.5">@include('admin.inventory._status', ['status' => \App\Services\Shop\Inventory::status($v)])</td>
                            <td class="px-4 py-2.5 text-right text-xs text-slate-600"><strong class="text-navy text-sm">{{ \App\Services\Shop\Inventory::available($v) }}</strong> available<div class="text-slate-400">alert at {{ \App\Services\Shop\Inventory::lowStockThreshold($v) }}</div></td>
                        </tr>
                    @empty
                        <tr><td class="px-4 py-10 text-center text-sm text-slate-400">Everything is well stocked.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="naoero-card overflow-hidden">
            <div class="px-5 py-3 border-b border-slate-100 font-extrabold text-navy text-sm">Best sellers · 30 days <span class="text-slate-400 font-semibold">({{ $unitsSold }} units)</span></div>
            <table class="min-w-full text-sm">
                <tbody class="divide-y divide-slate-100">
                    @forelse ($bestSellers as $b)
                        <tr>
                            <td class="px-4 py-2.5 text-xs"><div class="font-bold text-slate-700">{{ \App\Services\Shop\Inventory::label($b->product_title, $b->variant_title) }}</div><div class="font-mono text-slate-400">{{ $b->sku }}</div></td>
                            <td class="px-4 py-2.5 text-right font-extrabold text-navy">{{ $b->units }}</td>
                        </tr>
                    @empty
                        <tr><td class="px-4 py-10 text-center text-sm text-slate-400">No sales yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="naoero-card overflow-hidden">
        <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between">
            <span class="font-extrabold text-navy text-sm">Latest stock movements</span>
            <a href="{{ route('admin.inventory.movements') }}" class="text-xs font-bold text-navy hover:underline">All movements →</a>
        </div>
        @include('admin.inventory._movements_table', ['movements' => $recent])
    </div>
</div>
@endsection
