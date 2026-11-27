<?php

namespace Tests\Feature\Admin;

use App\Models\Notice;
use App\Models\SiteSetting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NoticeManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create([
            'email' => 'admin@naoero-airlines.com',
            'status' => 'active',
        ]);
        $this->admin->assignRole('Super Admin');
    }

    public function test_admin_can_view_notices_dashboard()
    {
        Notice::create([
            'title' => 'Update and News',
            'message' => 'Flight 201 to Nauru has been rescheduled due to adverse weather conditions',
            'type' => 'warning',
            'is_active' => true,
            'duration_hours' => 24,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.notices.index'));
        $response->assertStatus(200);
        $response->assertSee('Flight 201 to Nauru has been rescheduled');
        $response->assertSee('Notice Bar');
        $response->assertSee('Flight Announcements');
    }

    public function test_admin_can_create_notice_with_duration_hours()
    {
        $response = $this->actingAs($this->admin)->post(route('admin.notices.store'), [
            'title' => 'Update and News',
            'badge_text' => 'Weather Alert',
            'message' => 'Flight 201 to Nauru has been rescheduled due to adverse weather conditions',
            'link' => '/flight-schedule',
            'type' => 'warning',
            'is_active' => 1,
            'duration_hours' => 48,
            'priority' => 10,
        ]);

        $response->assertRedirect(route('admin.notices.index'));
        $this->assertDatabaseHas('notices', [
            'message' => 'Flight 201 to Nauru has been rescheduled due to adverse weather conditions',
            'duration_hours' => 48,
            'type' => 'warning',
            'is_active' => true,
        ]);

        $notice = Notice::where('message', 'like', '%Flight 201%')->first();
        $this->assertNotNull($notice->expires_at);
        $this->assertTrue($notice->isActiveNow());
    }

    public function test_admin_can_toggle_individual_notice_status()
    {
        $notice = Notice::create([
            'title' => 'Test Notice',
            'message' => 'Flight 201 to Nauru update',
            'type' => 'info',
            'is_active' => true,
        ]);

        $this->actingAs($this->admin)->patch(route('admin.notices.toggle-active', $notice));
        $this->assertFalse($notice->fresh()->is_active);

        $this->actingAs($this->admin)->patch(route('admin.notices.toggle-active', $notice));
        $this->assertTrue($notice->fresh()->is_active);
    }

    public function test_admin_can_extend_notice_duration()
    {
        $notice = Notice::create([
            'title' => 'Weather Notice',
            'message' => 'Flight 201 delayed',
            'type' => 'warning',
            'is_active' => true,
            'duration_hours' => 12,
            'starts_at' => now(),
            'expires_at' => now()->addHours(12),
        ]);

        $response = $this->actingAs($this->admin)->patch(route('admin.notices.extend-time', $notice), [
            'hours' => 24,
        ]);

        $response->assertSessionHas('success');
        $this->assertGreaterThan(12, $notice->fresh()->duration_hours);
    }

    public function test_admin_can_toggle_global_master_switch()
    {
        SiteSetting::updateOrCreate(
            ['key' => 'notice_bar_enabled'],
            ['value' => '1', 'group' => 'general', 'type' => 'boolean']
        );

        $this->actingAs($this->admin)->post(route('admin.notices.toggle-master'));
        $this->assertEquals('0', SiteSetting::where('key', 'notice_bar_enabled')->value('value'));

        $this->actingAs($this->admin)->post(route('admin.notices.toggle-master'));
        $this->assertEquals('1', SiteSetting::where('key', 'notice_bar_enabled')->value('value'));
    }

    public function test_public_api_returns_active_unexpired_notices()
    {
        // Active notice
        Notice::create([
            'title' => 'Update and News',
            'message' => 'Flight 201 to Nauru has been rescheduled due to adverse weather conditions',
            'type' => 'warning',
            'is_active' => true,
            'duration_hours' => 24,
            'starts_at' => now()->subHour(),
            'expires_at' => now()->addHours(23),
        ]);

        // Expired notice
        Notice::create([
            'title' => 'Old Notice',
            'message' => 'Old expired announcement',
            'type' => 'info',
            'is_active' => true,
            'duration_hours' => 5,
            'starts_at' => now()->subDays(2),
            'expires_at' => now()->subDay(),
        ]);

        $response = $this->getJson(route('api.notice-bar.index'));
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'master_enabled' => true,
        ]);
        $response->assertJsonFragment([
            'message' => 'Flight 201 to Nauru has been rescheduled due to adverse weather conditions',
        ]);
        $response->assertJsonMissing([
            'message' => 'Old expired announcement',
        ]);
    }

    public function test_notices_api_endpoints()
    {
        $notice = Notice::create([
            'title' => 'Update and News',
            'badge_text' => 'Weather Alert',
            'message' => 'Flight 201 to Nauru has been rescheduled due to adverse weather conditions',
            'type' => 'warning',
            'is_active' => true,
            'duration_hours' => 24,
            'starts_at' => now(),
            'expires_at' => now()->addHours(24),
            'priority' => 10,
        ]);

        // GET /api/notices
        $listResponse = $this->getJson(route('api.notices.index'));
        $listResponse->assertStatus(200);
        $listResponse->assertJsonStructure([
            'success',
            'master_enabled',
            'count',
            'data',
        ]);

        // GET /api/notices/latest
        $latestResponse = $this->getJson(route('api.notices.latest'));
        $latestResponse->assertStatus(200);
        $latestResponse->assertJsonPath('data.id', $notice->id);
        $latestResponse->assertJsonPath('data.badge_text', 'Weather Alert');

        // GET /api/notices/{id}
        $singleResponse = $this->getJson(route('api.notices.show', $notice->id));
        $singleResponse->assertStatus(200);
        $singleResponse->assertJsonPath('data.message', 'Flight 201 to Nauru has been rescheduled due to adverse weather conditions');
    }
}
