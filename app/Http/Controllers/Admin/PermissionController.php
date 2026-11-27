<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Services\Auth\AdminAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionController extends Controller
{
    /**
     * Display a listing of permissions.
     */
    public function index(): View
    {
        $permissions = Permission::with('roles')->orderBy('name')->get();
        $groupedPermissions = $permissions->groupBy(function ($perm) {
            $parts = explode('.', $perm->name);

            return ucfirst($parts[0] ?? 'General');
        });

        $roles = Role::orderBy('name')->get();

        return view('admin.permissions.index', compact('groupedPermissions', 'permissions', 'roles'));
    }

    /**
     * Store a newly created permission in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'module' => ['required', 'string', 'max:50'],
            'action' => ['required', 'string', 'max:50'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['exists:roles,name'],
        ]);

        $name = strtolower(trim($validated['module'])).'.'.strtolower(trim($validated['action']));

        if (Permission::where('name', $name)->exists()) {
            return back()->with('error', "Permission '{$name}' already exists!");
        }

        $permission = Permission::create([
            'name' => $name,
            'guard_name' => 'web',
        ]);

        if (! empty($validated['roles'])) {
            $permission->syncRoles($validated['roles']);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        ActivityLog::log('create', 'Permissions', "Created permission: {$permission->name}");

        return redirect()->route('admin.permissions.index')
            ->with('success', "Permission '{$permission->name}' created successfully!");
    }

    /**
     * Update the specified permission in storage.
     */
    public function update(Request $request, Permission $permission): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:permissions,name,'.$permission->id],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['exists:roles,name'],
        ]);

        // The panel's own permissions guard its screens: renaming one would lock everyone out of it.
        if ($validated['name'] !== $permission->name && array_key_exists($permission->name, AdminAccess::permissionLabels())) {
            return back()->with('error', "'{$permission->name}' is a built-in permission and cannot be renamed.");
        }
        // Giving a permission to roles (or taking it away) needs that permission yourself.
        $rolesBefore = $permission->roles->pluck('name')->all();
        if (array_diff($validated['roles'] ?? [], $rolesBefore) !== [] || array_diff($rolesBefore, $validated['roles'] ?? []) !== []) {
            if (! AdminAccess::canGrantPermission($request->user(), $permission->name)) {
                return back()->with('error', "You can only assign '{$permission->name}' to roles if you hold it yourself.");
            }
        }

        $permission->update(['name' => $validated['name']]);

        if (isset($validated['roles'])) {
            $permission->syncRoles($validated['roles']);
        } else {
            $permission->syncRoles([]);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        ActivityLog::log('update', 'Permissions', "Updated permission: {$permission->name}");

        return redirect()->route('admin.permissions.index')
            ->with('success', "Permission '{$permission->name}' updated successfully!");
    }

    /**
     * Remove the specified permission from storage.
     */
    public function destroy(Permission $permission): RedirectResponse
    {
        if (array_key_exists($permission->name, AdminAccess::permissionLabels())) {
            return back()->with('error', "'{$permission->name}' is a built-in permission and cannot be deleted.");
        }
        $permName = $permission->name;
        $permission->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        ActivityLog::log('delete', 'Permissions', "Deleted permission: {$permName}");

        return redirect()->route('admin.permissions.index')
            ->with('success', "Permission '{$permName}' removed successfully.");
    }
}
