@extends('layouts.admin')

@section('title', 'Push Notifications')

@section('content')
@php
    $when = fn ($v) => $v ? \Carbon\Carbon::parse($v)->timezone(config('app.timezone'))->format('j M Y, H:i') : '—';
    $statusTag = ['sent' => 'success', 'failed' => 'failed', 'invalid_token' => 'cancelled', 'sending' => 'pending'];
    $statusText = ['sent' => 'Sent', 'failed' => 'Failed', 'invalid_token' => 'Token no longer valid', 'sending' => 'Sending'];
    $input = 'w-full px-3 py-2 text-xs rounded-lg border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-accent/40';
    $label = 'block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1';
    $apns = $providers['apns'];
    $fcm = $providers['fcm'];
@endphp
<div class="max-w-7xl mx-auto space-y-6">
    <div>
        <div class="flex items-center gap-2 text-xs font-semibold text-ink-muted mb-1"><span>System & Settings</span><span>/</span><span class="text-navy font-bold">Push Notifications</span></div>
        <h1 class="text-2xl font-extrabold text-navy tracking-tight">Push Notifications</h1>
        <p class="text-sm text-ink-muted mt-0.5">Every notice in a customer's inbox (flight changes from the airline, bookings, shop orders) is also pushed to their phones. iPhones are sent to through Apple (APNs) and Android phones through Firebase Cloud Messaging, which is only the delivery pipe: device IDs, tokens and keys are all kept in our database.</p>
    </div>

    @if ($errors->any())
        <div class="naoero-card p-4 border-l-4 border-rose-500 text-sm text-rose-800 space-y-1">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
    @endif

    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3">
        @foreach ([
            ['Phones signed in', $stats['devices']],
            ['Can receive pushes', $stats['reachable']],
            ['iPhones', $stats['ios']],
            ['Android', $stats['android']],
            ['Sent, last 24 h', $stats['sent_day']],
            ['Failed, last 24 h', $stats['failed_day']],
        ] as [$name, $value])
            <div class="naoero-card p-4"><div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">{{ $name }}</div><div class="text-2xl font-extrabold text-navy mt-1">{{ number_format($value) }}</div></div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        {{-- Apple --}}
        <form method="POST" action="{{ route('admin.push.apns') }}" enctype="multipart/form-data" class="naoero-card p-5 space-y-4">
            @csrf
            <div class="flex items-start justify-between gap-3">
                <div>
                    <div class="text-sm font-extrabold text-navy">Apple Push (APNs): iPhones</div>
                    <div class="text-[11px] text-slate-500">Apple Developer > Certificates, IDs & Profiles > Keys: a key with Apple Push Notifications service. Sent straight to Apple; no Firebase.</div>
                </div>
                @include('admin.sales._tag', ['tag' => $apns['ready'] ? 'success' : ($apns['complete'] ? 'cancelled' : 'pending'), 'text' => $apns['ready'] ? 'Sending' : ($apns['complete'] ? 'Switched off' : 'Not set up')])
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div><label class="{{ $label }}">Team ID</label><input name="team_id" value="{{ old('team_id', $apns['config']['team_id']) }}" class="{{ $input }} font-mono" placeholder="ABCDE12345" @disabled(! $canEdit)></div>
                <div><label class="{{ $label }}">Key ID</label><input name="key_id" value="{{ old('key_id', $apns['config']['key_id']) }}" class="{{ $input }} font-mono" placeholder="XYZ987WXYZ" @disabled(! $canEdit)></div>
                <div><label class="{{ $label }}">App bundle ID</label><input name="bundle_id" value="{{ old('bundle_id', $apns['config']['bundle_id']) }}" class="{{ $input }} font-mono" placeholder="com.naoero.airlines" @disabled(! $canEdit)></div>
            </div>
            <div>
                <label class="{{ $label }}">.p8 key {!! $apns['config']['has_key'] ? '<span class="normal-case text-emerald-600">(saved; leave empty to keep it)</span>' : '' !!}</label>
                <textarea name="private_key" rows="3" class="{{ $input }} font-mono" placeholder="-----BEGIN PRIVATE KEY-----" @disabled(! $canEdit)></textarea>
                <input type="file" name="private_key_file" accept=".p8" class="mt-2 text-xs" @disabled(! $canEdit)>
            </div>
            <div class="flex items-center justify-between gap-3">
                <label class="flex items-center gap-2 text-xs font-semibold text-slate-700"><input type="hidden" name="enabled" value="0"><input type="checkbox" name="enabled" value="1" class="rounded border-slate-300 text-accent focus:ring-accent" @checked(old('enabled', $apns['enabled'])) @disabled(! $canEdit)> Send pushes to iPhones</label>
                @if ($canEdit)<button class="px-4 py-2 rounded-lg bg-navy text-white text-xs font-bold hover:bg-navy/90">Save Apple settings</button>@endif
            </div>
            <div class="text-[11px] text-slate-500 border-t border-slate-100 pt-3">
                Development builds use Apple's sandbox, App Store and TestFlight builds production; each phone tells us which. Updated {{ $when($apns['row']->updated_at ?? null) }}.
                @if ($apns['row']?->last_test_at) Last test {{ $when($apns['row']->last_test_at) }}: {{ $apns['row']->last_test_result }}. @endif
            </div>
        </form>

        {{-- Google --}}
        <form method="POST" action="{{ route('admin.push.fcm') }}" enctype="multipart/form-data" class="naoero-card p-5 space-y-4">
            @csrf
            <div class="flex items-start justify-between gap-3">
                <div>
                    <div class="text-sm font-extrabold text-navy">Firebase Cloud Messaging (FCM): Android</div>
                    <div class="text-[11px] text-slate-500">Firebase console > Project settings. The Android app must be added with the package name <code>com.example.nauru_airlines_updated</code>.</div>
                </div>
                @include('admin.sales._tag', ['tag' => $fcm['ready'] ? 'success' : ($fcm['complete'] ? 'cancelled' : 'pending'), 'text' => $fcm['ready'] ? 'Sending' : ($fcm['complete'] ? 'Switched off' : 'Not set up')])
            </div>
            <div>
                <label class="{{ $label }}">Service account key (JSON) {!! $fcm['config']['has_key'] ? '<span class="normal-case text-emerald-600">(saved for '.e($fcm['config']['client_email']).'; leave empty to keep it)</span>' : '' !!}</label>
                <textarea name="service_account" rows="3" class="{{ $input }} font-mono" placeholder='{"type": "service_account", "project_id": ...}' @disabled(! $canEdit)></textarea>
                <input type="file" name="service_account_file" accept=".json" class="mt-2 text-xs" @disabled(! $canEdit)>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div><label class="{{ $label }}">Android app ID</label><input name="app_id" value="{{ old('app_id', $fcm['config']['app_id']) }}" class="{{ $input }} font-mono" placeholder="1:1234567890:android:abc123" @disabled(! $canEdit)></div>
                <div><label class="{{ $label }}">Web API key</label><input name="api_key" value="{{ old('api_key', $fcm['config']['api_key']) }}" class="{{ $input }} font-mono" placeholder="AIza..." @disabled(! $canEdit)></div>
                <div><label class="{{ $label }}">Sender ID</label><input name="sender_id" value="{{ old('sender_id', $fcm['config']['sender_id']) }}" class="{{ $input }} font-mono" placeholder="from the app ID" @disabled(! $canEdit)></div>
            </div>
            <div class="flex items-center justify-between gap-3">
                <label class="flex items-center gap-2 text-xs font-semibold text-slate-700"><input type="hidden" name="enabled" value="0"><input type="checkbox" name="enabled" value="1" class="rounded border-slate-300 text-accent focus:ring-accent" @checked(old('enabled', $fcm['enabled'])) @disabled(! $canEdit)> Send pushes to Android phones</label>
                @if ($canEdit)<button class="px-4 py-2 rounded-lg bg-navy text-white text-xs font-bold hover:bg-navy/90">Save Firebase settings</button>@endif
            </div>
            <div class="text-[11px] text-slate-500 border-t border-slate-100 pt-3">
                The app ID, API key and sender ID are sent to the app so it can get a token; the service account key never leaves the server. Project {{ $fcm['config']['project_id'] ?: '—' }}. Updated {{ $when($fcm['row']->updated_at ?? null) }}.
                @if ($fcm['row']?->last_test_at) Last test {{ $when($fcm['row']->last_test_at) }}: {{ $fcm['row']->last_test_result }}. @endif
            </div>
        </form>
    </div>

    {{-- Devices --}}
    <div class="naoero-card overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-100 flex flex-wrap items-center justify-between gap-3">
            <div>
                <div class="text-sm font-extrabold text-navy">Registered phones</div>
                <div class="text-[11px] text-slate-500">Each app install (its device UID), registered on every launch and sign-in. A phone gets pushes only while its sign-in session is active.</div>
            </div>
            <form method="GET" class="flex flex-wrap gap-2">
                <input name="search" value="{{ $search }}" placeholder="Email, device UID or model" class="rounded-xl border border-slate-200 px-3 py-1.5 text-xs w-56">
                <select name="platform" onchange="this.form.submit()" class="rounded-xl border border-slate-200 px-3 py-1.5 text-xs">
                    <option value="">iOS and Android</option>
                    <option value="ios" @selected(request('platform') === 'ios')>iOS</option>
                    <option value="android" @selected(request('platform') === 'android')>Android</option>
                </select>
            </form>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-xs">
                <thead class="bg-slate-50 text-[10px] uppercase tracking-wider text-slate-500"><tr><th class="px-4 py-2.5 text-left">Customer</th><th class="px-4 py-2.5 text-left">Phone</th><th class="px-4 py-2.5 text-left">App</th><th class="px-4 py-2.5 text-left">Push</th><th class="px-4 py-2.5 text-left">Registered</th><th class="px-4 py-2.5 text-left">Last push</th><th></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($devices as $d)
                        @php
                            $muted = json_decode($d->muted_categories ?? '[]', true) ?: [];
                            $state = match (true) {
                                $d->customer_id === null => ['cancelled', 'Signed out'],
                                ! $d->live_sessions => ['cancelled', 'Session ended'],
                                $d->push_token === null => ['pending', 'No token yet'],
                                $d->token_invalid_at !== null => ['failed', 'Token no longer valid'],
                                ! $d->notifications_enabled => ['cancelled', 'Blocked in phone settings'],
                                ! $d->push_enabled => ['cancelled', 'Turned off in the app'],
                                default => ['success', 'Can receive'],
                            };
                        @endphp
                        <tr class="hover:bg-slate-50/70 align-top">
                            <td class="px-4 py-2">
                                @if ($d->customer_id)
                                    <a href="{{ route('admin.customers.show', $d->customer_id) }}" class="font-bold text-navy hover:underline">{{ trim($d->first_name.' '.$d->last_name) ?: '—' }}</a>
                                    <div class="text-[11px] text-slate-500">{{ $d->email }}</div>
                                @else <span class="text-slate-400">—</span> @endif
                            </td>
                            <td class="px-4 py-2">
                                <div class="font-semibold text-slate-700">{{ $d->platform === 'ios' ? 'iOS' : 'Android' }} · {{ $d->device_model ?: 'unknown model' }}</div>
                                <div class="text-[10px] text-slate-500">{{ $d->os_version }}</div>
                                <div class="font-mono text-[10px] text-slate-400">{{ $d->device_uid }}</div>
                            </td>
                            <td class="px-4 py-2 text-slate-600">{{ $d->app_version ?: '—' }}<div class="text-[10px] text-slate-400">{{ $d->locale }} {{ $d->timezone }}</div></td>
                            <td class="px-4 py-2">
                                @include('admin.sales._tag', ['tag' => $state[0], 'text' => $state[1]])
                                <div class="text-[10px] text-slate-500 mt-1">{{ $d->provider === 'apns' ? 'APNs '.($d->apns_environment ?? '') : 'FCM' }}@if ($muted) · off: {{ implode(', ', $muted) }}@endif</div>
                                @if ($d->last_error)<div class="text-[10px] text-rose-600 mt-0.5">{{ \Illuminate\Support\Str::limit($d->last_error, 60) }}</div>@endif
                            </td>
                            <td class="px-4 py-2 whitespace-nowrap text-slate-600">{{ $when($d->last_registered_at) }}</td>
                            <td class="px-4 py-2 whitespace-nowrap text-slate-600">{{ $when($d->last_push_at) }}</td>
                            <td class="px-4 py-2 text-right">
                                @if ($canEdit && $d->push_token && ! $d->token_invalid_at)
                                    <form method="POST" action="{{ route('admin.push.test', $d->id) }}">@csrf<button class="px-2.5 py-1 rounded-lg border border-slate-200 text-[11px] font-bold text-navy hover:bg-slate-50 whitespace-nowrap">Send test</button></form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-8 text-center text-slate-400">No phone has registered yet. Phones register when the app is opened and signed in.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t border-slate-100">{{ $devices->links() }}</div>
    </div>

    {{-- Deliveries --}}
    <div class="naoero-card overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-100 flex flex-wrap items-center justify-between gap-3">
            <div>
                <div class="text-sm font-extrabold text-navy">Pushes sent</div>
                <div class="text-[11px] text-slate-500">Each notice pushed to each phone, with what Apple or Google answered.</div>
            </div>
            <form method="GET" class="flex gap-2">
                <select name="status" onchange="this.form.submit()" class="rounded-xl border border-slate-200 px-3 py-1.5 text-xs">
                    <option value="">Every status</option>
                    @foreach ($statusText as $k => $v)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>@endforeach
                </select>
            </form>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-xs">
                <thead class="bg-slate-50 text-[10px] uppercase tracking-wider text-slate-500"><tr><th class="px-4 py-2.5 text-left">Time</th><th class="px-4 py-2.5 text-left">Notice</th><th class="px-4 py-2.5 text-left">Customer</th><th class="px-4 py-2.5 text-left">Phone</th><th class="px-4 py-2.5 text-left">Result</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($deliveries as $p)
                        <tr class="hover:bg-slate-50/70">
                            <td class="px-4 py-2 whitespace-nowrap text-slate-600">{{ $when($p->created_at) }}</td>
                            <td class="px-4 py-2 text-slate-700">{{ $p->title }} @if ($p->is_test)<span class="text-[10px] font-bold text-slate-400 uppercase">test</span>@endif</td>
                            <td class="px-4 py-2 text-slate-600">{{ $p->email ?? '—' }}</td>
                            <td class="px-4 py-2 text-slate-600">{{ $p->platform === 'ios' ? 'iOS' : 'Android' }} · {{ $p->device_model ?: '—' }}</td>
                            <td class="px-4 py-2">
                                @include('admin.sales._tag', ['tag' => $statusTag[$p->status] ?? 'info', 'text' => ($statusText[$p->status] ?? $p->status).($p->http_status ? ' · HTTP '.$p->http_status : '')])
                                @if ($p->error)<span class="text-[10px] text-rose-600">{{ \Illuminate\Support\Str::limit($p->error, 80) }}</span>@endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-slate-400">No pushes sent yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t border-slate-100">{{ $deliveries->links() }}</div>
    </div>
</div>
@endsection
