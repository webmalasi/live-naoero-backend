@extends('layouts.admin')

@section('title', 'Shop Help Requests')

@section('content')
@php
    $when = fn ($v) => $v ? \Carbon\Carbon::parse($v)->timezone(config('app.timezone'))->format('j M Y, H:i') : '—';
    $tag = ['open' => 'bg-amber-100 text-amber-800', 'answered' => 'bg-sky-100 text-sky-800', 'closed' => 'bg-slate-100 text-slate-500'];
@endphp
<div class="max-w-7xl mx-auto space-y-6">
    @include('admin.sales._nav', [
        'crumb' => 'Help Requests',
        'title' => 'Help Requests',
        'subtitle' => 'Customers asking about their orders from the app and the website. Your reply is a notice in their inbox.',
    ])

    <form method="GET" class="naoero-card p-4 space-y-3">
        <div class="flex items-center gap-1.5 overflow-x-auto border-b border-slate-100 pb-2.5">
            <a href="{{ route('admin.sales.support') }}" class="px-3 py-1.5 rounded-lg text-xs font-bold {{ request('status') ? 'text-slate-600 hover:bg-slate-100' : 'bg-navy text-white' }}">All <span class="opacity-60">{{ $counts->sum() }}</span></a>
            @foreach ($statuses as $key => $label)
                <a href="{{ route('admin.sales.support', ['status' => $key]) }}" class="px-3 py-1.5 rounded-lg text-xs font-bold whitespace-nowrap {{ request('status') === $key ? 'bg-navy text-white' : 'text-slate-600 hover:bg-slate-100' }}">{{ $label }} <span class="opacity-60">{{ $counts[$key] ?? 0 }}</span></a>
            @endforeach
        </div>
        <div class="flex gap-2">
            @if (request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
            <input name="search" value="{{ request('search') }}" placeholder="Request number, order number or customer email" class="flex-1 rounded-xl border border-slate-200 px-3 py-2 text-sm">
            <button class="px-4 py-2 rounded-xl bg-navy text-white text-xs font-bold">Search</button>
        </div>
    </form>

    <div class="naoero-card overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500">
                <tr><th class="px-4 py-3 text-left">Request</th><th class="px-4 py-3 text-left">Customer</th><th class="px-4 py-3 text-left">Topic</th><th class="px-4 py-3 text-left">Status</th><th class="px-4 py-3 text-left">Last message</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($requests as $r)
                    <tr class="hover:bg-slate-50/70">
                        <td class="px-4 py-2.5"><a href="{{ route('admin.sales.support.show', $r->id) }}" class="font-mono font-extrabold text-navy">{{ $r->number }}</a>
                            @if ($r->order_number)<div class="text-[11px] text-slate-400">Order {{ $r->order_number }}</div>@endif</td>
                        <td class="px-4 py-2.5 text-xs"><div class="font-semibold text-slate-700">{{ trim($r->first_name.' '.$r->last_name) ?: '—' }}</div><div class="text-slate-400">@include('admin.customers._identity', ['email' => $r->customer_main_email ?? null, 'login' => $r->zenith_login])</div></td>
                        <td class="px-4 py-2.5 text-xs text-slate-700">{{ $topics[$r->topic] ?? $r->topic }} <span class="text-slate-400">· {{ $r->messages }} message(s) · {{ $r->channel }}</span></td>
                        <td class="px-4 py-2.5"><span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold uppercase {{ $tag[$r->status] ?? '' }}">{{ $statuses[$r->status] ?? $r->status }}</span></td>
                        <td class="px-4 py-2.5 text-xs text-slate-500">{{ $when($r->last_message_at) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-14 text-center text-sm text-slate-400">No help requests.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-4 py-3 border-t border-slate-100">{{ $requests->links() }}</div>
    </div>
</div>
@endsection
