<?php

namespace Tests\Feature\Api;

use App\Models\City;
use App\Models\Country;
use App\Models\State;
use Database\Seeders\CountryStateCitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CountryStateCitySeeder::class);
    }

    public function test_can_fetch_all_active_countries()
    {
        $response = $this->getJson(route('api.locations.countries'));

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);
        $response->assertJsonFragment([
            'iso2' => 'NR',
            'name' => 'Nauru',
        ]);
        $response->assertJsonFragment([
            'iso2' => 'AU',
            'name' => 'Australia',
        ]);
    }

    public function test_can_filter_countries_by_region()
    {
        $response = $this->getJson(route('api.locations.countries', ['region' => 'Oceania']));

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'name' => 'Nauru',
        ]);
    }

    public function test_can_fetch_states_for_country_by_id_or_iso2()
    {
        $nauru = Country::where('iso2', 'NR')->first();

        $responseById = $this->getJson(route('api.locations.states', ['countryIdentifier' => $nauru->id]));
        $responseById->assertStatus(200);
        $responseById->assertJsonFragment([
            'name' => 'Yaren District',
        ]);

        $responseByIso = $this->getJson(route('api.locations.states', ['countryIdentifier' => 'AU']));
        $responseByIso->assertStatus(200);
        $responseByIso->assertJsonFragment([
            'name' => 'Queensland',
        ]);
    }

    public function test_can_fetch_cities_for_state()
    {
        $qld = State::where('state_code', 'QLD')->first();

        $response = $this->getJson(route('api.locations.cities', ['stateId' => $qld->id]));

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);
        $response->assertJsonFragment([
            'name' => 'Brisbane',
            'iata_code' => 'BNE',
        ]);
    }

    public function test_can_fetch_cities_directly_for_country()
    {
        $response = $this->getJson(route('api.locations.country-cities', ['countryIdentifier' => 'NR']));

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'name' => 'Yaren (Airport INU)',
            'iata_code' => 'INU',
        ]);
    }

    public function test_can_fetch_locations_summary()
    {
        $response = $this->getJson(route('api.locations.summary'));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'total_countries',
                'active_countries',
                'total_states',
                'active_states',
                'total_cities',
                'active_cities',
                'regions',
            ],
        ]);
    }
}
