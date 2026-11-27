@extends('shop.documents._layout', ['docTitle' => 'Payment receipt', 'docNumber' => $order->receipt_number])

@section('body')
@php
    $money = fn ($v) => $order->currency.' '.number_format((float) $v, 2);
    $date = fn ($v) => $v ? \Carbon\Carbon::parse($v)->timezone('Pacific/Nauru')->format('j M Y, H:i').' (Nauru time)' : '—';
    $card = trim(($payment['brand'] ?? 'Card').(isset($payment['last4']) ? ' •••• '.$payment['last4'] : ''));
@endphp
<div class="grid">
    <div>
        <h2>Received from</h2>
        <div><strong>{{ $customerName }}</strong></div>
        <div class="muted">{{ $order->contact_email }}</div>
    </div>
    <div>
        <h2>Payment</h2>
        <div>{{ $date($order->paid_at) }}</div>
        <div>{{ $card }}</div>
        <div class="muted">NAB transaction {{ $order->nab_transaction_id ?? '—' }}</div>
    </div>
    <div>
        <h2>For</h2>
        <div>Invoice {{ $order->invoice_number }}</div>
        <div class="muted">{{ $items->map(fn ($i) => $i->quantity.' × '.$i->title.(($i->variant_title ?? null) ? ' ('.$i->variant_title.')' : ''))->implode(', ') }}</div>
    </div>
</div>

<table>
    <thead><tr><th>Date</th><th>Description</th><th class="num">Amount</th></tr></thead>
    <tbody>
        <tr><td>{{ $date($order->paid_at) }}</td><td>Card payment ({{ $card }})</td><td class="num">{{ $money($order->total) }}</td></tr>
        @foreach ($refunds as $r)
            <tr><td>{{ $date($r->created_at) }}</td><td>Refund to card: {{ $r->reason }}@if ($r->nab_refund_id)<div class="muted">NAB refund {{ $r->nab_refund_id }}</div>@endif</td><td class="num">−{{ $money($r->amount) }}</td></tr>
        @endforeach
    </tbody>
</table>

<div class="totals">
    <div><span>Paid</span><span>{{ $money($order->total) }}</span></div>
    @if ((float) $order->refunded_amount > 0)<div><span>Refunded</span><span>−{{ $money($order->refunded_amount) }}</span></div>@endif
    <div class="grand"><span>Net received</span><span>{{ $money((float) $order->total - (float) $order->refunded_amount) }}</span></div>
</div>
@if ($giftCards->isNotEmpty())
    <p class="muted">Gift cards: {{ $giftCards->map(fn ($c) => ($c->voucher_code ? '•••• '.substr($c->voucher_code, -4) : 'pending').' ('.$c->status.')')->implode(', ') }}</p>
@endif
<p><span class="stamp {{ $order->status === 'refunded' ? 'refunded' : '' }}">{{ $order->status === 'refunded' ? 'REFUNDED' : 'PAYMENT RECEIVED' }}</span></p>
@endsection
