@extends('layouts.admin')

@section('title', 'Order '.$order->order_number)

@section('content')
@php
    $money = fn ($v) => '$'.number_format((float) $v, 2);
    $when = fn ($v) => $v ? \Carbon\Carbon::parse($v)->timezone(config('app.timezone'))->format('j M Y, H:i:s') : '—';
    $payment = $order->payment_details ? json_decode($order->payment_details, true) : null;
    $address = $order->shipping_address ? json_decode($order->shipping_address, true) : null;
    $paidLike = in_array($order->status, ['paid', 'partially_refunded', 'payment_review'], true) && $order->nab_transaction_id;
    $canRefund = $paidLike && $refundable > 0 && auth()->user()->can('shop.sales.refund');
    $canManage = auth()->user()->can('shop.sales.manage');
@endphp
<div class="max-w-7xl mx-auto space-y-6" x-data="{ refundOpen: {{ $errors->has('amount') || $errors->has('reason') ? 'true' : 'false' }}, cancelOpen: false }">
    @include('admin.sales._nav', [
        'crumb' => 'Order '.$order->order_number,
        'title' => 'Order '.$order->order_number,
        'subtitle' => 'Placed '.($order->channel === 'website' ? 'on the website' : 'in the app').' on '.$when($order->created_at).' · '.$order->currency.' '.number_format((float) $order->total, 2),
    ])

    {{-- Summary bar --}}
    <div class="naoero-card p-5 flex flex-col md:flex-row md:items-center gap-4">
        <div class="flex flex-wrap items-center gap-2">
            @include('admin.sales._channel', ['channel' => $order->channel ?? 'app'])
            @include('admin.sales._tag', ['tag' => $order->status])
            @if ($order->shipping_status) @include('admin.sales._tag', ['tag' => $order->shipping_status]) @endif
            @if ($order->status === 'pending_payment')
                <span class="text-xs text-slate-500">Payment window ends {{ $when($order->expires_at) }}</span>
            @endif
        </div>
        <div class="md:ml-auto flex flex-wrap gap-2">
            @if ($canManage && in_array($order->status, ['pending_payment', 'payment_review'], true))
                <form method="POST" action="{{ route('admin.sales.reconcile', $order->id) }}">@csrf
                    <button class="px-3.5 py-2 rounded-xl border border-slate-200 bg-white text-xs font-bold text-slate-700 hover:bg-slate-50">↻ Check with NAB</button>
                </form>
            @endif
            @if ($canManage && $order->status === 'pending_payment')
                <button @click="cancelOpen = true" class="px-3.5 py-2 rounded-xl border border-rose-200 bg-rose-50 text-xs font-bold text-rose-700">Cancel order</button>
            @endif
            @if ($canRefund)
                <button @click="refundOpen = true" class="px-3.5 py-2 rounded-xl bg-purple-600 hover:bg-purple-700 text-white text-xs font-bold shadow-sm">↩ Refund</button>
            @endif
        </div>
    </div>

    <div class="grid lg:grid-cols-3 gap-5">
        <div class="lg:col-span-2 space-y-5">
            {{-- Items --}}
            <div class="naoero-card overflow-hidden">
                <div class="px-5 py-3 border-b border-slate-100 font-extrabold text-navy text-sm">Items</div>
                <table class="min-w-full text-sm">
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($items as $i)
                            @php $gift = $i->gift ? json_decode($i->gift, true) : null; @endphp
                            <tr>
                                <td class="pl-5 pr-3 py-3 w-20">
                                    <div class="w-14 h-14 rounded-xl bg-slate-50 border border-slate-100 p-1.5 flex items-center justify-center">
                                        @php $img = \App\Services\Shop\ShopCatalog::imageUrl($i->image); @endphp
                                        @if ($img)<img src="{{ $img }}" class="max-w-full max-h-full object-contain" alt="" onerror="this.replaceWith(Object.assign(document.createElement('span'), {textContent: '{{ $i->type === 'gift_card' ? '🎁' : '👕' }}', className: 'text-2xl'}))">@else<span class="text-2xl">{{ $i->type === 'gift_card' ? '🎁' : '👕' }}</span>@endif
                                    </div>
                                </td>
                                <td class="py-3">
                                    <div class="font-bold text-slate-800">{{ $i->title }}@if ($i->variant_title ?? null) <span class="font-semibold text-slate-500">· {{ $i->variant_title }}</span>@endif</div>@if ($i->sku ?? null)<div class="text-[11px] font-mono text-slate-400">{{ $i->sku }}@if (($i->returned_quantity ?? 0) > 0) · {{ $i->returned_quantity }} back in stock @endif</div>@endif
                                    <div class="text-[11px] text-slate-400">{{ $i->type === 'gift_card' ? 'Gift card' : 'Merchandise' }} · {{ $i->quantity }} × {{ $money($i->unit_price) }}</div>
                                    @if ($gift)
                                        <div class="text-[11px] text-slate-500 mt-0.5">🎁 For {{ $gift['recipientName'] ?? '—' }} {{ isset($gift['recipientEmail']) ? '<'.$gift['recipientEmail'].'>' : '' }}{{ isset($gift['message']) ? ' · “'.$gift['message'].'”' : '' }}</div>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-right font-extrabold text-navy">{{ $money($i->line_total) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="px-5 py-3 bg-slate-50 text-sm space-y-1">
                    <div class="flex justify-between text-slate-500"><span>Items</span><span>{{ $money($order->items_total) }}</span></div>
                    <div class="flex justify-between text-slate-500"><span>Delivery</span><span>{{ (float) $order->delivery_fee > 0 ? $money($order->delivery_fee) : 'Free' }}</span></div>
                    <div class="flex justify-between font-extrabold text-navy"><span>Total</span><span>{{ $money($order->total) }} {{ $order->currency }}</span></div>
                    @if ((float) $order->refunded_amount > 0)
                        <div class="flex justify-between font-bold text-purple-700"><span>Refunded</span><span>−{{ $money($order->refunded_amount) }}</span></div>
                        <div class="flex justify-between font-extrabold text-slate-800"><span>Kept</span><span>{{ $money((float) $order->total - (float) $order->refunded_amount) }}</span></div>
                    @endif
                </div>
            </div>

            {{-- Gift cards --}}
            @if ($giftCards->isNotEmpty())
                <div class="naoero-card overflow-hidden">
                    <div class="px-5 py-3 border-b border-slate-100 font-extrabold text-navy text-sm">Gift cards ({{ $giftCards->count() }})</div>
                    <div class="divide-y divide-slate-100">
                        @foreach ($giftCards as $c)
                            <div class="px-5 py-3 flex flex-col sm:flex-row sm:items-center gap-3" x-data="{ cancelCard: false }">
                                <div class="flex-1">
                                    <div class="font-mono font-extrabold text-navy tracking-wider">{{ $c->voucher_code }}</div>
                                    <div class="text-[11px] text-slate-400">
                                        {{ $money($c->amount) }} · balance {{ $money($c->balance_remaining) }} · valid until {{ $c->view['expiresAt'] ?? '—' }}
                                        @if ($c->recipient_email) · to {{ $c->recipient_email }} @endif
                                    </div>
                                </div>
                                @include('admin.sales._tag', ['tag' => match ($c->view['status']) { 'issued', 'confirmed' => 'success', 'redeemed' => 'info', 'expired' => 'abandoned', default => 'cancelled' }, 'text' => ucfirst($c->view['status'])])
                                @if ($canManage && in_array($c->status, ['issued', 'confirmed'], true))
                                    <div class="flex gap-2">
                                        <form method="POST" action="{{ route('admin.sales.gift-cards.resend', $c->id) }}">@csrf
                                            <button class="px-2.5 py-1.5 rounded-lg border border-slate-200 text-[11px] font-bold text-slate-600 hover:bg-slate-50">Resend email</button>
                                        </form>
                                        <button @click="cancelCard = !cancelCard" class="px-2.5 py-1.5 rounded-lg border border-rose-200 text-[11px] font-bold text-rose-700">Cancel</button>
                                    </div>
                                    <form x-show="cancelCard" x-cloak method="POST" action="{{ route('admin.sales.gift-cards.cancel', $c->id) }}" class="flex gap-2 w-full sm:w-auto">@csrf
                                        <input name="reason" required minlength="3" placeholder="Reason" class="rounded-lg border border-slate-200 px-2 py-1 text-xs">
                                        <button class="px-2.5 py-1 rounded-lg bg-rose-600 text-white text-[11px] font-bold">Confirm</button>
                                    </form>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Shipping --}}
            @if ($order->shipping_status || $address)
                <div class="naoero-card p-5 space-y-3">
                    <div class="flex items-center justify-between">
                        <h2 class="font-extrabold text-navy text-sm">Delivery</h2>
                        @if ($order->shipping_status) @include('admin.sales._tag', ['tag' => $order->shipping_status]) @endif
                    </div>
                    @if ($address)
                        <div class="text-sm text-slate-700 leading-relaxed">
                            <b>{{ $address['name'] ?? '' }}</b> · {{ \App\Services\Support\Phones::display($order->contact_phone ?? ($address['phone'] ?? '')) }}<br>
                            {{ $address['line1'] ?? '' }}@if (! empty($address['line2'])), {{ $address['line2'] }}@endif<br>
                            {{ $address['city'] ?? '' }} {{ $address['state'] ?? '' }} {{ $address['postcode'] ?? '' }}, {{ $address['country'] ?? '' }}
                        </div>
                    @endif
                    @if ($order->shipped_at)
                        <div class="text-xs text-slate-500">Shipped {{ $when($order->shipped_at) }} with <b>{{ $order->shipping_carrier }}</b>
                            @if ($order->tracking_number) · tracking
                                @if ($order->tracking_url)<a href="{{ $order->tracking_url }}" target="_blank" rel="noopener" class="font-bold text-navy underline">{{ $order->tracking_number }}</a>@else<b>{{ $order->tracking_number }}</b>@endif
                            @endif
                            @if ($order->delivered_at) · delivered {{ $when($order->delivered_at) }} @endif
                        </div>
                    @endif
                    @if ($delivery)
                        {{-- The steps the customer sees --}}
                        <div class="flex flex-wrap gap-1.5 pt-1">
                            @foreach ($delivery['steps'] as $step)
                                <span class="px-2 py-1 rounded-lg text-[10px] font-bold {{ $step['current'] ? 'bg-navy text-white' : ($step['done'] ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-400') }}">{{ $step['done'] ? '✓ ' : '' }}{{ $step['label'] }}</span>
                            @endforeach
                        </div>
                        @if ($delivery['estimatedDelivery'])<div class="text-[11px] text-slate-500">Estimated delivery {{ \Carbon\Carbon::parse($delivery['estimatedDelivery'])->format('D j M Y') }}</div>@endif
                        @if ($delivery['events'])
                            <ol class="border-l-2 border-slate-100 ml-1 space-y-2 pt-1">
                                @foreach ($delivery['events'] as $ev)
                                    <li class="pl-3 text-xs"><span class="font-bold text-slate-700">{{ $ev['label'] }}</span>@if ($ev['location']) · {{ $ev['location'] }}@endif
                                        <span class="text-slate-400"> · {{ $when($ev['at']) }}</span><div class="text-slate-500">{{ $ev['message'] }}</div></li>
                                @endforeach
                            </ol>
                        @endif
                    @endif
                    @if ($canManage && in_array($order->shipping_status, ['processing', 'packed'], true) && in_array($order->status, ['paid', 'partially_refunded'], true))
                        <form method="POST" action="{{ route('admin.sales.ship', $order->id) }}" class="grid sm:grid-cols-4 gap-2 pt-2 border-t border-slate-100">@csrf
                            <input name="carrier" required placeholder="Carrier (e.g. Australia Post)" value="{{ old('carrier') }}" class="rounded-xl border border-slate-200 px-3 py-2 text-sm">
                            <input name="tracking_number" placeholder="Tracking number" value="{{ old('tracking_number') }}" class="rounded-xl border border-slate-200 px-3 py-2 text-sm">
                            <input name="tracking_url" type="url" placeholder="Tracking link (optional)" value="{{ old('tracking_url') }}" class="rounded-xl border border-slate-200 px-3 py-2 text-sm">
                            <button class="rounded-xl bg-navy text-white text-xs font-bold px-3 py-2">Mark as shipped</button>
                        </form>
                    @endif
                    @if ($canManage && $nextDelivery && in_array($order->status, ['paid', 'partially_refunded'], true))
                        <form method="POST" action="{{ route('admin.sales.shipment', $order->id) }}" class="grid sm:grid-cols-6 gap-2 pt-2 border-t border-slate-100">@csrf
                            <select name="status" required class="sm:col-span-2 rounded-xl border border-slate-200 px-3 py-2 text-sm">
                                @foreach ($nextDelivery as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                            </select>
                            <input name="location" placeholder="Where (e.g. Brisbane depot)" class="sm:col-span-2 rounded-xl border border-slate-200 px-3 py-2 text-sm">
                            <input name="estimated_delivery" type="date" title="New estimated delivery date" class="sm:col-span-2 rounded-xl border border-slate-200 px-3 py-2 text-sm">
                            <input name="message" maxlength="500" placeholder="Message for the customer (optional)" class="sm:col-span-4 rounded-xl border border-slate-200 px-3 py-2 text-sm">
                            <button class="sm:col-span-2 rounded-xl bg-emerald-600 text-white text-xs font-bold px-3 py-2">Update delivery</button>
                            @if ($order->shipping_status === 'shipped')
                                <span class="sm:col-span-6 text-[11px] text-slate-400">"Delivered" from here is the same as Mark as delivered.</span>
                            @endif
                        </form>
                        @if ($order->shipping_status === 'shipped')
                            <form method="POST" action="{{ route('admin.sales.deliver', $order->id) }}">@csrf
                                <button class="rounded-xl bg-emerald-600 text-white text-xs font-bold px-4 py-2">Mark as delivered</button>
                            </form>
                        @endif
                    @endif
                </div>
            @endif

            {{-- Returns and exchanges --}}
            @if ($returns->isNotEmpty())
                <div class="naoero-card overflow-hidden">
                    <div class="px-5 py-3 border-b border-slate-100 font-extrabold text-navy text-sm">Returns &amp; exchanges ({{ $returns->count() }})</div>
                    <div class="divide-y divide-slate-100">
                        @foreach ($returns as $rt)
                            <a href="{{ route('admin.sales.returns.show', $rt->id) }}" class="px-5 py-3 flex items-center gap-3 hover:bg-slate-50">
                                <span class="font-mono font-extrabold text-navy">{{ $rt->return_number }}</span>
                                <span class="text-xs text-slate-600">{{ ucfirst($rt->type) }} · {{ \App\Services\Shop\ShopReturns::REASONS[$rt->reason] ?? $rt->reason }}</span>
                                <span class="ml-auto px-2 py-0.5 rounded-full bg-slate-100 text-[10px] font-extrabold uppercase text-slate-700">{{ \App\Services\Shop\ShopReturns::STATUSES[$rt->status] ?? $rt->status }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Help requests --}}
            @if ($supportRequests->isNotEmpty())
                <div class="naoero-card overflow-hidden">
                    <div class="px-5 py-3 border-b border-slate-100 font-extrabold text-navy text-sm">Help requests ({{ $supportRequests->count() }})</div>
                    <div class="divide-y divide-slate-100">
                        @foreach ($supportRequests as $sr)
                            <a href="{{ route('admin.sales.support.show', $sr->id) }}" class="px-5 py-3 flex items-center gap-3 hover:bg-slate-50">
                                <span class="font-mono font-extrabold text-navy">{{ $sr->number }}</span>
                                <span class="text-xs text-slate-600">{{ $sr->subject }}</span>
                                <span class="ml-auto px-2 py-0.5 rounded-full text-[10px] font-extrabold uppercase {{ $sr->status === 'open' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-600' }}">{{ \App\Services\Shop\ShopSupport::STATUSES[$sr->status] ?? $sr->status }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Payment attempts --}}
            <div class="naoero-card overflow-hidden">
                <div class="px-5 py-3 border-b border-slate-100 font-extrabold text-navy text-sm">Payment attempts ({{ $attempts->count() }})</div>
                <table class="min-w-full text-xs">
                    <thead class="bg-slate-50 text-[10px] uppercase tracking-wider text-slate-500">
                        <tr><th class="px-4 py-2 text-left">Started</th><th class="px-4 py-2 text-left">Outcome</th><th class="px-4 py-2 text-left">Card</th><th class="px-4 py-2 text-left">Reason</th><th class="px-4 py-2 text-left">NAB transaction</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($attempts as $a)
                            <tr>
                                <td class="px-4 py-2 text-slate-600 whitespace-nowrap">{{ $when($a->created_at) }}</td>
                                <td class="px-4 py-2">@include('admin.sales._tag', ['tag' => \App\Http\Controllers\Admin\ShopSalesController::attemptOutcome((object) [...(array) $a, 'order_status' => $order->status])])</td>
                                <td class="px-4 py-2 text-slate-600">{{ trim(($a->card_brand ?? '').' '.($a->card_last4 ? '•••• '.$a->card_last4 : '')) ?: '—' }}</td>
                                <td class="px-4 py-2 text-slate-500">{{ $a->reason ?? ($a->status === 'started' ? ($order->status === 'pending_payment' ? 'Card form not completed yet' : 'Card form not completed') : '—') }}</td>
                                <td class="px-4 py-2 font-mono text-slate-600">{{ $a->nab_transaction_id ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-6 text-center text-slate-400">The customer never opened the payment page.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Refunds --}}
            @if ($refunds->isNotEmpty())
                <div class="naoero-card overflow-hidden">
                    <div class="px-5 py-3 border-b border-slate-100 font-extrabold text-navy text-sm">Refunds</div>
                    <table class="min-w-full text-xs">
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($refunds as $r)
                                <tr>
                                    <td class="px-4 py-2 whitespace-nowrap text-slate-600">{{ $when($r->created_at) }}</td>
                                    <td class="px-4 py-2 font-extrabold text-purple-700">{{ $money($r->amount) }}</td>
                                    <td class="px-4 py-2">@include('admin.sales._tag', ['tag' => $r->status])</td>
                                    <td class="px-4 py-2 text-slate-600">{{ $r->reason }}@if ($r->failure_reason)<div class="text-rose-600">{{ $r->failure_reason }}</div>@endif</td>
                                    <td class="px-4 py-2 text-slate-500">{{ $r->staff_name ?? '—' }}</td>
                                    <td class="px-4 py-2 font-mono text-slate-500">{{ $r->nab_refund_id ?? '' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="space-y-5">
            {{-- Customer --}}
            <div class="naoero-card p-5 space-y-2">
                <h2 class="font-extrabold text-navy text-sm">Customer</h2>
                <div class="text-sm font-bold text-slate-800">{{ trim($order->first_name.' '.$order->last_name) ?: '—' }}</div>
                <div class="text-xs text-slate-500">{{ $order->contact_email }}@if ($phone) · <a href="tel:{{ $phone }}" class="text-navy hover:underline">{{ \App\Services\Support\Phones::display($phone) }}</a>@endif</div>
                <div class="text-[11px] text-slate-400">Zenith customer {{ $order->zenith_customer_ref }} · app member since {{ \Carbon\Carbon::parse($order->customer_since)->format('M Y') }}</div>
                <div class="grid grid-cols-2 gap-2 pt-2">
                    <a href="{{ route('admin.sales.index', ['search' => $order->zenith_login]) }}" class="rounded-xl bg-slate-50 p-2.5 hover:bg-slate-100">
                        <div class="text-[10px] font-bold uppercase text-slate-400">Orders</div><div class="font-extrabold text-navy">{{ $customerOrders }}</div>
                    </a>
                    <div class="rounded-xl bg-slate-50 p-2.5"><div class="text-[10px] font-bold uppercase text-slate-400">Spent</div><div class="font-extrabold text-navy">{{ $money($customerSpend) }}</div></div>
                </div>
            </div>

            {{-- Payment --}}
            <div class="naoero-card p-5 space-y-1.5 text-xs">
                <h2 class="font-extrabold text-navy text-sm mb-1">Payment</h2>
                <div class="flex justify-between"><span class="text-slate-500">Method</span><span class="font-bold">{{ $payment ? ($payment['brand'] ?? 'Card').(isset($payment['last4']) ? ' •••• '.$payment['last4'] : '') : '—' }}</span></div>
                <div class="flex justify-between"><span class="text-slate-500">3-D Secure (ECI)</span><span class="font-bold">{{ $payment['threeDSecure'] ?? '—' }}</span></div>
                <div class="flex justify-between"><span class="text-slate-500">Paid</span><span class="font-bold">{{ $when($order->paid_at) }}</span></div>
                <div class="flex justify-between gap-2"><span class="text-slate-500">NAB transaction</span><span class="font-mono font-bold break-all text-right">{{ $order->nab_transaction_id ?? '—' }}</span></div>
                <div class="flex justify-between"><span class="text-slate-500">NAB reference</span><span class="font-mono font-bold">{{ $order->order_number }}</span></div>
                @if ($paidLike)<div class="flex justify-between"><span class="text-slate-500">Refundable</span><span class="font-bold text-purple-700">{{ $money($refundable) }}</span></div>@endif
            </div>

            {{-- Invoice and receipt --}}
            <div class="naoero-card p-5 space-y-2 text-xs">
                <h2 class="font-extrabold text-navy text-sm">Documents</h2>
                @if ($order->invoice_number)
                    <a href="{{ route('admin.sales.document', [$order->id, 'invoice']) }}" target="_blank" class="flex justify-between rounded-xl bg-slate-50 px-3 py-2 hover:bg-slate-100"><span class="font-bold text-navy">Invoice</span><span class="font-mono">{{ $order->invoice_number }}</span></a>
                    <a href="{{ route('admin.sales.document', [$order->id, 'receipt']) }}" target="_blank" class="flex justify-between rounded-xl bg-slate-50 px-3 py-2 hover:bg-slate-100"><span class="font-bold text-navy">Payment receipt</span><span class="font-mono">{{ $order->receipt_number }}</span></a>
                @else
                    <div class="text-slate-400">The invoice and receipt are made when the order is paid.</div>
                @endif
                <div class="text-slate-500 pt-1">Customer can cancel online: <b>{{ $cancellation['allowed'] ? ($cancellation['kind'] === 'refund' ? 'yes, with a full refund' : 'yes (unpaid)') : 'no' }}</b>@if (! $cancellation['allowed']) · {{ $cancellation['reason'] }}@endif</div>
            </div>

            {{-- Timeline --}}
            <div class="naoero-card p-5">
                <h2 class="font-extrabold text-navy text-sm mb-3">Timeline</h2>
                @if ($canManage)
                    <form method="POST" action="{{ route('admin.sales.note', $order->id) }}" class="mb-4 flex gap-2">@csrf
                        <input name="note" required minlength="2" maxlength="1000" placeholder="Add an internal note…" class="flex-1 rounded-xl border border-slate-200 px-3 py-2 text-xs">
                        <button class="px-3 py-2 rounded-xl bg-navy text-white text-xs font-bold">Add</button>
                    </form>
                @endif
                <ol class="relative border-l-2 border-slate-100 ml-2 space-y-4">
                    @foreach (array_reverse($timeline) as $e)
                        @php
                            $dot = ['success' => 'bg-emerald-500', 'refunded' => 'bg-purple-500', 'declined' => 'bg-rose-500', 'failed' => 'bg-red-600', 'review' => 'bg-amber-500', 'warning' => 'bg-orange-500', 'pending' => 'bg-yellow-400', 'cancelled' => 'bg-slate-400'][$e['outcome']] ?? 'bg-navy-300';
                        @endphp
                        <li class="ml-4">
                            <span class="absolute -left-[7px] mt-1 w-3 h-3 rounded-full ring-4 ring-white {{ $dot }}"></span>
                            <div class="flex flex-wrap items-center gap-1.5">
                                <span class="text-xs font-extrabold text-slate-800">{{ $e['label'] }}</span>
                                @include('admin.sales._tag', ['tag' => $e['outcome']])
                            </div>
                            <p class="text-xs text-slate-600 mt-0.5">{{ $e['message'] }}</p>
                            <p class="text-[10px] text-slate-400 mt-0.5">{{ $when($e['at']) }} · {{ $e['staff'] ? $e['staff'] : ucfirst($e['source']) }}</p>
                        </li>
                    @endforeach
                </ol>
            </div>
        </div>
    </div>

    {{-- Refund dialog --}}
    @if ($canRefund)
        <div x-show="refundOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-navy-950/60 p-4" @keydown.escape.window="refundOpen = false">
            <form method="POST" action="{{ route('admin.sales.refund', $order->id) }}" class="w-full max-w-md bg-white rounded-2xl shadow-2xl p-6 space-y-4" @click.outside="refundOpen = false"
                  x-data="{ amount: '{{ old('amount', number_format($refundable, 2, '.', '')) }}' }" onsubmit="return confirm('Refund $' + this.amount.value + ' to the customer\'s card through NAB? This cannot be undone.')">
                @csrf
                <div>
                    <h3 class="text-lg font-extrabold text-navy">Refund order {{ $order->order_number }}</h3>
                    <p class="text-xs text-slate-500 mt-1">The money goes back to {{ $payment ? ($payment['brand'] ?? 'the card').(isset($payment['last4']) ? ' •••• '.$payment['last4'] : '') : 'the card' }} through NAB. Up to {{ $money($refundable) }} can be refunded.</p>
                </div>
                <label class="block text-xs font-bold text-slate-700">Amount ({{ $order->currency }})
                    <input name="amount" x-model="amount" type="number" step="0.01" min="0.01" max="{{ $refundable }}" required class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                </label>
                <div class="flex gap-2">
                    <button type="button" @click="amount = '{{ number_format($refundable, 2, '.', '') }}'" class="px-2.5 py-1 rounded-lg bg-slate-100 text-[11px] font-bold">Full</button>
                    @if ((float) $order->delivery_fee > 0 && $refundable >= (float) $order->delivery_fee)
                        <button type="button" @click="amount = '{{ number_format((float) $order->delivery_fee, 2, '.', '') }}'" class="px-2.5 py-1 rounded-lg bg-slate-100 text-[11px] font-bold">Delivery fee</button>
                    @endif
                </div>
                <label class="block text-xs font-bold text-slate-700">Reason
                    <input name="reason" required minlength="3" maxlength="300" value="{{ old('reason') }}" placeholder="e.g. Customer changed their mind" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                </label>
                @php $restockable = $items->filter(fn ($i) => $i->type === 'merch' && in_array($i->stock_state ?? null, ['sold', 'reserved'], true) && $i->quantity > ($i->returned_quantity ?? 0)); @endphp
                @if ($restockable->isNotEmpty())
                    <div class="rounded-xl border border-slate-200 p-3 space-y-2">
                        <div class="text-xs font-bold text-slate-700">Put merchandise back in stock <span class="font-normal text-slate-400">(only what came back unused)</span></div>
                        @foreach ($restockable as $i)
                            @php $left = $i->quantity - ($i->returned_quantity ?? 0); @endphp
                            <label class="flex items-center justify-between gap-3 text-xs text-slate-600">
                                <span>{{ \App\Services\Shop\Inventory::label($i->title, $i->variant_title ?? null) }} <span class="text-slate-400">(up to {{ $left }})</span></span>
                                <input type="number" name="restock[{{ $i->id }}]" min="0" max="{{ $left }}" value="{{ old("restock.{$i->id}", 0) }}" class="w-20 rounded-lg border border-slate-200 px-2 py-1 text-sm text-right">
                            </label>
                        @endforeach
                    </div>
                @endif
                @if ($giftCards->where('status', 'issued')->isNotEmpty())
                    <label class="flex items-start gap-2 text-xs text-slate-600">
                        <input type="checkbox" name="cancel_gift_cards" value="1" class="mt-0.5 rounded">
                        <span>Cancel the order's unused gift cards (always done for a full refund; partly used cards stay active).</span>
                    </label>
                @endif
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="refundOpen = false" class="px-4 py-2 rounded-xl border border-slate-200 text-xs font-bold">Keep</button>
                    <button class="px-4 py-2 rounded-xl bg-purple-600 text-white text-xs font-bold">Refund through NAB</button>
                </div>
            </form>
        </div>
    @endif

    {{-- Cancel dialog --}}
    @if ($canManage && $order->status === 'pending_payment')
        <div x-show="cancelOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-navy-950/60 p-4" @keydown.escape.window="cancelOpen = false">
            <form method="POST" action="{{ route('admin.sales.cancel', $order->id) }}" class="w-full max-w-md bg-white rounded-2xl shadow-2xl p-6 space-y-4" @click.outside="cancelOpen = false">@csrf
                <h3 class="text-lg font-extrabold text-navy">Cancel unpaid order?</h3>
                <p class="text-xs text-slate-500">We check with NAB first, so an order that was in fact paid is never cancelled. Reserved stock goes back on sale.</p>
                <input name="reason" required minlength="3" placeholder="Reason" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                <div class="flex justify-end gap-2">
                    <button type="button" @click="cancelOpen = false" class="px-4 py-2 rounded-xl border border-slate-200 text-xs font-bold">Keep</button>
                    <button class="px-4 py-2 rounded-xl bg-rose-600 text-white text-xs font-bold">Cancel order</button>
                </div>
            </form>
        </div>
    @endif
</div>
@endsection
