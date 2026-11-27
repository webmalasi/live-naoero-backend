@php
    $states = [
        'paid' => ['ok', 'Payment successful', 'Thank you! Your order is confirmed. Your receipt and any gift cards are in your order.'],
        'pending' => ['wait', 'Confirming your payment', 'Your bank accepted the payment and we are confirming it now. Your order will update in a moment.'],
        'review' => ['wait', 'Payment under review', 'Your payment is being checked by the bank. We will email you as soon as it is confirmed.'],
        'declined' => ['bad', 'Payment declined', 'Your card was not charged. You can try again with another card from your order.'],
        'cancelled' => ['bad', 'Payment cancelled', 'Nothing was charged. You can pay for this order later from your orders.'],
        'unknown' => ['wait', 'Payment finished', 'You can see the latest status of your order in your orders.'],
    ];
    [$tone, $title, $text] = $states[$status] ?? $states['unknown'];
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $title }} · Naoero Airlines</title>
    @if ($target)
        {{-- Works without JavaScript too. --}}
        <meta http-equiv="refresh" content="{{ $seconds }};url={{ $target }}">
    @endif
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #012169; color: #fff; font: 15px/1.5 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; text-align: center; padding: 24px; box-sizing: border-box; }
        main { max-width: 360px; }
        b.brand { display: block; letter-spacing: 3px; font-size: 12px; color: #ffc631; margin-bottom: 22px; }
        .icon { width: 72px; height: 72px; border-radius: 50%; display: grid; place-items: center; margin: 0 auto 18px; font-size: 36px; font-weight: 700; }
        .ok { background: #16a34a; } .wait { background: #ffc631; color: #012169; } .bad { background: #dc2626; }
        h1 { margin: 0 0 8px; font-size: 24px; }
        p { color: #c9d3ea; margin: 0 0 6px; }
        .order { color: #fff; font-weight: 600; letter-spacing: .5px; }
        a.btn { display: inline-block; margin-top: 22px; background: #ffc631; color: #012169; text-decoration: none; font-weight: 700; padding: 12px 26px; border-radius: 999px; }
        .count { margin-top: 12px; font-size: 13px; color: #9fb0d6; }
    </style>
</head>
<body>
<main>
    <b class="brand">NAOERO AIRLINES</b>
    <div class="icon {{ $tone }}">{{ $tone === 'ok' ? '✓' : ($tone === 'bad' ? '✕' : '…') }}</div>
    <h1>{{ $title }}</h1>
    @if ($orderNumber)
        <p class="order">Order {{ $orderNumber }}</p>
    @endif
    <p>{{ $text }}</p>
    @if ($target)
        <a class="btn" href="{{ $target }}">View my order</a>
        <div class="count" id="count">Taking you to your order in {{ $seconds }} seconds…</div>
        <script>
            (function () {
                var left = {{ (int) $seconds }}, el = document.getElementById('count');
                var tick = setInterval(function () {
                    left--;
                    if (left <= 0) { clearInterval(tick); el.textContent = 'Opening your order…'; location.replace(@json($target)); return; }
                    el.textContent = 'Taking you to your order in ' + left + (left === 1 ? ' second…' : ' seconds…');
                }, 1000);
            })();
        </script>
    @else
        <p class="count">You can close this page. Your order is in My Orders in the Naoero Airlines app or on the website.</p>
    @endif
</main>
</body>
</html>
