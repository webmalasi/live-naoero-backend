<?php

namespace Tests\Feature\Admin;

use App\Http\Middleware\EnforceAdminPermission;
use App\Models\User;
use App\Services\Auth\AdminAccess;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Every admin screen needs its permission: on the server (refused with 403
 * otherwise) and in the sidebar (hidden otherwise). Ticking a permission
 * opens the screen; unticking it closes it again.
 */
class AdminPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function staff(array $permissions = [], array $roles = []): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->givePermissionTo($permissions);
        $user->assignRole($roles);

        return $user;
    }

    /** @return array<string, \Illuminate\Routing\Route> admin GET routes without parameters */
    private function listScreens(): array
    {
        $screens = [];
        foreach (Route::getRoutes() as $route) {
            $name = (string) $route->getName();
            if (str_starts_with($name, 'admin.') && in_array('GET', $route->methods(), true) && $route->parameterNames() === []
                && ! AdminAccess::isOpen($name) && ! str_ends_with($name, '.export')) {
                $screens[$name] = $route;
            }
        }
        ksort($screens);

        return $screens;
    }

    public function test_every_admin_route_has_a_rule_and_every_rule_names_real_permissions(): void
    {
        $unmapped = [];
        foreach (Route::getRoutes() as $route) {
            $name = (string) $route->getName();
            if (! str_starts_with($name, 'admin.')) {
                continue;
            }
            $this->assertContains(EnforceAdminPermission::class, $route->gatherMiddleware(), "{$name} is not guarded");
            foreach ($route->methods() as $method) {
                if (AdminAccess::required($name, $method) === null) {
                    $unmapped[] = "{$method} {$name}";
                }
            }
        }
        $this->assertSame([], $unmapped, 'Admin routes without a permission rule');

        $known = array_keys(AdminAccess::permissionLabels());
        foreach (AdminAccess::RULES as $pattern => [$view, $manage]) {
            foreach ([...$view, ...$manage] as $permission) {
                $this->assertContains($permission, $known, "{$pattern} uses an unknown permission");
            }
        }
        // The seeder creates them all.
        $this->assertSame([], array_values(array_diff($known, Permission::pluck('name')->all())));
    }

    public function test_an_account_without_permissions_only_reaches_the_dashboard(): void
    {
        $nobody = $this->staff();
        $this->actingAs($nobody)->get(route('admin.dashboard'))->assertOk()
            ->assertDontSee('tracking-wide">System & Settings<', false)->assertDontSee('tracking-wide">Access & Users<', false)
            ->assertDontSee('Dynamic Dropdowns')->assertDontSee('Cache & Optimizer', false)
            ->assertDontSee('tracking-wide">Shop & Gift Cards<', false)->assertDontSee('tracking-wide">Website &amp; App Pages<', false)
            ->assertDontSee('Inquiries Inbox')->assertDontSee('Audit Trail Stream');
        $this->actingAs($nobody)->get(route('admin.dashboard'))->assertDontSee('Executive Profile')->assertDontSee('Account Profile');

        foreach ($this->listScreens() as $name => $route) {
            $this->actingAs($nobody)->get(route($name))->assertForbidden();
        }
        // Changes too.
        $this->actingAs($nobody)->post(route('admin.settings.update'), ['site_name' => 'x'])->assertForbidden();
        $this->actingAs($nobody)->post(route('admin.system.cache.flush'))->assertForbidden();
        $this->actingAs($nobody)->post(route('admin.dropdowns.store'), [])->assertForbidden();
        $this->actingAs($nobody)->delete(route('admin.activity-logs.clear'))->assertForbidden();
    }

    /** Each screen opens with its view permission alone, and closes again when it is taken away. */
    public function test_each_screen_opens_with_its_permission_and_closes_without_it(): void
    {
        foreach ($this->listScreens() as $name => $route) {
            $needed = AdminAccess::required($name, 'GET');
            $this->assertNotEmpty($needed, $name);
            $master = AdminAccess::sectionMaster($name);
            $user = $this->staff(array_filter([$needed[0], $master]));

            $response = $this->actingAs($user)->get(route($name));
            $this->assertNotSame(403, $response->status(), "{$name} refused with {$needed[0]}");
            $this->assertLessThan(500, $response->status(), "{$name} failed with {$needed[0]}");

            if ($master !== null) {
                // The section master alone closes it, whatever else is ticked.
                $user->revokePermissionTo($master);
                $this->actingAs($user->fresh())->get(route($name))->assertForbidden();
                $user->givePermissionTo($master);
            }
            $user->revokePermissionTo($needed[0]);
            $this->actingAs($user->fresh())->get(route($name))->assertForbidden();
        }
    }

    public function test_a_view_permission_does_not_allow_changes(): void
    {
        $viewer = $this->staff(['content.view', 'system.access', 'dropdowns.view', 'locations.view', 'inquiries.view', 'careers.view']);
        $this->actingAs($viewer)->get(route('admin.posts.index'))->assertOk();
        $this->actingAs($viewer)->get(route('admin.posts.create'))->assertForbidden();
        $this->actingAs($viewer)->post(route('admin.posts.store'), [])->assertForbidden();
        $this->actingAs($viewer)->get(route('admin.dropdowns.index'))->assertOk();
        $this->actingAs($viewer)->post(route('admin.dropdowns.store'), [])->assertForbidden();
        $this->actingAs($viewer)->post(route('admin.locations.countries.store'), [])->assertForbidden();
        $this->actingAs($viewer)->get(route('admin.careers.create'))->assertForbidden();

        $editor = $this->staff(['content.manage']);
        $this->actingAs($editor)->get(route('admin.posts.create'))->assertOk();
        $this->actingAs($editor)->post(route('admin.posts.store'), [])->assertSessionHasErrors();
    }

    /** The case reported: each System & Settings tab has its own checkbox, and the master hides the whole menu. */
    public function test_each_system_tab_has_its_own_checkbox_and_the_master_hides_the_whole_menu(): void
    {
        $tabs = [
            'system.logs' => ['API, App & Login Logs', 'admin.system-logs.index'],
            'system.zenith' => ['Zenith Flight Events', 'admin.zenith-events.index'],
            'system.push' => ['Push Notifications', 'admin.push.index'],
            'system.audit' => ['Audit Activity Logs', 'admin.activity-logs.index'],
            'system.profile' => ['Executive Profile', 'admin.profile.edit'],
        ];
        $role = Role::create(['name' => 'Admin Naoero Airline', 'guard_name' => 'web']);
        $role->syncPermissions(['system.access', ...array_keys($tabs), 'users.view', 'content.view', 'bookings.view']);
        $user = $this->staff(roles: ['Admin Naoero Airline']);

        $page = $this->actingAs($user)->get(route('admin.dashboard'))->assertOk()->assertSee('tracking-wide">System & Settings<', false)
            ->assertDontSee('Dynamic Dropdowns')->assertDontSee('Site & Emergency Notice', false)->assertDontSee('Cache & Optimizer', false);
        foreach ($tabs as [$label, $route]) {
            $page->assertSee($label, false);
            $this->actingAs($user)->get(route($route))->assertOk();
        }
        $this->actingAs($user)->get(route('admin.settings.index'))->assertForbidden();

        // Unticking one tab hides and closes only that tab.
        foreach ($tabs as $permission => [$label, $route]) {
            $role->revokePermissionTo($permission);
            $page = $this->actingAs($user->fresh())->get(route('admin.dashboard'))->assertDontSee('<span>'.$label.'</span>', false);
            foreach (array_diff_key($tabs, [$permission => 1]) as [$otherLabel]) {
                $page->assertSee('<span>'.$otherLabel.'</span>', false);
            }
            $this->actingAs($user->fresh())->get(route($route))->assertForbidden();
            $role->givePermissionTo($permission);
        }

        // Settings ticked: it appears; unticked: gone again.
        $role->givePermissionTo('settings.general');
        $this->actingAs($user->fresh())->get(route('admin.settings.index'))->assertOk();
        $role->revokePermissionTo('settings.general');
        $this->actingAs($user->fresh())->get(route('admin.settings.index'))->assertForbidden();

        // The master unticked: the whole menu is gone and every screen in it refused, even with everything else ticked.
        $role->givePermissionTo(['settings.general', 'settings.api', 'dropdowns.view']);
        $role->revokePermissionTo('system.access');
        $page = $this->actingAs($user->fresh())->get(route('admin.dashboard'))->assertOk()
            ->assertDontSee('tracking-wide">System & Settings<', false)->assertDontSee('Account Profile')->assertDontSee('System & Cache', false);
        foreach ([...array_column($tabs, 1), 'admin.settings.index', 'admin.system.index', 'admin.dropdowns.index'] as $route) {
            $this->actingAs($user->fresh())->get(route($route))->assertForbidden();
        }
        // The rest of the panel is untouched.
        $this->actingAs($user->fresh())->get(route('admin.posts.index'))->assertOk();
    }

    /** One admin can be limited without touching the role every other admin shares. */
    public function test_a_permission_can_be_blocked_for_one_admin_only(): void
    {
        $role = Role::create(['name' => 'Admin Naoero Airline', 'guard_name' => 'web']);
        $role->syncPermissions(['system.access', 'system.logs', 'system.zenith', 'system.profile', 'content.view']);
        $limited = $this->staff(roles: ['Admin Naoero Airline']);
        $colleague = $this->staff(roles: ['Admin Naoero Airline']);
        $owner = $this->staff(roles: ['Super Admin']);

        $this->actingAs($owner)->get(route('admin.users.edit', $limited))->assertOk()
            ->assertSee('Permissions for this account only')->assertSee('name="access[system.access]"', false);

        // Block the whole System & Settings menu and allow one extra permission, for this admin only.
        $form = ['name' => $limited->name, 'email' => $limited->email, 'status' => 'active', 'roles' => ['Admin Naoero Airline'],
            'access' => ['system.access' => 'block', 'system.logs' => 'role', 'careers.view' => 'allow']];
        $this->actingAs($owner)->put(route('admin.users.update', $limited), $form)->assertSessionMissing('error');

        $limited = $limited->fresh();
        $this->assertSame(['system.access'], $limited->blockedPermissionNames());
        $this->assertFalse($limited->can('system.access'));
        $this->assertTrue($limited->can('careers.view'));
        $this->assertNotContains('system.access', $limited->getAllPermissions()->pluck('name')->all());
        $this->actingAs($limited)->get(route('admin.dashboard'))->assertDontSee('tracking-wide">System & Settings<', false);
        $this->actingAs($limited)->get(route('admin.system-logs.index'))->assertForbidden();
        $this->actingAs($limited)->get(route('admin.careers.index'))->assertOk();

        // The colleague with the same role is untouched, and so is the role.
        $this->actingAs($colleague)->get(route('admin.dashboard'))->assertSee('tracking-wide">System & Settings<', false);
        $this->actingAs($colleague)->get(route('admin.system-logs.index'))->assertOk();
        $this->assertTrue($role->fresh()->hasPermissionTo('system.access'));
        $this->actingAs($owner)->get(route('admin.users.show', $limited))->assertOk()->assertSee('Blocked for this account');

        // Back to the role's default: open again.
        $form['access']['system.access'] = 'role';
        $this->actingAs($owner)->put(route('admin.users.update', $limited), $form)->assertSessionMissing('error');
        $this->actingAs($limited->fresh())->get(route('admin.system-logs.index'))->assertOk();

        // A Super Admin is never limited by blocks.
        $owner->syncBlockedPermissions(['system.access']);
        $this->actingAs($owner->fresh())->get(route('admin.system-logs.index'))->assertOk();
    }

    public function test_blocks_follow_the_same_escalation_rules(): void
    {
        $manager = $this->staff(['users.view', 'users.edit', 'content.view']);
        $target = $this->staff(['settings.api', 'content.view']);
        $form = fn (array $access) => ['name' => $target->name, 'email' => $target->email, 'status' => 'active', 'access' => $access];

        // Blocking (or unblocking) a permission they don't hold is refused; one they hold is fine.
        $this->actingAs($manager)->put(route('admin.users.update', $target), $form(['settings.api' => 'block']))->assertSessionHas('error');
        $this->assertSame([], $target->fresh()->blockedPermissionNames());
        $this->actingAs($manager)->put(route('admin.users.update', $target), $form(['settings.api' => 'allow', 'content.view' => 'block']))->assertSessionMissing('error');
        $this->assertSame(['content.view'], $target->fresh()->blockedPermissionNames());

        // Nobody can block their own account.
        $this->actingAs($manager)->put(route('admin.users.update', $manager), ['name' => $manager->name, 'email' => $manager->email, 'status' => 'active',
            'access' => ['users.view' => 'allow', 'users.edit' => 'allow', 'content.view' => 'block']])->assertSessionHas('error');
        $this->assertTrue($manager->fresh()->can('content.view'));
    }

    public function test_the_super_admin_role_has_every_permission(): void
    {
        $role = Role::findByName('Super Admin');
        $role->syncPermissions([]);
        $admin = $this->staff(roles: ['Super Admin']);

        foreach (array_keys($this->listScreens()) as $name) {
            $this->assertTrue(AdminAccess::allows($admin, $name), $name);
        }
        $this->actingAs($admin)->get(route('admin.system.index'))->assertOk();
    }

    public function test_staff_cannot_give_access_they_do_not_hold(): void
    {
        $manager = $this->staff(['users.view', 'users.create', 'users.edit', 'content.view']);
        $target = $this->staff();
        $form = fn (array $extra) => ['name' => $target->name, 'email' => $target->email, 'status' => 'active', ...$extra];

        // Super Admin, a role with more than they hold, a permission they lack: all refused.
        $this->actingAs($manager)->put(route('admin.users.update', $target), $form(['roles' => ['Super Admin']]))->assertSessionHas('error');
        $this->actingAs($manager)->put(route('admin.users.update', $target), $form(['roles' => ['Admin']]))->assertSessionHas('error');
        $this->actingAs($manager)->put(route('admin.users.update', $target), $form(['permissions' => ['settings.api']]))->assertSessionHas('error');
        $this->actingAs($manager)->put(route('admin.users.update', $manager), ['name' => $manager->name, 'email' => $manager->email, 'status' => 'active', 'permissions' => ['users.view', 'users.create', 'users.edit', 'content.view', 'settings.api']])->assertSessionHas('error');
        $this->assertFalse($target->fresh()->hasRole('Super Admin'));
        $this->assertFalse($manager->fresh()->can('settings.api'));

        // A permission they hold: allowed.
        $this->actingAs($manager)->put(route('admin.users.update', $target), $form(['permissions' => ['content.view']]))->assertSessionHasNoErrors()->assertSessionMissing('error');
        $this->assertTrue($target->fresh()->can('content.view'));

        // A Super Admin account can't be changed, disabled or deleted by them.
        $boss = $this->staff(roles: ['Super Admin']);
        $this->actingAs($manager)->put(route('admin.users.update', $boss), ['name' => 'X', 'email' => $boss->email, 'status' => 'inactive', 'roles' => ['Super Admin']])->assertSessionHas('error');
        $this->assertSame('active', $boss->fresh()->status);

        // Roles: no permission they lack, no Super Admin role edits, not through the matrix either.
        $roleEditor = $this->staff(['roles.view', 'roles.edit', 'content.view']);
        $staffRole = Role::findByName('Staff / Support');
        $before = $staffRole->permissions->pluck('name')->sort()->values()->all();
        $this->actingAs($roleEditor)->put(route('admin.roles.update', $staffRole), ['name' => 'Staff / Support', 'permissions' => [...$before, 'settings.api']])->assertSessionHas('error');
        $this->actingAs($roleEditor)->post(route('admin.roles.matrix.update'), ['matrix' => [$staffRole->id => [...$before, 'settings.api']]])->assertSessionHas('error');
        $this->assertSame($before, $staffRole->fresh()->permissions->pluck('name')->sort()->values()->all());

        // Built-in permissions can't be renamed or deleted.
        $owner = $this->staff(roles: ['Super Admin']);
        $perm = Permission::findByName('settings.general');
        $this->actingAs($owner)->put(route('admin.permissions.update', $perm), ['name' => 'settings.renamed'])->assertSessionHas('error');
        $this->actingAs($owner)->delete(route('admin.permissions.destroy', $perm))->assertSessionHas('error');
        $this->assertNotNull(Permission::where('name', 'settings.general')->first());
    }
}
