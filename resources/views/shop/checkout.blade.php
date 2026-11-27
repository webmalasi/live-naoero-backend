<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex">
    <meta name="theme-color" content="#012169">
    <title>Secure payment · Naoero Airlines</title>
    <style>
        :root {
            --navy: #012169; --navy-2: #0a2d7a; --gold: #ffc631; --ink: #1b2a4a; --muted: #6b7690;
            --line: #e3e8f2; --bg: #f3f6fb; --ok: #12805c; --bad: #c0392b;
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; background: var(--bg); color: var(--ink); font: 15px/1.45 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; -webkit-font-smoothing: antialiased; }
        .hero { background: linear-gradient(160deg, var(--navy) 0%, var(--navy-2) 100%); color: #fff; padding: calc(18px + env(safe-area-inset-top)) 20px 64px; position: relative; overflow: hidden; }
        .hero::after { content: ""; position: absolute; right: -60px; top: -40px; width: 220px; height: 220px; border-radius: 50%; border: 34px solid rgba(255,198,49,.12); }
        /* The logo is navy on transparent: it sits on a white badge. */
        /* The image has wide empty margins of its own: those pad the badge. */
        .brand { display: inline-flex; align-items: center; background: #fff; border-radius: 14px; padding: 0 4px; box-shadow: 0 6px 18px rgba(0,0,0,.18); position: relative; z-index: 1; overflow: hidden; }
        .brand img { display: block; height: 58px; width: auto; }
        .hero h1 { margin: 18px 0 4px; font-size: 22px; }
        .hero p { margin: 0; color: #c9d3ea; font-size: 13px; }
        .amount { margin-top: 14px; font-size: 34px; font-weight: 800; letter-spacing: -.5px; }
        .amount small { font-size: 14px; font-weight: 600; color: var(--gold); margin-left: 6px; letter-spacing: 1px; }
        main { margin: -44px 14px 24px; position: relative; z-index: 1; }
        .card { background: #fff; border-radius: 18px; box-shadow: 0 10px 30px rgba(1,33,105,.10); padding: 18px; margin-bottom: 14px; }
        .summary-head { display: flex; justify-content: space-between; align-items: center; cursor: pointer; user-select: none; }
        .summary-head b { font-size: 15px; color: var(--navy); }
        .summary-head span { color: var(--muted); font-size: 13px; }
        .chev { transition: transform .2s; display: inline-block; margin-left: 6px; }
        .open .chev { transform: rotate(180deg); }
        .lines { display: none; margin-top: 14px; border-top: 1px dashed var(--line); padding-top: 10px; }
        .open .lines { display: block; }
        .line { display: flex; gap: 12px; align-items: center; padding: 8px 0; }
        .line img { width: 44px; height: 44px; border-radius: 10px; object-fit: cover; background: var(--bg); flex: none; }
        .line .t { flex: 1; font-size: 14px; }
        .line .t small { display: block; color: var(--muted); font-size: 12px; }
        .line .p { font-weight: 700; color: var(--navy); }
        .row { display: flex; justify-content: space-between; font-size: 14px; padding: 4px 0; color: var(--muted); }
        .row.total { color: var(--navy); font-weight: 800; font-size: 16px; padding-top: 8px; }
        .pay-title { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
        .pay-title b { color: var(--navy); font-size: 16px; }
        .lock { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; color: var(--ok); font-weight: 600; }
        #ucButtons { min-height: 10px; }
        /* NAB's card form needs about 320 px: let it use the card's full width on phones. */
        #ucScreen iframe, #ucButtons iframe { width: 100% !important; }
        @media (max-width: 480px) {
            main { margin: -44px 10px 24px; }
            #payArea { margin: 0 -12px; }
            #payArea .pay-title { padding: 0 12px; }
        }
        #ucScreen { min-height: 20px; }
        .state { text-align: center; padding: 26px 10px 14px; }
        .state h2 { color: var(--navy); margin: 14px 0 6px; font-size: 19px; }
        .state p { color: var(--muted); margin: 0 0 16px; font-size: 14px; }
        .spinner { width: 46px; height: 46px; border-radius: 50%; border: 4px solid var(--line); border-top-color: var(--navy); margin: 0 auto; animation: spin .9s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .badge { width: 64px; height: 64px; border-radius: 50%; margin: 0 auto; display: grid; place-items: center; animation: pop .45s cubic-bezier(.2,1.6,.4,1); }
        .badge.ok { background: #e3f6ee; color: var(--ok); }
        .badge.bad { background: #fdecea; color: var(--bad); }
        .badge svg { width: 34px; height: 34px; }
        @keyframes pop { from { transform: scale(.3); opacity: 0; } to { transform: scale(1); opacity: 1; } }
        .btn { display: block; width: 100%; border: 0; border-radius: 12px; padding: 14px; font-size: 15px; font-weight: 700; cursor: pointer; text-decoration: none; text-align: center; }
        .btn.primary { background: var(--navy); color: #fff; }
        .btn.ghost { background: transparent; color: var(--navy); margin-top: 8px; }
        .trust { display: flex; justify-content: center; gap: 16px; flex-wrap: wrap; color: var(--muted); font-size: 12px; margin: 6px 0 4px; }
        .trust span { display: inline-flex; align-items: center; gap: 5px; }
        .hide { display: none !important; }
        .note { font-size: 12px; color: var(--muted); text-align: center; margin-top: 10px; }

        /* Progress: order -> payment -> confirmation. */
        .steps { list-style: none; display: flex; align-items: center; gap: 8px; margin: 18px 0 0; padding: 0; font-size: 12px; color: #9fb0d6; position: relative; z-index: 1; }
        .steps li { display: flex; align-items: center; gap: 6px; white-space: nowrap; }
        .steps li + li::before { content: ""; width: 18px; height: 2px; border-radius: 2px; background: rgba(255,255,255,.25); margin-right: 2px; }
        .steps i { width: 18px; height: 18px; border-radius: 50%; display: grid; place-items: center; font-style: normal; font-size: 10px; font-weight: 800; border: 2px solid rgba(255,255,255,.35); }
        .steps .done { color: #dfe7f7; }
        .steps .done i { background: var(--gold); border-color: var(--gold); color: var(--navy); }
        .steps .now { color: #fff; font-weight: 700; }
        .steps .now i { border-color: var(--gold); color: var(--gold); box-shadow: 0 0 0 4px rgba(255,198,49,.18); }

        .shell-foot { padding: 4px 16px calc(20px + env(safe-area-inset-bottom)); }

        /* Larger screens: the checkout sits in one card on a light page
           instead of spanning the window. Phones keep the full width the
           NAB card form needs. */
        @media (min-width: 560px) {
            html, body { min-height: 100%; background: #e9eef8; }
            .shell { width: calc(100% - 48px); max-width: 600px; margin: 40px auto 48px; background: var(--bg); border-radius: 26px; overflow: hidden; box-shadow: 0 24px 60px rgba(1,20,70,.16), 0 2px 6px rgba(1,20,70,.08); }
            .hero { padding: 26px 32px 72px; }
            .brand img { height: 68px; }
            main { margin: -48px 22px 8px; }
            .card { padding: 22px 26px; }
            .shell-foot { padding: 6px 26px 24px; }
        }
        @media (max-width: 380px) { .brand img { height: 52px; } }
    </style>
</head>
<body>
@php
    $order = $data['order'] ?? null;
    $money = fn ($v) => '$'.number_format((float) $v, 2);
@endphp
<div class="shell">
<header class="hero">
    <div class="brand">
        <img src="{{ url('/assets/logo.png') }}" alt="Naoero Airlines" width="193" height="68">
    </div>
    <h1>Secure checkout</h1>
    <p>{{ $order ? 'Order '.$order['orderNumber'] : 'Naoero Shop' }}</p>
    @if ($order)
        <div class="amount">{{ $money($order['total']) }}<small>{{ $order['currency'] }}</small></div>
    @endif
    @php $paidAlready = ($data['state'] ?? '') === 'paid'; @endphp
    <ol class="steps" aria-label="Checkout progress">
        <li class="done"><i>✓</i>Order</li>
        <li class="{{ $paidAlready ? 'done' : 'now' }}" id="stepPay"><i>{{ $paidAlready ? '✓' : '2' }}</i>Payment</li>
        <li class="{{ $paidAlready ? 'done' : '' }}" id="stepDone"><i>{{ $paidAlready ? '✓' : '3' }}</i>Confirmation</li>
    </ol>
</header>

<main>
    @if ($order)
        <section class="card open" id="summary">
            <div class="summary-head" onclick="this.parentElement.classList.toggle('open')">
                <b>Order summary</b>
                <span>{{ count($order['items']) }} {{ count($order['items']) === 1 ? 'item' : 'items' }} <span class="chev">▾</span></span>
            </div>
            <div class="lines">
                @foreach ($order['items'] as $item)
                    <div class="line">
                        @if ($item['image'])<img src="{{ $item['image'] }}" alt="">@endif
                        <div class="t">{{ $item['title'] }}<small>{{ $item['quantity'] }} × {{ $item['type'] === 'gift_card' ? 'digital gift card' : 'merchandise' }}</small></div>
                        <div class="p">{{ $money($item['lineTotal']) }}</div>
                    </div>
                @endforeach
                <div class="row"><span>Items</span><span>{{ $money($order['itemsTotal']) }}</span></div>
                <div class="row"><span>Delivery</span><span>{{ $order['deliveryFee'] > 0 ? $money($order['deliveryFee']) : 'Free' }}</span></div>
                <div class="row total"><span>Total</span><span>{{ $money($order['total']) }} {{ $order['currency'] }}</span></div>
            </div>
        </section>
    @endif

    <section class="card">
        @if (($data['state'] ?? '') === 'ready')
            <div id="payArea">
                <div class="pay-title">
                    <b>Pay by card</b>
                    <span class="lock">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M17 9V7A5 5 0 0 0 7 7v2H5v13h14V9h-2zm-8 0V7a3 3 0 0 1 6 0v2H9z"/></svg>
                        Encrypted
                    </span>
                </div>
                <div id="ucButtons"></div>
                <div id="ucScreen"></div>
                <div id="loading" class="state"><div class="spinner"></div><p style="margin-top:14px">Opening the secure card form…</p></div>
            </div>
        @endif

        <div id="processing" class="state hide"><div class="spinner"></div><h2>Confirming your payment</h2><p>Please keep this screen open. This takes a few seconds.</p></div>

        <div id="success" class="state hide">
            <div class="badge ok"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg></div>
            <h2>Payment received</h2><p id="successText">Taking you back to the app…</p>
        </div>

        <div id="failure" class="state {{ in_array($data['state'] ?? '', ['error', 'closed'], true) ? '' : 'hide' }}">
            <div class="badge bad"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg></div>
            <h2 id="failureTitle">{{ ($data['state'] ?? '') === 'closed' ? 'This order is closed' : 'Payment unavailable' }}</h2>
            <p id="failureText">{{ $data['message'] ?? (($data['state'] ?? '') === 'closed' ? 'It can no longer be paid. Please place a new order in the app.' : '') }}</p>
            <button class="btn primary {{ ($data['state'] ?? '') === 'ready' ? '' : 'hide' }}" id="retry" type="button" onclick="location.reload()">Try again</button>
        </div>

        @if (($data['state'] ?? '') === 'paid')
            <div class="state">
                <div class="badge ok"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg></div>
                <h2>Already paid</h2><p>This order has been paid. Returning to the app…</p>
            </div>
        @endif

        <a class="btn ghost" id="cancel" href="{{ $doneUrl }}?status={{ ($data['state'] ?? '') === 'paid' ? 'paid' : 'cancelled' }}">{{ ($data['state'] ?? '') === 'ready' ? 'Cancel and return to the app' : 'Return to the app' }}</a>
    </section>

</main>
<footer class="shell-foot">
    <div class="trust">
        <span>🔒 Secured by NAB Gateway</span>
        <span>✓ 3-D Secure</span>
        <span>Visa · Mastercard</span>
    </div>
    <p class="note">Your card details go straight to National Australia Bank. Naoero Airlines never sees or stores them.</p>
</footer>
</div>

@if (($data['state'] ?? '') === 'paid')
    <script>setTimeout(function () { location.replace(@json($doneUrl.'?status=paid')); }, 1200);</script>
@endif

@if (($data['state'] ?? '') === 'ready')
    <script src="{{ $data['clientLibrary'] }}" @if (! empty($data['clientLibraryIntegrity'])) integrity="{{ $data['clientLibraryIntegrity'] }}" crossorigin="anonymous" @endif></script>
    <script>
        (function () {
            var captureContext = @json($data['captureContext']);
            var completeUrl = @json(url('/api/v1/shop/checkout/'.$attempt.'/complete'));
            var token = @json($token);
            var show = function (id) {
                ['payArea', 'processing', 'success', 'failure'].forEach(function (x) {
                    var el = document.getElementById(x);
                    if (el) el.classList.toggle('hide', x !== id);
                });
                document.getElementById('cancel').classList.toggle('hide', id === 'processing' || id === 'success');
                if (id === 'success') {
                    ['stepPay', 'stepDone'].forEach(function (x) {
                        var el = document.getElementById(x);
                        el.className = 'done';
                        el.querySelector('i').textContent = '✓';
                    });
                }
            };
            var fail = function (title, text) {
                document.getElementById('failureTitle').textContent = title;
                document.getElementById('failureText').textContent = text;
                show('failure');
            };
            var reasonText = function (e) {
                var reason = (e && (e.reason || e.message)) || '';
                if (/CANCEL/i.test(reason)) return 'The payment was cancelled. You can try again.';
                if (/DECLIN|AUTHENTICATION|3DS/i.test(reason)) return 'Your card was declined or could not be verified. Please try another card.';
                return 'Something went wrong while paying. No money was taken unless you see a confirmation.';
            };

            async function report(completeResponse) {
                show('processing');
                var res = await fetch(completeUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ t: token, completeResponse: completeResponse })
                });
                var body = await res.json().catch(function () { return {}; });
                if (!res.ok) throw { message: (body.error && body.error.message) || 'Payment check failed' };
                if (body.status === 'paid' || body.status === 'pending' || body.status === 'review') {
                    document.getElementById('successText').textContent = body.message;
                    if (body.status !== 'paid') document.querySelector('#success h2').textContent = 'Payment submitted';
                    show('success');
                    setTimeout(function () { location.replace(body.redirect); }, 1400);
                } else {
                    fail('Payment not completed', body.message || 'Your card was declined. Please try another card.');
                }
            }

            async function start() {
                try {
                    var accept = await window.Accept(captureContext);
                    var up = await accept.unifiedPayments(false);
                    document.getElementById('loading').classList.add('hide');
                    var transientToken = await up.show({ containers: { paymentSelection: '#ucButtons', paymentScreen: '#ucScreen' } });
                    show('processing');
                    var completeResponse = await up.complete(transientToken);
                    await report(completeResponse);
                } catch (e) {
                    fail('Payment not completed', reasonText(e));
                }
            }

            if (window.Accept) start();
            else fail('Payment unavailable', 'The secure card form could not load. Please check your connection and try again.');
        })();
    </script>
@endif
</body>
</html>
