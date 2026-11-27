<?php

namespace Tests\Feature\Admin;

use App\Models\AirlineService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AirlineServiceAdminTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => 'Super Admin', 'guard_name' => 'web']);
        $this->admin = User::create([
            'name' => 'Test Super Admin',
            'email' => 'admin@test.com',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
        ]);
        $this->admin->assignRole($role);
    }

    public function test_admin_can_view_services_index_page(): void
    {
        AirlineService::create([
            'title' => 'Passenger Services – Pacific Comfort',
            'slug' => 'passenger-services',
            'type' => 'passenger',
            'description' => 'First class hospitality and international comfort.',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->get('/admin/services');
        $response->assertStatus(200)
            ->assertSee('Services Management')
            ->assertSee('Passenger Services');
    }

    public function test_admin_can_create_new_service_with_upload(): void
    {
        Storage::fake('public');
        $image = UploadedFile::fake()->image('service_cargo.jpg', 800, 600);

        $payload = [
            'title' => 'Dedicated Cargo & Freight Logistics',
            'slug' => 'dedicated-cargo-logistics',
            'type' => 'cargo',
            'icon' => 'cargofreight',
            'description' => 'Fast and secure freight transit across Micronesia and Australia.',
            'features' => "Perishable cold chain\nDangerous goods certified\nWeekly dedicated freighter flights",
            'cta_text' => 'Freight Quotes',
            'cta_link' => '/freight',
            'sort_order' => 5,
            'is_active' => '1',
            'image_file' => $image,
        ];

        $response = $this->actingAs($this->admin)->post('/admin/services', $payload);

        $response->assertRedirect('/admin/services');
        $this->assertDatabaseHas('airline_services', [
            'slug' => 'dedicated-cargo-logistics',
            'type' => 'cargo',
            'cta_text' => 'Freight Quotes',
            'is_active' => true,
        ]);

        $service = AirlineService::where('slug', 'dedicated-cargo-logistics')->first();
        $this->assertNotNull($service->image);
        $this->assertCount(3, $service->features);
        $this->assertEquals('Perishable cold chain', $service->features[0]);
    }

    public function test_admin_can_update_service(): void
    {
        $service = AirlineService::create([
            'title' => 'VIP Private Charter',
            'slug' => 'vip-private-charter',
            'type' => 'charter',
            'description' => 'Original charter description.',
            'is_active' => true,
        ]);

        $payload = [
            'title' => 'VIP Private Aircraft Charter Hub',
            'slug' => 'vip-private-charter-hub',
            'type' => 'charter',
            'description' => 'Updated luxury charter solutions.',
            'cta_text' => 'Charter Quote',
            'cta_link' => '/charter',
            'sort_order' => 3,
            'is_active' => '1',
        ];

        $response = $this->actingAs($this->admin)->put("/admin/services/{$service->id}", $payload);

        $response->assertRedirect('/admin/services');
        $this->assertDatabaseHas('airline_services', [
            'id' => $service->id,
            'title' => 'VIP Private Aircraft Charter Hub',
            'slug' => 'vip-private-charter-hub',
        ]);
    }

    public function test_admin_can_toggle_service_active_status(): void
    {
        $service = AirlineService::create([
            'title' => 'Toggle Test Service',
            'slug' => 'toggle-test-service',
            'type' => 'passenger',
            'description' => 'Testing active toggle.',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->patch("/admin/services/{$service->id}/toggle-active");

        $response->assertRedirect();
        $this->assertDatabaseHas('airline_services', [
            'id' => $service->id,
            'is_active' => false,
        ]);
    }

    public function test_admin_can_delete_service(): void
    {
        $service = AirlineService::create([
            'title' => 'Delete Test Service',
            'slug' => 'delete-test-service',
            'type' => 'passenger',
            'description' => 'Testing deletion.',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->delete("/admin/services/{$service->id}");

        $response->assertRedirect('/admin/services');
        $this->assertDatabaseMissing('airline_services', [
            'id' => $service->id,
        ]);
    }
}
