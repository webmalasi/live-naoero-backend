@extends('shop.documents._layout', ['docTitle' => 'Invoice', 'docNumber' => $order->invoice_number])

@section('body')
@php
    $money = fn ($v) => $order->currency.' '.number_format((float) $v, 2);
    $date = fn ($v) => $v ? \Carbon\Carbon::parse($v)->timezone('Pacific/Nauru')->format('j M Y') : '—';
    $refunded = (float) $order->refunded_amount;
@endphp
<div class="grid">
    <div>
        <h2>Billed to</h2>
        <div><strong>{{ $customerName }}</strong></div>
        <div class="muted">{{ $order->contact_email }}</div>
    </div>
    @if ($address)
        <div>
            <h2>Delivered to</h2>
            <div><strong>{{ $address['name'] ?? '' }}</strong></div>
            <div>{{ $address['line1'] ?? '' }}@if (! empty($address['line2'])), {{ $address['line2'] }}@endif</div>
            <div>{{ trim(($address['city'] ?? '').' '.($address['state'] ?? '').' '.($address['postcode'] ?? '')) }}</div>
            <div>{{ $address['country'] ?? '' }}</div>
        </div>
    @endif
    <div>
        <h2>Details</h2>
        <div>Invoice date: {{ $date($order->paid_at) }}</div>
        <div>Order date: {{ $date($order->created_at) }}</div>
        <div>Status: <strong>{{ ['paid' => 'Paid', 'partially_refunded' => 'Paid, partly refunded', 'refunded' => 'Refunded', 'payment_review' => 'Payment under review'][$order->status] ?? ucfirst($order->status) }}</strong></div>
    </div>
</div>

<table>
    <thead><tr><th>Item</th><th>SKU</th><th class="num">Qty</th><th class="num">Unit price</th><th class="num">Amount</th></tr></thead>
    <tbody>
        @foreach ($items as $i)
            <tr>
                <td>
                    <strong>{{ $i->title }}</strong>@if ($i->variant_title ?? null) · {{ $i->variant_title }}@endif
                    <div class="muted">{{ $i->type === 'gift_card' ? 'Digital gift card' : 'Merchandise' }}@if (($i->returned_quantity ?? 0) > 0) · {{ $i->returned_quantity }} returned @endif</div>
                </td>
                <td class="muted">{{ $i->sku ?? '—' }}</td>
                <td class="num">{{ $i->quantity }}</td>
                <td class="num">{{ $money($i->unit_price) }}</td>
                <td class="num">{{ $money($i->line_total) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<div class="totals">
    <div><span>Items</span><span>{{ $money($order->items_total) }}</span></div>
    <div><span>Delivery</span><span>{{ $money($order->delivery_fee) }}</span></div>
    <div class="grand"><span>Total</span><span>{{ $money($order->total) }}</span></div>
    <div><span>Paid by card</span><span>{{ $money($order->total) }}</span></div>
    @if ($refunded > 0)
        <div><span>Refunded</span><span>−{{ $money($refunded) }}</span></div>
        <div><strong>Net paid</strong><strong>{{ $money((float) $order->total - $refunded) }}</strong></div>
    @endif
    <div><span>Balance due</span><span>{{ $money(0) }}</span></div>
</div>
<p><span class="stamp {{ $order->status === 'refunded' ? 'refunded' : '' }}">{{ $order->status === 'refunded' ? 'REFUNDED' : 'PAID' }}</span></p>
@endsection
