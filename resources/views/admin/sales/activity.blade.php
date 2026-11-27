@extends('layouts.admin')

@section('title', 'Shop Activity Log')

@section('content')
@php
    $when = fn ($v) => \Carbon\Carbon::parse($v)->timezone(config('app.timezone'))->format('j M Y, H:i:s');
    $sources = ['customer' => 'Customer', 'system' => 'System', 'nab' => 'NAB Gateway', 'staff' => 'Staff'];
@endphp
<div class="max-w-7xl mx-auto space-y-6">
    @include('admin.sales._nav', [
        'crumb' => 'Activity Log',
        'title' => 'Shop Activity Log',
        'subtitle' => 'Everything that happened to every shop order, tagged by outcome: orders, payments, declines, gift cards, refunds, shipping and staff actions.',
    ])

    <form method="GET" class="naoero-card p-4 grid grid-cols-1 md:grid-cols-5 gap-2.5">
        <input name="search" value="{{ request('search') }}" placeholder="Order number or text" class="md:col-span-2 rounded-xl border border-slate-200 px-3 py-2 text-sm">
        <select name="outcome" class="rounded-xl border border-slate-200 px-3 py-2 text-sm">
            <option value="">Every outcome</option>
            @foreach ($outcomes as $key => $label)<option value="{{ $key }}" @selected(request('outcome') === $key)>{{ $label }}</option>@endforeach
        </select>
        <select name="type" class="rounded-xl border border-slate-200 px-3 py-2 text-sm">
            <option value="">Every event</option>
            @foreach ($types as $key => $label)<option value="{{ $key }}" @selected(request('type') === $key)>{{ $label }}</option>@endforeach
        </select>
        <div class="flex gap-2">
            <select name="source" class="flex-1 rounded-xl border border-slate-200 px-3 py-2 text-sm">
                <option value="">Anyone</option>
                @foreach ($sources as $key => $label)<option value="{{ $key }}" @selected(request('source') === $key)>{{ $label }}</option>@endforeach
            </select>
            <button class="px-4 py-2 rounded-xl bg-navy text-white text-xs font-bold">Filter</button>
        </div>
    </form>

    <div class="naoero-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-4 py-3 text-left">When</th>
                        <th class="px-4 py-3 text-left">Order</th>
                        <th class="px-4 py-3 text-left">Event</th>
                        <th class="px-4 py-3 text-left">Outcome</th>
                        <th class="px-4 py-3 text-left">Details</th>
                        <th class="px-4 py-3 text-left">By</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($events as $e)
                        <tr class="hover:bg-slate-50/70 align-top">
                            <td class="px-4 py-3 text-xs text-slate-600 whitespace-nowrap">{{ $when($e->created_at) }}</td>
                            <td class="px-4 py-3"><a href="{{ route('admin.sales.show', $e->shop_order_id) }}" class="font-extrabold text-navy whitespace-nowrap">{{ $e->order_number }}</a></td>
                            <td class="px-4 py-3 text-xs font-bold text-slate-700 whitespace-nowrap">{{ $types[$e->type] ?? $e->type }}</td>
                            <td class="px-4 py-3">@include('admin.sales._tag', ['tag' => $e->outcome])</td>
                            <td class="px-4 py-3 text-xs text-slate-600">{{ $e->message }}@if ($e->amount !== null)<span class="font-bold text-navy"> · ${{ number_format((float) $e->amount, 2) }}</span>@endif</td>
                            <td class="px-4 py-3 text-xs text-slate-500 whitespace-nowrap">{{ $e->staff_name ?? ($sources[$e->source] ?? $e->source) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-14 text-center text-sm text-slate-400">Nothing recorded yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t border-slate-100">{{ $events->links() }}</div>
    </div>
</div>
@endsection
