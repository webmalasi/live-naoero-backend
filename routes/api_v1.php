<?php

use App\Http\Controllers\Api\V1\AppLogController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BookingController;
use App\Http\Controllers\Api\V1\CancellationController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\DocsController;
use App\Http\Controllers\Api\V1\FlightController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\RefundRequestController;
use App\Http\Controllers\Api\V1\ShopAftercareController;
use App\Http\Controllers\Api\V1\ShopCheckoutController;
use App\Http\Controllers\Api\V1\ShopController;
use App\Http\Controllers\Api\V1\TravellerController;
use App\Http\Controllers\Api\V1\TripChangeController;
use App\Http\Controllers\Api\V1\ZenithEventController;
use App\Http\Middleware\AuthenticateCustomer;
use App\Http\Middleware\RecordZenithCall;
use App\Http\Middleware\RequireAppToken;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Mobile app API, version 1 (/api/v1/...)
|--------------------------------------------------------------------------
|
| Customer accounts, flight search, bookings and payments through the
| airline's Zenith Sales API. Documented at /api/v1/docs. The website's
| content routes in routes/api.php are separate and unchanged.
|
*/

Route::get('health', [DocsController::class, 'health'])->name('api.v1.health');
Route::get('openapi.json', [DocsController::class, 'openapi'])->name('api.v1.openapi');
Route::get('docs', [DocsController::class, 'docs'])->name('api.v1.docs');

Route::middleware(RequireAppToken::class)->group(function () {
    Route::get('flight-config', [FlightController::class, 'config'])->name('api.v1.flight-config');
    Route::post('flight-search', [FlightController::class, 'search'])->name('api.v1.flight-search');
    // Seats per day for the date picker (Calendar), and services sold on their own.
    Route::get('flight-calendar', [FlightController::class, 'calendar'])->middleware('throttle:60,1')->name('api.v1.flight-calendar');
    Route::get('services', [CancellationController::class, 'services'])->name('api.v1.services');
    // What the app needs to ask Apple or Google for a push token.
    Route::get('push/config', [DeviceController::class, 'config'])->name('api.v1.push.config');
});
Route::get('reference/countries', [FlightController::class, 'countries'])->name('api.v1.countries');
Route::get('reference/phone-codes', [FlightController::class, 'phoneCodes'])->name('api.v1.phone-codes');
Route::get('reference/value-code-lists', [FlightController::class, 'valueCodeLists'])->name('api.v1.value-code-lists');
Route::get('reference/value-codes/{name}', [FlightController::class, 'valueCodes'])->middleware('throttle:60,1')->name('api.v1.value-codes');

Route::prefix('auth')->name('api.v1.auth.')->group(function () {
    Route::get('config', [AuthController::class, 'config'])->name('config');
    Route::get('password-policy', [AuthController::class, 'passwordPolicy'])->name('password-policy');
    // Is this username free? As the customer types (Zenith confirms it at sign-up).
    Route::get('username-available', [AuthController::class, 'usernameAvailable'])->middleware('throttle:60,1')->name('username-available');
    Route::post('signup', [AuthController::class, 'signup'])->name('signup');
    Route::post('login', [AuthController::class, 'login'])->name('login');
    Route::post('refresh', [AuthController::class, 'refresh'])->name('refresh');
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');
    Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->name('forgot-password');
    Route::post('change-password', [AuthController::class, 'changePassword'])->middleware(AuthenticateCustomer::class)->name('change-password');
});

Route::middleware(AuthenticateCustomer::class)->group(function () {
    Route::get('me', [ProfileController::class, 'show'])->name('api.v1.me');
    Route::patch('me', [ProfileController::class, 'update'])->name('api.v1.me.update');
    Route::get('me/avatar', [ProfileController::class, 'avatar'])->name('api.v1.me.avatar');
    Route::put('me/avatar', [ProfileController::class, 'uploadAvatar'])->name('api.v1.me.avatar.update');
    Route::delete('me/avatar', [ProfileController::class, 'deleteAvatar'])->name('api.v1.me.avatar.delete');
    // Account deletion: asked here, done by staff (the account is disabled, never deleted).
    Route::get('me/delete-request', [ProfileController::class, 'deletionStatus'])->name('api.v1.me.delete-request.show');
    Route::post('me/delete-request', [ProfileController::class, 'deleteRequest'])->name('api.v1.me.delete-request');
    Route::delete('me/delete-request', [ProfileController::class, 'withdrawDeleteRequest'])->name('api.v1.me.delete-request.withdraw');
    Route::get('me/travellers', [TravellerController::class, 'index'])->name('api.v1.me.travellers');
    // Credit vouchers (MCOs) from refunds and changes, and services bought on their own.
    Route::get('me/vouchers', [CancellationController::class, 'vouchers'])->name('api.v1.me.vouchers');
    Route::post('services/purchase', [CancellationController::class, 'buyService'])->middleware('throttle:20,1')->name('api.v1.services.purchase');
    Route::delete('me/travellers/{id}', [TravellerController::class, 'destroy'])->name('api.v1.me.travellers.delete');

    // Notices about the customer's flights (time changes, cancellations, transfers): app and website.
    Route::get('me/notifications', [NotificationController::class, 'index'])->name('api.v1.me.notifications');
    Route::get('me/notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('api.v1.me.notifications.unread-count');
    Route::post('me/notifications/read-all', [NotificationController::class, 'readAll'])->name('api.v1.me.notifications.read-all');
    Route::post('me/notifications/{id}/read', [NotificationController::class, 'read'])->name('api.v1.me.notifications.read');
    Route::delete('me/notifications/read', [NotificationController::class, 'clearRead'])->name('api.v1.me.notifications.clear-read');
    Route::delete('me/notifications/{id}', [NotificationController::class, 'destroy'])->name('api.v1.me.notifications.delete');

    // This phone's push registration: UID, token and what it receives (customer_devices).
    Route::get('me/devices', [DeviceController::class, 'index'])->name('api.v1.me.devices');
    Route::put('me/devices/{uid}', [DeviceController::class, 'update'])->middleware('throttle:30,1')->name('api.v1.me.devices.update');
    Route::delete('me/devices/{uid}', [DeviceController::class, 'destroy'])->name('api.v1.me.devices.delete');

    Route::get('bookings', [BookingController::class, 'index'])->name('api.v1.bookings');
    Route::post('bookings/prepare', [BookingController::class, 'prepare'])->name('api.v1.bookings.prepare');
    Route::post('bookings', [BookingController::class, 'store'])->name('api.v1.bookings.store');
    Route::post('bookings/find', [BookingController::class, 'find'])->name('api.v1.bookings.find');
    Route::get('bookings/{pnr}', [BookingController::class, 'show'])->name('api.v1.bookings.show');
    Route::delete('bookings/{pnr}', [BookingController::class, 'destroy'])->name('api.v1.bookings.destroy');
    Route::get('bookings/{pnr}/payment-options', [BookingController::class, 'paymentOptions'])->name('api.v1.bookings.payment-options');
    Route::post('bookings/{pnr}/payments', [BookingController::class, 'startPayment'])->name('api.v1.bookings.payments');
    Route::post('bookings/{pnr}/payments/{paymentId}', [BookingController::class, 'finishPayment'])->name('api.v1.bookings.payments.result');
    Route::post('bookings/{pnr}/confirmation', [BookingController::class, 'confirmation'])->name('api.v1.bookings.confirmation');
    Route::get('bookings/{pnr}/extras', [BookingController::class, 'extras'])->name('api.v1.bookings.extras');
    Route::post('bookings/{pnr}/extras', [BookingController::class, 'modifyExtras'])->name('api.v1.bookings.extras.update');
    Route::get('bookings/{pnr}/refund-requests', [RefundRequestController::class, 'index'])->name('api.v1.bookings.refund-requests');
    Route::post('bookings/{pnr}/refund-requests', [RefundRequestController::class, 'store'])->name('api.v1.bookings.refund-requests.store');

    // After booking: flight status, passengers, changes, extra trips, split, check-in.
    Route::get('bookings/{pnr}/flight-status', [TripChangeController::class, 'flightStatus'])->name('api.v1.bookings.flight-status');
    Route::get('bookings/{pnr}/passengers', [TripChangeController::class, 'passengers'])->name('api.v1.bookings.passengers');
    Route::post('bookings/{pnr}/passengers', [TripChangeController::class, 'updatePassengers'])->name('api.v1.bookings.passengers.update');
    Route::get('bookings/{pnr}/change', [TripChangeController::class, 'changeOptions'])->name('api.v1.bookings.change');
    Route::post('bookings/{pnr}/change/search', [TripChangeController::class, 'searchChange'])->middleware('throttle:30,1')->name('api.v1.bookings.change.search');
    Route::post('bookings/{pnr}/change/quote', [TripChangeController::class, 'quoteChange'])->name('api.v1.bookings.change.quote');
    Route::post('bookings/{pnr}/change', [TripChangeController::class, 'confirmChange'])->name('api.v1.bookings.change.confirm');
    Route::delete('bookings/{pnr}/change', [TripChangeController::class, 'cancelChange'])->name('api.v1.bookings.change.cancel');
    Route::post('bookings/{pnr}/add-trip/search', [TripChangeController::class, 'searchTrip'])->middleware('throttle:30,1')->name('api.v1.bookings.add-trip.search');
    Route::post('bookings/{pnr}/add-trip/quote', [TripChangeController::class, 'quoteTrip'])->name('api.v1.bookings.add-trip.quote');
    Route::post('bookings/{pnr}/add-trip', [TripChangeController::class, 'addTrip'])->name('api.v1.bookings.add-trip');
    Route::post('bookings/{pnr}/split', [TripChangeController::class, 'split'])->name('api.v1.bookings.split');
    Route::get('bookings/{pnr}/check-in', [TripChangeController::class, 'checkIn'])->name('api.v1.bookings.check-in');
    Route::post('bookings/{pnr}/check-in', [TripChangeController::class, 'doCheckIn'])->name('api.v1.bookings.check-in.do');
    Route::post('bookings/{pnr}/check-in/undo', [TripChangeController::class, 'undoCheckIn'])->name('api.v1.bookings.check-in.undo');

    // Cancelling the booking yourself (unpaid: release it; paid: refund to a voucher), and flown flights.
    Route::get('bookings/{pnr}/cancel', [CancellationController::class, 'options'])->name('api.v1.bookings.cancel');
    Route::post('bookings/{pnr}/cancel', [CancellationController::class, 'cancel'])->middleware('throttle:10,1')->name('api.v1.bookings.cancel.do');
    Route::get('bookings/{pnr}/flown', [CancellationController::class, 'flown'])->middleware('throttle:30,1')->name('api.v1.bookings.flown');
});

Route::post('logs/app', [AppLogController::class, 'store'])->name('api.v1.logs.app');

// Flight events Zenith posts to us (EventMap: FlightChange, SegmentFlightCancelled,
// FlightTransfer, FlightReminder). The webhook key (ZENITH_WEBHOOK_SECRET) in the address
// tells us they are Zenith's; in preproduction Zenith adds "test" to the
// event (in lowercase). Every call is saved with our answer (RecordZenithCall).
// Nothing is turned away: every post is saved and answered 200 (see the controller).
Route::match(['POST', 'PUT', 'PATCH'], 'zenith/events/{secret}/{event}', [ZenithEventController::class, 'store'])
    ->where(['secret' => '[^/]{1,200}', 'event' => '[^/]{1,80}'])
    ->middleware(['throttle:3000,1', RecordZenithCall::class])
    ->name('api.v1.zenith.events');
Route::match(['POST', 'PUT', 'PATCH'], 'zenith/events/{event}', [ZenithEventController::class, 'missingSecret'])
    ->where('event', '[^/]{1,80}')
    ->middleware(['throttle:3000,1', RecordZenithCall::class]);

// Where the airline's payment page returns a website customer (no sign-in:
// the browser arrives from the payment page). It only redirects onward.
Route::match(['get', 'post'], 'bookings/payments/{paymentId}/return/{outcome}', [BookingController::class, 'paymentReturn'])
    ->whereIn('outcome', ['accept', 'decline', 'cancel'])
    ->middleware('throttle:30,1')
    ->name('api.v1.bookings.payments.return');
// The airline's card form for a payment, on our own HTTPS address (the link's
// secret authorises it; the app's WebView has no access token).
Route::get('bookings/payments/{paymentId}/form', [BookingController::class, 'paymentForm'])
    ->middleware('throttle:60,1')
    ->name('api.v1.bookings.payments.form');

// Shop: gift cards and merchandise, paid by card through NAB Gateway.
Route::get('shop/products', [ShopController::class, 'products'])->name('api.v1.shop.products');
Route::get('shop/products/{id}', [ShopController::class, 'product'])->name('api.v1.shop.product');
Route::post('shop/cart/check', [ShopController::class, 'check'])->middleware('throttle:60,1')->name('api.v1.shop.cart.check');
Route::middleware(AuthenticateCustomer::class)->group(function () {
    Route::get('shop/orders', [ShopController::class, 'index'])->name('api.v1.shop.orders');
    Route::post('shop/orders', [ShopController::class, 'store'])->name('api.v1.shop.orders.store');
    Route::get('shop/orders/{id}', [ShopController::class, 'show'])->name('api.v1.shop.orders.show');
    Route::post('shop/orders/{id}/pay', [ShopController::class, 'pay'])->name('api.v1.shop.orders.pay');
    Route::post('shop/orders/{id}/cancel', [ShopController::class, 'cancel'])->name('api.v1.shop.orders.cancel');
    Route::get('me/gift-cards', [ShopController::class, 'giftCards'])->name('api.v1.me.gift-cards');
    Route::post('shop/stock-alerts', [ShopController::class, 'createStockAlert'])->middleware('throttle:30,1')->name('api.v1.shop.stock-alerts.store');
    Route::get('me/stock-alerts', [ShopController::class, 'stockAlerts'])->name('api.v1.me.stock-alerts');
    Route::delete('me/stock-alerts/{id}', [ShopController::class, 'deleteStockAlert'])->name('api.v1.me.stock-alerts.delete');

    // After the order: tracking, invoice and receipt, help, returns, reviews.
    Route::get('shop/orders/{id}/tracking', [ShopAftercareController::class, 'tracking'])->name('api.v1.shop.orders.tracking');
    Route::get('shop/orders/{id}/documents', [ShopAftercareController::class, 'documents'])->name('api.v1.shop.orders.documents');
    Route::post('shop/orders/{id}/returns', [ShopAftercareController::class, 'createReturn'])->middleware('throttle:20,1')->name('api.v1.shop.orders.returns.store');
    Route::get('me/returns', [ShopAftercareController::class, 'myReturns'])->name('api.v1.me.returns');
    Route::get('me/returns/{id}', [ShopAftercareController::class, 'myReturn'])->name('api.v1.me.returns.show');
    Route::post('me/returns/{id}/cancel', [ShopAftercareController::class, 'cancelReturn'])->name('api.v1.me.returns.cancel');
    Route::get('me/support-requests', [ShopAftercareController::class, 'supportRequests'])->name('api.v1.me.support-requests');
    Route::post('me/support-requests', [ShopAftercareController::class, 'createSupportRequest'])->middleware('throttle:20,1')->name('api.v1.me.support-requests.store');
    Route::get('me/support-requests/{id}', [ShopAftercareController::class, 'supportRequest'])->name('api.v1.me.support-requests.show');
    Route::post('me/support-requests/{id}/messages', [ShopAftercareController::class, 'replySupportRequest'])->middleware('throttle:30,1')->name('api.v1.me.support-requests.reply');
    Route::post('me/support-requests/{id}/close', [ShopAftercareController::class, 'closeSupportRequest'])->name('api.v1.me.support-requests.close');
    Route::post('shop/products/{id}/reviews', [ShopAftercareController::class, 'createReview'])->middleware('throttle:20,1')->name('api.v1.shop.products.reviews.store');
    Route::get('me/reviews', [ShopAftercareController::class, 'myReviews'])->name('api.v1.me.reviews');
    Route::put('me/reviews/{id}', [ShopAftercareController::class, 'updateReview'])->name('api.v1.me.reviews.update');
    Route::delete('me/reviews/{id}', [ShopAftercareController::class, 'deleteReview'])->name('api.v1.me.reviews.delete');
});
Route::get('shop/help', [ShopAftercareController::class, 'help'])->name('api.v1.shop.help');
Route::get('shop/products/{id}/reviews', [ShopAftercareController::class, 'productReviews'])->name('api.v1.shop.products.reviews');
// The invoice and receipt pages, opened from the links in an order (signed, they expire).
Route::get('shop/orders/{id}/invoice', [ShopAftercareController::class, 'document'])->defaults('kind', 'invoice')->middleware('signed:relative')->name('api.v1.shop.orders.invoice');
Route::get('shop/orders/{id}/receipt', [ShopAftercareController::class, 'document'])->defaults('kind', 'receipt')->middleware('signed:relative')->name('api.v1.shop.orders.receipt');
// The secure payment page opened in the app (authorised by its one-time link).
Route::get('shop/checkout/{attempt}', [ShopCheckoutController::class, 'page'])->name('api.v1.shop.checkout');
Route::post('shop/checkout/{attempt}/complete', [ShopCheckoutController::class, 'complete'])->middleware('throttle:20,1')->name('api.v1.shop.checkout.complete');
Route::get('shop/checkout/{attempt}/done', [ShopCheckoutController::class, 'done'])->name('api.v1.shop.checkout.done');
