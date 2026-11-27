<?php

namespace Tests\Feature\Admin;

use App\Models\DropdownOption;
use App\Models\User;
use Database\Seeders\DropdownOptionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DropdownOptionTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(DropdownOptionSeeder::class);

        $this->admin = User::factory()->create([
            'email' => 'admin@naoero-airlines.com',
            'status' => 'active',
        ]);
        $this->admin->assignRole('Super Admin');
    }

    public function test_admin_can_view_dropdowns_master_console()
    {
        $response = $this->actingAs($this->admin)->get(route('admin.dropdowns.index'));

        $response->assertStatus(200);
        $response->assertSee('Dynamic Dropdowns');
        $response->assertSee('Shop Product Categories');
        $response->assertSee('Aircraft Fleet Models');
    }

    public function test_admin_can_filter_dropdowns_by_group()
    {
        $response = $this->actingAs($this->admin)->get(route('admin.dropdowns.index', ['group' => 'fleet_types']));

        $response->assertStatus(200);
        $response->assertSee('Boeing 737-700');
        $response->assertSee('B737-700');
    }

    public function test_admin_can_create_new_dropdown_option()
    {
        $payload = [
            'group' => 'post_categories',
            'name' => 'Island Eco-Tourism',
            'value' => 'Eco-Tourism',
            'badge_color' => 'emerald',
            'icon' => '🌿',
            'description' => 'Sustainable travel and conservation articles.',
            'sort_order' => 10,
            'is_active' => '1',
        ];

        $response = $this->actingAs($this->admin)->post(route('admin.dropdowns.store'), $payload);

        $response->assertRedirect(route('admin.dropdowns.index', ['group' => 'post_categories']));
        $this->assertDatabaseHas('dropdown_options', [
            'group' => 'post_categories',
            'name' => 'Island Eco-Tourism',
            'value' => 'Eco-Tourism',
            'badge_color' => 'emerald',
            'icon' => '🌿',
            'is_active' => true,
        ]);
    }

    public function test_admin_can_update_dropdown_option()
    {
        $option = DropdownOption::where('group', 'post_categories')->first();

        $response = $this->actingAs($this->admin)->put(route('admin.dropdowns.update', $option), [
            'name' => 'Updated Category Title',
            'value' => 'Updated Category Title',
            'badge_color' => 'indigo',
            'icon' => '📰',
            'description' => 'Updated description content.',
            'sort_order' => 5,
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('admin.dropdowns.index', ['group' => $option->group]));
        $this->assertDatabaseHas('dropdown_options', [
            'id' => $option->id,
            'name' => 'Updated Category Title',
            'badge_color' => 'indigo',
        ]);
    }

    public function test_admin_can_toggle_dropdown_active_status()
    {
        $option = DropdownOption::create([
            'group' => 'faq_categories',
            'name' => 'Temporary FAQ Category',
            'value' => 'Temporary FAQ Category',
            'is_active' => true,
            'is_system' => false,
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.dropdowns.toggle', $option));

        $response->assertJson([
            'success' => true,
            'is_active' => false,
        ]);

        $this->assertFalse($option->fresh()->is_active);
    }

    public function test_quick_store_ajax_creates_option()
    {
        $response = $this->actingAs($this->admin)->postJson(route('admin.dropdowns.quick-store'), [
            'group' => 'career_departments',
            'name' => 'In-Flight Catering Services',
            'badge_color' => 'amber',
        ]);

        $response->assertStatus(201);
        $response->assertJson([
            'success' => true,
            'data' => [
                'group' => 'career_departments',
                'name' => 'In-Flight Catering Services',
                'value' => 'In-Flight Catering Services',
            ],
        ]);

        $this->assertDatabaseHas('dropdown_options', [
            'group' => 'career_departments',
            'name' => 'In-Flight Catering Services',
        ]);
    }

    public function test_admin_can_delete_custom_dropdown_option()
    {
        $customOption = DropdownOption::create([
            'group' => 'shop_categories',
            'name' => 'Seasonal Holiday Promo',
            'value' => 'Holiday Promo',
            'is_system' => false,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->delete(route('admin.dropdowns.destroy', $customOption));

        $response->assertRedirect();
        $this->assertDatabaseMissing('dropdown_options', [
            'id' => $customOption->id,
        ]);
    }

    public function test_system_dropdown_option_cannot_be_deleted()
    {
        $systemOption = DropdownOption::where('is_system', true)->first();

        $response = $this->actingAs($this->admin)->delete(route('admin.dropdowns.destroy', $systemOption));

        $response->assertRedirect();
        $this->assertDatabaseHas('dropdown_options', [
            'id' => $systemOption->id,
        ]);
    }

    public function test_model_helper_methods_return_active_sorted_options()
    {
        $categories = DropdownOption::getValues('shop_categories');
        $this->assertIsArray($categories);
        $this->assertContains('Apparel', $categories);
        $this->assertContains('Drinkware', $categories);
        $this->assertContains('Gift Card', $categories);

        $fleetTypes = DropdownOption::getValues('fleet_types');
        $this->assertContains('B737-700', $fleetTypes);

        $kvMap = DropdownOption::getKeyValueMap('currencies');
        $this->assertArrayHasKey('AUD', $kvMap);
    }
}
