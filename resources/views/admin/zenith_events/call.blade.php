@extends('layouts.admin')

@section('title', 'Zenith Event Call')

@section('content')
@php
    $when = fn ($v) => $v ? \Carbon\Carbon::parse($v)->timezone(config('app.timezone'))->format('j M Y, H:i:s') : '—';
@endphp
<div class="max-w-7xl mx-auto space-y-6" x-data="{ copied: null, copy(ref) { navigator.clipboard.writeText(this.$refs[ref].innerText); this.copied = ref; setTimeout(() => this.copied = null, 1500) } }">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-ink-muted mb-1"><span>System & Settings</span><span>/</span><a href="{{ route('admin.zenith-events.index', ['env' => $env]) }}" class="hover:text-navy">Zenith Flight Events</a><span>/</span><span class="text-navy font-bold">{{ $env === 'preproduction' ? 'Pre-Production' : 'Production' }} call</span></div>
            <h1 class="text-xl font-extrabold text-navy tracking-tight font-mono">{{ $c->method }} {{ $c->path }}@if ($c->query_string)?{{ $c->query_string }}@endif</h1>
            <p class="text-sm text-ink-muted mt-0.5">{{ $when($c->received_at) }} from {{ $c->ip ?? 'an unknown IP' }} · {{ $outcomes[$c->outcome] ?? $c->outcome }}</p>
        </div>
        <div class="flex gap-2">
            @if ($c->zenith_event_id)<a href="{{ route('admin.zenith-events.show', $c->zenith_event_id) }}" class="px-4 py-2 rounded-xl bg-navy text-white text-xs font-bold">View the stored event</a>@endif
            <a href="{{ route('admin.zenith-events.index', ['env' => $env]) }}" class="px-4 py-2 rounded-xl border border-slate-200 bg-white text-xs font-bold text-navy hover:bg-slate-50">Back to events</a>
        </div>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="naoero-card p-4"><div class="text-[10px] uppercase tracking-wider text-slate-500 mb-1">Our response</div>@include('admin.sales._tag', ['tag' => $c->response_status == 200 ? 'success' : 'failed', 'text' => 'HTTP '.$c->response_status])</div>
        <div class="naoero-card p-4"><div class="text-[10px] uppercase tracking-wider text-slate-500 mb-1">Answered in</div><div class="text-lg font-extrabold text-navy">{{ $c->duration_ms }} ms</div></div>
        <div class="naoero-card p-4"><div class="text-[10px] uppercase tracking-wider text-slate-500 mb-1">Request body</div><div class="text-lg font-extrabold text-navy">{{ number_format($c->request_bytes) }} bytes</div><div class="text-[10px] text-slate-400 font-mono truncate" title="SHA-256 {{ $c->request_sha256 }}">sha256 {{ \Illuminate\Support\Str::limit($c->request_sha256, 16, '…') }}</div></div>
        <div class="naoero-card p-4"><div class="text-[10px] uppercase tracking-wider text-slate-500 mb-1">Event</div><div class="font-bold text-navy">{{ $c->event ?? 'Unknown' }}</div>@if ($c->request_id)<a href="{{ route('admin.system-logs.index', ['tab' => 'api', 'search' => $c->request_id]) }}" class="text-[11px] text-navy font-semibold hover:underline">Server log entries</a>@endif</div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="naoero-card overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-100 text-sm font-extrabold text-navy">Request headers</div>
            <table class="min-w-full text-[11px]">
                @foreach ($requestHeaders as $name => $value)
                    <tr class="border-b border-slate-50"><td class="px-4 py-1.5 font-mono font-bold text-slate-600 align-top whitespace-nowrap">{{ $name }}</td><td class="px-4 py-1.5 font-mono text-slate-700 break-all">{{ $value }}</td></tr>
                @endforeach
            </table>
            <div class="px-4 py-2 text-[10px] text-slate-400">Cookies and authorization headers are not saved.</div>
        </div>
        <div class="naoero-card overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-100 text-sm font-extrabold text-navy">Response headers</div>
            <table class="min-w-full text-[11px]">
                @foreach ($responseHeaders as $name => $value)
                    <tr class="border-b border-slate-50"><td class="px-4 py-1.5 font-mono font-bold text-slate-600 align-top whitespace-nowrap">{{ $name }}</td><td class="px-4 py-1.5 font-mono text-slate-700 break-all">{{ $value }}</td></tr>
                @endforeach
            </table>
        </div>
    </div>

    <div class="naoero-card overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-100 flex items-center justify-between">
            <div><div class="text-sm font-extrabold text-navy">Request body</div><div class="text-[11px] text-slate-500">{{ $c->content_type ?? 'No content type' }}@if ($c->request_body_truncated) · first 64 KB shown @endif</div></div>
            @if ($body !== null)<button type="button" @click="copy('body')" class="px-2.5 py-1.5 rounded-lg border border-slate-200 text-[11px] font-bold text-navy hover:bg-slate-50" x-text="copied === 'body' ? 'Copied' : 'Copy'">Copy</button>@endif
        </div>
        @if ($body !== null)
            <pre x-ref="body" class="p-4 text-[11px] leading-relaxed text-slate-700 bg-slate-50 overflow-x-auto max-h-[36rem] whitespace-pre">{{ $body }}</pre>
        @else
            <div class="p-6 text-center text-sm text-slate-400">The request had no body.</div>
        @endif
    </div>

    <div class="naoero-card overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-100 flex items-center justify-between">
            <div class="text-sm font-extrabold text-navy">Response body we sent</div>
            <button type="button" @click="copy('response')" class="px-2.5 py-1.5 rounded-lg border border-slate-200 text-[11px] font-bold text-navy hover:bg-slate-50" x-text="copied === 'response' ? 'Copied' : 'Copy'">Copy</button>
        </div>
        <pre x-ref="response" class="p-4 text-[11px] leading-relaxed text-slate-700 bg-slate-50 overflow-x-auto max-h-96 whitespace-pre-wrap">{{ $c->response_body }}</pre>
    </div>
</div>
@endsection
