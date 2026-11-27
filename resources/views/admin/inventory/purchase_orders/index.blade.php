@extends('layouts.admin')

@section('title', 'Purchase Orders')

@section('content')
@php
    $money = fn ($v) => '$'.number_format((float) $v, 2);
    $date = fn ($v) => $v ? \Carbon\Carbon::parse($v)->timezone(config('app.timezone'))->format('j M Y') : '—';
@endphp
<div class="max-w-7xl mx-auto space-y-6">
    @include('admin.inventory._nav', [
        'crumb' => 'Purchase Orders',
        'title' => 'Purchase Orders',
        'subtitle' => 'Stock bought from suppliers. Receiving a delivery adds it to stock at its cost; partial deliveries are fine.',
    ])

    <form method="GET" class="naoero-card p-4 space-y-3">
        <div class="flex items-center gap-1.5 overflow-x-auto border-b border-slate-100 pb-2.5">
            <a href="{{ route('admin.inventory.purchase-orders.index') }}" class="px-3 py-1.5 rounded-lg text-xs font-bold {{ request('status') ? 'text-slate-600 hover:bg-slate-100' : 'bg-navy text-white' }}">All <span class="opacity-60">{{ $counts->sum() }}</span></a>
            @foreach ($statuses as $key => $label)
                <a href="{{ route('admin.inventory.purchase-orders.index', ['status' => $key]) }}" class="px-3 py-1.5 rounded-lg text-xs font-bold whitespace-nowrap {{ request('status') === $key ? 'bg-navy text-white' : 'text-slate-600 hover:bg-slate-100' }}">{{ $label }} <span class="opacity-60">{{ $counts[$key] ?? 0 }}</span></a>
            @endforeach
        </div>
        <div class="flex flex-wrap gap-2">
            @if (request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
            <input name="search" value="{{ request('search') }}" placeholder="PO number or supplier reference" class="flex-1 min-w-[14rem] rounded-xl border border-slate-200 px-3 py-2 text-sm">
            <select name="supplier" class="rounded-xl border border-slate-200 px-3 py-2 text-sm">
                <option value="">All suppliers</option>
                @foreach ($suppliers as $s)<option value="{{ $s->id }}" @selected((int) request('supplier') === $s->id)>{{ $s->name }}</option>@endforeach
            </select>
            <button class="px-4 py-2 rounded-xl bg-navy text-white text-xs font-bold">Filter</button>
            @can('shop.inventory.purchasing')
                <a href="{{ route('admin.inventory.purchase-orders.create', ['from' => 'suggestions']) }}" class="px-4 py-2 rounded-xl border border-slate-200 bg-white text-xs font-bold text-navy">Reorder what's low</a>
                <a href="{{ route('admin.inventory.purchase-orders.create') }}" class="px-4 py-2 rounded-xl bg-accent text-navy text-xs font-bold">+ New purchase order</a>
            @endcan
        </div>
    </form>

    <div class="naoero-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500">
                    <tr><th class="px-4 py-3 text-left">PO</th><th class="px-4 py-3 text-left">Supplier</th><th class="px-4 py-3 text-left">Status</th><th class="px-4 py-3 text-right">Received</th><th class="px-4 py-3 text-right">Cost</th><th class="px-4 py-3 text-left">Expected</th><th class="px-4 py-3 text-left">Created</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($orders as $o)
                        <tr class="hover:bg-slate-50/70">
                            <td class="px-4 py-2.5"><a href="{{ route('admin.inventory.purchase-orders.show', $o->id) }}" class="font-mono font-extrabold text-navy">{{ $o->po_number }}</a>
                                @if ($o->supplier_reference)<div class="text-[11px] text-slate-400">Ref {{ $o->supplier_reference }}</div>@endif</td>
                            <td class="px-4 py-2.5 text-xs font-semibold text-slate-700">{{ $o->supplier_name ?? '—' }}</td>
                            <td class="px-4 py-2.5">@include('admin.inventory.purchase_orders._status', ['status' => $o->status])</td>
                            <td class="px-4 py-2.5 text-right text-xs"><strong class="text-navy">{{ $o->units_received }}</strong> / {{ $o->units_ordered }}</td>
                            <td class="px-4 py-2.5 text-right font-bold text-slate-700">{{ $money((float) $o->items_cost + (float) $o->shipping_cost) }}</td>
                            <td class="px-4 py-2.5 text-xs {{ $o->expected_at && in_array($o->status, ['ordered', 'partially_received'], true) && \Carbon\Carbon::parse($o->expected_at)->isPast() ? 'text-rose-600 font-bold' : 'text-slate-600' }}">{{ $date($o->expected_at) }}</td>
                            <td class="px-4 py-2.5 text-xs text-slate-500">{{ $date($o->created_at) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-14 text-center text-sm text-slate-400">No purchase orders yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t border-slate-100">{{ $orders->links() }}</div>
    </div>
</div>
@endsection
