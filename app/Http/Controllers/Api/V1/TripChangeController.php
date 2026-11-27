<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Services\Booking\TripChanges;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * After booking: flight status, passenger details, changing flights,
 * adding a trip, splitting a booking and online check-in. See TripChanges.
 */
class TripChangeController extends ApiController
{
    public function __construct(private readonly TripChanges $changes) {}

    private static function pnr(string $raw): string
    {
        $pnr = strtoupper(trim(rawurldecode($raw)));
        if (! preg_match('/^[A-Z0-9]{5,8}$/', $pnr)) {
            throw ApiException::invalid('Invalid booking reference.');
        }

        return $pnr;
    }

    /** @return array{offerRef: string, itineraryRef: string} */
    private static function offer(Request $request): array
    {
        return $request->validate([
            'offerRef' => ['required', 'string', 'max:400'],
            'itineraryRef' => ['required', 'string', 'max:100'],
        ], ['required' => ':attribute is required.']);
    }

    private static function legOrder(Request $request): int
    {
        return (int) $request->validate(['legOrder' => ['required', 'integer', 'min:0', 'max:20']], ['required' => 'Choose the flight to change.'])['legOrder'];
    }

    public function flightStatus(Request $request, string $pnr): JsonResponse
    {
        return $this->json($this->changes->flightStatus($this->customer($request), self::pnr($pnr)));
    }

    public function passengers(Request $request, string $pnr): JsonResponse
    {
        return $this->json($this->changes->passengers($this->customer($request), self::pnr($pnr)));
    }

    public function updatePassengers(Request $request, string $pnr): JsonResponse
    {
        $input = $request->validate([
            'passengers' => ['required', 'array', 'min:1', 'max:9'],
            'passengers.*.ref' => ['required', 'string', 'max:100'],
            'passengers.*.values' => ['required', 'array'],
        ], ['required' => 'Enter the details to change.']);

        return $this->json($this->changes->updatePassengers($this->customer($request), self::pnr($pnr), $input));
    }

    public function changeOptions(Request $request, string $pnr): JsonResponse
    {
        return $this->json($this->changes->changeOptions($this->customer($request), self::pnr($pnr)));
    }

    public function searchChange(Request $request, string $pnr): JsonResponse
    {
        $date = $request->validate(['date' => ['required', 'date_format:Y-m-d']], ['required' => 'Choose a date.', 'date_format' => 'Use the format YYYY-MM-DD.'])['date'];

        return $this->json($this->changes->searchChange($this->customer($request), self::pnr($pnr), self::legOrder($request), $date));
    }

    public function quoteChange(Request $request, string $pnr): JsonResponse
    {
        $offer = self::offer($request);

        return $this->json($this->changes->quoteChange($this->customer($request), self::pnr($pnr), self::legOrder($request), $offer['offerRef'], $offer['itineraryRef']));
    }

    public function confirmChange(Request $request, string $pnr): JsonResponse
    {
        $offer = self::offer($request);

        return $this->json($this->changes->confirmChange($this->customer($request), self::pnr($pnr), self::legOrder($request), $offer['offerRef'], $offer['itineraryRef']));
    }

    public function cancelChange(Request $request, string $pnr): JsonResponse
    {
        return $this->json($this->changes->cancelChange($this->customer($request), self::pnr($pnr)));
    }

    public function searchTrip(Request $request, string $pnr): JsonResponse
    {
        $input = $request->validate([
            'origin' => ['required', 'string', 'regex:/^[A-Za-z]{3}$/'],
            'destination' => ['required', 'string', 'regex:/^[A-Za-z]{3}$/', 'different:origin'],
            'date' => ['required', 'date_format:Y-m-d'],
        ], [
            'regex' => 'Choose an airport.',
            'different' => 'Choose a destination different from the departure airport.',
            'required' => ':attribute is required.',
            'date_format' => 'Use the format YYYY-MM-DD.',
        ]);

        return $this->json($this->changes->searchTrip($this->customer($request), self::pnr($pnr), strtoupper($input['origin']), strtoupper($input['destination']), $input['date']));
    }

    public function quoteTrip(Request $request, string $pnr): JsonResponse
    {
        $offer = self::offer($request);

        return $this->json($this->changes->quoteTrip($this->customer($request), self::pnr($pnr), $offer['offerRef'], $offer['itineraryRef']));
    }

    public function addTrip(Request $request, string $pnr): JsonResponse
    {
        $offer = self::offer($request);

        return $this->json($this->changes->addTrip($this->customer($request), self::pnr($pnr), $offer['offerRef'], $offer['itineraryRef']));
    }

    public function split(Request $request, string $pnr): JsonResponse
    {
        $refs = $request->validate([
            'passengerRefs' => ['required', 'array', 'min:1', 'max:9'],
            'passengerRefs.*' => ['string', 'max:100'],
        ], ['required' => 'Choose who gets a booking of their own.', 'min' => 'Choose who gets a booking of their own.'])['passengerRefs'];

        return $this->json($this->changes->split($this->customer($request), self::pnr($pnr), $refs));
    }

    public function checkIn(Request $request, string $pnr): JsonResponse
    {
        return $this->json($this->changes->checkIn($this->customer($request), self::pnr($pnr)));
    }

    public function doCheckIn(Request $request, string $pnr): JsonResponse
    {
        $group = (int) $request->validate(['group' => ['required', 'integer', 'min:0', 'max:50']], ['required' => 'Choose the flights to check in for.'])['group'];

        return $this->json($this->changes->doCheckIn($this->customer($request), self::pnr($pnr), $group));
    }

    public function undoCheckIn(Request $request, string $pnr): JsonResponse
    {
        $input = $request->validate([
            'group' => ['required', 'integer', 'min:0', 'max:50'],
            'confirm' => ['nullable', 'boolean'],
        ], ['required' => 'Choose the flights to undo check-in for.']);

        return $this->json($this->changes->undoCheckIn($this->customer($request), self::pnr($pnr), (int) $input['group'], (bool) ($input['confirm'] ?? false)));
    }
}
