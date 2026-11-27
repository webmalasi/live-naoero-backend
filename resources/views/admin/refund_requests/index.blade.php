@extends('layouts.admin')

@section('title', 'Refund Requests')

@section('content')
@php
    $when = fn ($v) => $v ? \Carbon\Carbon::parse($v)->timezone(config('app.timezone'))->format('j M Y, H:i') : '—';
    $open = ($counts['submitted'] ?? 0) + ($counts['in_review'] ?? 0);
@endphp
<div class="max-w-7xl mx-auto space-y-6">
    <div>
        <div class="flex items-center gap-2 text-xs font-semibold text-ink-muted mb-1"><span>Flight Network</span><span>/</span><span class="text-navy font-bold">Refund Requests</span></div>
        <h1 class="text-2xl font-extrabold text-navy tracking-tight">Refund Requests</h1>
        <p class="text-sm text-ink-muted mt-0.5">Tickets are non-refundable. Customers ask here from the app or website; decide each case and reply. Approved refunds are then made with the airline, outside this page.</p>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3.5">
        <a href="{{ route('admin.refund-requests.index', ['status' => 'open']) }}" class="naoero-card p-5 bg-gradient-to-br from-navy to-navy-900 text-white border-0">
            <span class="text-[11px] font-bold uppercase tracking-wider text-accent">Waiting for a decision</span>
            <p class="text-3xl font-extrabold mt-1.5">{{ $open }}</p>
            <p class="text-[11px] text-white/70 mt-1">{{ $counts['submitted'] ?? 0 }} new · {{ $counts['in_review'] ?? 0 }} in review</p>
        </a>
        <a href="{{ route('admin.refund-requests.index', ['status' => 'approved']) }}" class="naoero-card p-5">
            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Approved</span>
            <p class="text-2xl font-extrabold text-emerald-600 mt-1.5">{{ $counts['approved'] ?? 0 }}</p>
        </a>
        <a href="{{ route('admin.refund-requests.index', ['status' => 'declined']) }}" class="naoero-card p-5">
            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Declined</span>
            <p class="text-2xl font-extrabold text-rose-600 mt-1.5">{{ $counts['declined'] ?? 0 }}</p>
        </a>
        <a href="{{ route('admin.refund-requests.index', ['status' => 'closed']) }}" class="naoero-card p-5">
            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Closed</span>
            <p class="text-2xl font-extrabold text-slate-600 mt-1.5">{{ $counts['closed'] ?? 0 }}</p>
        </a>
    </div>

    <form method="GET" class="naoero-card p-4 space-y-3">
        <div class="flex items-center gap-1.5 overflow-x-auto border-b border-slate-100 pb-2.5">
            <a href="{{ route('admin.refund-requests.index', array_filter(['search' => request('search')])) }}" class="px-3 py-1.5 rounded-lg text-xs font-bold {{ ! request('status') ? 'bg-navy text-white' : 'text-slate-600 hover:bg-slate-100' }}">All</a>
            <a href="{{ route('admin.refund-requests.index', array_filter(['status' => 'open', 'search' => request('search')])) }}" class="px-3 py-1.5 rounded-lg text-xs font-bold whitespace-nowrap {{ request('status') === 'open' ? 'bg-navy text-white' : 'text-slate-600 hover:bg-slate-100' }}">Waiting</a>
            @foreach ($statuses as $k => $label)
                <a href="{{ route('admin.refund-requests.index', array_filter(['status' => $k, 'search' => request('search')])) }}" class="px-3 py-1.5 rounded-lg text-xs font-bold whitespace-nowrap {{ request('status') === $k ? 'bg-navy text-white' : 'text-slate-600 hover:bg-slate-100' }}">{{ $label }}</a>
            @endforeach
        </div>
        <div class="flex gap-2.5">
            @if (request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
            <input name="search" value="{{ request('search') }}" placeholder="Request number, PNR, customer or passenger" class="flex-1 rounded-xl border border-slate-200 px-3 py-2 text-sm">
            <button class="px-4 py-2 rounded-xl bg-navy text-white text-xs font-bold">Search</button>
        </div>
    </form>

    <div class="naoero-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500">
                    <tr><th class="px-4 py-3 text-left">Request</th><th class="px-4 py-3 text-left">Booking</th><th class="px-4 py-3 text-left">Customer</th><th class="px-4 py-3 text-left">Reason</th><th class="px-4 py-3 text-left">Status</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($requests as $r)
                        <tr class="hover:bg-slate-50/70 cursor-pointer" onclick="window.location='{{ route('admin.refund-requests.show', $r->id) }}'">
                            <td class="px-4 py-3"><a href="{{ route('admin.refund-requests.show', $r->id) }}" class="font-extrabold text-navy">{{ $r->reference }}</a><div class="text-[11px] text-slate-400">{{ $when($r->created_at) }} · {{ $r->channel === 'website' ? 'Website' : 'App' }}</div></td>
                            <td class="px-4 py-3"><span class="font-extrabold text-navy tracking-wider">{{ $r->pnr }}</span><div class="text-[11px] text-slate-400">{{ $r->passenger_names }}</div></td>
                            <td class="px-4 py-3 text-xs"><div class="font-semibold text-slate-700">{{ $r->customer_name }}</div><div class="text-slate-400">{{ $r->customer_email }}</div></td>
                            <td class="px-4 py-3 text-xs text-slate-600">{{ $reasons[$r->reason] ?? $r->reason }}</td>
                            <td class="px-4 py-3">@include('admin.refund_requests._status', ['status' => $r->status])</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-14 text-center text-sm text-slate-400">No refund requests match.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t border-slate-100">{{ $requests->links() }}</div>
    </div>
</div>
@endsection
