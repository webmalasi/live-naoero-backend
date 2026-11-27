<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Services\Auth\AuthEvents;
use App\Services\Booking\SavedTravellers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Passengers the customer saved while booking. */
class TravellerController extends ApiController
{
    public function __construct(
        private readonly SavedTravellers $travellers,
        private readonly AuthEvents $events,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return $this->json(['travellers' => $this->travellers->list($this->customer($request)->id)]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $customer = $this->customer($request);
        if (! Str::isUuid($id)) {
            throw ApiException::invalid('Invalid traveller.');
        }
        $removed = $this->travellers->delete($customer->id, $id);
        if ($removed) {
            $this->events->record('traveller_remove', true, $this->meta($request), $customer->id, $customer->zenith_login);
        }

        return $this->json(['ok' => true, 'removed' => $removed]);
    }
}
