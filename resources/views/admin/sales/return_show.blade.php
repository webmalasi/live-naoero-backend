@extends('layouts.admin')

@section('title', 'Return '.$r->return_number)

@section('content')
@php
    $money = fn ($v) => '$'.number_format((float) $v, 2);
    $when = fn ($v) => $v ? \Carbon\Carbon::parse($v)->timezone(config('app.timezone'))->format('j M Y, H:i') : '—';
    $canManage = auth()->user()->can('shop.sales.manage');
    $canRefund = auth()->user()->can('shop.sales.refund');
    $field = 'w-full rounded-xl border border-slate-200 px-3 py-2 text-sm';
@endphp
<div class="max-w-6xl mx-auto space-y-6">
    @include('admin.sales._nav', [
        'crumb' => 'Returns',
        'title' => ($r->type === 'exchange' ? 'Exchange ' : 'Return ').$r->return_number,
        'subtitle' => (trim($r->first_name.' '.$r->last_name) ?: $r->zenith_login).' · '.($reasons[$r->reason] ?? $r->reason).' · requested '.$when($r->created_at),
    ])

    <div class="flex flex-wrap items-center gap-2">
        <span class="px-2.5 py-1 rounded-full bg-navy text-white text-[11px] font-extrabold uppercase">{{ $statuses[$r->status] ?? $r->status }}</span>
        <a href="{{ route('admin.sales.show', $r->shop_order_id) }}" class="px-3.5 py-2 rounded-xl border border-slate-200 bg-white text-xs font-bold text-navy">Order {{ $r->order_number }} →</a>
        @if ($r->handled_by_name)<span class="text-xs text-slate-500">Handled by {{ $r->handled_by_name }}</span>@endif
    </div>

    <div class="grid lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <form method="POST" action="{{ route('admin.sales.returns.receive', $r->id) }}" class="naoero-card overflow-hidden">@csrf
                <div class="px-5 py-3 border-b border-slate-100 font-extrabold text-navy text-sm">Items</div>
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500">
                        <tr><th class="px-4 py-3 text-left">Item</th><th class="px-4 py-3 text-right">Qty</th><th class="px-4 py-3 text-right">Value</th><th class="px-4 py-3 text-left">{{ $r->status === 'approved' ? 'Condition on arrival' : 'Condition' }}</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($items as $i)
                            <tr>
                                <td class="px-4 py-2.5"><div class="font-bold text-slate-700">{{ \App\Services\Shop\Inventory::label($i->title, $i->variant_title) }}</div>
                                    <div class="text-[11px] text-slate-400 font-mono">{{ $i->sku }}</div>
                                    @if ($i->exchange_title)<div class="text-xs text-sky-700 font-semibold">Exchange for {{ $i->exchange_title }}@if ($i->replacement_state) · stock {{ $i->replacement_state }}@endif</div>@endif</td>
                                <td class="px-4 py-2.5 text-right">{{ $i->quantity }}</td>
                                <td class="px-4 py-2.5 text-right">{{ $money($i->quantity * (float) $i->unit_price) }}</td>
                                <td class="px-4 py-2.5 text-xs">
                                    @if ($r->status === 'approved' && $canManage)
                                        <select name="items[{{ $i->id }}][condition]" class="rounded-lg border border-slate-200 px-2 py-1 text-xs">
                                            @foreach ($conditions as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                                        </select>
                                        <label class="flex items-center gap-1.5 mt-1"><input type="checkbox" name="items[{{ $i->id }}][restock]" value="1" checked> Put back in stock</label>
                                    @else
                                        {{ $i->condition ? ($conditions[$i->condition] ?? $i->condition) : '—' }}@if ($i->restocked) · back in stock @endif
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="px-4 py-3 border-t border-slate-100 flex items-center gap-3">
                    <span class="text-sm text-slate-600">Value {{ $money($value) }}</span>
                    @if ($r->status === 'approved' && $canManage)
                        <input name="note" maxlength="1000" placeholder="Note (what was found)" class="flex-1 rounded-xl border border-slate-200 px-3 py-2 text-sm">
                        <button class="px-4 py-2 rounded-xl bg-emerald-600 text-white text-xs font-bold" onclick="return confirm('Mark the parcel as received?')">Mark as received</button>
                    @endif
                </div>
            </form>

            @if ($canManage && $r->status === 'requested')
                <div class="grid md:grid-cols-2 gap-4">
                    <form method="POST" action="{{ route('admin.sales.returns.approve', $r->id) }}" class="naoero-card p-5 space-y-3">@csrf
                        <h2 class="font-extrabold text-navy text-sm">Approve</h2>
                        <textarea name="instructions" rows="4" maxlength="1000" placeholder="How to send it back (leave empty for the standard instructions with our address)" class="{{ $field }}"></textarea>
                        @if ($r->type === 'exchange')<p class="text-[11px] text-slate-500">The replacement stock is held now.</p>@endif
                        <button class="w-full px-4 py-2 rounded-xl bg-navy text-white text-xs font-bold">Approve {{ $r->type }}</button>
                    </form>
                    <form method="POST" action="{{ route('admin.sales.returns.reject', $r->id) }}" class="naoero-card p-5 space-y-3">@csrf
                        <h2 class="font-extrabold text-navy text-sm">Don't accept</h2>
                        <textarea name="note" rows="4" required minlength="3" maxlength="1000" placeholder="Why (the customer sees this)" class="{{ $field }}"></textarea>
                        <button class="w-full px-4 py-2 rounded-xl border border-rose-200 text-rose-700 text-xs font-bold">Reject</button>
                    </form>
                </div>
            @endif
            @if ($canManage && $r->status === 'approved')
                <form method="POST" action="{{ route('admin.sales.returns.reject', $r->id) }}" class="flex gap-2">@csrf
                    <input name="note" required minlength="3" placeholder="Reason, if the parcel never came" class="flex-1 rounded-xl border border-slate-200 px-3 py-2 text-sm">
                    <button class="px-4 py-2 rounded-xl border border-rose-200 text-rose-700 text-xs font-bold">Close as not accepted</button>
                </form>
            @endif
            @if ($r->status === 'received' && $r->type === 'return' && $canRefund)
                <form method="POST" action="{{ route('admin.sales.returns.refund', $r->id) }}" class="naoero-card p-5 flex flex-wrap items-end gap-3" onsubmit="return confirm('Refund through NAB? This cannot be undone.')">@csrf
                    <label class="text-xs font-bold text-slate-600">Refund amount ({{ $r->currency }})
                        <input name="amount" type="number" step="0.01" min="0.01" max="{{ $refundable }}" value="{{ number_format(min($value, $refundable), 2, '.', '') }}" class="mt-1 block rounded-xl border border-slate-200 px-3 py-2 text-sm">
                    </label>
                    <span class="text-[11px] text-slate-500 pb-2">Up to {{ $money($refundable) }} is still refundable on the order. Delivery is not refunded unless you add it.</span>
                    <button class="ml-auto px-4 py-2 rounded-xl bg-purple-600 text-white text-xs font-bold">Refund to card</button>
                </form>
            @endif
            @if ($r->status === 'received' && $r->type === 'exchange' && $canManage)
                <form method="POST" action="{{ route('admin.sales.returns.exchange', $r->id) }}" class="naoero-card p-5 grid sm:grid-cols-3 gap-2">@csrf
                    <input name="carrier" required placeholder="Carrier" class="rounded-xl border border-slate-200 px-3 py-2 text-sm">
                    <input name="tracking_number" placeholder="Tracking number" class="rounded-xl border border-slate-200 px-3 py-2 text-sm">
                    <button class="rounded-xl bg-navy text-white text-xs font-bold px-3 py-2">Send replacement</button>
                </form>
            @endif
        </div>

        <div class="naoero-card p-5 space-y-2 text-sm self-start">
            <h2 class="font-extrabold text-navy">Details</h2>
            <div><span class="text-slate-500">Customer</span><div class="font-semibold">{{ trim($r->first_name.' '.$r->last_name) ?: '—' }}</div><div class="text-xs text-slate-500">@include('admin.customers._identity', ['email' => $r->customer_main_email ?? null, 'login' => $r->zenith_login])</div></div>
            <div><span class="text-slate-500">Reason</span><div class="font-semibold">{{ $reasons[$r->reason] ?? $r->reason }}</div>@if ($r->details)<div class="text-xs text-slate-600 whitespace-pre-line">{{ $r->details }}</div>@endif</div>
            @if ($r->instructions)<div><span class="text-slate-500">Instructions sent</span><div class="text-xs text-slate-600 whitespace-pre-line">{{ $r->instructions }}</div></div>@endif
            @if ($r->staff_note)<div><span class="text-slate-500">Note</span><div class="text-xs text-slate-600">{{ $r->staff_note }}</div></div>@endif
            @if ($r->refund_amount)<div><span class="text-slate-500">Refunded</span><div class="font-bold text-purple-700">{{ $money($r->refund_amount) }}</div></div>@endif
            @if ($r->replacement_carrier)<div><span class="text-slate-500">Replacement</span><div class="text-xs">{{ $r->replacement_carrier }} {{ $r->replacement_tracking }}</div></div>@endif
            <div class="text-xs text-slate-500 pt-2 border-t border-slate-100 space-y-0.5">
                <div>Approved {{ $when($r->approved_at) }}</div><div>Received {{ $when($r->received_at) }}</div><div>Closed {{ $when($r->completed_at) }}</div>
            </div>
        </div>
    </div>
</div>
@endsection
