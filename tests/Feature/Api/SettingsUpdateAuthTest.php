<?php

namespace Tests\Feature\Api;

use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * POST /api/settings changes the live website (logo, header text, menu),
 * so only signed-in, active admin panel staff may use it.
 */
class SettingsUpdateAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_anonymous_requests_cannot_change_settings(): void
    {
        $this->postJson('/api/settings', ['top_header_text' => 'Hacked'])
            ->assertStatus(401)
            ->assertJson(['success' => false]);

        $this->postJson('/api/settings', ['top_header_text' => 'Hacked'], [
            'Authorization' => 'Bearer not-a-real-token',
        ])->assertStatus(401);

        $this->assertNull(SiteSetting::where('key', 'top_header_text')->value('value'));
    }

    public function test_a_deactivated_account_cannot_change_settings(): void
    {
        $user = User::factory()->create(['status' => 'inactive']);
        Cache::put('api_token:old-token', $user->id, now()->addDay());

        $this->postJson('/api/settings', ['top_header_text' => 'Hacked'], [
            'Authorization' => 'Bearer old-token',
        ])->assertStatus(401);
    }

    public function test_signed_in_staff_can_change_settings_only_with_the_settings_permission(): void
    {
        $user = User::factory()->create([
            'email' => 'staff@naoero.test',
            'password' => bcrypt('secret-pass'),
            'status' => 'active',
        ]);

        $token = $this->postJson('/api/auth/login', [
            'email' => 'staff@naoero.test',
            'password' => 'secret-pass',
        ])->assertOk()->json('token');

        // Signed in, but without settings.general: refused.
        $this->postJson('/api/settings', ['top_header_text' => 'Hacked'], [
            'Authorization' => "Bearer {$token}",
        ])->assertForbidden()->assertJson(['success' => false]);
        $this->assertNull(SiteSetting::where('key', 'top_header_text')->value('value'));

        Permission::create(['name' => 'settings.general', 'guard_name' => 'web']);
        $user->givePermissionTo('settings.general');

        $this->postJson('/api/settings', ['top_header_text' => 'Welcome aboard'], [
            'Authorization' => "Bearer {$token}",
        ])->assertOk();

        $this->assertSame(
            'Welcome aboard',
            SiteSetting::where('key', 'top_header_text')->value('value')
        );
        $this->assertNotNull($user->fresh());
    }
}
