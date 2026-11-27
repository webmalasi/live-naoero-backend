<?php

namespace Tests\Feature\Api;

use App\Models\DropdownOption;
use Database\Seeders\DropdownOptionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DropdownApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DropdownOptionSeeder::class);
    }

    public function test_can_fetch_all_dropdown_groups_via_api()
    {
        $response = $this->getJson(route('api.dropdowns.index'));

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);
        $response->assertJsonStructure([
            'success',
            'groups_count',
            'total_options',
            'data' => [
                'shop_categories',
                'fleet_types',
                'post_categories',
            ],
        ]);
    }

    public function test_can_fetch_specific_dropdown_group_via_api()
    {
        $response = $this->getJson(route('api.dropdowns.show', ['group' => 'shop_categories']));

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'group' => 'shop_categories',
        ]);
        $response->assertJsonFragment([
            'name' => 'Apparel',
        ]);
    }
}
