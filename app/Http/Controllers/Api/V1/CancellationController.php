<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Services\Auth\RequestMeta;
use App\Services\Booking\Cancellations;
use App\Services\Booking\FlownFlights;
use App\Services\Booking\ServiceSales;
use App\Services\Booking\Vouchers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Cancelling and refunding a booking yourself, credit vouchers, services
 * sold on their own and flown flights. See Cancellations, Vouchers,
 * ServiceSales and FlownFlights.
 */
class CancellationController extends ApiController
{
    public function __construct(
        private readonly Cancellations $cancellations,
        private readonly Vouchers $vouchers,
        private readonly ServiceSales $services,
        private readonly FlownFlights $flown,
    ) {}

    private static function pnr(string $raw): string
    {
        $pnr = strtoupper(trim(rawurldecode($raw)));
        if (! preg_match('/^[A-Z0-9]{5,8}$/', $pnr)) {
            throw ApiException::invalid('Invalid booking reference.');
        }

        return $pnr;
    }

    public function options(Request $request, string $pnr): JsonResponse
    {
        $pnr = self::pnr($pnr);
        $customer = $this->customer($request);

        return $this->json([...$this->cancellations->options($customer, $pnr), 'history' => $this->cancellations->history($customer, $pnr)]);
    }

    public function cancel(Request $request, string $pnr): JsonResponse
    {
        $input = $request->validate([
            'mode' => ['required', 'string', 'in:'.implode(',', Cancellations::MODES)],
            'segmentRefs' => ['required_if:mode,flights', 'array', 'max:20'],
            'segmentRefs.*' => ['string', 'max:40'],
            'expectedRefund' => ['required_if:mode,refund', 'nullable', 'numeric', 'min:0'],
            'confirm' => ['accepted'],
        ], [
            'mode.required' => 'Choose how to cancel.',
            'mode.in' => 'Choose how to cancel.',
            'segmentRefs.required_if' => 'Choose the flights to cancel.',
            'expectedRefund.required_if' => 'Confirm the refund amount shown.',
            'confirm.accepted' => 'Confirm that you want to cancel.',
        ]);
        $ip = RequestMeta::clientKey($request);

        return $this->json($this->cancellations->cancel(
            $this->customer($request),
            self::pnr($pnr),
            $input['mode'],
            $input['segmentRefs'] ?? [],
            isset($input['expectedRefund']) ? (float) $input['expectedRefund'] : null,
            $ip === 'local' ? null : $ip,
        ));
    }

    public function vouchers(Request $request): JsonResponse
    {
        $input = $request->validate([
            'usable' => ['nullable', 'boolean'],
            'currency' => ['nullable', 'string', 'regex:/^[A-Za-z]{3}$/'],
        ], ['regex' => 'Use a 3-letter currency code.']);

        return $this->json($this->vouchers->forCustomer(
            $this->customer($request),
            (bool) ($input['usable'] ?? false),
            isset($input['currency']) ? strtoupper($input['currency']) : null,
        ));
    }

    public function services(Request $request): JsonResponse
    {
        $currency = $request->validate(['currency' => ['nullable', 'string', 'regex:/^[A-Za-z]{3}$/']], ['regex' => 'Use a 3-letter currency code.'])['currency'] ?? null;

        return $this->json($this->services->catalog($currency));
    }

    public function buyService(Request $request): JsonResponse
    {
        $input = $request->validate([
            'ref' => ['required', 'string', 'max:100'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:10'],
            'currency' => ['nullable', 'string', 'regex:/^[A-Za-z]{3}$/'],
        ], ['ref.required' => 'Choose a service.', 'regex' => 'Use a 3-letter currency code.']);

        return $this->json($this->services->purchase($this->customer($request), $input['ref'], (int) ($input['quantity'] ?? 1), $input['currency'] ?? null), 201);
    }

    public function flown(Request $request, string $pnr): JsonResponse
    {
        return $this->json($this->flown->forBooking($this->customer($request), self::pnr($pnr)));
    }
}
