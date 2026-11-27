<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\City;
use App\Models\Country;
use App\Models\State;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LocationController extends Controller
{
    /**
     * Display the Location Management dashboard (Countries, States, Cities).
     */
    public function index(Request $request): View
    {
        $tab = $request->query('tab', 'countries');
        $search = $request->query('search');
        $countryFilter = $request->query('country_id');
        $stateFilter = $request->query('state_id');
        $regionFilter = $request->query('region');

        // Summary Counts
        $stats = [
            'total_countries' => Country::count(),
            'active_countries' => Country::where('is_active', true)->count(),
            'total_states' => State::count(),
            'active_states' => State::where('is_active', true)->count(),
            'total_cities' => City::count(),
            'active_cities' => City::where('is_active', true)->count(),
        ];

        // All active countries and states for filter dropdowns and modals
        $allCountries = Country::orderBy('sort_order', 'asc')->orderBy('name', 'asc')->get();
        $allStates = State::with('country')->orderBy('sort_order', 'asc')->orderBy('name', 'asc')->get();
        $regions = Country::whereNotNull('region')->distinct()->pluck('region');

        // Tab-specific queries
        $countries = null;
        $states = null;
        $cities = null;

        if ($tab === 'countries') {
            $query = Country::withCount(['states', 'cities']);

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'ilike', "%{$search}%")
                      ->orWhere('iso2', 'ilike', "%{$search}%")
                      ->orWhere('iso3', 'ilike', "%{$search}%")
                      ->orWhere('capital', 'ilike', "%{$search}%")
                      ->orWhere('currency', 'ilike', "%{$search}%");
                });
            }

            if ($regionFilter) {
                $query->where('region', $regionFilter);
            }

            $countries = $query->orderBy('sort_order', 'asc')->orderBy('name', 'asc')->paginate(15)->withQueryString();
        } elseif ($tab === 'states') {
            $query = State::with('country')->withCount('cities');

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'ilike', "%{$search}%")
                      ->orWhere('state_code', 'ilike', "%{$search}%")
                      ->orWhereHas('country', function ($cq) use ($search) {
                          $cq->where('name', 'ilike', "%{$search}%");
                      });
                });
            }

            if ($countryFilter) {
                $query->where('country_id', $countryFilter);
            }

            $states = $query->orderBy('sort_order', 'asc')->orderBy('name', 'asc')->paginate(15)->withQueryString();
        } elseif ($tab === 'cities') {
            $query = City::with(['country', 'state']);

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'ilike', "%{$search}%")
                      ->orWhere('postal_code', 'ilike', "%{$search}%")
                      ->orWhere('iata_code', 'ilike', "%{$search}%")
                      ->orWhereHas('country', function ($cq) use ($search) {
                          $cq->where('name', 'ilike', "%{$search}%");
                      })
                      ->orWhereHas('state', function ($sq) use ($search) {
                          $sq->where('name', 'ilike', "%{$search}%");
                      });
                });
            }

            if ($countryFilter) {
                $query->where('country_id', $countryFilter);
            }

            if ($stateFilter) {
                $query->where('state_id', $stateFilter);
            }

            $cities = $query->orderBy('sort_order', 'asc')->orderBy('name', 'asc')->paginate(15)->withQueryString();
        }

        return view('admin.locations.index', compact(
            'tab',
            'stats',
            'allCountries',
            'allStates',
            'regions',
            'countries',
            'states',
            'cities',
            'search',
            'countryFilter',
            'stateFilter',
            'regionFilter'
        ));
    }

    // ==========================================
    // Country CRUD
    // ==========================================

    public function storeCountry(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'iso2' => 'required|string|size:2|unique:countries,iso2',
            'iso3' => 'nullable|string|max:3',
            'phone_code' => 'nullable|string|max:20',
            'currency' => 'nullable|string|max:10',
            'currency_symbol' => 'nullable|string|max:10',
            'currency_name' => 'nullable|string|max:100',
            'capital' => 'nullable|string|max:100',
            'region' => 'nullable|string|max:100',
            'subregion' => 'nullable|string|max:100',
            'flag_emoji' => 'nullable|string|max:20',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['iso2'] = strtoupper($validated['iso2']);
        $validated['iso3'] = isset($validated['iso3']) ? strtoupper($validated['iso3']) : null;
        $validated['currency'] = isset($validated['currency']) ? strtoupper($validated['currency']) : null;
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        $country = Country::create($validated);

        ActivityLog::log(
            'created',
            'locations',
            "Created country '{$country->name}' ({$country->iso2})",
            $country->toArray()
        );

        return redirect()->route('admin.locations.index', ['tab' => 'countries'])
            ->with('success', "Country '{$country->name}' created successfully.");
    }

    public function updateCountry(Request $request, Country $country): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'iso2' => ['required', 'string', 'size:2', Rule::unique('countries', 'iso2')->ignore($country->id)],
            'iso3' => 'nullable|string|max:3',
            'phone_code' => 'nullable|string|max:20',
            'currency' => 'nullable|string|max:10',
            'currency_symbol' => 'nullable|string|max:10',
            'currency_name' => 'nullable|string|max:100',
            'capital' => 'nullable|string|max:100',
            'region' => 'nullable|string|max:100',
            'subregion' => 'nullable|string|max:100',
            'flag_emoji' => 'nullable|string|max:20',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['iso2'] = strtoupper($validated['iso2']);
        $validated['iso3'] = isset($validated['iso3']) ? strtoupper($validated['iso3']) : null;
        $validated['currency'] = isset($validated['currency']) ? strtoupper($validated['currency']) : null;
        $validated['is_active'] = $request->boolean('is_active');
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        $country->update($validated);

        ActivityLog::log(
            'updated',
            'locations',
            "Updated country '{$country->name}' ({$country->iso2})",
            $country->toArray()
        );

        return redirect()->route('admin.locations.index', ['tab' => 'countries'])
            ->with('success', "Country '{$country->name}' updated successfully.");
    }

    public function destroyCountry(Country $country): RedirectResponse
    {
        $name = $country->name;
        $country->delete();

        ActivityLog::log(
            'deleted',
            'locations',
            "Deleted country '{$name}'"
        );

        return redirect()->route('admin.locations.index', ['tab' => 'countries'])
            ->with('success', "Country '{$name}' and its associated states/cities deleted successfully.");
    }

    public function toggleCountry(Country $country): RedirectResponse
    {
        $country->is_active = !$country->is_active;
        $country->save();

        ActivityLog::log(
            'updated',
            'locations',
            "Toggled active status of country '{$country->name}' to " . ($country->is_active ? 'Active' : 'Inactive')
        );

        return back()->with('success', "Status of '{$country->name}' updated to " . ($country->is_active ? 'Active' : 'Inactive') . ".");
    }

    // ==========================================
    // State CRUD
    // ==========================================

    public function storeState(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'country_id' => 'required|exists:countries,id',
            'name' => 'required|string|max:150',
            'state_code' => 'nullable|string|max:50',
            'type' => 'nullable|string|max:50',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['type'] = $validated['type'] ?: 'State';

        $state = State::create($validated);

        ActivityLog::log(
            'created',
            'locations',
            "Created state '{$state->name}' for country ID #{$state->country_id}",
            $state->toArray()
        );

        return redirect()->route('admin.locations.index', ['tab' => 'states', 'country_id' => $state->country_id])
            ->with('success', "State/Province '{$state->name}' created successfully.");
    }

    public function updateState(Request $request, State $state): RedirectResponse
    {
        $validated = $request->validate([
            'country_id' => 'required|exists:countries,id',
            'name' => 'required|string|max:150',
            'state_code' => 'nullable|string|max:50',
            'type' => 'nullable|string|max:50',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['type'] = $validated['type'] ?: 'State';

        $state->update($validated);

        ActivityLog::log(
            'updated',
            'locations',
            "Updated state '{$state->name}'",
            $state->toArray()
        );

        return redirect()->route('admin.locations.index', ['tab' => 'states'])
            ->with('success', "State/Province '{$state->name}' updated successfully.");
    }

    public function destroyState(State $state): RedirectResponse
    {
        $name = $state->name;
        $state->delete();

        ActivityLog::log(
            'deleted',
            'locations',
            "Deleted state '{$name}'"
        );

        return redirect()->route('admin.locations.index', ['tab' => 'states'])
            ->with('success', "State '{$name}' and its associated cities deleted successfully.");
    }

    public function toggleState(State $state): RedirectResponse
    {
        $state->is_active = !$state->is_active;
        $state->save();

        ActivityLog::log(
            'updated',
            'locations',
            "Toggled state '{$state->name}' to " . ($state->is_active ? 'Active' : 'Inactive')
        );

        return back()->with('success', "Status of '{$state->name}' updated.");
    }

    // ==========================================
    // City CRUD
    // ==========================================

    public function storeCity(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'country_id' => 'required|exists:countries,id',
            'state_id' => 'required|exists:states,id',
            'name' => 'required|string|max:150',
            'postal_code' => 'nullable|string|max:50',
            'iata_code' => 'nullable|string|max:10',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['iata_code'] = isset($validated['iata_code']) ? strtoupper(trim($validated['iata_code'])) : null;
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        $city = City::create($validated);

        ActivityLog::log(
            'created',
            'locations',
            "Created city '{$city->name}'",
            $city->toArray()
        );

        return redirect()->route('admin.locations.index', ['tab' => 'cities', 'country_id' => $city->country_id, 'state_id' => $city->state_id])
            ->with('success', "City '{$city->name}' created successfully.");
    }

    public function updateCity(Request $request, City $city): RedirectResponse
    {
        $validated = $request->validate([
            'country_id' => 'required|exists:countries,id',
            'state_id' => 'required|exists:states,id',
            'name' => 'required|string|max:150',
            'postal_code' => 'nullable|string|max:50',
            'iata_code' => 'nullable|string|max:10',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['iata_code'] = isset($validated['iata_code']) ? strtoupper(trim($validated['iata_code'])) : null;
        $validated['is_active'] = $request->boolean('is_active');
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        $city->update($validated);

        ActivityLog::log(
            'updated',
            'locations',
            "Updated city '{$city->name}'",
            $city->toArray()
        );

        return redirect()->route('admin.locations.index', ['tab' => 'cities'])
            ->with('success', "City '{$city->name}' updated successfully.");
    }

    public function destroyCity(City $city): RedirectResponse
    {
        $name = $city->name;
        $city->delete();

        ActivityLog::log(
            'deleted',
            'locations',
            "Deleted city '{$name}'"
        );

        return redirect()->route('admin.locations.index', ['tab' => 'cities'])
            ->with('success', "City '{$name}' deleted successfully.");
    }

    public function toggleCity(City $city): RedirectResponse
    {
        $city->is_active = !$city->is_active;
        $city->save();

        ActivityLog::log(
            'updated',
            'locations',
            "Toggled city '{$city->name}' to " . ($city->is_active ? 'Active' : 'Inactive')
        );

        return back()->with('success', "Status of '{$city->name}' updated.");
    }

    /**
     * AJAX helper to fetch states for selected country.
     */
    public function getStatesJson(Country $country): JsonResponse
    {
        $states = $country->states()->select(['id', 'name', 'state_code'])->orderBy('name')->get();
        return response()->json($states);
    }
}
