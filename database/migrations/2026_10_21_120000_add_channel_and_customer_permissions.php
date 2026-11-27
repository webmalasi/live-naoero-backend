<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * Shop orders come from the website and the app alike: each records the
 * platform it was placed on. Item images are kept as paths (the host is
 * added when shown, so they work on every address). And staff permissions
 * for the customer directory and the logs.
 */
return new class extends Migration
{
    public const PERMISSIONS = [
        'customers.view' => 'View public users (customers), their bookings, orders and activity',
        'customers.manage' => 'Disable / re-enable customers and sign them out of every device',
    ];

    public function up(): void
    {
        Schema::table('shop_orders', function (Blueprint $table) {
            $table->text('channel')->default('app'); // app / website
            $table->index(['channel', 'created_at'], 'shop_orders_channel');
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("alter table shop_orders add constraint shop_orders_channel_check check (channel in ('app', 'website'))");
        }

        // https://host/assets/x.png -> assets/x.png
        foreach (DB::table('shop_order_items')->whereNotNull('image')->get(['id', 'image']) as $item) {
            $path = parse_url($item->image, PHP_URL_PATH);
            if (is_string($path) && str_starts_with($item->image, 'http')) {
                DB::table('shop_order_items')->where('id', $item->id)->update(['image' => ltrim($path, '/')]);
            }
        }

        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles')) {
            return;
        }
        $now = now();
        foreach (array_keys(self::PERMISSIONS) as $name) {
            if (! DB::table('permissions')->where('name', $name)->where('guard_name', 'web')->exists()) {
                DB::table('permissions')->insert(['name' => $name, 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now]);
            }
        }
        $grants = [
            'Super Admin' => ['customers.view', 'customers.manage', 'bookings.view', 'audit.view'],
            'Admin' => ['customers.view', 'customers.manage', 'bookings.view', 'audit.view'],
            'Staff / Support' => ['customers.view', 'bookings.view'],
            'Flight Operations Manager' => ['customers.view', 'bookings.view'],
            'Data Analyst' => ['customers.view', 'bookings.view'],
        ];
        foreach ($grants as $role => $permissions) {
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
        DB::table('permissions')->whereIn('name', array_keys(self::PERMISSIONS))->delete();
        Schema::table('shop_orders', function (Blueprint $table) {
            $table->dropIndex('shop_orders_channel');
            $table->dropColumn('channel');
        });
    }
};
