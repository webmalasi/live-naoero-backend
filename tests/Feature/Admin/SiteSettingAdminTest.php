<?php

namespace Tests\Feature\Admin;

use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SiteSettingAdminTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Create Super Admin role and user
        $role = Role::create(['name' => 'Super Admin', 'guard_name' => 'web']);
        $this->admin = User::create([
            'name' => 'Test Super Admin',
            'email' => 'admin@test.com',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
        ]);
        $this->admin->assignRole($role);
    }

    public function test_admin_can_view_settings_page(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/settings');
        $response->assertStatus(200)
            ->assertSee('Site Settings, Branding')
            ->assertSee('Top Header Bar Welcome Text')
            ->assertSee('Top Header Quick Menu Navigation');
    }

    public function test_admin_can_update_settings_logo_header_text_and_menu(): void
    {
        Storage::fake('public');

        $logoFile = UploadedFile::fake()->image('custom_logo.png', 300, 60);

        $payload = [
            'site_logo_file' => $logoFile,
            'top_header_text' => 'Welcome to Naoero Airlines - Connecting the Pacific',
            'menu_labels' => ['My Booking', 'Flight Status', 'Contact Us'],
            'menu_routes' => ['https://book.naoeroairlines.com/manage', '/travel-info/flight-schedule', '/contact-us'],
            'menu_icons' => ['Ticket', 'PlaneTakeoff', 'Phone'],
            'menu_externals' => ['1', '0', '0'],
            'airline_name' => 'Naoero Airlines Official',
            'reservations_email' => 'booking@naoeroairlines.com.au',
        ];

        $response = $this->actingAs($this->admin)->post('/admin/settings', $payload);
        $response->assertRedirect()->assertSessionHas('success');

        $this->assertEquals('Welcome to Naoero Airlines - Connecting the Pacific', SiteSetting::get('top_header_text'));
        $this->assertEquals('Naoero Airlines Official', SiteSetting::get('airline_name'));

        $menu = json_decode(SiteSetting::get('top_header_menu'), true);
        $this->assertIsArray($menu);
        $this->assertCount(3, $menu);
        $this->assertEquals('My Booking', $menu[0]['label']);
        $this->assertTrue($menu[0]['is_external']);
        $this->assertEquals('Flight Status', $menu[1]['label']);
        $this->assertFalse($menu[1]['is_external']);

        $logoSetting = SiteSetting::get('site_logo');
        $this->assertStringStartsWith('/storage/settings/', $logoSetting);
    }
}
