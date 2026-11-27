@extends('layouts.admin')

@section('title', 'Zenith Flight Events')

@section('content')
@php
    $when = fn ($v) => $v ? \Carbon\Carbon::parse($v)->timezone(config('app.timezone'))->format('j M Y, H:i:s') : '—';
    $statusTag = ['processed' => 'success', 'ignored' => 'cancelled', 'failed' => 'failed', 'received' => 'pending', 'unverified' => 'review'];
    $statusText = ['processed' => 'Processed', 'ignored' => 'Not acted on', 'failed' => 'Failed', 'received' => 'Waiting', 'unverified' => 'Unverified (key not accepted)'];
    $keyText = \App\Services\Notifications\FlightEvents::KEY_PROBLEMS;
    $labels = ['FlightChange' => 'Time change', 'SegmentFlightCancelled' => 'Cancellation', 'FlightTransfer' => 'Transfer', 'FlightReminder' => 'Flight reminder'];
    $envs = ['all' => 'All', 'production' => 'Production', 'preproduction' => 'Pre-Production'];
    $envTag = fn ($isTest) => $isTest
        ? '<span class="inline-block px-1.5 py-0.5 rounded bg-amber-50 border border-amber-200 text-[10px] font-bold text-amber-800">PRE-PROD</span>'
        : '<span class="inline-block px-1.5 py-0.5 rounded bg-sky-50 border border-sky-200 text-[10px] font-bold text-sky-800">PROD</span>';
    $recent = fn ($v) => $v && \Carbon\Carbon::parse($v)->gt(now()->subDays(7));
@endphp
<div class="max-w-7xl mx-auto space-y-6">
    <div>
        <div class="flex items-center gap-2 text-xs font-semibold text-ink-muted mb-1"><span>System & Settings</span><span>/</span><span class="text-navy font-bold">Zenith Flight Events</span></div>
        <h1 class="text-2xl font-extrabold text-navy tracking-tight">Zenith Flight Events</h1>
        <p class="text-sm text-ink-muted mt-0.5">What Zenith posts to our event addresses (time changes, cancellations, transfers), what we answered, and the notices each post sent to customers in the app and on the website.</p>
    </div>

    <div class="flex gap-1 p-1 rounded-xl bg-white border border-slate-200 shadow-sm w-fit">
        @foreach ($envs as $k => $label)
            <a href="{{ route('admin.zenith-events.index', ['env' => $k]) }}" class="px-5 py-2 rounded-lg text-sm font-bold {{ $env === $k ? 'bg-navy text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100' }}">{{ $label }}</a>
        @endforeach
    </div>

    @unless ($configured)
        <div class="naoero-card p-4 border-l-4 border-rose-500 text-sm text-rose-800"><span class="font-bold">ZENITH_WEBHOOK_SECRET is not set on the server.</span> Posts are still saved and answered 200, but as unverified: none notifies a customer until it is set in <code>.env</code> (64 random characters, e.g. <code>openssl rand -hex 32</code>).</div>
    @endunless

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4" x-data="{ copied: null }">
        @foreach ($stats as $event => $s)
            <div class="naoero-card p-4 space-y-3">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-sm font-extrabold text-navy">{{ $labels[$event] }}</div>
                        <div class="text-[11px] text-slate-400 font-mono">{{ $event }}</div>
                    </div>
                    @if ($recent($s['last']))
                        @include('admin.sales._tag', ['tag' => 'success', 'text' => 'Receiving'])
                    @elseif ($s['last'])
                        @include('admin.sales._tag', ['tag' => 'cancelled', 'text' => 'Nothing in 7 days'])
                    @else
                        @include('admin.sales._tag', ['tag' => 'pending', 'text' => 'Nothing yet'])
                    @endif
                </div>
                <div class="flex items-center gap-2">
                    <code class="flex-1 min-w-0 truncate text-[11px] bg-slate-50 border border-slate-200 rounded-lg px-2 py-1.5 text-slate-700" title="The webhook key is hidden; Copy gives the full address">{{ $configured ? $s['shown'] : $s['address'] }}</code>
                    <button type="button" @click="navigator.clipboard.writeText(@js($s['address'])); copied = @js($event); setTimeout(() => copied = null, 1500)" class="shrink-0 px-2.5 py-1.5 rounded-lg border border-slate-200 text-[11px] font-bold text-navy hover:bg-slate-50" x-text="copied === @js($event) ? 'Copied' : 'Copy'">Copy</button>
                </div>
                <div class="grid grid-cols-4 gap-2 text-center">
                    <div class="rounded-lg bg-slate-50 py-1.5"><div class="text-lg font-extrabold text-navy">{{ $s['total'] }}</div><div class="text-[10px] uppercase tracking-wider text-slate-500">received</div></div>
                    <div class="rounded-lg bg-slate-50 py-1.5"><div class="text-lg font-extrabold text-navy">{{ $s['day'] }}</div><div class="text-[10px] uppercase tracking-wider text-slate-500">last 24 h</div></div>
                    <div class="rounded-lg bg-slate-50 py-1.5"><div class="text-lg font-extrabold {{ $s['notified'] ? 'text-emerald-700' : 'text-navy' }}">{{ $s['notified'] }}</div><div class="text-[10px] uppercase tracking-wider text-slate-500">notices</div></div>
                    <div class="rounded-lg bg-slate-50 py-1.5"><div class="text-lg font-extrabold {{ $s['failed'] ? 'text-rose-700' : 'text-navy' }}">{{ $s['failed'] }}</div><div class="text-[10px] uppercase tracking-wider text-slate-500">failed</div></div>
                </div>
                @if ($s['unverified'])<div class="text-[11px] font-semibold text-amber-700">{{ $s['unverified'] }} unverified (key not accepted), waiting for review.</div>@endif
                <div class="text-[11px] text-slate-500">Last received: <span class="font-semibold text-slate-700">{{ $when($s['last']) }}</span></div>
            </div>
        @endforeach
    </div>

    <form method="GET" class="naoero-card p-4 grid grid-cols-1 md:grid-cols-5 gap-2.5">
        <input type="hidden" name="env" value="{{ $env }}">
        <input name="search" value="{{ request('search') }}" placeholder="Booking reference, IP or text in the post" class="md:col-span-2 rounded-xl border border-slate-200 px-3 py-2 text-sm">
        <select name="event" class="rounded-xl border border-slate-200 px-3 py-2 text-sm">
            <option value="">Every event</option>
            @foreach ($labels as $k => $v)<option value="{{ $k }}" @selected(request('event') === $k)>{{ $v }}</option>@endforeach
        </select>
        <select name="status" class="rounded-xl border border-slate-200 px-3 py-2 text-sm">
            <option value="">Any status</option>
            @foreach ($statusText as $k => $v)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>@endforeach
        </select>
        <button class="px-4 py-2 rounded-xl bg-navy text-white text-xs font-bold">Filter</button>
    </form>

    <div class="naoero-card overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-100 text-sm font-extrabold text-navy">Events received{{ $env === 'all' ? '' : ' ('.$envs[$env].')' }}</div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-xs">
                <thead class="bg-slate-50 text-[10px] uppercase tracking-wider text-slate-500"><tr><th class="px-4 py-2.5 text-left">Received</th><th class="px-4 py-2.5 text-left">Event</th><th class="px-4 py-2.5 text-left">Flight</th><th class="px-4 py-2.5 text-left">Booking</th><th class="px-4 py-2.5 text-left">Status</th><th class="px-4 py-2.5 text-left">Result</th><th class="px-4 py-2.5 text-right">Notices</th><th class="px-4 py-2.5 text-left">From IP</th><th></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($rows as $r)
                        <tr class="align-top hover:bg-slate-50/70">
                            <td class="px-4 py-2 whitespace-nowrap text-slate-600">{{ $when($r->received_at) }}</td>
                            <td class="px-4 py-2 font-bold text-slate-700">{{ $labels[$r->event] ?? $r->event }}<div class="text-[10px] font-normal text-slate-400 uppercase">{{ $r->format }} @if ($env === 'all') {!! $envTag($r->is_test) !!} @endif</div>@if ($r->address)<div class="text-[10px] font-mono font-normal text-slate-400">{{ $r->address }}</div>@endif @if (($r->key_status ?? 'valid') !== 'valid')<div class="text-[10px] font-semibold text-amber-700">{{ $keyText[$r->key_status] ?? $r->key_status }}</div>@endif</td>
                            <td class="px-4 py-2 text-slate-700 whitespace-nowrap">@if ($r->flight_number)<span class="font-bold">{{ $r->airline }}{{ $r->flight_number }}</span> {{ $r->origin }}–{{ $r->destination }}<div class="text-[10px] text-slate-500">{{ $r->departure_at ? \Carbon\Carbon::parse($r->departure_at)->format('j M H:i') : '' }}@if ($r->new_departure_at) → @if ($r->new_flight_number && $r->new_flight_number !== $r->flight_number){{ $r->new_airline }}{{ $r->new_flight_number }} @endif{{ \Carbon\Carbon::parse($r->new_departure_at)->format('j M H:i') }}@endif</div>@else<span class="text-slate-300">—</span>@endif</td>
                            <td class="px-4 py-2 font-mono font-bold text-navy">{{ $r->pnr ?? '—' }}</td>
                            <td class="px-4 py-2">@include('admin.sales._tag', ['tag' => $statusTag[$r->status] ?? 'info', 'text' => $statusText[$r->status] ?? $r->status])</td>
                            <td class="px-4 py-2 text-slate-500 max-w-xs">{{ \Illuminate\Support\Str::limit($r->detail, 120) }}@if ($r->attempts > 1)<div class="text-[10px] text-slate-400">{{ $r->attempts }} attempts</div>@endif</td>
                            <td class="px-4 py-2 text-right font-bold {{ $r->notified ? 'text-emerald-700' : 'text-slate-400' }}">{{ $r->notified }}</td>
                            <td class="px-4 py-2 font-mono text-[10px] text-slate-500">{{ $r->ip ?? '—' }}</td>
                            <td class="px-4 py-2 text-right"><a href="{{ route('admin.zenith-events.show', $r->id) }}" class="px-2.5 py-1 rounded-lg border border-slate-200 text-[11px] font-bold text-navy hover:bg-slate-50 whitespace-nowrap">View post</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-4 py-12 text-center text-slate-400">No {{ $envs[$env] }} events received yet. Once Zenith enables the events for our key, each post appears here.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t border-slate-100">{{ $rows->links() }}</div>
    </div>

    <div class="naoero-card overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-100 flex flex-wrap items-center justify-between gap-3">
            <div>
                <div class="text-sm font-extrabold text-navy">Every call to the {{ $env === 'all' ? 'event' : $envs[$env] }} addresses</div>
                <div class="text-[11px] text-slate-500">Each request with the response we sent, including ones turned away. Open one for its headers and bodies.</div>
            </div>
            <form method="GET" class="flex gap-2">
                <input type="hidden" name="env" value="{{ $env }}">
                <select name="outcome" onchange="this.form.submit()" class="rounded-xl border border-slate-200 px-3 py-1.5 text-xs">
                    <option value="">Every outcome</option>
                    @foreach ($outcomes as $k => $v)<option value="{{ $k }}" @selected(request('outcome') === $k)>{{ $v }}</option>@endforeach
                </select>
            </form>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-xs">
                <thead class="bg-slate-50 text-[10px] uppercase tracking-wider text-slate-500"><tr><th class="px-4 py-2.5 text-left">Time</th><th class="px-4 py-2.5 text-left">Address</th><th class="px-4 py-2.5 text-left">Outcome</th><th class="px-4 py-2.5 text-left">Our response</th><th class="px-4 py-2.5 text-right">Size</th><th class="px-4 py-2.5 text-right">ms</th><th class="px-4 py-2.5 text-left">From IP</th><th></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($calls as $c)
                        <tr class="hover:bg-slate-50/70">
                            <td class="px-4 py-2 whitespace-nowrap text-slate-600">{{ $when($c->received_at) }}</td>
                            <td class="px-4 py-2 font-mono text-[11px] text-slate-700">{{ $c->method }} {{ $c->path }}@if ($env === 'all') <div class="mt-0.5 font-sans">{!! $envTag($c->in_test) !!}</div>@endif</td>
                            <td class="px-4 py-2 text-slate-600">{{ $outcomes[$c->outcome] ?? $c->outcome }}</td>
                            <td class="px-4 py-2">@include('admin.sales._tag', ['tag' => $c->response_status == 200 ? 'success' : 'failed', 'text' => 'HTTP '.$c->response_status]) <span class="font-mono text-[10px] text-slate-500">{{ \Illuminate\Support\Str::limit($c->response_body, 40) }}</span></td>
                            <td class="px-4 py-2 text-right text-slate-500 whitespace-nowrap">{{ number_format($c->request_bytes) }} B</td>
                            <td class="px-4 py-2 text-right text-slate-500">{{ $c->duration_ms }}</td>
                            <td class="px-4 py-2 font-mono text-[10px] text-slate-500">{{ $c->ip ?? '—' }}</td>
                            <td class="px-4 py-2 text-right"><a href="{{ route('admin.zenith-events.call', $c->id) }}" class="px-2.5 py-1 rounded-lg border border-slate-200 text-[11px] font-bold text-navy hover:bg-slate-50 whitespace-nowrap">View call</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-8 text-center text-slate-400">No calls yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t border-slate-100">{{ $calls->links() }}</div>
    </div>
</div>
@endsection
