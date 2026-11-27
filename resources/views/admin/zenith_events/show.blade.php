@extends('layouts.admin')

@section('title', 'Zenith Flight Event')

@section('content')
@php
    $when = fn ($v) => $v ? \Carbon\Carbon::parse($v)->timezone(config('app.timezone'))->format('j M Y, H:i:s') : '—';
    $statusTag = ['processed' => 'success', 'ignored' => 'cancelled', 'failed' => 'failed', 'received' => 'pending', 'unverified' => 'review'];
    $statusText = ['processed' => 'Processed', 'ignored' => 'Not acted on', 'failed' => 'Failed', 'received' => 'Waiting', 'unverified' => 'Unverified (key not accepted)'];
    $keyText = \App\Services\Notifications\FlightEvents::KEY_PROBLEMS;
    $labels = ['FlightChange' => 'Time change', 'SegmentFlightCancelled' => 'Cancellation', 'FlightTransfer' => 'Transfer', 'FlightReminder' => 'Flight reminder'];
@endphp
<div class="max-w-7xl mx-auto space-y-6" x-data="{ copied: false }">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-ink-muted mb-1"><span>System & Settings</span><span>/</span><a href="{{ route('admin.zenith-events.index', ['env' => $env]) }}" class="hover:text-navy">Zenith Flight Events</a><span>/</span><span class="text-navy font-bold">{{ $env === 'preproduction' ? 'Pre-Production' : 'Production' }}</span></div>
            <h1 class="text-2xl font-extrabold text-navy tracking-tight">{{ $labels[$e->event] ?? $e->event }} @if ($e->pnr)<span class="font-mono">· {{ $e->pnr }}</span>@endif</h1>
            <p class="text-sm text-ink-muted mt-0.5">Received {{ $when($e->received_at) }} from {{ $e->ip ?? 'an unknown IP' }}@if ($e->address) at <span class="font-mono">…/{{ $e->address }}</span>@endif.</p>
            @if (($e->key_status ?? 'valid') !== 'valid')
                <p class="text-sm font-semibold text-amber-800 mt-1">{{ $keyText[$e->key_status] ?? $e->key_status }} It was saved and answered 200, but no customer is notified unless you process it after checking it is genuine.</p>
            @endif
        </div>
        <div class="flex gap-2">
            @if (in_array($e->status, ['failed', 'received'], true))
                <form method="POST" action="{{ route('admin.zenith-events.retry', $e->id) }}">@csrf<button class="px-4 py-2 rounded-xl bg-navy text-white text-xs font-bold">Process again</button></form>
            @elseif ($e->status === 'unverified' && isset(\App\Services\Notifications\FlightEvents::TYPES[$e->event]))
                <form method="POST" action="{{ route('admin.zenith-events.retry', $e->id) }}" onsubmit="return confirm('Process this post and notify the customers holding the booking? Only do this if you are sure it came from Zenith.')">@csrf<button class="px-4 py-2 rounded-xl bg-amber-600 text-white text-xs font-bold">I checked it: process</button></form>
            @endif
            <a href="{{ route('admin.zenith-events.index', ['env' => $env]) }}" class="px-4 py-2 rounded-xl border border-slate-200 bg-white text-xs font-bold text-navy hover:bg-slate-50">Back to events</a>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="naoero-card p-4"><div class="text-[10px] uppercase tracking-wider text-slate-500 mb-1">We answered Zenith</div>
            @forelse ($calls as $c)
                <a href="{{ route('admin.zenith-events.call', $c->id) }}" class="block hover:opacity-80">@include('admin.sales._tag', ['tag' => $c->response_status == 200 ? 'success' : 'failed', 'text' => 'HTTP '.$c->response_status.' '.\Illuminate\Support\Str::limit($c->response_body, 20)]) <span class="text-[11px] text-slate-500">{{ $c->outcome === 'duplicate' ? 'retry' : ($c->outcome === 'recovered' ? 'turned away then, saved after the fix' : 'first post') }}, {{ $c->duration_ms }} ms</span></a>
            @empty
                <span class="text-[11px] text-slate-500">Not recorded (received before calls were saved).</span>
            @endforelse
        </div>
        <div class="naoero-card p-4"><div class="text-[10px] uppercase tracking-wider text-slate-500 mb-1">Processing</div>@include('admin.sales._tag', ['tag' => $statusTag[$e->status] ?? 'info', 'text' => $statusText[$e->status] ?? $e->status])<div class="text-[11px] text-slate-500 mt-1">{{ $e->attempts }} attempt(s) · {{ $when($e->processed_at) }}</div></div>
        <div class="naoero-card p-4"><div class="text-[10px] uppercase tracking-wider text-slate-500 mb-1">Notices sent</div><div class="text-2xl font-extrabold {{ $notices->count() ? 'text-emerald-700' : 'text-slate-400' }}">{{ $notices->count() }}</div></div>
        <div class="naoero-card p-4"><div class="text-[10px] uppercase tracking-wider text-slate-500 mb-1">Booking</div>
            <div class="font-mono font-bold text-navy">{{ $e->pnr ?? '—' }}</div>
            @foreach ($bookings as $b)<a href="{{ route('admin.flight-bookings.show', $b->id) }}" class="block text-[11px] text-navy font-semibold hover:underline">Open booking</a>@endforeach
            @if ($e->pnr && $bookings->isEmpty())<div class="text-[11px] text-slate-500">No customer of ours holds it.</div>@endif
        </div>
    </div>

    @if ($e->detail)
        <div class="naoero-card p-4 text-sm"><span class="font-bold text-navy">Result:</span> <span class="text-slate-700">{{ $e->detail }}</span></div>
    @endif

    @php
        $local = fn ($v) => $v ? \Carbon\Carbon::parse($v)->format('D j M Y, H:i') : '—';
        $hasNew = $e->new_departure_at || $e->new_flight_number;
        $rows = [
            'Flight' => [$e->flight_number ? $e->airline.$e->flight_number : null, $e->new_flight_number ? $e->new_airline.$e->new_flight_number : null],
            'From' => [$e->origin, $e->new_origin],
            'To' => [$e->destination, $e->new_destination],
            'Departs (local)' => [$local($e->departure_at), $hasNew ? $local($e->new_departure_at) : null],
            'Arrives (local)' => [$local($e->arrival_at), $hasNew ? $local($e->new_arrival_at) : null],
            'Aircraft' => [$e->equipment, $e->new_equipment],
        ];
    @endphp
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="naoero-card overflow-hidden lg:col-span-2">
            <div class="px-4 py-3 border-b border-slate-100 text-sm font-extrabold text-navy">What Zenith says</div>
            <table class="min-w-full text-xs">
                <thead class="bg-slate-50 text-[10px] uppercase tracking-wider text-slate-500"><tr><th class="px-4 py-2 text-left"></th><th class="px-4 py-2 text-left">{{ match ($e->event) { 'SegmentFlightCancelled' => 'Cancelled flight', 'FlightReminder' => 'Flight', default => 'Before' } }}</th>@if ($hasNew)<th class="px-4 py-2 text-left">Now</th>@endif</tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($rows as $label => [$was, $now])
                        <tr><td class="px-4 py-1.5 font-bold text-slate-500 whitespace-nowrap">{{ $label }}</td><td class="px-4 py-1.5 text-slate-700">{{ $was ?? '—' }}</td>@if ($hasNew)<td class="px-4 py-1.5 {{ $now !== null && $now !== $was ? 'font-bold text-navy' : 'text-slate-700' }}">{{ $now ?? '—' }}</td>@endif</tr>
                    @endforeach
                    @if ($e->origin_terminal || $e->destination_terminal)<tr><td class="px-4 py-1.5 font-bold text-slate-500">Terminals</td><td class="px-4 py-1.5 text-slate-700" colspan="2">{{ $e->origin_terminal ?? '—' }} → {{ $e->destination_terminal ?? '—' }}</td></tr>@endif
                    @if ($e->segment_ref)<tr><td class="px-4 py-1.5 font-bold text-slate-500">Segment ref</td><td class="px-4 py-1.5 font-mono text-slate-700" colspan="2">{{ $e->segment_ref }}</td></tr>@endif
                    @if ($e->reason)<tr><td class="px-4 py-1.5 font-bold text-slate-500">Reason</td><td class="px-4 py-1.5 text-slate-700" colspan="2">{{ $e->reason }}</td></tr>@endif
                </tbody>
            </table>
        </div>
        <div class="naoero-card p-4 text-xs space-y-1.5">
            <div class="text-sm font-extrabold text-navy mb-2">Customer named by Zenith</div>
            <div><span class="text-slate-500">Name:</span> <span class="font-semibold text-slate-700">{{ $e->customer_name ?: '—' }}</span></div>
            <div><span class="text-slate-500">Zenith ref:</span> <span class="font-mono text-slate-700">{{ $e->customer_ref ?? '—' }}</span></div>
            <div><span class="text-slate-500">Email:</span> <span class="text-slate-700">{{ $e->customer_email ?? '—' }}</span></div>
            <div><span class="text-slate-500">Phone:</span> <span class="text-slate-700">{{ $e->customer_phone ?? '—' }}</span></div>
        </div>
    </div>

    <div class="naoero-card overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-100 flex items-center justify-between">
            <div><div class="text-sm font-extrabold text-navy">The post as received</div><div class="text-[11px] text-slate-500">{{ strtoupper($e->format) }}, indented for reading.</div></div>
            <button type="button" @click="navigator.clipboard.writeText($refs.raw.innerText); copied = true; setTimeout(() => copied = false, 1500)" class="px-2.5 py-1.5 rounded-lg border border-slate-200 text-[11px] font-bold text-navy hover:bg-slate-50" x-text="copied ? 'Copied' : 'Copy'">Copy</button>
        </div>
        <pre x-ref="raw" class="p-4 text-[11px] leading-relaxed text-slate-700 bg-slate-50 overflow-x-auto max-h-[36rem] whitespace-pre">{{ $raw }}</pre>
    </div>

    <div class="naoero-card overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-100 text-sm font-extrabold text-navy">Notices sent to customers</div>
        <div class="divide-y divide-slate-100">
            @forelse ($notices as $n)
                <div class="p-4 text-sm">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="font-bold text-navy">{{ $n->title }}</div>
                        <div class="text-[11px] text-slate-500">
                            @if ($n->customer_id)<a href="{{ route('admin.customers.show', $n->customer_id) }}" class="text-navy font-semibold">{{ trim("{$n->first_name} {$n->last_name}") ?: $n->customer_email }}</a> · @endif
                            {{ $when($n->created_at) }} · {{ $n->read_at ? 'read '.$when($n->read_at) : 'not read yet' }}
                        </div>
                    </div>
                    <p class="text-slate-600 mt-1">{{ $n->body }}</p>
                </div>
            @empty
                <div class="p-6 text-center text-sm text-slate-400">No notice was sent for this post.</div>
            @endforelse
        </div>
    </div>

    @if ($payload)
        <details class="naoero-card overflow-hidden">
            <summary class="px-4 py-3 text-sm font-extrabold text-navy cursor-pointer">How we read it (parsed)</summary>
            <pre class="p-4 text-[11px] leading-relaxed text-slate-600 bg-slate-50 overflow-x-auto max-h-[36rem]">{{ $payload }}</pre>
        </details>
    @endif
</div>
@endsection
