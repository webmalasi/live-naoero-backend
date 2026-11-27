@extends('layouts.admin')

@section('title', $po->po_number)

@section('content')
@php
    $money = fn ($v) => '$'.number_format((float) $v, 2);
    $when = fn ($v) => $v ? \Carbon\Carbon::parse($v)->timezone(config('app.timezone'))->format('j M Y, H:i') : '—';
    $canBuy = auth()->user()->can('shop.inventory.purchasing');
    $receivable = in_array($po->status, ['draft', 'ordered', 'partially_received'], true);
    $itemsCost = $items->sum(fn ($i) => $i->quantity_ordered * (float) $i->unit_cost);
@endphp
<div class="max-w-6xl mx-auto space-y-6">
    @include('admin.inventory._nav', [
        'crumb' => 'Purchase Orders',
        'title' => $po->po_number,
        'subtitle' => ($po->supplier_name ?? 'No supplier yet').' · created '.$when($po->created_at).($po->created_by_name ? ' by '.$po->created_by_name : ''),
    ])

    <div class="flex flex-wrap items-center gap-2">
        @include('admin.inventory.purchase_orders._status', ['status' => $po->status])
        @if ($canBuy)
            @if ($po->status === 'draft')
                <a href="{{ route('admin.inventory.purchase-orders.edit', $po->id) }}" class="px-3.5 py-2 rounded-xl border border-slate-200 bg-white text-xs font-bold text-slate-700">Edit draft</a>
                <form method="POST" action="{{ route('admin.inventory.purchase-orders.ordered', $po->id) }}">@csrf<button class="px-3.5 py-2 rounded-xl bg-sky-600 text-white text-xs font-bold">Mark as ordered</button></form>
            @endif
            @if (in_array($po->status, ['draft', 'ordered', 'partially_received'], true))
                <form method="POST" action="{{ route('admin.inventory.purchase-orders.close', $po->id) }}" onsubmit="return confirm('{{ $po->status === 'partially_received' ? 'Close this order? What arrived stays in stock; the rest is no longer expected.' : 'Cancel this purchase order?' }}')">
                    @csrf<button class="px-3.5 py-2 rounded-xl border border-rose-200 bg-white text-xs font-bold text-rose-600">{{ $po->status === 'partially_received' ? 'Close (rest not coming)' : 'Cancel order' }}</button>
                </form>
            @endif
        @endif
    </div>

    <div class="grid lg:grid-cols-3 gap-6">
        <form method="POST" action="{{ route('admin.inventory.purchase-orders.receive', $po->id) }}" class="lg:col-span-2 naoero-card overflow-hidden" onsubmit="return confirm('Add these units to stock?')">
            @csrf
            <div class="px-5 py-3 border-b border-slate-100 font-extrabold text-navy text-sm">{{ $receivable && $canBuy ? 'Lines · receive a delivery' : 'Lines' }}</div>
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500">
                    <tr><th class="px-4 py-3 text-left">Item</th><th class="px-4 py-3 text-right">Ordered</th><th class="px-4 py-3 text-right">Received</th><th class="px-4 py-3 text-right">Unit cost</th><th class="px-4 py-3 text-right">Line</th>
                        @if ($receivable && $canBuy)<th class="px-4 py-3 text-right w-28">Arrived now</th>@endif</tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($items as $i)
                        @php $left = $i->quantity_ordered - $i->quantity_received; @endphp
                        <tr>
                            <td class="px-4 py-2.5"><div class="font-bold text-slate-700">{{ $i->title }}</div><div class="text-[11px] font-mono text-slate-400">{{ $i->sku }}</div></td>
                            <td class="px-4 py-2.5 text-right">{{ $i->quantity_ordered }}</td>
                            <td class="px-4 py-2.5 text-right font-bold {{ $left === 0 ? 'text-emerald-700' : 'text-slate-700' }}">{{ $i->quantity_received }}</td>
                            <td class="px-4 py-2.5 text-right text-xs">{{ $money($i->unit_cost) }}</td>
                            <td class="px-4 py-2.5 text-right font-semibold">{{ $money($i->quantity_ordered * (float) $i->unit_cost) }}</td>
                            @if ($receivable && $canBuy)
                                <td class="px-4 py-2.5">
                                    @if ($left > 0 && $i->variant_id)
                                        <input type="number" name="receive[{{ $i->id }}]" min="0" max="{{ $left }}" placeholder="{{ $left }}" class="w-full rounded-lg border border-slate-200 px-2 py-1 text-sm text-right">
                                    @elseif (! $i->variant_id)
                                        <span class="text-[11px] text-rose-500">Item deleted</span>
                                    @else
                                        <span class="text-[11px] text-emerald-600 font-bold">✓ All in</span>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="px-4 py-3 border-t border-slate-100 flex items-center justify-between text-sm">
                <div class="text-slate-600">Items {{ $money($itemsCost) }} + freight {{ $money($po->shipping_cost) }} = <strong class="text-navy">{{ $money($itemsCost + (float) $po->shipping_cost) }}</strong></div>
                @if ($receivable && $canBuy && $items->contains(fn ($i) => $i->quantity_received < $i->quantity_ordered))
                    <button class="px-4 py-2 rounded-xl bg-emerald-600 text-white text-xs font-bold">Receive into stock</button>
                @endif
            </div>
        </form>

        <div class="naoero-card p-5 space-y-2 text-sm self-start">
            <div class="font-extrabold text-navy">Details</div>
            <div class="flex justify-between"><span class="text-slate-500">Supplier</span><span class="font-semibold text-right">{{ $po->supplier_name ?? '—' }}</span></div>
            @if ($po->supplier_contact)<div class="flex justify-between"><span class="text-slate-500">Contact</span><span>{{ $po->supplier_contact }}</span></div>@endif
            @if ($po->supplier_email)<div class="flex justify-between"><span class="text-slate-500">Email</span><a href="mailto:{{ $po->supplier_email }}" class="text-sky-700">{{ $po->supplier_email }}</a></div>@endif
            @if ($po->supplier_phone)<div class="flex justify-between"><span class="text-slate-500">Phone</span><span>{{ $po->supplier_phone }}</span></div>@endif
            <div class="flex justify-between"><span class="text-slate-500">Their reference</span><span>{{ $po->supplier_reference ?? '—' }}</span></div>
            <div class="flex justify-between"><span class="text-slate-500">Expected</span><span>{{ $po->expected_at ? \Carbon\Carbon::parse($po->expected_at)->format('j M Y') : '—' }}</span></div>
            <div class="flex justify-between"><span class="text-slate-500">Ordered</span><span>{{ $when($po->ordered_at) }}</span></div>
            <div class="flex justify-between"><span class="text-slate-500">Received</span><span>{{ $when($po->received_at) }}</span></div>
            @if ($po->cancelled_at)<div class="flex justify-between"><span class="text-slate-500">Cancelled</span><span>{{ $when($po->cancelled_at) }}</span></div>@endif
            @if ($po->notes)<div class="pt-2 border-t border-slate-100 text-xs text-slate-600">{{ $po->notes }}</div>@endif
        </div>
    </div>

    <div class="naoero-card overflow-hidden">
        <div class="px-5 py-3 border-b border-slate-100 font-extrabold text-navy text-sm">Deliveries received</div>
        @include('admin.inventory._movements_table', ['movements' => $receipts])
    </div>
</div>
@endsection
