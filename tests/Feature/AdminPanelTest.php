<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/admin/login');
        $response->assertStatus(200);
        $response->assertSee('Admin sign in');
        $response->assertSee('Naoero Airlines');
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@naoero-airlines.com',
            'status' => 'active',
        ]);
        $user->assignRole('Super Admin');

        $response = $this->post('/admin/login', [
            'email' => 'admin@naoero-airlines.com',
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('admin.dashboard'));
    }

    public function test_admin_addresses_lead_to_the_sign_in_page_and_dashboard(): void
    {
        // The earlier /login address and /admin send guests to /admin/login.
        $this->get('/login')->assertStatus(301)->assertRedirect('/admin/login');
        $this->get('/admin')->assertRedirect(route('admin.dashboard'));
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
        $this->assertSame(url('/admin/login'), route('login'));

        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('Super Admin');
        $this->actingAs($user)->get('/admin')->assertRedirect(route('admin.dashboard'));
        $this->actingAs($user)->post('/admin/logout')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_admin_dashboard_can_be_rendered_for_authenticated_users(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('Super Admin');

        $response = $this->actingAs($user)->get(route('admin.dashboard'));
        $response->assertStatus(200);
        $response->assertSee('Executive Flight Operations Dashboard');
    }

    public function test_user_management_index_renders(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('Super Admin');

        $response = $this->actingAs($user)->get(route('admin.users.index'));
        $response->assertStatus(200);
        $response->assertSee('Admin Users &amp; Staff', false);
    }

    public function test_roles_management_and_matrix_renders(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('Super Admin');

        $response = $this->actingAs($user)->get(route('admin.roles.index'));
        $response->assertStatus(200);
        $response->assertSee('Roles');

        $matrixResponse = $this->actingAs($user)->get(route('admin.roles.matrix'));
        $matrixResponse->assertStatus(200);
        $matrixResponse->assertSee('Interactive Access Matrix');
    }

    public function test_permissions_management_renders(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('Super Admin');

        $response = $this->actingAs($user)->get(route('admin.permissions.index'));
        $response->assertStatus(200);
        $response->assertSee('Permissions Directory');
    }

    public function test_system_and_redis_page_renders(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('Super Admin');

        $response = $this->actingAs($user)->get(route('admin.system.index'));
        $response->assertStatus(200);
        $response->assertSee('PostgreSQL');
        $response->assertSee('Redis In-Memory Engine');
    }
}
