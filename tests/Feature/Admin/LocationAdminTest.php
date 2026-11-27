<?php

namespace Tests\Feature\Admin;

use App\Models\City;
use App\Models\Country;
use App\Models\State;
use App\Models\User;
use Database\Seeders\CountryStateCitySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationAdminTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(CountryStateCitySeeder::class);

        $this->admin = User::factory()->create([
            'email' => 'admin@naoero-airlines.com',
            'status' => 'active',
        ]);
        $this->admin->assignRole('Super Admin');
    }

    public function test_admin_can_view_locations_console()
    {
        $response = $this->actingAs($this->admin)->get(route('admin.locations.index'));

        $response->assertStatus(200);
        $response->assertSee('Global Locations Master');
        $response->assertSee('Nauru');
        $response->assertSee('Australia');
    }

    public function test_admin_can_view_states_and_cities_tabs()
    {
        $statesResponse = $this->actingAs($this->admin)->get(route('admin.locations.index', ['tab' => 'states']));
        $statesResponse->assertStatus(200);
        $statesResponse->assertSee('Queensland');

        $citiesResponse = $this->actingAs($this->admin)->get(route('admin.locations.index', ['tab' => 'cities']));
        $citiesResponse->assertStatus(200);
        $citiesResponse->assertSee('Brisbane');
    }

    public function test_admin_can_create_new_country()
    {
        $payload = [
            'name' => 'Cook Islands',
            'iso2' => 'CK',
            'iso3' => 'COK',
            'phone_code' => '+682',
            'currency' => 'NZD',
            'currency_symbol' => '$',
            'capital' => 'Avarua',
            'region' => 'Oceania',
            'subregion' => 'Polynesia',
            'flag_emoji' => '🇨🇰',
            'sort_order' => 20,
            'is_active' => '1',
        ];

        $response = $this->actingAs($this->admin)->post(route('admin.locations.countries.store'), $payload);

        $response->assertRedirect(route('admin.locations.index', ['tab' => 'countries']));
        $this->assertDatabaseHas('countries', [
            'name' => 'Cook Islands',
            'iso2' => 'CK',
        ]);
    }

    public function test_admin_can_update_country()
    {
        $nauru = Country::where('iso2', 'NR')->first();

        $payload = [
            'name' => 'Republic of Nauru',
            'iso2' => 'NR',
            'iso3' => 'NRU',
            'phone_code' => '+674',
            'currency' => 'AUD',
            'capital' => 'Yaren',
            'region' => 'Oceania',
            'sort_order' => 1,
            'is_active' => '1',
        ];

        $response = $this->actingAs($this->admin)->put(route('admin.locations.countries.update', $nauru), $payload);

        $response->assertRedirect(route('admin.locations.index', ['tab' => 'countries']));
        $this->assertDatabaseHas('countries', [
            'id' => $nauru->id,
            'name' => 'Republic of Nauru',
        ]);
    }

    public function test_admin_can_toggle_country_status()
    {
        $nauru = Country::where('iso2', 'NR')->first();
        $this->assertTrue($nauru->is_active);

        $response = $this->actingAs($this->admin)->patch(route('admin.locations.countries.toggle', $nauru));

        $response->assertStatus(302);
        $this->assertFalse($nauru->fresh()->is_active);
    }

    public function test_admin_can_create_state_and_city()
    {
        $australia = Country::where('iso2', 'AU')->first();

        // Create State
        $statePayload = [
            'country_id' => $australia->id,
            'name' => 'Northern Territory',
            'state_code' => 'NT',
            'type' => 'Territory',
            'sort_order' => 10,
            'is_active' => '1',
        ];

        $stateResponse = $this->actingAs($this->admin)->post(route('admin.locations.states.store'), $statePayload);
        $stateResponse->assertRedirect();
        $state = State::where('name', 'Northern Territory')->first();
        $this->assertNotNull($state);

        // Create City
        $cityPayload = [
            'country_id' => $australia->id,
            'state_id' => $state->id,
            'name' => 'Darwin',
            'iata_code' => 'DRW',
            'postal_code' => '0800',
            'sort_order' => 1,
            'is_active' => '1',
        ];

        $cityResponse = $this->actingAs($this->admin)->post(route('admin.locations.cities.store'), $cityPayload);
        $cityResponse->assertRedirect();
        $this->assertDatabaseHas('cities', [
            'name' => 'Darwin',
            'iata_code' => 'DRW',
        ]);
    }

    public function test_admin_can_delete_city()
    {
        $city = City::first();
        $id = $city->id;

        $response = $this->actingAs($this->admin)->delete(route('admin.locations.cities.destroy', $city));

        $response->assertRedirect();
        $this->assertDatabaseMissing('cities', ['id' => $id]);
    }
}
