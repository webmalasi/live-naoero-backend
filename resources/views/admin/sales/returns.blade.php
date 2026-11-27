@extends('layouts.admin')

@section('title', 'Shop Returns & Exchanges')

@section('content')
@php
    $when = fn ($v) => $v ? \Carbon\Carbon::parse($v)->timezone(config('app.timezone'))->format('j M Y, H:i') : '—';
@endphp
<div class="max-w-7xl mx-auto space-y-6">
    @include('admin.sales._nav', [
        'crumb' => 'Returns',
        'title' => 'Returns & Exchanges',
        'subtitle' => 'Delivered merchandise customers want to send back, within '.config('naoero.shop.return_window_days', 30).' days of delivery. Approve, receive, then refund or send the replacement.',
    ])

    <form method="GET" class="naoero-card p-4 space-y-3">
        <div class="flex items-center gap-1.5 overflow-x-auto border-b border-slate-100 pb-2.5">
            <a href="{{ route('admin.sales.returns') }}" class="px-3 py-1.5 rounded-lg text-xs font-bold {{ request('status') ? 'text-slate-600 hover:bg-slate-100' : 'bg-navy text-white' }}">All <span class="opacity-60">{{ $counts->sum() }}</span></a>
            @foreach ($statuses as $key => $label)
                <a href="{{ route('admin.sales.returns', ['status' => $key]) }}" class="px-3 py-1.5 rounded-lg text-xs font-bold whitespace-nowrap {{ request('status') === $key ? 'bg-navy text-white' : 'text-slate-600 hover:bg-slate-100' }}">{{ $label }} <span class="opacity-60">{{ $counts[$key] ?? 0 }}</span></a>
            @endforeach
        </div>
        <div class="flex flex-wrap gap-2">
            @if (request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
            <input name="search" value="{{ request('search') }}" placeholder="Return number, order number or customer email" class="flex-1 min-w-[14rem] rounded-xl border border-slate-200 px-3 py-2 text-sm">
            <select name="type" class="rounded-xl border border-slate-200 px-3 py-2 text-sm"><option value="">Returns and exchanges</option><option value="return" @selected(request('type') === 'return')>Returns</option><option value="exchange" @selected(request('type') === 'exchange')>Exchanges</option></select>
            <button class="px-4 py-2 rounded-xl bg-navy text-white text-xs font-bold">Filter</button>
        </div>
    </form>

    <div class="naoero-card overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500">
                <tr><th class="px-4 py-3 text-left">Return</th><th class="px-4 py-3 text-left">Customer</th><th class="px-4 py-3 text-left">Type · reason</th><th class="px-4 py-3 text-right">Units</th><th class="px-4 py-3 text-left">Status</th><th class="px-4 py-3 text-left">Requested</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($returns as $r)
                    <tr class="hover:bg-slate-50/70">
                        <td class="px-4 py-2.5"><a href="{{ route('admin.sales.returns.show', $r->id) }}" class="font-mono font-extrabold text-navy">{{ $r->return_number }}</a><div class="text-[11px] text-slate-400">Order {{ $r->order_number }}</div></td>
                        <td class="px-4 py-2.5 text-xs"><div class="font-semibold text-slate-700">{{ trim($r->first_name.' '.$r->last_name) ?: '—' }}</div><div class="text-slate-400">@include('admin.customers._identity', ['email' => $r->customer_main_email ?? null, 'login' => $r->zenith_login])</div></td>
                        <td class="px-4 py-2.5 text-xs text-slate-700">{{ ucfirst($r->type) }} · {{ $reasons[$r->reason] ?? $r->reason }}</td>
                        <td class="px-4 py-2.5 text-right font-bold">{{ $r->units }}</td>
                        <td class="px-4 py-2.5"><span class="px-2 py-0.5 rounded-full bg-slate-100 text-[10px] font-extrabold uppercase text-slate-700">{{ $statuses[$r->status] ?? $r->status }}</span></td>
                        <td class="px-4 py-2.5 text-xs text-slate-500">{{ $when($r->created_at) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-14 text-center text-sm text-slate-400">No returns.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-4 py-3 border-t border-slate-100">{{ $returns->links() }}</div>
    </div>
</div>
@endsection
