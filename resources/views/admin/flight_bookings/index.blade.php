@extends('layouts.admin')

@section('title', 'Flight Sales & Bookings')

@section('content')
@php
    $money = fn ($v) => '$'.number_format((float) $v, 2);
    $when = fn ($v) => $v ? \Carbon\Carbon::parse($v)->timezone(config('app.timezone'))->format('j M Y, H:i') : '—';
    $p = $stats['payments30'];
@endphp
<div class="max-w-7xl mx-auto space-y-6">
    <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-ink-muted mb-1"><span>Flight Network</span><span>/</span><span class="text-navy font-bold">Flight Sales & Bookings</span></div>
            <h1 class="text-2xl font-extrabold text-navy tracking-tight">Flight Sales & Bookings</h1>
            <p class="text-sm text-ink-muted mt-0.5">Bookings customers made through Zenith (PNR, flights, passengers, e-tickets) and every payment attempt on them.</p>
        </div>
        <a href="{{ route('admin.flight-bookings.index', [...request()->query(), 'export' => 'csv']) }}" class="self-start lg:self-auto px-4 py-2 rounded-xl border border-slate-200 bg-white text-xs font-bold text-slate-700 shadow-sm">⇩ Export CSV</a>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3.5">
        <div class="naoero-card p-5 bg-gradient-to-br from-navy to-navy-900 text-white border-0">
            <span class="text-[11px] font-bold uppercase tracking-wider text-accent">Ticketed sales</span>
            <p class="text-3xl font-extrabold mt-1.5">{{ $money($stats['revenue']) }}</p>
            <p class="text-[11px] text-white/70 mt-1">{{ $stats['ticketed'] }} ticketed of {{ $stats['total'] }} bookings</p>
        </div>
        <a href="{{ route('admin.flight-bookings.index', ['status' => 'pending_payment']) }}" class="naoero-card p-5">
            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500">On hold, unpaid</span>
            <p class="text-2xl font-extrabold text-amber-600 mt-1.5">{{ $stats['onHold'] }}</p>
            <p class="text-[11px] text-slate-400 mt-1">{{ $money($stats['onHoldValue']) }} awaiting payment</p>
        </a>
        <a href="{{ route('admin.flight-bookings.index', ['status' => 'expired']) }}" class="naoero-card p-5">
            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Expired unpaid</span>
            <p class="text-2xl font-extrabold text-slate-600 mt-1.5">{{ $stats['expired'] }}</p>
            <p class="text-[11px] text-slate-400 mt-1">Seats released by the airline</p>
        </a>
        <div class="naoero-card p-5">
            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Payments, 30 days</span>
            <p class="text-2xl font-extrabold text-navy mt-1.5">{{ $p['confirmed'] ?? 0 }} <span class="text-sm text-emerald-600">paid</span></p>
            <p class="text-[11px] text-slate-400 mt-1">{{ $p['declined'] ?? 0 }} declined · {{ ($p['cancelled'] ?? 0) + ($p['closed'] ?? 0) }} abandoned · {{ $p['failed'] ?? 0 }} failed · {{ $stats['last30'] }} new bookings</p>
        </div>
    </div>

    <form method="GET" class="naoero-card p-4 space-y-3">
        <div class="flex items-center gap-1.5 overflow-x-auto border-b border-slate-100 pb-2.5">
            <a href="{{ route('admin.flight-bookings.index', array_filter(['search' => request('search')])) }}" class="px-3 py-1.5 rounded-lg text-xs font-bold {{ ! request('status') ? 'bg-navy text-white' : 'text-slate-600 hover:bg-slate-100' }}">All</a>
            @foreach ($statuses as $k => $label)
                <a href="{{ route('admin.flight-bookings.index', array_filter(['status' => $k, 'search' => request('search')])) }}" class="px-3 py-1.5 rounded-lg text-xs font-bold whitespace-nowrap {{ request('status') === $k ? 'bg-navy text-white' : 'text-slate-600 hover:bg-slate-100' }}">{{ $label }}</a>
            @endforeach
        </div>
        <div class="grid grid-cols-1 md:grid-cols-5 gap-2.5">
            @if (request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
            <input name="search" value="{{ request('search') }}" placeholder="PNR, customer, passenger or flight" class="md:col-span-2 rounded-xl border border-slate-200 px-3 py-2 text-sm">
            <input type="date" name="from" value="{{ request('from') }}" class="rounded-xl border border-slate-200 px-3 py-2 text-sm">
            <input type="date" name="to" value="{{ request('to') }}" class="rounded-xl border border-slate-200 px-3 py-2 text-sm">
            <button class="px-4 py-2 rounded-xl bg-navy text-white text-xs font-bold">Search</button>
        </div>
    </form>

    <div class="naoero-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500">
                    <tr><th class="px-4 py-3 text-left">Booking</th><th class="px-4 py-3 text-left">Customer</th><th class="px-4 py-3 text-left">Flights</th><th class="px-4 py-3 text-center">Pax</th><th class="px-4 py-3 text-right">Total</th><th class="px-4 py-3 text-left">Status</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($bookings as $b)
                        @php $s = json_decode($b->summary, true) ?: []; $status = \App\Http\Controllers\Admin\FlightBookingController::status($b); @endphp
                        <tr class="hover:bg-slate-50/70 cursor-pointer" onclick="window.location='{{ route('admin.flight-bookings.show', $b->id) }}'">
                            <td class="px-4 py-3"><a href="{{ route('admin.flight-bookings.show', $b->id) }}" class="font-extrabold text-navy tracking-wider">{{ $b->pnr }}</a><div class="text-[11px] text-slate-400">{{ $when($b->created_at) }}</div>@if (($b->source ?? 'app') !== 'app')<span class="inline-block mt-0.5 rounded-full bg-sky-50 text-sky-700 text-[10px] font-bold px-2 py-0.5">{{ $b->source === 'account' ? 'From airline account' : 'Added by reference' }}</span>@endif @if ($b->hidden_at ?? null)<span class="inline-block mt-0.5 rounded-full bg-slate-100 text-slate-500 text-[10px] font-bold px-2 py-0.5">Removed by customer</span>@endif</td>
                            <td class="px-4 py-3 text-xs"><div class="font-semibold text-slate-700">{{ trim($b->first_name.' '.$b->last_name) }}</div><div class="text-slate-400">@include('admin.customers._identity', ['email' => $b->customer_main_email ?? null, 'login' => $b->zenith_login])</div></td>
                            <td class="px-4 py-3 text-xs">
                                @foreach ($s['flights'] ?? [] as $f)
                                    <div><b class="text-navy">{{ $f['origin'] }} → {{ $f['destination'] }}</b> {{ $f['flightNumber'] }} · {{ $f['departure'] ? \Carbon\Carbon::parse($f['departure'])->format('j M, H:i') : '' }}</div>
                                @endforeach
                            </td>
                            <td class="px-4 py-3 text-center font-bold">{{ count($s['passengers'] ?? []) }}</td>
                            <td class="px-4 py-3 text-right"><div class="font-extrabold text-navy">{{ $money($b->total_amount) }}</div>@if ((float) $b->amount_due > 0 && $status === 'pending_payment')<div class="text-[11px] text-amber-600 font-bold">due by {{ \Carbon\Carbon::parse($b->time_limit)->timezone(config('app.timezone'))->format('j M H:i') }}</div>@endif</td>
                            <td class="px-4 py-3">@include('admin.flight_bookings._status', ['status' => $status])@if ($b->payment_attempts)<div class="text-[10px] text-slate-400 mt-0.5">{{ $b->payment_attempts }} payment attempt{{ $b->payment_attempts > 1 ? 's' : '' }}</div>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-14 text-center text-sm text-slate-400">No bookings match.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t border-slate-100">{{ $bookings->links() }}</div>
    </div>
</div>
@endsection
