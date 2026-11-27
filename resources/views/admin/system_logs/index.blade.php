@extends('layouts.admin')

@section('title', 'API, App & Login Logs')

@section('content')
@php
    $when = fn ($v) => $v ? \Carbon\Carbon::parse($v)->timezone(config('app.timezone'))->format('j M, H:i:s') : '—';
    $levelTag = fn ($l) => ['error' => 'failed', 'warn' => 'warning'][$l] ?? 'info';
    $tabs = ['api' => 'API & server', 'app' => 'Mobile app', 'logins' => 'Sign-ins & security'];
@endphp
<div class="max-w-7xl mx-auto space-y-6">
    <div>
        <div class="flex items-center gap-2 text-xs font-semibold text-ink-muted mb-1"><span>System & Settings</span><span>/</span><span class="text-navy font-bold">API, App & Login Logs</span></div>
        <h1 class="text-2xl font-extrabold text-navy tracking-tight">API, App & Login Logs</h1>
        <p class="text-sm text-ink-muted mt-0.5">Every API request, Zenith and NAB call, what the apps report, and every sign-in. Passwords, tokens and keys are removed before anything is stored. Kept 90 days.</p>
    </div>

    <div class="flex flex-wrap items-center gap-3">
        <div class="flex gap-1 p-1 rounded-xl bg-white border border-slate-200 shadow-sm">
            @foreach ($tabs as $k => $label)
                <a href="{{ route('admin.system-logs.index', ['tab' => $k]) }}" class="px-3.5 py-2 rounded-lg text-xs font-bold {{ $tab === $k ? 'bg-navy text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100' }}">{{ $label }}</a>
            @endforeach
        </div>
        <div class="flex gap-2 text-xs text-slate-500">
            <span class="font-bold">Last 24 hours:</span>
            @if ($tab === 'logins')
                <span class="text-emerald-700 font-bold">{{ $summary[1] ?? $summary['1'] ?? $summary[true] ?? 0 }} succeeded</span><span class="text-rose-700 font-bold">{{ $summary[0] ?? $summary['0'] ?? 0 }} failed</span>
            @else
                @foreach (['info' => 'text-slate-600', 'warn' => 'text-orange-600', 'error' => 'text-rose-700'] as $lv => $cls)
                    <span class="{{ $cls }} font-bold">{{ $summary[$lv] ?? 0 }} {{ $lv }}</span>
                @endforeach
            @endif
        </div>
    </div>

    <form method="GET" class="naoero-card p-4 grid grid-cols-1 md:grid-cols-5 gap-2.5">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <input name="search" value="{{ request('search') }}" placeholder="{{ $tab === 'api' ? 'Message, path, customer email or request id' : ($tab === 'app' ? 'Event, message, device id or customer email' : 'Email, IP or detail') }}" class="md:col-span-2 rounded-xl border border-slate-200 px-3 py-2 text-sm">
        <select name="level" class="rounded-xl border border-slate-200 px-3 py-2 text-sm">
            <option value="">{{ $tab === 'logins' ? 'Any result' : 'Any level' }}</option>
            @foreach ($tab === 'logins' ? ['success' => 'Succeeded', 'failed' => 'Failed'] : ['error' => 'Errors', 'warn' => 'Warnings', 'info' => 'Info', 'debug' => 'Debug'] as $k => $v)
                <option value="{{ $k }}" @selected(request('level') === $k)>{{ $v }}</option>
            @endforeach
        </select>
        @if ($tab !== 'app')
            <select name="source" class="rounded-xl border border-slate-200 px-3 py-2 text-sm">
                <option value="">{{ $tab === 'logins' ? 'Every event' : 'Every source' }}</option>
                @foreach ($sources as $s)<option value="{{ $s }}" @selected(request('source') === $s)>{{ ucfirst(str_replace('_', ' ', $s)) }}</option>@endforeach
            </select>
        @else
            <div></div>
        @endif
        <button class="px-4 py-2 rounded-xl bg-navy text-white text-xs font-bold">Filter</button>
    </form>

    <div class="naoero-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-xs">
                @if ($tab === 'api')
                    <thead class="bg-slate-50 text-[10px] uppercase tracking-wider text-slate-500"><tr><th class="px-4 py-2.5 text-left">Time</th><th class="px-4 py-2.5 text-left">Level</th><th class="px-4 py-2.5 text-left">Source</th><th class="px-4 py-2.5 text-left">Message</th><th class="px-4 py-2.5 text-right">ms</th><th class="px-4 py-2.5 text-left">Customer / IP</th></tr></thead>
                    <tbody class="divide-y divide-slate-100" x-data>
                        @forelse ($rows as $l)
                            <tr class="align-top hover:bg-slate-50/70">
                                <td class="px-4 py-2 whitespace-nowrap text-slate-600">{{ $when($l->logged_at) }}</td>
                                <td class="px-4 py-2">@include('admin.sales._tag', ['tag' => $levelTag($l->level), 'text' => strtoupper($l->level)])</td>
                                <td class="px-4 py-2 font-bold text-slate-700">{{ $l->source }}</td>
                                <td class="px-4 py-2 text-slate-700">
                                    {{ $l->message }}
                                    @if ($l->detail)<details class="mt-1"><summary class="text-[10px] text-navy cursor-pointer">details</summary><pre class="mt-1 whitespace-pre-wrap text-[10px] text-slate-500">{{ json_encode(json_decode($l->detail), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre></details>@endif
                                    @if ($l->request_id)<a href="{{ route('admin.system-logs.index', ['tab' => 'api', 'search' => $l->request_id]) }}" class="block text-[10px] text-slate-400 font-mono hover:text-navy">request {{ \Illuminate\Support\Str::limit($l->request_id, 8, '') }}</a>@endif
                                </td>
                                <td class="px-4 py-2 text-right text-slate-500">{{ $l->duration_ms }}</td>
                                <td class="px-4 py-2 text-slate-500">@if ($l->customer_id)<a href="{{ route('admin.customers.show', $l->customer_id) }}" class="text-navy font-semibold">{{ $l->customer_email }}</a>@endif<div class="font-mono text-[10px]">{{ $l->ip }}</div></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-12 text-center text-slate-400">Nothing logged.</td></tr>
                        @endforelse
                    </tbody>
                @elseif ($tab === 'app')
                    <thead class="bg-slate-50 text-[10px] uppercase tracking-wider text-slate-500"><tr><th class="px-4 py-2.5 text-left">Phone time</th><th class="px-4 py-2.5 text-left">Level</th><th class="px-4 py-2.5 text-left">Event</th><th class="px-4 py-2.5 text-left">Message</th><th class="px-4 py-2.5 text-left">Device</th><th class="px-4 py-2.5 text-left">Customer</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($rows as $l)
                            <tr class="align-top hover:bg-slate-50/70">
                                <td class="px-4 py-2 whitespace-nowrap text-slate-600">{{ $when($l->client_time ?? $l->received_at) }}</td>
                                <td class="px-4 py-2">@include('admin.sales._tag', ['tag' => $levelTag($l->level), 'text' => strtoupper($l->level)])</td>
                                <td class="px-4 py-2 font-bold text-slate-700">{{ $l->event }}</td>
                                <td class="px-4 py-2 text-slate-600">{{ $l->message }}@if ($l->detail)<details><summary class="text-[10px] text-navy cursor-pointer">details</summary><pre class="whitespace-pre-wrap text-[10px] text-slate-500">{{ json_encode(json_decode($l->detail), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre></details>@endif</td>
                                <td class="px-4 py-2 text-slate-500"><a href="{{ route('admin.system-logs.index', ['tab' => 'app', 'search' => $l->device_id]) }}" class="font-mono hover:text-navy">{{ \Illuminate\Support\Str::limit($l->device_id, 12) }}</a><div>{{ $l->platform }} {{ $l->app_version }}</div></td>
                                <td class="px-4 py-2">@if ($l->customer_id)<a href="{{ route('admin.customers.show', $l->customer_id) }}" class="text-navy font-semibold">{{ $l->customer_email }}</a>@else<span class="text-slate-300">signed out</span>@endif</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-12 text-center text-slate-400">Nothing reported.</td></tr>
                        @endforelse
                    </tbody>
                @else
                    <thead class="bg-slate-50 text-[10px] uppercase tracking-wider text-slate-500"><tr><th class="px-4 py-2.5 text-left">Time</th><th class="px-4 py-2.5 text-left">Event</th><th class="px-4 py-2.5 text-left">Result</th><th class="px-4 py-2.5 text-left">Account</th><th class="px-4 py-2.5 text-left">Detail</th><th class="px-4 py-2.5 text-left">IP / device</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($rows as $l)
                            <tr class="align-top hover:bg-slate-50/70">
                                <td class="px-4 py-2 whitespace-nowrap text-slate-600">{{ $when($l->created_at) }}</td>
                                <td class="px-4 py-2 font-bold text-slate-700">{{ ucfirst(str_replace('_', ' ', $l->event)) }}</td>
                                <td class="px-4 py-2">@include('admin.sales._tag', ['tag' => $l->success ? 'success' : 'failed'])</td>
                                <td class="px-4 py-2">@if ($l->customer_id)<a href="{{ route('admin.customers.show', $l->customer_id) }}" class="text-navy font-semibold">{{ $l->login ?? $l->customer_email }}</a>@else<span class="text-slate-600">{{ $l->login ?? '—' }}</span>@endif</td>
                                <td class="px-4 py-2 text-slate-500">{{ $l->detail }}</td>
                                <td class="px-4 py-2 text-[10px] text-slate-400"><a href="{{ route('admin.system-logs.index', ['tab' => 'logins', 'search' => $l->ip]) }}" class="font-mono hover:text-navy">{{ $l->ip }}</a><div class="truncate max-w-[14rem]">{{ $l->user_agent }}</div></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-12 text-center text-slate-400">Nothing recorded.</td></tr>
                        @endforelse
                    </tbody>
                @endif
            </table>
        </div>
        <div class="px-4 py-3 border-t border-slate-100">{{ $rows->links() }}</div>
    </div>
</div>
@endsection
