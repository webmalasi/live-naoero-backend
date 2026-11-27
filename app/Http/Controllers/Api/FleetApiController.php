<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Fleet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FleetApiController extends Controller
{
    /**
     * Get list of fleet aircraft (passenger & freighter).
     */
    public function index(Request $request): JsonResponse
    {
        $query = Fleet::query()->active()->ordered();

        if ($category = $request->query('category')) {
            $query->where('category', $category);
        }

        $fleets = $query->get();

        $passengerFleet = $fleets->where('category', 'passenger')->values();
        $freightFleet = $fleets->where('category', 'freight')->values();

        return response()->json([
            'success' => true,
            'count' => $fleets->count(),
            'data' => $fleets,
            'passengerFleet' => $passengerFleet,
            'freightFleet' => $freightFleet,
        ]);
    }

    /**
     * Get single aircraft detail by ID or registration.
     */
    public function show(string $identifier): JsonResponse
    {
        $fleet = Fleet::query()
            ->active()
            ->where(function ($q) use ($identifier) {
                if (is_numeric($identifier)) {
                    $q->where('id', (int) $identifier)->orWhere('registration', $identifier);
                } else {
                    $q->where('registration', $identifier);
                }
            })
            ->first();

        if (! $fleet) {
            return response()->json([
                'success' => false,
                'message' => 'Aircraft not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $fleet,
        ]);
    }
}
