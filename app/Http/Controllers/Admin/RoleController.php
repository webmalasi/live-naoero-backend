<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Services\Auth\AdminAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleController extends Controller
{
    /**
     * Display a listing of roles.
     */
    public function index(): View
    {
        $roles = Role::withCount(['users', 'permissions'])
            ->with(['permissions' => function ($q) {
                $q->take(8);
            }])
            ->orderBy('id')
            ->get();

        $totalRoles = $roles->count();
        $totalPermissions = Permission::count();

        return view('admin.roles.index', compact('roles', 'totalRoles', 'totalPermissions'));
    }

    /**
     * Show the form for creating a new role.
     */
    public function create(): View
    {
        $permissions = Permission::all()->groupBy(function ($perm) {
            $parts = explode('.', $perm->name);

            return ucfirst($parts[0] ?? 'General');
        });

        return view('admin.roles.create', compact('permissions'));
    }

    /**
     * Store a newly created role in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['exists:permissions,name'],
        ]);

        if ($refused = AdminAccess::refusedPermissionChanges($request->user(), [], $validated['permissions'] ?? [])) {
            return back()->withInput()->with('error', 'You can only give access you hold yourself. Not allowed: '.implode(', ', $refused).'.');
        }

        $role = Role::create([
            'name' => $validated['name'],
            'guard_name' => 'web',
        ]);

        if (! empty($validated['permissions'])) {
            $role->syncPermissions($validated['permissions']);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        ActivityLog::log('create', 'Roles', "Created role: {$role->name} with ".count($validated['permissions'] ?? []).' permissions.');

        return redirect()->route('admin.roles.index')
            ->with('success', "Role '{$role->name}' created successfully!");
    }

    /**
     * Display the specified role.
     */
    public function show(Role $role): View
    {
        $role->load(['permissions', 'users']);
        $allPermissions = Permission::all()->groupBy(function ($perm) {
            $parts = explode('.', $perm->name);

            return ucfirst($parts[0] ?? 'General');
        });

        return view('admin.roles.show', compact('role', 'allPermissions'));
    }

    /**
     * Show the form for editing the specified role.
     */
    public function edit(Role $role): View
    {
        $permissions = Permission::all()->groupBy(function ($perm) {
            $parts = explode('.', $perm->name);

            return ucfirst($parts[0] ?? 'General');
        });

        $rolePermissions = $role->permissions->pluck('name')->toArray();

        return view('admin.roles.edit', compact('role', 'permissions', 'rolePermissions'));
    }

    /**
     * Update the specified role in storage.
     */
    public function update(Request $request, Role $role): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name,'.$role->id],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['exists:permissions,name'],
        ]);

        // Prevent renaming Super Admin
        if ($role->name === 'Super Admin' && $validated['name'] !== 'Super Admin') {
            return back()->with('error', 'The system default Super Admin role name cannot be changed.');
        }
        if ($role->name === AdminAccess::SUPER_ADMIN && ! AdminAccess::isSuperAdmin($request->user())) {
            return back()->with('error', 'Only a Super Admin can change the Super Admin role.');
        }
        if ($refused = AdminAccess::refusedPermissionChanges($request->user(), $role->permissions->pluck('name')->all(), $validated['permissions'] ?? [])) {
            return back()->withInput()->with('error', 'You can only give or remove access you hold yourself. Not allowed: '.implode(', ', $refused).'.');
        }

        $role->update(['name' => $validated['name']]);

        if (isset($validated['permissions'])) {
            $role->syncPermissions($validated['permissions']);
        } else {
            $role->syncPermissions([]);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        ActivityLog::log('update', 'Roles', "Updated role: {$role->name} permissions.");

        return redirect()->route('admin.roles.index')
            ->with('success', "Role '{$role->name}' updated successfully!");
    }

    /**
     * Remove the specified role from storage.
     */
    public function destroy(Role $role): RedirectResponse
    {
        if ($role->name === 'Super Admin') {
            return back()->with('error', 'The Super Admin role cannot be deleted.');
        }

        if ($role->users()->count() > 0) {
            return back()->with('error', "Cannot delete role '{$role->name}' because it is assigned to {$role->users()->count()} user(s). Reassign them first.");
        }

        $roleName = $role->name;
        $role->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        ActivityLog::log('delete', 'Roles', "Deleted role: {$roleName}");

        return redirect()->route('admin.roles.index')
            ->with('success', "Role '{$roleName}' has been removed.");
    }

    /**
     * Show interactive Role-Permission Matrix.
     */
    public function matrix(): View
    {
        $roles = Role::with('permissions')->orderBy('id')->get();
        $permissions = Permission::all()->groupBy(function ($perm) {
            $parts = explode('.', $perm->name);

            return ucfirst($parts[0] ?? 'General');
        });

        return view('admin.roles.matrix', compact('roles', 'permissions'));
    }

    /**
     * Batch update role permissions from Matrix.
     */
    public function updateMatrix(Request $request): RedirectResponse|JsonResponse
    {
        $matrix = $request->input('matrix', []); // array of role_id => [permission_names]

        $roles = Role::with('permissions')->get();
        $known = Permission::pluck('name')->all();
        $changes = [];
        $refused = [];
        foreach ($roles as $role) {
            if ($role->name === 'Super Admin') {
                continue;
            }
            $permList = array_values(array_intersect(array_map('strval', (array) ($matrix[$role->id] ?? [])), $known));
            $changes[] = [$role, $permList];
            array_push($refused, ...AdminAccess::refusedPermissionChanges($request->user(), $role->permissions->pluck('name')->all(), $permList));
        }
        if ($refused !== []) {
            $message = 'You can only give or remove access you hold yourself. Not allowed: '.implode(', ', array_unique($refused)).'.';

            return $request->wantsJson() ? response()->json(['success' => false, 'message' => $message], 403) : back()->with('error', $message);
        }
        foreach ($roles as $role) {
            if ($role->name === 'Super Admin') {
                $role->syncPermissions(Permission::all());
            }
        }
        foreach ($changes as [$role, $permList]) {
            $role->syncPermissions($permList);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        ActivityLog::log('matrix_update', 'Roles', 'Updated global Role & Permission access matrix.');

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Permission matrix updated successfully.']);
        }

        return back()->with('success', 'Role & Permission access matrix updated successfully!');
    }
}
