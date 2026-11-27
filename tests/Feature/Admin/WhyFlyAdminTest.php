<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Models\WhyFlyFeature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WhyFlyAdminTest extends TestCase
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

    public function test_admin_can_view_why_fly_index_page(): void
    {
        WhyFlyFeature::create([
            'title' => 'Trusted Routes',
            'description' => 'The official national carrier connecting the Pacific.',
            'icon' => 'Plane',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->get('/admin/why-fly');
        $response->assertStatus(200)
            ->assertSee('Why Fly With Us Cards')
            ->assertSee('Trusted Routes');
    }

    public function test_admin_can_create_new_why_fly_card(): void
    {
        Storage::fake('public');
        $iconFile = UploadedFile::fake()->image('custom_icon.png', 64, 64);

        $payload = [
            'title' => 'Pacific Hospitality',
            'description' => 'Experience genuine island warmth on every flight.',
            'icon' => 'HeartHandshake',
            'sort_order' => 5,
            'is_active' => '1',
            'icon_image_file' => $iconFile,
        ];

        $response = $this->actingAs($this->admin)->post('/admin/why-fly', $payload);

        $response->assertRedirect('/admin/why-fly');
        $this->assertDatabaseHas('why_fly_features', [
            'title' => 'Pacific Hospitality',
            'icon' => 'HeartHandshake',
            'is_active' => true,
        ]);

        $feature = WhyFlyFeature::where('title', 'Pacific Hospitality')->first();
        $this->assertNotNull($feature->icon_image);
    }

    public function test_admin_can_update_why_fly_card(): void
    {
        $feature = WhyFlyFeature::create([
            'title' => 'Original Title',
            'description' => 'Original description.',
            'icon' => 'Plane',
            'is_active' => true,
        ]);

        $payload = [
            'title' => 'Updated Selling Point Title',
            'description' => 'Updated description content.',
            'icon' => 'ShieldCheck',
            'sort_order' => 2,
            'is_active' => '1',
        ];

        $response = $this->actingAs($this->admin)->put("/admin/why-fly/{$feature->id}", $payload);

        $response->assertRedirect('/admin/why-fly');
        $this->assertDatabaseHas('why_fly_features', [
            'id' => $feature->id,
            'title' => 'Updated Selling Point Title',
            'icon' => 'ShieldCheck',
        ]);
    }

    public function test_admin_can_toggle_why_fly_active_status(): void
    {
        $feature = WhyFlyFeature::create([
            'title' => 'Toggle Active Card',
            'description' => 'Testing active toggle.',
            'icon' => 'Plane',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->patch("/admin/why-fly/{$feature->id}/toggle-active");

        $response->assertRedirect();
        $this->assertDatabaseHas('why_fly_features', [
            'id' => $feature->id,
            'is_active' => false,
        ]);
    }

    public function test_admin_can_delete_why_fly_card(): void
    {
        $feature = WhyFlyFeature::create([
            'title' => 'Delete Card',
            'description' => 'Testing deletion.',
            'icon' => 'Package',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->delete("/admin/why-fly/{$feature->id}");

        $response->assertRedirect('/admin/why-fly');
        $this->assertDatabaseMissing('why_fly_features', [
            'id' => $feature->id,
        ]);
    }

    public function test_admin_can_update_why_fly_section_headings(): void
    {
        $payload = [
            'why_fly_heading' => 'Why Travel With Naoero Airlines',
            'why_fly_subheading' => 'Over half a century of trusted Pacific aviation.',
        ];

        $response = $this->actingAs($this->admin)->post('/admin/why-fly/update-headings', $payload);

        $response->assertRedirect();
        $this->assertDatabaseHas('site_settings', [
            'key' => 'why_fly_heading',
            'value' => 'Why Travel With Naoero Airlines',
        ]);
    }
}
