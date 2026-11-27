<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * The System & Settings menu gets a master checkbox (system.access) and one
 * checkbox per screen. audit.view used to open the logs, Zenith events, push
 * notifications and the audit log together: whoever held it gets the four
 * new screen permissions and the master instead, so nobody loses access,
 * and audit.view goes. Every staff role (and staff account with direct
 * permissions only) keeps its own Executive Profile.
 */
return new class extends Migration
{
    private const NEW = ['system.access', 'system.logs', 'system.zenith', 'system.push', 'system.audit', 'system.profile'];

    private const FROM_AUDIT = ['system.access', 'system.logs', 'system.zenith', 'system.push', 'system.audit'];

    private const PROFILE = ['system.access', 'system.profile'];

    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles')) {
            return;
        }
        $now = now();
        foreach (self::NEW as $name) {
            if (! $this->permissionId($name)) {
                DB::table('permissions')->insert(['name' => $name, 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now]);
            }
        }

        $auditId = $this->permissionId('audit.view');
        foreach (DB::table('roles')->where('guard_name', 'web')->pluck('id') as $roleId) {
            $hadAudit = $auditId && DB::table('role_has_permissions')->where(['role_id' => $roleId, 'permission_id' => $auditId])->exists();
            foreach (array_unique([...self::PROFILE, ...($hadAudit ? self::FROM_AUDIT : [])]) as $name) {
                DB::table('role_has_permissions')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $this->permissionId($name)]);
            }
        }

        $userType = User::class;
        $withDirect = DB::table('model_has_permissions')->where('model_type', $userType)->distinct()->pluck('model_id');
        $withRole = DB::table('model_has_roles')->where('model_type', $userType)->distinct()->pluck('model_id')->all();
        foreach ($withDirect as $userId) {
            $hadAudit = $auditId && DB::table('model_has_permissions')->where(['model_type' => $userType, 'model_id' => $userId, 'permission_id' => $auditId])->exists();
            $names = [...($hadAudit ? self::FROM_AUDIT : []), ...(in_array($userId, $withRole) ? [] : self::PROFILE)];
            foreach (array_unique($names) as $name) {
                DB::table('model_has_permissions')->insertOrIgnore(['model_type' => $userType, 'model_id' => $userId, 'permission_id' => $this->permissionId($name)]);
            }
        }

        if ($auditId) {
            DB::table('role_has_permissions')->where('permission_id', $auditId)->delete();
            DB::table('model_has_permissions')->where('permission_id', $auditId)->delete();
            DB::table('permissions')->where('id', $auditId)->delete();
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }
        $now = now();
        if (! $this->permissionId('audit.view')) {
            DB::table('permissions')->insert(['name' => 'audit.view', 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now]);
        }
        $auditId = $this->permissionId('audit.view');
        $logsId = $this->permissionId('system.logs');
        if ($logsId) {
            foreach (DB::table('role_has_permissions')->where('permission_id', $logsId)->pluck('role_id') as $roleId) {
                DB::table('role_has_permissions')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $auditId]);
            }
            foreach (DB::table('model_has_permissions')->where('permission_id', $logsId)->get() as $row) {
                DB::table('model_has_permissions')->insertOrIgnore(['model_type' => $row->model_type, 'model_id' => $row->model_id, 'permission_id' => $auditId]);
            }
        }
        DB::table('permissions')->whereIn('name', self::NEW)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function permissionId(string $name): ?int
    {
        return DB::table('permissions')->where('name', $name)->where('guard_name', 'web')->value('id');
    }
};
