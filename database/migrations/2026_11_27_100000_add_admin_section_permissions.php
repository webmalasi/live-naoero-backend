<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * Every admin section now needs a permission (AdminAccess). Sections that
 * were open to every staff account get permissions of their own, given to
 * the roles that used them so nobody loses what their job needs. The System
 * & Settings screens (dropdowns, site settings, cache) are not given to
 * roles without settings: an account sees them only when granted.
 */
return new class extends Migration
{
    public const NEW = [
        'content.view', 'content.manage', 'notices.manage',
        'locations.view', 'locations.manage',
        'shop.products.view', 'shop.products.manage',
        'inquiries.view', 'inquiries.manage',
        'newsletter.view', 'newsletter.manage',
        'careers.view', 'careers.manage',
        'dropdowns.view', 'dropdowns.manage',
    ];

    private const EVERYDAY = [
        'content.view', 'content.manage', 'notices.manage', 'locations.view', 'locations.manage',
        'shop.products.view', 'shop.products.manage', 'inquiries.view', 'inquiries.manage',
        'newsletter.view', 'newsletter.manage', 'careers.view', 'careers.manage',
    ];

    /** Role => the new permissions it gets. */
    private function grants(): array
    {
        return [
            'Super Admin' => self::NEW,
            // The roles that run the panel day to day keep every section they used.
            'Admin' => [...self::EVERYDAY, 'dropdowns.view', 'dropdowns.manage'],
            'Admin Naoero Airline' => self::EVERYDAY,
            'Flight Operations Manager' => ['content.view', 'locations.view', 'locations.manage'],
            'Data Analyst' => ['content.view', 'locations.view', 'shop.products.view', 'inquiries.view', 'newsletter.view', 'careers.view'],
            'Staff / Support' => ['content.view', 'locations.view', 'shop.products.view', 'inquiries.view', 'inquiries.manage', 'newsletter.view'],
        ];
    }

    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles')) {
            return;
        }
        $now = now();
        foreach (self::NEW as $name) {
            if (! DB::table('permissions')->where('name', $name)->where('guard_name', 'web')->exists()) {
                DB::table('permissions')->insert(['name' => $name, 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now]);
            }
        }
        foreach ($this->grants() as $role => $permissions) {
            $roleId = DB::table('roles')->where('name', $role)->where('guard_name', 'web')->value('id');
            foreach ($roleId ? $permissions : [] as $name) {
                $permissionId = DB::table('permissions')->where('name', $name)->where('guard_name', 'web')->value('id');
                if ($permissionId && ! DB::table('role_has_permissions')->where(['permission_id' => $permissionId, 'role_id' => $roleId])->exists()) {
                    DB::table('role_has_permissions')->insert(['permission_id' => $permissionId, 'role_id' => $roleId]);
                }
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        if (Schema::hasTable('permissions')) {
            DB::table('permissions')->whereIn('name', self::NEW)->delete();
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }
};
