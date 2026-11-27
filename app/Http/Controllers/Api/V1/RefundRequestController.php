<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Http\Requests\Api\V1\Bookings\RefundRequestRequest;
use App\Services\Auth\AuthEvents;
use App\Services\Booking\RefundRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Refund requests on a booking: tickets are non-refundable, so customers
 * ask and staff decide in the admin panel (Flight Network → Refund Requests).
 */
class RefundRequestController extends ApiController
{
    public function __construct(
        private readonly RefundRequests $refunds,
        private readonly AuthEvents $events,
    ) {}

    private static function pnr(string $raw): string
    {
        $pnr = strtoupper(trim(rawurldecode($raw)));
        if (! preg_match('/^[A-Z0-9]{5,8}$/', $pnr)) {
            throw ApiException::invalid('Invalid booking reference.');
        }

        return $pnr;
    }

    /** The policy, the reasons, the booking's passengers and its requests. */
    public function index(Request $request, string $pnr): JsonResponse
    {
        return $this->json($this->refunds->forBooking($this->customer($request), self::pnr($pnr)));
    }

    /** Sends a request. Limited to 5 per account and 10 per network per day. */
    public function store(RefundRequestRequest $request, string $pnr): JsonResponse
    {
        $customer = $this->customer($request);
        $pnr = self::pnr($pnr);
        $meta = $this->meta($request);
        $this->events->enforceLimit('refund_request', $customer->zenith_login, $meta->ip, perLogin: 5, perIp: 10, windowMinutes: 1440, countSuccesses: true);
        $channel = strtolower(trim((string) $request->header('X-Client-Platform'))) === 'website' ? 'website' : 'app';
        try {
            $created = $this->refunds->submit($customer, $pnr, $request->validated(), $channel);
        } catch (Throwable $e) {
            $this->events->record('refund_request', false, $meta, $customer->id, $customer->zenith_login, "pnr {$pnr}: ".($e instanceof ApiException ? $e->errorCode : 'error'));
            throw $e;
        }
        $this->events->record('refund_request', true, $meta, $customer->id, $customer->zenith_login, "pnr {$pnr}, {$created['reference']}");

        return $this->json(['request' => $created, 'message' => "Thank you. Your request {$created['reference']} has been sent. Our team will reply within 10 business days."], 201);
    }
}
