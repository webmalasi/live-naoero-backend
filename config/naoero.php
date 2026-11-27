<?php

/*
|--------------------------------------------------------------------------
| Naoero mobile app API (/api/v1)
|--------------------------------------------------------------------------
|
| Settings for the customer-facing API used by the mobile app: the Zenith
| (TTInteractive) Modern JSON Sales API, customer sessions, rate limits and
| logging. The Zenith key only ever lives in the environment.
|
*/

return [

    'zenith' => [
        'endpoint' => rtrim((string) env('ZENITH_ENDPOINT', 'https://tstws2.ttinteractive.com/Zenith/TTI.PublicApi.Services/ModernJsonSaleEngineService.svc'), '/'),
        'key' => env('ZENITH_AUTH_KEY'),
        'culture' => env('ZENITH_CULTURE', 'en-GB'),
        'timeout_ms' => (int) env('ZENITH_TIMEOUT_MS', 30000),
    ],

    // Undoing an online check-in (Zenith Uncheck). Off unless the airline
    // agrees: Zenith warns that it cancels the passenger's flight as a
    // no-show and releases the seat, and only airline staff can restore it.
    'allow_uncheck' => (bool) env('NAOERO_ALLOW_UNCHECK', false),

    // Paid bookings: "cancel flights only, keep the ticket value" (an
    // itinerary cancel: the seats are released, the tickets stay open for a
    // later change or refund). Off until the airline agrees to offer it.
    'allow_keep_ticket_cancel' => (bool) env('NAOERO_ALLOW_KEEP_TICKET_CANCEL', false),

    // Refunds to a credit voucher (MCO): "auto" offers them only when the
    // key's RefundConfig lists MCO among its refund forms of payment;
    // "on" / "off" force it.
    'refund_to_voucher' => env('NAOERO_REFUND_TO_VOUCHER', 'auto'),

    // Flight events Zenith posts to us (EventMap). ZENITH_WEBHOOK_SECRET (64
    // random characters; at least 32) is part of each address given to
    // Zenith: a call without it is refused. ZENITH_EVENTS_IPS optionally
    // limits calls to Zenith's addresses.
    'zenith_events' => [
        'secret' => env('ZENITH_WEBHOOK_SECRET'),
        'allowed_ips' => array_values(array_filter(array_map('trim', explode(',', (string) env('ZENITH_EVENTS_IPS', ''))))),
    ],

    // Signs the 15-minute access tokens (HS256). At least 32 characters.
    'jwt_secret' => env('AUTH_JWT_SECRET'),

    // When set, flight config and search require it in the X-App-Token header.
    'app_access_token' => env('APP_ACCESS_TOKEN'),

    // Zenith calls per minute per client IP for flight search.
    'search_rate_limit' => (int) env('SEARCH_RATE_LIMIT', 20),

    // server_logs and app_logs older than this are deleted daily.
    'log_retention_days' => (int) env('LOG_RETENTION_DAYS', 90),

    // Cache store for Zenith reference data (null = the default store).
    'cache_store' => env('NAOERO_CACHE_STORE'),

    // Write each log entry to the server_logs table as well as the log file.
    'server_logs' => (bool) env('NAOERO_SERVER_LOGS', true),

    // Shop payments: NAB Gateway (NAB-branded Cybersource), Unified Checkout.
    'nab' => [
        'host' => env('NAB_API_HOST'), // apitest.cybersource.com (sandbox) / api.cybersource.com
        'merchant_id' => env('NAB_MERCHANT_ID'),
        'key_id' => env('NAB_KEY_ID'),
        'shared_secret' => env('NAB_SHARED_SECRET'),
        'client_version' => env('NAB_UC_CLIENT_VERSION', '0.31'),
        // Wallets only appear once enabled in the NAB portal (Unified Checkout).
        'payment_types' => explode(',', (string) env('NAB_UC_PAYMENT_TYPES', 'PANENTRY,GOOGLEPAY,APPLEPAY,CLICKTOPAY')),
        'card_networks' => explode(',', (string) env('NAB_CARD_NETWORKS', 'VISA,MASTERCARD')),
    ],

    'shop' => [
        // The https origin that serves the payment page (Unified Checkout
        // only runs on https and checks this exact origin).
        'pay_origin' => rtrim((string) env('SHOP_PAY_ORIGIN', env('APP_URL', '')), '/'),
        'currency' => 'AUD',
        // Flat delivery fee when an order has merchandise; gift cards are digital.
        'delivery_fee' => (float) env('SHOP_DELIVERY_FEE', 10),
        'max_quantity' => 10,
        // Days from payment to the delivery date first shown to the customer.
        'delivery_days' => (int) env('SHOP_DELIVERY_DAYS', 10),
        // Days after delivery a customer can ask to return or exchange merchandise.
        'return_window_days' => (int) env('SHOP_RETURN_WINDOW_DAYS', 30),
        // Who issues invoices and receipts, and where customers get help.
        'seller' => [
            'name' => env('SHOP_SELLER_NAME', 'Naoero Airlines'),
            'address' => env('SHOP_SELLER_ADDRESS', 'Yaren District, Republic of Nauru'),
            'email' => env('SHOP_HELP_EMAIL'),
            'phone' => env('SHOP_HELP_PHONE'),
            'tax_note' => env('SHOP_TAX_NOTE', 'Prices include all applicable taxes.'),
        ],
        'help_hours' => env('SHOP_HELP_HOURS', 'Monday to Friday, 8:30 to 17:00 (Nauru time)'),
        // A variant is low on stock at or below this many available, unless it has its own threshold.
        'low_stock_threshold' => (int) env('SHOP_LOW_STOCK_THRESHOLD', 5),
        // Who gets the daily low-stock email (comma-separated); empty: nobody.
        'inventory_alert_emails' => array_values(array_filter(array_map('trim', explode(',', (string) env('SHOP_INVENTORY_ALERT_EMAILS', ''))))),
        // An order not paid within this time is cancelled and its stock released.
        'payment_window_minutes' => 30,
        'gift_card_validity_years' => 3,
        // Websites the payment page may send customers back to after paying
        // (the pay call's returnUrl), as comma-separated host names.
        'return_hosts' => array_values(array_filter(array_map('trim', explode(',', strtolower((string) env('SHOP_RETURN_HOSTS', '')))))),
        // The order page for a browser customer when no returnUrl was given;
        // {order}, {id} and {status} are filled in. Empty: it just says it's done.
        'order_page_url' => (string) env('SHOP_ORDER_PAGE_URL', ''),
    ],

];
