@extends('layouts.admin')

@section('title', 'Refund request '.$r->reference)

@section('content')
@php
    $money = fn ($v) => '$'.number_format((float) $v, 2);
    $when = fn ($v) => $v ? \Carbon\Carbon::parse($v)->timezone(config('app.timezone'))->format('j M Y, H:i') : '—';
    $canManage = auth()->user()?->can('bookings.manage');
@endphp
<div class="max-w-6xl mx-auto space-y-6">
    <div class="flex items-center gap-2 text-xs font-semibold text-ink-muted">
        <a href="{{ route('admin.refund-requests.index') }}" class="hover:text-navy">Refund Requests</a><span>/</span><span class="text-navy font-bold">{{ $r->reference }}</span>
    </div>

    <div class="naoero-card p-6 flex flex-col md:flex-row md:items-center gap-4">
        <div>
            <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Refund request</div>
            <h1 class="text-2xl font-extrabold text-navy tracking-wide">{{ $r->reference }}</h1>
            <div class="text-xs text-slate-500 mt-1">Sent {{ $when($r->created_at) }} from the {{ $r->channel === 'website' ? 'website' : 'app' }} · policy accepted {{ $when($r->policy_accepted_at) }}</div>
            @if ($r->decided_at)<div class="text-xs text-slate-500 mt-1">Decided {{ $when($r->decided_at) }}@if ($handler) by {{ $handler }}@endif</div>@endif
        </div>
        <div class="md:ml-auto">@include('admin.refund_requests._status', ['status' => $r->status])</div>
    </div>

    <div class="grid lg:grid-cols-3 gap-5">
        <div class="lg:col-span-2 space-y-5">
            <div class="naoero-card p-5 space-y-3">
                <h2 class="font-extrabold text-navy text-sm">What the customer told us</h2>
                <div class="text-xs font-bold text-slate-500">Reason</div>
                <div class="text-sm font-semibold text-slate-800">{{ $reasons[$r->reason] ?? $r->reason }}</div>
                <div class="text-xs font-bold text-slate-500 pt-1">Details</div>
                <div class="text-sm text-slate-700 whitespace-pre-line rounded-xl bg-slate-50 p-4">{{ $r->details }}</div>
                <div class="text-xs font-bold text-slate-500 pt-1">Passengers</div>
                <div class="text-sm text-slate-800">{{ $r->passenger_names }}</div>
            </div>

            <div class="naoero-card p-5">
                <h2 class="font-extrabold text-navy text-sm mb-3">Booking {{ $r->pnr }}</h2>
                @if ($booking)
                    <div class="space-y-2">
                        @foreach ($summary['flights'] ?? [] as $f)
                            <div class="text-sm"><b class="text-navy">{{ $f['origin'] }} → {{ $f['destination'] }}</b> {{ $f['flightNumber'] }} · {{ $f['departure'] ? \Carbon\Carbon::parse($f['departure'])->format('D j M Y, H:i') : '' }}</div>
                        @endforeach
                    </div>
                    <div class="text-xs text-slate-500 mt-3">Paid {{ $money($booking->total_amount) }} {{ $booking->currency }} · tickets {{ implode(', ', json_decode($booking->ticket_numbers, true) ?: []) ?: '—' }} · status {{ $booking->status }}</div>
                    @can('bookings.view')<a href="{{ route('admin.flight-bookings.show', $booking->id) }}" class="inline-block mt-3 text-xs font-bold text-navy hover:underline">Open the booking →</a>@endcan
                @else
                    <div class="text-sm text-slate-500">The booking is no longer in the system. Amount at request time: {{ $money($r->booking_total) }} {{ $r->currency }}.</div>
                @endif
            </div>

            @if ($others->isNotEmpty())
                <div class="naoero-card p-5">
                    <h2 class="font-extrabold text-navy text-sm mb-3">This customer's other requests</h2>
                    @foreach ($others as $o)
                        <a href="{{ route('admin.refund-requests.show', $o->id) }}" class="flex items-center justify-between py-1.5 text-sm hover:text-navy"><span><b>{{ $o->reference }}</b> · {{ $o->pnr }} · {{ $when($o->created_at) }}</span>@include('admin.refund_requests._status', ['status' => $o->status])</a>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="space-y-5">
            <div class="naoero-card p-5 space-y-2 text-sm">
                <h2 class="font-extrabold text-navy text-sm">Customer</h2>
                <div class="font-semibold text-slate-800">{{ $r->customer_name }}</div>
                <div><a href="mailto:{{ $r->customer_email }}" class="text-navy hover:underline">{{ $r->customer_email }}</a></div>
                @if ($r->contact_phone)<div><a href="tel:{{ $r->contact_phone }}" class="text-navy hover:underline">{{ \App\Services\Support\Phones::display($r->contact_phone) }}</a></div>@endif
                @can('customers.view')<a href="{{ route('admin.customers.show', $r->customer_id) }}" class="inline-block text-xs font-bold text-navy hover:underline">Open the customer →</a>@endcan
            </div>

            <form method="POST" action="{{ route('admin.refund-requests.update', $r->id) }}" class="naoero-card p-5 space-y-3">
                @csrf
                <h2 class="font-extrabold text-navy text-sm">Decision</h2>
                @unless ($canManage)<p class="text-xs text-slate-500">You can view this request. Deciding needs the bookings.manage permission.</p>@endunless
                <label class="block text-xs font-bold text-slate-600">Status
                    <select name="status" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" @disabled(! $canManage)>
                        @foreach ($statuses as $k => $label)<option value="{{ $k }}" @selected(old('status', $r->status) === $k)>{{ $label }}</option>@endforeach
                    </select>
                </label>
                <label class="block text-xs font-bold text-slate-600">Reply to the customer <span class="font-normal text-slate-400">(shown in the app and on the website; required to approve or decline)</span>
                    <textarea name="customer_message" rows="5" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" @disabled(! $canManage)>{{ old('customer_message', $r->customer_message) }}</textarea>
                </label>
                <label class="block text-xs font-bold text-slate-600">Internal note <span class="font-normal text-slate-400">(staff only)</span>
                    <textarea name="staff_note" rows="3" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" @disabled(! $canManage)>{{ old('staff_note', $r->staff_note) }}</textarea>
                </label>
                @if ($canManage)<button class="w-full px-4 py-2.5 rounded-xl bg-navy text-white text-sm font-bold">Save</button>@endif
            </form>
        </div>
    </div>
</div>
@endsection
