<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Country;
use App\Models\State;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationApiController extends Controller
{
    /**
     * Get list of countries.
     */
    public function countries(Request $request): JsonResponse
    {
        $query = Country::query();

        if ($request->boolean('active_only', true)) {
            $query->where('is_active', true);
        }

        if ($request->filled('region')) {
            $query->where('region', $request->input('region'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('iso2', 'ilike', "%{$search}%")
                  ->orWhere('iso3', 'ilike', "%{$search}%")
                  ->orWhere('capital', 'ilike', "%{$search}%");
            });
        }

        $countries = $query->orderBy('sort_order', 'asc')
            ->orderBy('name', 'asc')
            ->get([
                'id',
                'name',
                'iso2',
                'iso3',
                'phone_code',
                'currency',
                'currency_symbol',
                'currency_name',
                'capital',
                'region',
                'subregion',
                'flag_emoji',
                'latitude',
                'longitude',
                'is_active',
                'sort_order',
            ]);

        return response()->json([
            'success' => true,
            'count' => $countries->count(),
            'data' => $countries,
        ]);
    }

    /**
     * Get states for a given country (by ID or ISO2 code).
     */
    public function states(Request $request, string|int $countryIdentifier): JsonResponse
    {
        $country = is_numeric($countryIdentifier)
            ? Country::find($countryIdentifier)
            : Country::where('iso2', strtoupper($countryIdentifier))->first();

        if (!$country) {
            return response()->json([
                'success' => false,
                'message' => 'Country not found.',
                'data' => [],
            ], 404);
        }

        $query = State::where('country_id', $country->id);

        if ($request->boolean('active_only', true)) {
            $query->where('is_active', true);
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('state_code', 'ilike', "%{$search}%");
            });
        }

        $states = $query->orderBy('sort_order', 'asc')
            ->orderBy('name', 'asc')
            ->get([
                'id',
                'country_id',
                'name',
                'state_code',
                'type',
                'latitude',
                'longitude',
                'is_active',
                'sort_order',
            ]);

        return response()->json([
            'success' => true,
            'country' => [
                'id' => $country->id,
                'name' => $country->name,
                'iso2' => $country->iso2,
                'flag_emoji' => $country->flag_emoji,
            ],
            'count' => $states->count(),
            'data' => $states,
        ]);
    }

    /**
     * Get cities for a given state.
     */
    public function cities(Request $request, int $stateId): JsonResponse
    {
        $state = State::with('country')->find($stateId);

        if (!$state) {
            return response()->json([
                'success' => false,
                'message' => 'State not found.',
                'data' => [],
            ], 404);
        }

        $query = City::where('state_id', $state->id);

        if ($request->boolean('active_only', true)) {
            $query->where('is_active', true);
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('postal_code', 'ilike', "%{$search}%")
                  ->orWhere('iata_code', 'ilike', "%{$search}%");
            });
        }

        $cities = $query->orderBy('sort_order', 'asc')
            ->orderBy('name', 'asc')
            ->get([
                'id',
                'country_id',
                'state_id',
                'name',
                'postal_code',
                'iata_code',
                'latitude',
                'longitude',
                'is_active',
                'sort_order',
            ]);

        return response()->json([
            'success' => true,
            'country' => [
                'id' => $state->country?->id,
                'name' => $state->country?->name,
                'iso2' => $state->country?->iso2,
            ],
            'state' => [
                'id' => $state->id,
                'name' => $state->name,
                'state_code' => $state->state_code,
            ],
            'count' => $cities->count(),
            'data' => $cities,
        ]);
    }

    /**
     * Get all cities directly for a country.
     */
    public function countryCities(Request $request, string|int $countryIdentifier): JsonResponse
    {
        $country = is_numeric($countryIdentifier)
            ? Country::find($countryIdentifier)
            : Country::where('iso2', strtoupper($countryIdentifier))->first();

        if (!$country) {
            return response()->json([
                'success' => false,
                'message' => 'Country not found.',
                'data' => [],
            ], 404);
        }

        $query = City::with('state:id,name,state_code')->where('country_id', $country->id);

        if ($request->boolean('active_only', true)) {
            $query->where('is_active', true);
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('postal_code', 'ilike', "%{$search}%")
                  ->orWhere('iata_code', 'ilike', "%{$search}%");
            });
        }

        $cities = $query->orderBy('sort_order', 'asc')
            ->orderBy('name', 'asc')
            ->get([
                'id',
                'country_id',
                'state_id',
                'name',
                'postal_code',
                'iata_code',
                'latitude',
                'longitude',
                'is_active',
                'sort_order',
            ]);

        return response()->json([
            'success' => true,
            'country' => [
                'id' => $country->id,
                'name' => $country->name,
                'iso2' => $country->iso2,
                'flag_emoji' => $country->flag_emoji,
            ],
            'count' => $cities->count(),
            'data' => $cities,
        ]);
    }

    /**
     * Summary of regions and statistics.
     */
    public function summary(): JsonResponse
    {
        $regions = Country::selectRaw('region, count(*) as total_countries')
            ->whereNotNull('region')
            ->groupBy('region')
            ->orderBy('total_countries', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'total_countries' => Country::count(),
                'active_countries' => Country::where('is_active', true)->count(),
                'total_states' => State::count(),
                'active_states' => State::where('is_active', true)->count(),
                'total_cities' => City::count(),
                'active_cities' => City::where('is_active', true)->count(),
                'regions' => $regions,
            ],
        ]);
    }
}
