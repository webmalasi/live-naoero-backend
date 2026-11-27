@extends('layouts.admin')

@section('title', 'Stock Movements')

@section('content')
<div class="max-w-7xl mx-auto space-y-6">
    @include('admin.inventory._nav', [
        'crumb' => 'Movements',
        'title' => 'Stock Movements',
        'subtitle' => 'The ledger of every stock change: orders holding, selling and releasing stock, deliveries, adjustments, counts and returns. Never edited.',
    ])

    <form method="GET" class="naoero-card p-4 flex flex-wrap gap-2 items-end">
        @if (request('variant'))<input type="hidden" name="variant" value="{{ request('variant') }}">@endif
        <input name="search" value="{{ request('search') }}" placeholder="SKU, product, order / PO / count number, note" class="flex-1 min-w-[14rem] rounded-xl border border-slate-200 px-3 py-2 text-sm">
        <select name="type" class="rounded-xl border border-slate-200 px-3 py-2 text-sm">
            <option value="">All types</option>
            @foreach ($types as $key => $label)
                <option value="{{ $key }}" @selected(request('type') === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="reason" class="rounded-xl border border-slate-200 px-3 py-2 text-sm">
            <option value="">Any reason</option>
            @foreach ($reasons as $key => $label)
                <option value="{{ $key }}" @selected(request('reason') === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <label class="text-[11px] font-bold text-slate-500">From<input type="date" name="from" value="{{ request('from') }}" class="block rounded-xl border border-slate-200 px-3 py-1.5 text-sm"></label>
        <label class="text-[11px] font-bold text-slate-500">To<input type="date" name="to" value="{{ request('to') }}" class="block rounded-xl border border-slate-200 px-3 py-1.5 text-sm"></label>
        <button class="px-4 py-2 rounded-xl bg-navy text-white text-xs font-bold">Filter</button>
        @if (request()->hasAny(['search', 'type', 'reason', 'from', 'to', 'variant']))
            <a href="{{ route('admin.inventory.movements') }}" class="px-3 py-2 text-xs font-bold text-slate-500">Clear</a>
        @endif
        @can('shop.inventory.export')
            <a href="{{ route('admin.inventory.movements', [...request()->query(), 'export' => 'csv']) }}" class="px-4 py-2 rounded-xl border border-slate-200 bg-white text-xs font-bold text-slate-700">⇩ Export CSV</a>
        @endcan
    </form>

    <div class="naoero-card overflow-hidden">
        @include('admin.inventory._movements_table', ['movements' => $movements])
        <div class="px-4 py-3 border-t border-slate-100">{{ $movements->links() }}</div>
    </div>
</div>
@endsection
