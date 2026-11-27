@extends('layouts.admin')

@section('title', 'Shop Stock')

@section('content')
@use('App\Services\Shop\Inventory')
@php
    $money = fn ($v) => $v === null ? '—' : '$'.number_format((float) $v, 2);
    $statuses = ['' => 'All', 'in_stock' => 'In stock', 'low_stock' => 'Low stock', 'out_of_stock' => 'Sold out', 'untracked' => 'Not tracked'];
@endphp
<div class="max-w-7xl mx-auto space-y-6" x-data="{ adjust: null }">
    @include('admin.inventory._nav', [
        'crumb' => 'Stock',
        'title' => 'Stock',
        'subtitle' => 'Every SKU: on hand, held for orders awaiting payment, and available to sell. Adjust stock by hand here; deliveries and counts update it too.',
    ])

    <form method="GET" class="naoero-card p-4 space-y-3">
        <div class="flex items-center gap-1.5 overflow-x-auto border-b border-slate-100 pb-2.5">
            @foreach ($statuses as $key => $label)
                <a href="{{ route('admin.inventory.stock', array_filter([...request()->except(['status', 'page']), 'status' => $key ?: null])) }}"
                   class="px-3 py-1.5 rounded-lg text-xs font-bold whitespace-nowrap {{ (string) request('status') === (string) $key ? 'bg-navy text-white' : 'text-slate-600 hover:bg-slate-100' }}">{{ $label }}</a>
            @endforeach
        </div>
        <div class="flex flex-wrap gap-2">
            @if (request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
            <input name="search" value="{{ request('search') }}" placeholder="Product, variant, SKU or barcode" class="flex-1 min-w-[14rem] rounded-xl border border-slate-200 px-3 py-2 text-sm">
            <select name="category" class="rounded-xl border border-slate-200 px-3 py-2 text-sm">
                <option value="">All categories</option>
                @foreach ($categories as $c)
                    <option value="{{ $c }}" @selected(request('category') === $c)>{{ $c }}</option>
                @endforeach
            </select>
            <label class="flex items-center gap-2 px-3 text-xs font-semibold text-slate-600"><input type="checkbox" name="show" value="all" @checked(request('show') === 'all')> Include switched off</label>
            <button class="px-4 py-2 rounded-xl bg-navy text-white text-xs font-bold">Filter</button>
            @can('shop.inventory.export')
                <a href="{{ route('admin.inventory.stock', [...request()->query(), 'export' => 'csv']) }}" class="px-4 py-2 rounded-xl border border-slate-200 bg-white text-xs font-bold text-slate-700">⇩ Export CSV</a>
            @endcan
        </div>
        <div class="text-[11px] text-slate-500">{{ $totals['items'] }} SKU(s), {{ number_format($totals['units']) }} unit(s) on hand.</div>
    </form>

    <div class="naoero-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-4 py-3 text-left">Item</th>
                        <th class="px-4 py-3 text-left">Status</th>
                        <th class="px-4 py-3 text-right">On hand</th>
                        <th class="px-4 py-3 text-right">Held</th>
                        <th class="px-4 py-3 text-right">Available</th>
                        <th class="px-4 py-3 text-right">Cost</th>
                        <th class="px-4 py-3 text-right">Price</th>
                        <th class="px-4 py-3 text-left">Location</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($rows as $v)
                        @php $status = Inventory::status($v); @endphp
                        <tr class="hover:bg-slate-50/70 {{ $v->is_active ? '' : 'opacity-60' }}">
                            <td class="px-4 py-2.5">
                                <a href="{{ route('admin.inventory.product', $v->shop_product_id) }}" class="font-bold text-navy hover:underline">{{ $v->product_title }}</a>
                                @if ($v->title)<span class="text-xs font-semibold text-slate-600"> · {{ $v->title }}</span>@endif
                                <div class="text-[11px] text-slate-400"><span class="font-mono">{{ $v->sku }}</span>@if ($v->barcode) · {{ $v->barcode }}@endif · {{ $v->category }}</div>
                            </td>
                            <td class="px-4 py-2.5">@include('admin.inventory._status', ['status' => $status])</td>
                            <td class="px-4 py-2.5 text-right font-bold text-slate-700">{{ $v->track_inventory ? $v->on_hand : '∞' }}</td>
                            <td class="px-4 py-2.5 text-right text-amber-700 font-semibold">{{ $v->reserved ?: '' }}</td>
                            <td class="px-4 py-2.5 text-right font-extrabold {{ $status === 'out_of_stock' ? 'text-rose-700' : ($status === 'low_stock' ? 'text-amber-700' : 'text-navy') }}">{{ Inventory::available($v) ?? '∞' }}</td>
                            <td class="px-4 py-2.5 text-right text-xs text-slate-600">{{ $money($v->cost_price) }}</td>
                            <td class="px-4 py-2.5 text-right text-xs text-slate-600">{{ $money($v->price ?? $v->product_price) }}</td>
                            <td class="px-4 py-2.5 text-xs text-slate-500">{{ $v->bin_location }}</td>
                            <td class="px-4 py-2.5 text-right whitespace-nowrap">
                                @can('shop.inventory.manage')
                                    @if ($v->track_inventory && $v->is_active)
                                        <button type="button" class="px-2.5 py-1 rounded-lg border border-slate-200 text-[11px] font-bold text-navy hover:bg-slate-50"
                                                @click="adjust = @js(['id' => $v->id, 'sku' => $v->sku, 'name' => Inventory::label($v->product_title, $v->title), 'onHand' => (int) $v->on_hand, 'reserved' => (int) $v->reserved])">Adjust</button>
                                    @endif
                                @endcan
                                <a href="{{ route('admin.inventory.movements', ['variant' => $v->id]) }}" class="px-2.5 py-1 rounded-lg text-[11px] font-bold text-slate-500 hover:bg-slate-100">History</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-4 py-14 text-center text-sm text-slate-400">No stock matches.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t border-slate-100">{{ $rows->links() }}</div>
    </div>

    @can('shop.inventory.manage')
        @include('admin.inventory._adjust')
    @endcan
</div>
@endsection
