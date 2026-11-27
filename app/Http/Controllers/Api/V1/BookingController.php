<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Http\Requests\Api\V1\Bookings\CreateBookingRequest;
use App\Http\Requests\Api\V1\Bookings\FindBookingRequest;
use App\Http\Requests\Api\V1\Bookings\ModifyExtrasRequest;
use App\Http\Requests\Api\V1\Bookings\PaymentOutcomeRequest;
use App\Http\Requests\Api\V1\Bookings\PrepareBookingRequest;
use App\Services\Auth\AuthEvents;
use App\Services\Booking\BookingFinder;
use App\Services\Booking\BookingService;
use App\Services\Shop\ShopCheckout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Throwable;

class BookingController extends ApiController
{
    public function __construct(
        private readonly BookingService $bookings,
        private readonly BookingFinder $finder,
        private readonly AuthEvents $events,
    ) {}

    /** Booking references are 5-8 letters and digits. */
    private static function pnr(string $raw): string
    {
        $pnr = strtoupper(trim(rawurldecode($raw)));
        if (! preg_match('/^[A-Z0-9]{5,8}$/', $pnr)) {
            throw ApiException::invalid('Invalid booking reference.');
        }

        return $pnr;
    }

    /**
     * The signed-in customer's bookings, newest first: booked here, found on
     * their airline account (searched at most every 5 minutes, or on
     * ?refresh=1 at most every 30 seconds), or added by reference.
     */
    public function index(Request $request): JsonResponse
    {
        $customer = $this->customer($request);
        $sync = $request->query('sync') === '0'
            ? ['status' => 'skipped', 'syncedAt' => null, 'found' => 0, 'imported' => 0, 'updated' => 0]
            : $this->finder->sync($customer, $request->boolean('refresh'));

        return $this->json(['bookings' => $this->bookings->list($customer), 'sync' => $sync]);
    }

    /**
     * Adds a booking made elsewhere (website, travel agent, airport) with its
     * reference and a passenger's surname. Wrong answers are limited (5 per
     * account, 20 per network, per 15 minutes).
     */
    public function find(FindBookingRequest $request): JsonResponse
    {
        $customer = $this->customer($request);
        $input = $request->validated();
        $meta = $this->meta($request);
        $ticket = $input['ticketNumber'] ?? null;
        $pnr = $ticket ? "ticket {$ticket}" : strtoupper($input['pnr']);
        $this->events->enforceLimit('booking_find', $customer->zenith_login, $meta->ip, perLogin: 5, perIp: 20, windowMinutes: 15);
        try {
            $booking = $this->finder->find($customer, $ticket ? null : $pnr, $input['lastName'], $input['firstName'] ?? null, $ticket);
        } catch (Throwable $e) {
            $this->events->record('booking_find', false, $meta, $customer->id, $customer->zenith_login, "pnr {$pnr}: ".($e instanceof ApiException ? $e->errorCode : 'error'));
            throw $e;
        }
        $this->events->record('booking_find', true, $meta, $customer->id, $customer->zenith_login, "pnr {$booking['pnr']}".($ticket ? " by ticket {$ticket}" : '')." ({$booking['source']})");

        return $this->json(['booking' => $booking], 201);
    }

    /** Takes a booking made elsewhere off the list (it stays with the airline). */
    public function destroy(Request $request, string $pnr): JsonResponse
    {
        $customer = $this->customer($request);
        $pnr = self::pnr($pnr);
        $this->finder->remove($customer, $pnr);
        $this->events->record('booking_remove', true, $this->meta($request), $customer->id, $customer->zenith_login, "pnr {$pnr}");

        return $this->json(['removed' => true]);
    }

    /**
     * Starts a booking for the selected fare: Zenith PrepareFlights confirms
     * availability and price and lists the passenger data it requires.
     */
    public function prepare(PrepareBookingRequest $request): JsonResponse
    {
        $customer = $this->customer($request);
        $input = $request->validated();
        $meta = $this->meta($request);
        $this->events->enforceLimit('booking_prepare', $customer->zenith_login, $meta->ip, perLogin: 30, perIp: 60, windowMinutes: 15, countSuccesses: true);
        $prepared = $this->bookings->prepare($customer, $input['offerRef'], $input['itineraryRef']);
        $this->events->record('booking_prepare', true, $meta, $customer->id, $customer->zenith_login);

        return $this->json($prepared);
    }

    /**
     * Creates the booking (Zenith CreateBooking) from a draft and the
     * passenger form. The PNR is saved against the customer immediately;
     * seats are held until the time limit, by which it must be paid.
     */
    public function store(CreateBookingRequest $request): JsonResponse
    {
        $customer = $this->customer($request);
        $meta = $this->meta($request);
        $this->events->enforceLimit('booking_create', $customer->zenith_login, $meta->ip, perLogin: 15, perIp: 30, windowMinutes: 15, countSuccesses: true);
        try {
            $booking = $this->bookings->create($customer, $request->validated()['draftId'], $request->booking());
        } catch (Throwable $e) {
            $this->events->record('booking_create', false, $meta, $customer->id, $customer->zenith_login, $e instanceof ApiException ? $e->errorCode : 'error');
            throw $e;
        }
        $this->events->record('booking_create', true, $meta, $customer->id, $customer->zenith_login, "pnr {$booking['pnr']}");

        return $this->json(['booking' => $booking], 201);
    }

    /** One booking, reloaded from Zenith (LoadBooking with the customer reference). */
    public function show(Request $request, string $pnr): JsonResponse
    {
        $customer = $this->customer($request);

        return $this->json(['booking' => $this->bookings->refresh($customer, self::pnr($pnr))]);
    }

    /**
     * Starts paying a booking: reloads the live amount due, then Zenith
     * CreateTicket with the airline's online payment method. Returns the
     * payment page (a URL or an HTML form) for the app's in-app browser, or
     * for a website: then `returnUrl` (an https page on an allowed website,
     * as for the shop) is where the customer comes back to.
     */
    /** The ways this booking can be paid, as the airline offers them now. */
    public function paymentOptions(Request $request, string $pnr): JsonResponse
    {
        return $this->json($this->bookings->paymentOptions($this->customer($request), self::pnr($pnr)));
    }

    public function startPayment(Request $request, string $pnr): JsonResponse
    {
        $customer = $this->customer($request);
        $pnr = self::pnr($pnr);
        $meta = $this->meta($request);
        $returnUrl = ShopCheckout::safeReturnUrl($request->input('returnUrl'));
        $fop = $request->input('fop');
        if ($fop !== null && (! is_string($fop) || ! preg_match('/^[A-Za-z0-9_.-]{1,40}$/', $fop))) {
            throw ApiException::invalid('Invalid way of paying.');
        }
        $this->events->enforceLimit('payment_start', $customer->zenith_login, $meta->ip, perLogin: 10, perIp: 30, windowMinutes: 60, countSuccesses: true);
        try {
            $payment = $this->bookings->startPayment($customer, $pnr, $returnUrl, $fop);
        } catch (Throwable $e) {
            $this->events->record('payment_start', false, $meta, $customer->id, $customer->zenith_login, "pnr {$pnr}: ".($e instanceof ApiException ? $e->errorCode : 'error'));
            throw $e;
        }
        $this->events->record('payment_start', true, $meta, $customer->id, $customer->zenith_login, "pnr {$pnr}, {$payment['currency']} {$payment['amount']}, payment {$payment['paymentId']}");

        return $this->json($payment, 201);
    }

    /**
     * Where the airline's payment page returns a website customer (accept /
     * decline / cancel). Not proof of payment and changes nothing: it sends
     * the browser on to the website page given when paying, which then asks
     * POST /bookings/{pnr}/payments/{paymentId} for the real result. Only the
     * stored, already checked page is used, so it can't redirect elsewhere.
     */
    public function paymentReturn(string $paymentId, string $outcome): Response
    {
        $target = BookingService::paymentReturnTarget($paymentId, $outcome);
        $headers = ['Cache-Control' => 'no-store', 'Referrer-Policy' => 'no-referrer', 'X-Content-Type-Options' => 'nosniff'];
        if ($target !== null) {
            return response('', 303, ['Location' => $target, ...$headers]);
        }

        return response('<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            .'<title>Naoero Airlines</title><p style="font-family:sans-serif;padding:24px">The payment page has finished. You can close this window and check your booking.</p>',
            200, ['Content-Type' => 'text/html; charset=utf-8', ...$headers]);
    }

    /**
     * The airline's card form, served from our own HTTPS page (opened by the
     * app's WebView and the website from redirectUrl). The link's secret
     * authorises it; no sign-in, as the WebView has no access token.
     */
    public function paymentForm(Request $request, string $paymentId): Response
    {
        $page = $this->bookings->paymentForm($paymentId, (string) $request->query('t', ''));

        return response($page['html'], $page['status'], [
            'Content-Type' => 'text/html; charset=utf-8',
            'Cache-Control' => 'no-store',
            // The link holds a secret: never pass it on (the form posts to the airline).
            'Referrer-Policy' => 'no-referrer',
            'X-Content-Type-Options' => 'nosniff',
            'X-Robots-Tag' => 'noindex, nofollow',
            // Only our own pages and the website may frame it.
            'Content-Security-Policy' => "frame-ancestors 'self' https://napacificbridge.info https://*.napacificbridge.info",
        ]);
    }

    /**
     * The app reports where the payment page ended (accept / decline /
     * cancel, or closed by the user). That is never taken as proof of
     * payment: the booking is reloaded from Zenith.
     */
    public function finishPayment(PaymentOutcomeRequest $request, string $pnr, string $paymentId): JsonResponse
    {
        $customer = $this->customer($request);
        $pnr = self::pnr($pnr);
        if (! Str::isUuid($paymentId)) {
            throw ApiException::invalid('Invalid payment reference.');
        }
        $outcome = $request->validated()['outcome'];
        $result = $this->bookings->finishPayment($customer, $pnr, $paymentId, $outcome);
        $this->events->record('payment_result', $result['booking']['status'] === 'ticketed', $this->meta($request), $customer->id, $customer->zenith_login,
            "pnr {$pnr}, page said {$outcome}, booking {$result['booking']['status']}");

        return $this->json($result);
    }

    /**
     * The booking's extras: the special services, seats and paid extras it
     * holds, and everything that can still be added (Zenith
     * PrepareBookingModification), with seat maps and fare rules.
     */
    public function extras(Request $request, string $pnr): JsonResponse
    {
        return $this->json($this->bookings->extras($this->customer($request), self::pnr($pnr)));
    }

    /**
     * Adds or removes special services, seats and paid extras (Zenith
     * ModifyBooking). New paid extras go on the amount due and are paid with
     * POST /bookings/{pnr}/payments.
     */
    public function modifyExtras(ModifyExtrasRequest $request, string $pnr): JsonResponse
    {
        $customer = $this->customer($request);
        $pnr = self::pnr($pnr);
        $meta = $this->meta($request);
        $this->events->enforceLimit('booking_modify', $customer->zenith_login, $meta->ip, perLogin: 20, perIp: 40, windowMinutes: 15, countSuccesses: true);
        try {
            $result = $this->bookings->modifyExtras($customer, $pnr, $request->changes());
        } catch (Throwable $e) {
            $this->events->record('booking_modify', false, $meta, $customer->id, $customer->zenith_login, "pnr {$pnr}: ".($e instanceof ApiException ? $e->errorCode : 'error'));
            throw $e;
        }
        $this->events->record('booking_modify', true, $meta, $customer->id, $customer->zenith_login, "pnr {$pnr}");

        return $this->json($result);
    }

    /** Re-sends the e-ticket confirmation email (Zenith SendTicketConfirmation). */
    public function confirmation(Request $request, string $pnr): JsonResponse
    {
        $this->bookings->resendConfirmation($this->customer($request), self::pnr($pnr));

        return $this->json(['ok' => true, 'message' => 'Your e-ticket has been emailed again.']);
    }
}
