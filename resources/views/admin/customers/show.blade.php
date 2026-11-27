@extends('layouts.admin')

@php
    $name = trim(($c->first_name ?? '').' '.($c->middle_name ? $c->middle_name.' ' : '').($c->last_name ?? '')) ?: $c->zenith_login;
@endphp

@section('title', $name)

@section('content')
@php
    $money = fn ($v) => '$'.number_format((float) $v, 2);
    $when = fn ($v) => $v ? \Carbon\Carbon::parse($v)->timezone(config('app.timezone'))->format('j M Y, H:i') : '—';
    $day = fn ($v) => $v ? \Carbon\Carbon::parse($v)->format('j M Y') : '—';
    $ago = fn ($v) => $v ? \Carbon\Carbon::parse($v)->diffForHumans() : 'Never';
    $canManage = auth()->user()->can('customers.manage');
    $tabs = [
        'profile' => 'Profile',
        'bookings' => 'Flight bookings ('.$bookings->count().')',
        'orders' => 'Shop orders ('.$orders->count().')',
        'giftcards' => 'Gift cards ('.$giftCards->count().')',
        'travellers' => 'Saved travellers ('.$travellers->count().')',
        'devices' => 'Devices & sessions ('.$sessions->count().')',
        'security' => 'Sign-in & security',
        'activity' => 'App & API activity',
    ];
    $levelTag = fn ($l) => ['error' => 'failed', 'warn' => 'warning', 'info' => 'info', 'debug' => 'info'][$l] ?? 'info';
    $reasons = \App\Services\Auth\AccountDeletion::REASONS;
    $requestStatus = \App\Services\Auth\AccountDeletion::STATUSES;
    $pendingDeletion = $deletionRequests->firstWhere('status', 'pending');
    $row = fn ($label, $value) => '<div class="flex justify-between gap-4 py-1.5 border-b border-slate-50 text-sm"><span class="text-slate-500">'.e($label).'</span><span class="font-semibold text-slate-800 text-right">'.($value === null || $value === '' ? '<span class="text-slate-300">—</span>' : e($value)).'</span></div>';
@endphp
<div class="max-w-7xl mx-auto space-y-6" x-data="{ tab: (location.hash || '#profile').slice(1), statusOpen: false, declineOpen: false }" x-init="$watch('tab', t => history.replaceState(null, '', '#' + t))">
    <div class="flex items-center gap-2 text-xs font-semibold text-ink-muted">
        <a href="{{ route('admin.customers.index') }}" class="hover:text-navy">Users Directory</a><span>/</span><span class="text-navy font-bold">{{ $name }}</span>
    </div>

    @if (session('success'))<div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">✓ {{ session('success') }}</div>@endif
    @if (session('error'))<div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-800">{{ session('error') }}</div>@endif

    {{-- A deletion request waiting for staff --}}
    @if ($pendingDeletion)
        <div class="naoero-card p-5 border-l-4 border-amber-500 bg-amber-50/40" id="deletion-request">
            <div class="flex flex-col lg:flex-row lg:items-start gap-4">
                <div class="flex-1 min-w-0 space-y-1">
                    <div class="text-sm font-extrabold text-amber-900">This customer asked for their account to be deleted</div>
                    <div class="text-sm text-slate-700"><span class="font-semibold">{{ $reasons[$pendingDeletion->reason_code] ?? $pendingDeletion->reason_code }}</span>@if ($pendingDeletion->reason_text) · “{{ $pendingDeletion->reason_text }}”@endif</div>
                    <div class="text-[11px] text-slate-500">Asked {{ $when($pendingDeletion->requested_at) }} ({{ $ago($pendingDeletion->requested_at) }}) on the {{ $pendingDeletion->platform ?? 'unknown platform' }}, from {{ $pendingDeletion->ip ?? 'an unknown IP' }}. Their password was checked when they asked.</div>
                    <div class="text-[11px] text-slate-500">Nothing is ever deleted. Completing the request <span class="font-semibold">disables</span> the account: it keeps its bookings, orders and data, is signed out everywhere, and can't sign in until re-enabled.</div>
                </div>
                @if ($canManage)
                    <div class="flex flex-wrap gap-2 shrink-0">
                        <form method="POST" action="{{ route('admin.customers.deletion.complete', [$c->id, $pendingDeletion->id]) }}" onsubmit="return confirm('Disable this account now? It is signed out everywhere and cannot sign in until re-enabled. Nothing is deleted.')" class="flex flex-wrap gap-2">@csrf
                            <input name="note" maxlength="500" placeholder="Note for the audit log (optional)" class="rounded-xl border border-slate-200 px-3 py-2 text-xs w-56">
                            <button class="px-4 py-2 rounded-xl bg-rose-600 text-white text-xs font-bold">Disable account</button>
                        </form>
                        <button type="button" @click="declineOpen = true" class="px-4 py-2 rounded-xl border border-slate-200 bg-white text-xs font-bold text-slate-700">Decline</button>
                    </div>
                @endif
            </div>
        </div>
    @endif
    @if ($c->status === 'disabled')
        <div class="naoero-card p-4 border-l-4 border-rose-500 text-sm text-rose-900">
            <span class="font-bold">This account is disabled</span>@if ($c->disabled_at) since {{ $when($c->disabled_at) }}@endif @if ($disabledBy) by {{ $disabledBy }}@endif. @if ($c->disabled_reason)<span class="text-slate-700">{{ $c->disabled_reason }}</span>@endif
            <div class="text-[11px] text-slate-500 mt-0.5">It can't sign in on the website or in the app. All its data is kept; "Re-enable account" lets it sign in again.</div>
        </div>
    @endif
    @if ($errors->any())<div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ $errors->first() }}</div>@endif

    {{-- Header card --}}
    <div class="naoero-card overflow-hidden">
        <div class="h-20 bg-gradient-to-r from-navy via-navy-700 to-navy-900"></div>
        {{-- Only the avatar overlaps the banner; the name sits on white below it. --}}
        <div class="px-6 pb-5 flex flex-col md:flex-row md:items-start gap-4">
            @if ($avatar)
                <img src="{{ route('admin.customers.avatar', $c->id) }}" class="-mt-10 shrink-0 w-20 h-20 rounded-2xl object-cover ring-4 ring-white shadow bg-white" alt="">
            @else
                <div class="-mt-10 shrink-0 w-20 h-20 rounded-2xl bg-accent text-navy ring-4 ring-white shadow flex items-center justify-center text-2xl font-extrabold">{{ strtoupper(mb_substr($c->first_name ?? $c->zenith_login, 0, 1).mb_substr($c->last_name ?? '', 0, 1)) }}</div>
            @endif
            <div class="flex-1 min-w-0 md:pt-3">
                <h1 class="text-2xl font-extrabold text-navy leading-tight break-words">{{ trim(($c->civility_code ? ucfirst(strtolower($c->civility_code)).' ' : '').$name) }}</h1>
                <div class="flex flex-wrap items-center gap-2 mt-1 text-xs text-slate-500">
                    <span>{{ $c->email ?? $c->zenith_login }}</span><span>·</span>@if (strcasecmp($c->zenith_login, (string) $c->email) !== 0)<span>Username <span class="font-mono font-semibold text-slate-700">{{ $c->zenith_login }}</span></span><span>·</span>@endif<span>Member since {{ $day($c->created_at) }}</span><span>·</span><span>Last sign-in {{ $ago($c->last_login_at) }}</span>
                    @include('admin.sales._tag', ['tag' => match ($c->status) { 'active' => 'success', 'disabled' => 'declined', default => 'review' }, 'text' => match ($c->status) { 'active' => 'Active', 'disabled' => 'Disabled', default => 'Deletion requested' }])
                    @if ($pendingDeletion)@include('admin.sales._tag', ['tag' => 'review', 'text' => 'Deletion requested'])@endif
                </div>
            </div>
            @if ($canManage)
                <div class="flex flex-wrap gap-2 md:pt-3 shrink-0">
                    <form method="POST" action="{{ route('admin.customers.sign-out', $c->id) }}" onsubmit="return confirm('Sign this user out of every device?')">@csrf
                        <button class="px-3.5 py-2 rounded-xl border border-slate-200 bg-white text-xs font-bold text-slate-700">Sign out everywhere</button>
                    </form>
                    <button @click="statusOpen = true" class="px-3.5 py-2 rounded-xl text-xs font-bold {{ $c->status !== 'disabled' ? 'bg-rose-50 border border-rose-200 text-rose-700' : 'bg-emerald-600 text-white' }}">{{ $c->status !== 'disabled' ? 'Disable account' : 'Re-enable account' }}</button>
                </div>
            @endif
        </div>
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 border-t border-slate-100 divide-x divide-slate-100">
            @foreach ([
                ['Flight spend', $money($stats['flightSpend']), 'text-navy'],
                ['Tickets', $stats['tickets'], 'text-navy'],
                ['On hold', $stats['onHold'], 'text-amber-600'],
                ['Shop spend', $money($stats['shopSpend']), 'text-purple-600'],
                ['Active sessions', $stats['activeSessions'], 'text-emerald-600'],
                ['Devices', $stats['devices'], 'text-sky-600'],
                ['Failed sign-ins · 30d', $stats['failedLogins30'], $stats['failedLogins30'] >= 3 ? 'text-rose-600' : 'text-slate-700'],
            ] as [$label, $value, $color])
                <div class="px-4 py-3 min-w-0"><div class="text-[10px] font-bold uppercase tracking-wide text-slate-400 truncate" title="{{ $label }}">{{ $label }}</div><div class="text-lg font-extrabold {{ $color }}">{{ $value }}</div></div>
            @endforeach
        </div>
    </div>

    {{-- Tabs --}}
    <div class="flex flex-wrap gap-1 p-1 rounded-xl bg-white border border-slate-200 shadow-sm">
        @foreach ($tabs as $key => $label)
            <button type="button" @click="tab = '{{ $key }}'" :class="tab === '{{ $key }}' ? 'bg-navy text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100'" class="px-3.5 py-2 rounded-lg text-xs font-bold whitespace-nowrap">{{ $label }}</button>
        @endforeach
    </div>

    {{-- Profile --}}
    <div x-show="tab === 'profile'" class="grid lg:grid-cols-3 gap-5">
        <div class="naoero-card p-5">
            <h2 class="font-extrabold text-navy text-sm mb-2">Personal details</h2>
            {!! $row('Username (login)', $c->zenith_login) !!}
            {!! $row('Main email', $c->email) !!}
            {!! $row('Title', $c->civility_code) !!}
            {!! $row('First name', $c->first_name) !!}
            {!! $row('Middle name', $c->middle_name) !!}
            {!! $row('Last name', $c->last_name) !!}
            {!! $row('Date of birth', $c->birth_date ? $day($c->birth_date) : null) !!}
            {!! $row('Account type', $c->customer_type) !!}
            {!! $row('Currency', $c->currency) !!}
            {!! $row('Language', $c->culture_name) !!}
            {!! $row('Newsletter', $c->newsletter_opt_in === null ? null : ($c->newsletter_opt_in ? 'Subscribed' : 'No')) !!}
            {!! $row('Marketing emails and texts', $c->marketing_opt_in === null ? null : ($c->marketing_opt_in ? 'Agreed' : 'Declined').($c->marketing_opt_in_at ? ' on '.$day($c->marketing_opt_in_at) : '')) !!}
            {!! $row('Conditions and privacy policy', $c->terms_accepted_at ? 'Accepted '.$when($c->terms_accepted_at) : null) !!}
            {!! $row('Company', $c->company_name) !!}
        </div>
        <div class="naoero-card p-5">
            <h2 class="font-extrabold text-navy text-sm mb-2">Contact</h2>
            @foreach ($emails as $e){!! $row($loop->first ? 'Email' : 'Email '.($loop->index + 1), $e->email) !!}@endforeach
            @if ($c->phone_e164){!! $row('Mobile for SMS', \App\Services\Support\Phones::display($c->phone_e164)) !!}@endif
            @foreach ($phones as $p){!! $row(($p->phone_type_code ?: 'Phone').($p->location_type_code ? ' · '.$p->location_type_code : ''), \App\Services\Support\Phones::display($p->e164 ?? $p->phone_number)) !!}@endforeach
            @forelse ($addresses as $a)
                <div class="mt-3 rounded-xl bg-slate-50 p-3 text-sm text-slate-700">
                    <div class="text-[10px] font-bold uppercase text-slate-400 mb-1">{{ $a->type_code ?: 'Address' }}</div>
                    {{ trim(($a->street_number ?? '').' '.($a->address1 ?? '')) }}@if ($a->address2)<br>{{ $a->address2 }}@endif<br>
                    {{ $a->city }} {{ $a->state }} {{ $a->zip_code }} {{ $a->country_code }}
                </div>
            @empty
                <p class="text-xs text-slate-400 mt-3">No address on file.</p>
            @endforelse
            <h3 class="font-extrabold text-navy text-xs mt-4 mb-1">Emergency contact</h3>
            {!! $row('Name', $c->emergency_contact_name) !!}
            {!! $row('Phone', $c->emergency_contact_phone ? \App\Services\Support\Phones::display($c->emergency_contact_phone) : null) !!}
        </div>
        <div class="space-y-5">
            <div class="naoero-card p-5">
                <h2 class="font-extrabold text-navy text-sm mb-2">Travel documents</h2>
                @forelse ($documents as $d)
                    <div class="rounded-xl border border-slate-100 p-3 text-sm mb-2">
                        <div class="font-bold text-slate-800">{{ ['PP' => 'Passport', 'NI' => 'National ID', 'DOI' => 'Document of Identity', 'RT' => 'Refugee travel document'][$d->document_type_code] ?? $d->document_type_code }} · {{ $d->document_number }}</div>
                        <div class="text-xs text-slate-500">Issued by {{ $d->issue_country_code ?? '—' }} · nationality {{ $d->nationality_country_code ?? '—' }} · gender {{ $d->gender ?? '—' }}</div>
                        <div class="text-xs {{ $d->expiry_date && \Carbon\Carbon::parse($d->expiry_date)->isPast() ? 'text-rose-600 font-bold' : 'text-slate-500' }}">Issued {{ $day($d->issuance_date) }} · expires {{ $day($d->expiry_date) }}</div>
                    </div>
                @empty
                    <p class="text-xs text-slate-400">No travel document on file.</p>
                @endforelse
            </div>
            <div class="naoero-card p-5">
                <h2 class="font-extrabold text-navy text-sm mb-2">Account</h2>
                {!! $row('Zenith customer', $c->zenith_customer_ref) !!}
                {!! $row('Zenith environment', $c->zenith_environment) !!}
                {!! $row('Profile synced', $when($c->profile_synced_at)) !!}
                {!! $row('Profile photo', $avatar ? 'Updated '.$ago($avatar->updated_at) : null) !!}
                {!! $row('Deletion requested', $c->removal_requested_at ? $when($c->removal_requested_at) : null) !!}
                <details class="mt-3"><summary class="text-xs font-bold text-navy cursor-pointer">Full profile as Zenith holds it</summary>
                    <pre class="mt-2 max-h-72 overflow-auto rounded-xl bg-slate-900 text-emerald-200 text-[11px] p-3">{{ json_encode(json_decode($c->zenith_profile), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                </details>
            </div>
        </div>
    </div>

    {{-- Flight bookings --}}
    <div x-show="tab === 'bookings'" x-cloak class="naoero-card overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500"><tr><th class="px-4 py-3 text-left">Booking</th><th class="px-4 py-3 text-left">Flights</th><th class="px-4 py-3 text-left">Passengers</th><th class="px-4 py-3 text-right">Total</th><th class="px-4 py-3 text-left">Status</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($bookings as $b)
                    @php $s = json_decode($b->summary, true) ?: []; $expired = $b->status === 'pending_payment' && $b->time_limit && \Carbon\Carbon::parse($b->time_limit)->isPast(); @endphp
                    <tr class="hover:bg-slate-50/70">
                        <td class="px-4 py-3"><a href="{{ route('admin.flight-bookings.show', $b->id) }}" class="font-extrabold text-navy tracking-wider">{{ $b->pnr }}</a><div class="text-[11px] text-slate-400">{{ $when($b->created_at) }}</div></td>
                        <td class="px-4 py-3 text-xs">@foreach ($s['flights'] ?? [] as $f)<div><b>{{ $f['flightNumber'] }}</b> {{ $f['origin'] }} → {{ $f['destination'] }} · {{ $f['departure'] ? \Carbon\Carbon::parse($f['departure'])->format('j M H:i') : '' }}</div>@endforeach</td>
                        <td class="px-4 py-3 text-xs text-slate-600">{{ collect($s['passengers'] ?? [])->pluck('name')->implode(', ') }}</td>
                        <td class="px-4 py-3 text-right font-bold text-navy">{{ $money($b->total_amount) }} <span class="text-[10px] text-slate-400">{{ $b->currency }}</span></td>
                        <td class="px-4 py-3">@include('admin.flight_bookings._status', ['status' => $expired ? 'expired' : $b->status])</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-slate-400">No flight bookings yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Shop orders --}}
    <div x-show="tab === 'orders'" x-cloak class="naoero-card overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500"><tr><th class="px-4 py-3 text-left">Order</th><th class="px-4 py-3 text-right">Total</th><th class="px-4 py-3 text-left">Payment</th><th class="px-4 py-3 text-left">Fulfilment</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($orders as $o)
                    <tr class="hover:bg-slate-50/70">
                        <td class="px-4 py-3"><a href="{{ route('admin.sales.show', $o->id) }}" class="font-extrabold text-navy">{{ $o->order_number }}</a><div class="text-[11px] text-slate-400">{{ $when($o->created_at) }}</div>@include('admin.sales._channel', ['channel' => $o->channel ?? 'app'])</td>
                        <td class="px-4 py-3 text-right font-bold text-navy">{{ $money($o->total) }}@if ((float) $o->refunded_amount > 0)<div class="text-[11px] text-purple-600">−{{ $money($o->refunded_amount) }}</div>@endif</td>
                        <td class="px-4 py-3">@include('admin.sales._tag', ['tag' => $o->status])</td>
                        <td class="px-4 py-3">@if ($o->shipping_status)@include('admin.sales._tag', ['tag' => $o->shipping_status])@else<span class="text-slate-300">—</span>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-10 text-center text-slate-400">No shop orders yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Gift cards --}}
    <div x-show="tab === 'giftcards'" x-cloak class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
        @forelse ($giftCards as $g)
            <div class="rounded-2xl p-5 text-white shadow-lg bg-gradient-to-br from-navy-700 via-navy to-navy-950 {{ in_array($g->view['status'], ['issued', 'confirmed']) ? '' : 'opacity-60 grayscale' }}">
                <div class="flex justify-between text-[10px] font-bold tracking-[0.2em]"><span>NAOERO AIRLINES</span><span class="text-accent">GIFT CARD</span></div>
                <div class="text-3xl font-extrabold mt-5">{{ $money($g->amount) }} <span class="text-xs text-accent">{{ $g->currency }}</span></div>
                <div class="font-mono font-bold tracking-widest mt-2">{{ $g->voucher_code }}</div>
                <div class="text-[11px] text-white/70 mt-2">Balance {{ $money($g->balance_remaining) }} · {{ ucfirst($g->view['status']) }} · until {{ $g->view['expiresAt'] ?? '—' }}</div>
                @if ($g->recipient_name)<div class="text-[11px] text-white/70">For {{ $g->recipient_name }} {{ $g->recipient_email ? '<'.$g->recipient_email.'>' : '' }}</div>@endif
            </div>
        @empty
            <div class="naoero-card p-10 text-center text-sm text-slate-400 sm:col-span-2 lg:col-span-3">No gift cards bought.</div>
        @endforelse
    </div>

    {{-- Saved travellers --}}
    <div x-show="tab === 'travellers'" x-cloak class="naoero-card overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500"><tr><th class="px-4 py-3 text-left">Traveller</th><th class="px-4 py-3 text-left">Born</th><th class="px-4 py-3 text-left">Document</th><th class="px-4 py-3 text-left">Contact</th><th class="px-4 py-3 text-left">Last booked</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($travellers as $t)
                    <tr>
                        <td class="px-4 py-3 font-bold text-slate-800">{{ trim(($t->title ? ucfirst(strtolower($t->title)).' ' : '').$t->first_name.' '.$t->last_name) }} <span class="text-[11px] text-slate-400">{{ $t->last_passenger_type }}</span></td>
                        <td class="px-4 py-3 text-xs">{{ $day($t->date_of_birth) }} {{ $t->gender ? '· '.$t->gender : '' }}</td>
                        <td class="px-4 py-3 text-xs">{{ $t->document_type }} {{ $t->document_number ? '•••'.substr($t->document_number, -4) : '—' }} {{ $t->nationality ? '· '.$t->nationality : '' }} {{ $t->document_expiry ? '· exp '.$day($t->document_expiry) : '' }}</td>
                        <td class="px-4 py-3 text-xs text-slate-500">{{ $t->email ?? '' }} {{ $t->phone ? \App\Services\Support\Phones::display($t->phone) : '' }}</td>
                        <td class="px-4 py-3 text-xs text-slate-500">{{ $ago($t->last_used_at) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-slate-400">No saved travellers.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Devices --}}
    <div x-show="tab === 'devices'" x-cloak class="naoero-card overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500"><tr><th class="px-4 py-3 text-left">Device</th><th class="px-4 py-3 text-left">Signed in</th><th class="px-4 py-3 text-left">Last used</th><th class="px-4 py-3 text-left">IP</th><th class="px-4 py-3 text-left">Session</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($sessions as $s)
                    <tr>
                        <td class="px-4 py-3 font-bold text-slate-800">📱 {{ $s->device }}<div class="text-[11px] font-normal text-slate-400">{{ $s->refreshes }} token refreshes</div></td>
                        <td class="px-4 py-3 text-xs">{{ $when($s->started) }}</td>
                        <td class="px-4 py-3 text-xs">{{ $ago($s->lastUsed) }}</td>
                        <td class="px-4 py-3 text-xs font-mono">{{ $s->ip ?? '—' }}</td>
                        <td class="px-4 py-3">@include('admin.sales._tag', ['tag' => $s->active ? 'success' : 'cancelled', 'text' => $s->active ? 'Active until '.\Carbon\Carbon::parse($s->expires)->format('j M') : 'Ended: '.str_replace('_', ' ', $s->ended)])</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-slate-400">Never signed in on a device.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Security --}}
    <div x-show="tab === 'security'" x-cloak class="naoero-card overflow-hidden">
        <div class="px-5 py-3 border-b border-slate-100">
            <div class="text-sm font-extrabold text-navy">Account deletion requests</div>
            <div class="text-[11px] text-slate-500">Every request, kept for the record. Nothing is ever deleted: a completed request disabled the account.</div>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-xs">
                <thead class="bg-slate-50 text-[10px] uppercase tracking-wider text-slate-500"><tr><th class="px-4 py-2 text-left">Asked</th><th class="px-4 py-2 text-left">Reason</th><th class="px-4 py-2 text-left">From</th><th class="px-4 py-2 text-left">Status</th><th class="px-4 py-2 text-left">Handled</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($deletionRequests as $r)
                        <tr>
                            <td class="px-4 py-2 whitespace-nowrap text-slate-600">{{ $when($r->requested_at) }}</td>
                            <td class="px-4 py-2 text-slate-700"><span class="font-semibold">{{ $reasons[$r->reason_code] ?? $r->reason_code }}</span>@if ($r->reason_text)<div class="text-[11px] text-slate-500">“{{ $r->reason_text }}”</div>@endif</td>
                            <td class="px-4 py-2 text-slate-500">{{ ucfirst($r->platform ?? '—') }}<div class="font-mono text-[10px]">{{ $r->ip }}</div></td>
                            <td class="px-4 py-2">@include('admin.sales._tag', ['tag' => ['pending' => 'review', 'completed' => 'declined', 'declined' => 'cancelled', 'withdrawn' => 'cancelled'][$r->status] ?? 'info', 'text' => $requestStatus[$r->status] ?? $r->status])</td>
                            <td class="px-4 py-2 text-slate-500">@if ($r->resolved_at){{ $when($r->resolved_at) }}@if ($r->resolved_by_name) by {{ $r->resolved_by_name }}@endif @if ($r->staff_note)<div class="text-[11px]">{{ $r->staff_note }}</div>@endif @else — @endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-4 text-center text-slate-400">No deletion requests.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div x-show="tab === 'security'" x-cloak class="naoero-card overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500"><tr><th class="px-4 py-3 text-left">When</th><th class="px-4 py-3 text-left">Event</th><th class="px-4 py-3 text-left">Result</th><th class="px-4 py-3 text-left">Detail</th><th class="px-4 py-3 text-left">IP / device</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($authEvents as $e)
                    <tr>
                        <td class="px-4 py-2.5 text-xs whitespace-nowrap">{{ $when($e->created_at) }}</td>
                        <td class="px-4 py-2.5 text-xs font-bold text-slate-700">{{ ucfirst(str_replace('_', ' ', $e->event)) }}</td>
                        <td class="px-4 py-2.5">@include('admin.sales._tag', ['tag' => $e->success ? 'success' : 'failed'])</td>
                        <td class="px-4 py-2.5 text-xs text-slate-500">{{ $e->detail }}</td>
                        <td class="px-4 py-2.5 text-[11px] text-slate-400"><span class="font-mono">{{ $e->ip }}</span><div class="truncate max-w-[14rem]">{{ $e->user_agent }}</div></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-slate-400">Nothing recorded.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Activity --}}
    <div x-show="tab === 'activity'" x-cloak class="grid lg:grid-cols-2 gap-5">
        <div class="naoero-card overflow-hidden">
            <div class="px-5 py-3 border-b border-slate-100 font-extrabold text-navy text-sm">In the app (latest {{ $appLogs->count() }})</div>
            <div class="max-h-[32rem] overflow-y-auto divide-y divide-slate-100">
                @forelse ($appLogs as $l)
                    <div class="px-5 py-2.5 text-xs">
                        <div class="flex items-center gap-2">@include('admin.sales._tag', ['tag' => $levelTag($l->level), 'text' => strtoupper($l->level)])<b class="text-slate-700">{{ $l->event }}</b><span class="ml-auto text-slate-400">{{ $when($l->client_time ?? $l->received_at) }}</span></div>
                        @if ($l->message)<div class="text-slate-500 mt-0.5">{{ $l->message }}</div>@endif
                        <div class="text-[10px] text-slate-400">{{ $l->platform }} {{ $l->app_version }} · device {{ \Illuminate\Support\Str::limit($l->device_id, 14) }}</div>
                    </div>
                @empty
                    <p class="px-5 py-10 text-center text-sm text-slate-400">No app activity.</p>
                @endforelse
            </div>
        </div>
        <div class="naoero-card overflow-hidden">
            <div class="px-5 py-3 border-b border-slate-100 font-extrabold text-navy text-sm">On our server (latest {{ $serverLogs->count() }})</div>
            <div class="max-h-[32rem] overflow-y-auto divide-y divide-slate-100">
                @forelse ($serverLogs as $l)
                    <div class="px-5 py-2.5 text-xs">
                        <div class="flex items-center gap-2">@include('admin.sales._tag', ['tag' => $levelTag($l->level), 'text' => $l->source])<span class="text-slate-700">{{ $l->message }}</span><span class="ml-auto text-slate-400 whitespace-nowrap">{{ $when($l->logged_at) }}</span></div>
                        @if ($l->duration_ms !== null)<div class="text-[10px] text-slate-400">{{ $l->duration_ms }} ms · request {{ \Illuminate\Support\Str::limit((string) $l->request_id, 8, '') }}</div>@endif
                    </div>
                @empty
                    <p class="px-5 py-10 text-center text-sm text-slate-400">No requests recorded.</p>
                @endforelse
            </div>
        </div>
    </div>

    @if ($canManage)
        <div x-show="statusOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-navy-950/60 p-4" @keydown.escape.window="statusOpen = false">
            <form method="POST" action="{{ route('admin.customers.toggle-status', $c->id) }}" class="w-full max-w-md bg-white rounded-2xl shadow-2xl p-6 space-y-4" @click.outside="statusOpen = false">@csrf @method('PATCH')
                <h3 class="text-lg font-extrabold text-navy">{{ $c->status !== 'disabled' ? 'Disable this account?' : 'Re-enable this account?' }}</h3>
                <p class="text-xs text-slate-500">{{ $c->status !== 'disabled' ? 'They are signed out of every device at once and cannot sign in until re-enabled. Nothing is deleted: their Zenith account, bookings, orders and data stay as they are.'.($pendingDeletion ? ' Their deletion request is completed with this.' : '') : 'They will be able to sign in again with their usual password.' }}</p>
                <input name="reason" required minlength="3" maxlength="300" placeholder="Reason (kept in the audit log)" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                <div class="flex justify-end gap-2">
                    <button type="button" @click="statusOpen = false" class="px-4 py-2 rounded-xl border border-slate-200 text-xs font-bold">Keep</button>
                    <button class="px-4 py-2 rounded-xl text-xs font-bold text-white {{ $c->status !== 'disabled' ? 'bg-rose-600' : 'bg-emerald-600' }}">{{ $c->status !== 'disabled' ? 'Disable' : 'Re-enable' }}</button>
                </div>
            </form>
        </div>
        @if ($pendingDeletion)
            <div x-show="declineOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-navy-950/60 p-4" @keydown.escape.window="declineOpen = false">
                <form method="POST" action="{{ route('admin.customers.deletion.decline', [$c->id, $pendingDeletion->id]) }}" class="w-full max-w-md bg-white rounded-2xl shadow-2xl p-6 space-y-4" @click.outside="declineOpen = false">@csrf
                    <h3 class="text-lg font-extrabold text-navy">Decline the deletion request?</h3>
                    <p class="text-xs text-slate-500">The account stays active. The customer gets a notice with your note, so write it to them (for example, a booking that must be settled first).</p>
                    <textarea name="note" required minlength="3" maxlength="500" rows="3" placeholder="Why, for the customer" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"></textarea>
                    <div class="flex justify-end gap-2">
                        <button type="button" @click="declineOpen = false" class="px-4 py-2 rounded-xl border border-slate-200 text-xs font-bold">Keep</button>
                        <button class="px-4 py-2 rounded-xl bg-navy text-white text-xs font-bold">Decline request</button>
                    </div>
                </form>
            </div>
        @endif
    @endif
</div>
@endsection
