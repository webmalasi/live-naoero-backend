<?php

namespace Database\Seeders;

use App\Services\Auth\AdminAccess;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Every permission the admin panel uses (AdminAccess guards each screen with them).
        $permissionsByModule = AdminAccess::PERMISSIONS;

        // Create all permissions
        foreach ($permissionsByModule as $module => $permissions) {
            foreach ($permissions as $name => $description) {
                Permission::firstOrCreate(
                    ['name' => $name, 'guard_name' => 'web'],
                    ['guard_name' => 'web']
                );
            }
        }

        // Create Roles and assign permissions
        $superAdminRole = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $superAdminRole->syncPermissions(Permission::all());

        $everyday = [
            'content.view', 'content.manage', 'notices.manage', 'locations.view', 'locations.manage',
            'shop.products.view', 'shop.products.manage', 'inquiries.view', 'inquiries.manage',
            'newsletter.view', 'newsletter.manage', 'careers.view', 'careers.manage',
        ];
        // The System & Settings menu: the master, the logs screens, and everyone's own profile.
        $systemLogs = ['system.access', 'system.logs', 'system.zenith', 'system.push', 'system.audit', 'system.profile'];
        $ownProfile = ['system.access', 'system.profile'];
        $shop = [
            'shop.sales.view', 'shop.sales.manage', 'shop.sales.refund', 'shop.sales.export',
            'shop.inventory.view', 'shop.inventory.manage', 'shop.inventory.purchasing', 'shop.inventory.count', 'shop.inventory.export',
        ];

        $adminRole = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        $adminRole->syncPermissions([
            'users.view', 'users.create', 'users.edit', 'users.status',
            'roles.view', 'permissions.view',
            'flights.view', 'flights.manage', 'fleet.manage', 'routes.manage', 'bookings.view', 'bookings.manage',
            'customers.view', 'customers.manage',
            'analytics.view', 'reports.export', ...$systemLogs,
            'settings.general', 'dropdowns.view', 'dropdowns.manage',
            ...$everyday, ...$shop,
        ]);

        $opsManagerRole = Role::firstOrCreate(['name' => 'Flight Operations Manager', 'guard_name' => 'web']);
        $opsManagerRole->syncPermissions([
            'flights.view', 'flights.manage', 'fleet.manage', 'routes.manage',
            'bookings.view', 'bookings.manage', 'customers.view',
            'analytics.view', 'reports.export',
            'content.view', 'locations.view', 'locations.manage', ...$ownProfile,
        ]);

        $analystRole = Role::firstOrCreate(['name' => 'Data Analyst', 'guard_name' => 'web']);
        $analystRole->syncPermissions([
            'flights.view', 'bookings.view', 'customers.view',
            'analytics.view', 'reports.export', ...$systemLogs,
            'shop.sales.view', 'shop.sales.export', 'shop.inventory.view', 'shop.inventory.export',
            'content.view', 'locations.view', 'shop.products.view', 'inquiries.view', 'newsletter.view', 'careers.view',
        ]);

        $staffRole = Role::firstOrCreate(['name' => 'Staff / Support', 'guard_name' => 'web']);
        $staffRole->syncPermissions([
            'users.view',
            'flights.view',
            'bookings.view', 'bookings.manage', 'customers.view',
            'shop.sales.view', 'shop.sales.manage', 'shop.inventory.view', 'shop.inventory.count',
            'content.view', 'locations.view', 'shop.products.view', 'inquiries.view', 'inquiries.manage', 'newsletter.view', ...$ownProfile,
        ]);
    }
}
