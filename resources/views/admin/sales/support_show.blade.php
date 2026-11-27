@extends('layouts.admin')

@section('title', 'Help request '.$r->number)

@section('content')
@php
    $when = fn ($v) => $v ? \Carbon\Carbon::parse($v)->timezone(config('app.timezone'))->format('j M Y, H:i') : '—';
    $canManage = auth()->user()->can('shop.sales.manage');
@endphp
<div class="max-w-5xl mx-auto space-y-6">
    @include('admin.sales._nav', [
        'crumb' => 'Help Requests',
        'title' => $r->number.' · '.$r->subject,
        'subtitle' => (trim($r->first_name.' '.$r->last_name) ?: $r->zenith_login).' · '.($statuses[$r->status] ?? $r->status).' · opened '.$when($r->created_at).' from the '.$r->channel,
    ])

    <div class="flex flex-wrap gap-2 items-center">
        @if ($r->shop_order_id)<a href="{{ route('admin.sales.show', $r->shop_order_id) }}" class="px-3.5 py-2 rounded-xl border border-slate-200 bg-white text-xs font-bold text-navy">Order {{ $r->order_number }} →</a>@endif
        <span class="text-xs text-slate-500">@include('admin.customers._identity', ['email' => $r->customer_main_email ?? null, 'login' => $r->zenith_login])</span>
        @if ($canManage)
            <form method="POST" action="{{ route('admin.sales.support.status', $r->id) }}" class="ml-auto flex gap-2">@csrf
                <select name="status" class="rounded-xl border border-slate-200 px-3 py-2 text-xs">
                    @foreach ($statuses as $key => $label)<option value="{{ $key }}" @selected($r->status === $key)>{{ $label }}</option>@endforeach
                </select>
                <button class="px-3 py-2 rounded-xl border border-slate-200 bg-white text-xs font-bold">Set status</button>
            </form>
        @endif
    </div>

    <div class="naoero-card p-5 space-y-4">
        @foreach ($messages as $m)
            <div class="flex {{ $m->author === 'staff' ? 'justify-end' : 'justify-start' }}">
                <div class="max-w-[80%] rounded-2xl px-4 py-3 text-sm {{ $m->author === 'staff' ? 'bg-navy text-white' : ($m->author === 'system' ? 'bg-amber-50 text-amber-900 border border-amber-200' : 'bg-slate-100 text-slate-800') }}">
                    <div class="text-[11px] font-bold opacity-70 mb-1">{{ $m->author === 'staff' ? ($m->staff_name ?? 'Staff') : ($m->author === 'system' ? 'System' : 'Customer') }} · {{ $when($m->created_at) }}</div>
                    <div class="whitespace-pre-line">{{ $m->body }}</div>
                </div>
            </div>
        @endforeach
    </div>

    @if ($canManage)
        <form method="POST" action="{{ route('admin.sales.support.reply', $r->id) }}" class="naoero-card p-5 space-y-3">@csrf
            <textarea name="message" required minlength="2" maxlength="4000" rows="4" placeholder="Reply to the customer…" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">{{ old('message') }}</textarea>
            <div class="flex items-center gap-3">
                <label class="flex items-center gap-2 text-xs text-slate-600"><input type="checkbox" name="close" value="1"> Close the request with this reply</label>
                <button class="ml-auto px-4 py-2 rounded-xl bg-navy text-white text-xs font-bold">Send reply</button>
            </div>
        </form>
    @endif
</div>
@endsection
