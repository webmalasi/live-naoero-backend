@extends('layouts.admin')

@section('title', 'Booking '.$b->pnr)

@section('content')
@php
    $money = fn ($v) => '$'.number_format((float) $v, 2);
    $when = fn ($v) => $v ? \Carbon\Carbon::parse($v)->timezone(config('app.timezone'))->format('j M Y, H:i') : '—';
    $paymentTag = ['confirmed' => 'success', 'accepted' => 'pending', 'started' => 'pending', 'declined' => 'declined', 'failed' => 'failed', 'cancelled' => 'abandoned', 'closed' => 'abandoned'];
@endphp
<div class="max-w-7xl mx-auto space-y-6">
    <div class="flex items-center gap-2 text-xs font-semibold text-ink-muted">
        <a href="{{ route('admin.flight-bookings.index') }}" class="hover:text-navy">Flight Sales & Bookings</a><span>/</span><span class="text-navy font-bold">{{ $b->pnr }}</span>
    </div>

    <div class="naoero-card p-6 flex flex-col md:flex-row md:items-center gap-4">
        <div>
            <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Booking reference</div>
            <h1 class="text-3xl font-extrabold text-navy tracking-[0.15em]">{{ $b->pnr }}</h1>
            <div class="text-xs text-slate-500 mt-1">Booked {{ $when($b->created_at) }} · last synced with Zenith {{ $when($b->synced_at) }}</div>
            <div class="text-xs text-slate-500 mt-1">
                Source: <span class="font-bold text-navy">{{ \App\Services\Booking\BookingService::SOURCES[$b->source ?? 'app'] ?? 'Booked with Naoero Airlines' }}</span>
                @if ($b->linked_at) · linked {{ $when($b->linked_at) }}@endif
                @if ($b->verify_surname) · verified by passenger name {{ $b->verify_surname }}@endif
                @if ($b->hidden_at) · <span class="font-bold text-slate-600">removed from the customer's list {{ $when($b->hidden_at) }}</span>@endif
            </div>
        </div>
        <div class="md:ml-auto text-right space-y-1">
            @include('admin.flight_bookings._status', ['status' => $status])
            <div class="text-2xl font-extrabold text-navy">{{ $money($b->total_amount) }} <span class="text-xs text-slate-400">{{ $b->currency }}</span></div>
            @if ($status === 'pending_payment')<div class="text-xs font-bold text-amber-600">{{ $money($b->amount_due) }} due by {{ $when($b->time_limit) }}</div>@endif
        </div>
    </div>

    <div class="grid lg:grid-cols-3 gap-5">
        <div class="lg:col-span-2 space-y-5">
            <div class="naoero-card p-5">
                <h2 class="font-extrabold text-navy text-sm mb-3">Flights</h2>
                <div class="space-y-3">
                    @foreach ($summary['flights'] ?? [] as $f)
                        <div class="rounded-xl border border-slate-100 p-4 flex items-center gap-4">
                            <div class="text-center"><div class="text-xl font-extrabold text-navy">{{ $f['origin'] }}</div><div class="text-[11px] text-slate-500">{{ $f['departure'] ? \Carbon\Carbon::parse($f['departure'])->format('D j M, H:i') : '' }}</div></div>
                            <div class="flex-1 text-center text-[11px] text-slate-400"><div class="border-t-2 border-dashed border-slate-200 relative"><span class="absolute left-1/2 -translate-x-1/2 -top-2.5 bg-white px-2">✈</span></div><div class="mt-2 font-bold text-slate-600">{{ $f['flightNumber'] }} · {{ $f['status'] }}</div></div>
                            <div class="text-center"><div class="text-xl font-extrabold text-navy">{{ $f['destination'] }}</div><div class="text-[11px] text-slate-500">{{ $f['arrival'] ? \Carbon\Carbon::parse($f['arrival'])->format('D j M, H:i') : '' }}</div></div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="naoero-card overflow-hidden">
                <div class="px-5 py-3 border-b border-slate-100 font-extrabold text-navy text-sm">Passengers & e-tickets</div>
                <table class="min-w-full text-sm">
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($summary['passengers'] ?? [] as $p)
                            @php $ticket = collect($summary['tickets'] ?? [])->firstWhere('passengerRef', $p['ref']); @endphp
                            <tr>
                                <td class="px-5 py-3 font-bold text-slate-800">{{ $p['name'] }}</td>
                                <td class="px-5 py-3 text-xs text-slate-500">{{ ['AD' => 'Adult', 'CHD' => 'Child', 'INF' => 'Infant'][$p['type']] ?? $p['type'] }}</td>
                                <td class="px-5 py-3 text-xs font-mono">{{ $ticket['number'] ?? 'Not ticketed' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                @if (! empty($summary['ticketPrintUrl']))<div class="px-5 py-3 border-t border-slate-100"><a href="{{ $summary['ticketPrintUrl'] }}" target="_blank" rel="noopener" class="text-xs font-bold text-navy underline">Open e-ticket (PDF)</a></div>@endif
            </div>

            {{-- Seats, special services (with the airline's answer) and paid extras. --}}
            @php
                $paxName = fn ($ref) => collect($summary['passengers'] ?? [])->firstWhere('ref', $ref)['name'] ?? ($ref ? 'Passenger' : 'Whole booking');
                $route = fn ($ref) => ($s = collect($extras['segments'] ?? [])->firstWhere('ref', $ref)) ? $s['origin'].' → '.$s['destination'] : '—';
                $tone = fn ($state) => ['confirmed' => 'success', 'refused' => 'declined', 'cancelled' => 'cancelled'][$state] ?? 'pending';
            @endphp
            <div class="naoero-card overflow-hidden">
                <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between">
                    <span class="font-extrabold text-navy text-sm">Seats &amp; extras ({{ count($extras['seats']) + count($extras['services']) + count($extras['ancillaries']) }})</span>
                    @if ($extras['total'] > 0)<span class="text-xs font-bold text-slate-600">Paid extras: {{ $b->currency }} {{ number_format($extras['total'], 2) }}</span>@endif
                </div>
                @if (! $extras['seats'] && ! $extras['services'] && ! $extras['ancillaries'])
                    <p class="px-5 py-4 text-xs text-slate-400">No seats, special services or paid extras on this booking.</p>
                @else
                    <table class="min-w-full text-xs">
                        <thead class="bg-slate-50 text-[10px] uppercase tracking-wider text-slate-500"><tr><th class="px-4 py-2 text-left">Item</th><th class="px-4 py-2 text-left">Passenger</th><th class="px-4 py-2 text-left">Flight</th><th class="px-4 py-2 text-left">Status</th><th class="px-4 py-2 text-right">Price</th></tr></thead>
                        <tbody class="divide-y divide-slate-50">
                            @foreach ($extras['seats'] as $s)
                                <tr><td class="px-4 py-2 font-semibold">💺 Seat {{ $s['seat'] }}</td><td class="px-4 py-2">{{ $paxName($s['passengerRef']) }}</td><td class="px-4 py-2">{{ $route($s['segmentRef']) }}</td><td class="px-4 py-2">@include('admin.sales._tag', ['tag' => $tone($s['status']), 'text' => $s['statusLabel']])</td><td class="px-4 py-2 text-right">—</td></tr>
                            @endforeach
                            @foreach ($extras['ancillaries'] as $a)
                                <tr><td class="px-4 py-2 font-semibold">🧳 {{ $a['label'] }}@if ($a['emdNumber'])<div class="text-[10px] text-slate-400">EMD {{ $a['emdNumber'] }}</div>@endif</td><td class="px-4 py-2">{{ $paxName($a['passengerRef']) }}</td><td class="px-4 py-2">{{ $route($a['segmentRef']) }}</td><td class="px-4 py-2">@include('admin.sales._tag', ['tag' => $a['paid'] ? 'success' : 'pending', 'text' => $a['paid'] ? 'Paid' : 'Awaiting payment'])</td><td class="px-4 py-2 text-right font-semibold">{{ $b->currency }} {{ number_format($a['price'], 2) }}</td></tr>
                            @endforeach
                            @foreach ($extras['services'] as $s)
                                <tr><td class="px-4 py-2 font-semibold">{{ $s['label'] }} <span class="text-slate-400 font-normal">({{ $s['code'] }})</span>@if ($s['text'])<div class="text-[10px] text-slate-500">“{{ $s['text'] }}”</div>@endif</td><td class="px-4 py-2">{{ $paxName($s['passengerRef']) }}</td><td class="px-4 py-2">{{ $s['segmentRef'] ? $route($s['segmentRef']) : '—' }}</td><td class="px-4 py-2">@include('admin.sales._tag', ['tag' => $tone($s['status']), 'text' => $s['statusLabel']])</td><td class="px-4 py-2 text-right">Free request</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

            <div class="naoero-card overflow-hidden">
                <div class="px-5 py-3 border-b border-slate-100 font-extrabold text-navy text-sm">Payment attempts ({{ $payments->count() }})</div>
                <table class="min-w-full text-xs">
                    <thead class="bg-slate-50 text-[10px] uppercase tracking-wider text-slate-500"><tr><th class="px-4 py-2 text-left">Started</th><th class="px-4 py-2 text-left">Outcome</th><th class="px-4 py-2 text-left">Method</th><th class="px-4 py-2 text-right">Amount</th><th class="px-4 py-2 text-left">Detail</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($payments as $pay)
                            <tr>
                                <td class="px-4 py-2 whitespace-nowrap">{{ $when($pay->created_at) }}</td>
                                <td class="px-4 py-2">@include('admin.sales._tag', ['tag' => $paymentTag[$pay->status] ?? 'info', 'text' => ucfirst($pay->status)])</td>
                                <td class="px-4 py-2">{{ $pay->fop_code }}</td>
                                <td class="px-4 py-2 text-right font-bold">{{ $money($pay->amount) }} {{ $pay->currency }}</td>
                                <td class="px-4 py-2 text-slate-500">{{ $pay->detail ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-6 text-center text-slate-400">No payment started.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($cancellations->isNotEmpty())
                @php
                    $cancelTag = ['done' => 'success', 'failed' => 'failed', 'pending' => 'pending', 'unknown' => 'warning'];
                    $cancelMode = ['booking' => 'Whole booking', 'flights' => 'Some flights', 'refund' => 'Refund to voucher', 'keep_ticket' => 'Flights only, tickets kept'];
                @endphp
                <div class="naoero-card overflow-hidden">
                    <div class="px-5 py-3 border-b border-slate-100 font-extrabold text-navy text-sm">Cancellations by the customer ({{ $cancellations->count() }})</div>
                    <table class="min-w-full text-xs">
                        <thead class="bg-slate-50 text-[10px] uppercase tracking-wider text-slate-500"><tr><th class="px-4 py-2 text-left">When</th><th class="px-4 py-2 text-left">What</th><th class="px-4 py-2 text-left">Result</th><th class="px-4 py-2 text-right">To voucher</th><th class="px-4 py-2 text-right">Penalty</th><th class="px-4 py-2 text-left">Detail</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($cancellations as $c)
                                <tr>
                                    <td class="px-4 py-2 whitespace-nowrap">{{ $when($c->created_at) }}</td>
                                    <td class="px-4 py-2">{{ $cancelMode[$c->mode] ?? $c->mode }}<div class="text-[10px] text-slate-400 font-mono">{{ implode(', ', json_decode($c->segment_refs, true) ?: []) }}</div></td>
                                    <td class="px-4 py-2">@include('admin.sales._tag', ['tag' => $cancelTag[$c->status] ?? 'info', 'text' => ucfirst($c->status)])</td>
                                    <td class="px-4 py-2 text-right font-bold">{{ $c->refund_amount !== null ? $money($c->refund_amount).' '.$c->currency : '—' }}</td>
                                    <td class="px-4 py-2 text-right">{{ $c->penalty_amount !== null ? $money($c->penalty_amount) : '—' }}</td>
                                    <td class="px-4 py-2 text-slate-500">{{ $c->error_message ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="space-y-5">
            <div class="naoero-card p-5 space-y-1">
                <h2 class="font-extrabold text-navy text-sm mb-2">Customer</h2>
                <a href="{{ route('admin.customers.show', $b->customer_id) }}" class="font-bold text-navy underline">{{ trim($b->first_name.' '.$b->last_name) ?: $b->zenith_login }}</a>
                <div class="text-xs text-slate-500">@include('admin.customers._identity', ['email' => $b->customer_main_email ?? null, 'login' => $b->zenith_login])</div>
                @if ($b->contact_phone)
                    <div class="pt-2 text-xs"><div class="text-[10px] font-bold uppercase text-slate-400">Contact mobile (on the ticket)</div><a href="tel:{{ $b->contact_phone }}" class="text-navy hover:underline">{{ \App\Services\Support\Phones::display($b->contact_phone) }}</a></div>
                @endif
                @if ($b->customer_phone && $b->customer_phone !== $b->contact_phone)
                    <div class="pt-1 text-xs"><div class="text-[10px] font-bold uppercase text-slate-400">Account mobile</div><a href="tel:{{ $b->customer_phone }}" class="text-navy hover:underline">{{ \App\Services\Support\Phones::display($b->customer_phone) }}</a></div>
                @endif
                @if (! $b->contact_phone && ! $b->customer_phone)<div class="pt-2 text-xs text-slate-400">No phone number on this booking.</div>@endif
            </div>
            <div class="naoero-card p-5">
                <h2 class="font-extrabold text-navy text-sm mb-3">History</h2>
                <ol class="relative border-l-2 border-slate-100 ml-2 space-y-3">
                    @forelse ($events as $e)
                        <li class="ml-4">
                            <span class="absolute -left-[7px] mt-1 w-3 h-3 rounded-full ring-4 ring-white {{ $e->success ? 'bg-emerald-500' : 'bg-rose-500' }}"></span>
                            <div class="text-xs font-bold text-slate-800">{{ ['booking_create' => 'Booking created', 'payment_start' => 'Payment started', 'payment_result' => 'Payment result'][$e->event] ?? $e->event }}</div>
                            <div class="text-[11px] text-slate-500">{{ $e->detail }}</div>
                            <div class="text-[10px] text-slate-400">{{ $when($e->created_at) }}</div>
                        </li>
                    @empty
                        <li class="ml-4 text-xs text-slate-400">Nothing recorded.</li>
                    @endforelse
                </ol>
            </div>
            <details class="naoero-card p-5"><summary class="text-xs font-bold text-navy cursor-pointer">Full booking as Zenith returned it</summary>
                <pre class="mt-2 max-h-96 overflow-auto rounded-xl bg-slate-900 text-emerald-200 text-[11px] p-3">{{ json_encode(json_decode($b->zenith_booking), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
            </details>
        </div>
    </div>
</div>
@endsection
