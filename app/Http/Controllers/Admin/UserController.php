<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Services\Auth\AdminAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserController extends Controller
{
    /**
     * Display a listing of users.
     */
    public function index(Request $request): View
    {
        $query = User::with(['roles', 'permissions', 'blockedPermissions']);

        // Search Filter
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('job_title', 'like', "%{$search}%")
                    ->orWhere('department', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Role Filter
        if ($role = $request->input('role')) {
            $query->whereHas('roles', function ($q) use ($role) {
                $q->where('name', $role);
            });
        }

        // Status Filter
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Department Filter
        if ($department = $request->input('department')) {
            $query->where('department', $department);
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $allowedSorts = ['name', 'email', 'status', 'created_at', 'last_login_at', 'department'];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder === 'asc' ? 'asc' : 'desc');
        } else {
            $query->latest();
        }

        $users = $query->paginate(10)->withQueryString();
        $roles = Role::orderBy('name')->get();
        $departments = User::whereNotNull('department')->distinct()->pluck('department');

        $metrics = [
            'total' => User::count(),
            'active' => User::where('status', 'active')->count(),
            'inactive' => User::where('status', 'inactive')->count(),
            'admins' => User::role(['Super Admin', 'Admin'])->count(),
        ];

        return view('admin.users.index', compact('users', 'roles', 'departments', 'metrics'));
    }

    /**
     * Show the form for creating a new user.
     */
    public function create(): View
    {
        $roles = Role::with('permissions')->orderBy('name')->get();
        $permissions = Permission::all()->groupBy(function ($perm) {
            $parts = explode('.', $perm->name);

            return ucfirst($parts[0] ?? 'General');
        });

        return view('admin.users.create', compact('roles', 'permissions'));
    }

    /**
     * Store a newly created user in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::defaults()],
            'phone' => ['nullable', 'string', 'max:50'],
            'job_title' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:100'],
            'status' => ['required', 'in:active,inactive,suspended'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp,svg', 'max:2048'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['exists:roles,name'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['exists:permissions,name'],
            'blocked' => ['nullable', 'array'],
            'blocked.*' => ['exists:permissions,name'],
            'access' => ['nullable', 'array'],
            'access.*' => ['in:role,allow,block'],
        ]);

        [$direct, $blocked] = $this->requestedAccess($request, $validated, []);
        if ($refused = $this->refusedAccess($request->user(), [], $validated['roles'] ?? [], [], $direct, [], $blocked)) {
            return $refused;
        }

        $avatarPath = null;
        if ($request->hasFile('avatar')) {
            $avatarPath = $request->file('avatar')->store('avatars', 'public');
        }

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'phone' => $validated['phone'] ?? null,
            'job_title' => $validated['job_title'] ?? null,
            'department' => $validated['department'] ?? null,
            'status' => $validated['status'],
            'bio' => $validated['bio'] ?? null,
            'avatar' => $avatarPath,
            'email_verified_at' => now(),
        ]);

        // Assign Roles
        if (! empty($validated['roles'])) {
            $user->syncRoles($validated['roles']);
        }

        // Assign Direct Permissions, and those blocked for this account only
        if ($direct !== []) {
            $user->syncPermissions($direct);
        }
        if ($blocked !== []) {
            $user->syncBlockedPermissions($blocked);
        }

        ActivityLog::log('create', 'Users', "Created user account: {$user->name} ({$user->email}) with role: ".($user->roles->pluck('name')->join(', ') ?: 'None'));

        return redirect()->route('admin.users.index')
            ->with('success', "User '{$user->name}' has been created successfully!");
    }

    /**
     * Display the specified user.
     */
    public function show(User $user): View
    {
        $user->load(['roles.permissions', 'permissions', 'blockedPermissions', 'activityLogs' => function ($q) {
            $q->latest()->take(10);
        }]);

        $allPermissions = $user->getAllPermissions();

        return view('admin.users.show', compact('user', 'allPermissions'));
    }

    /**
     * Show the form for editing the specified user.
     */
    public function edit(User $user): View
    {
        $user->load(['roles', 'permissions']);
        $roles = Role::with('permissions')->orderBy('name')->get();
        $permissions = Permission::all()->groupBy(function ($perm) {
            $parts = explode('.', $perm->name);

            return ucfirst($parts[0] ?? 'General');
        });

        $userRoleNames = $user->roles->pluck('name')->toArray();
        $userDirectPermissions = $user->permissions->pluck('name')->toArray();
        $userBlockedPermissions = $user->blockedPermissionNames();

        return view('admin.users.edit', compact('user', 'roles', 'permissions', 'userRoleNames', 'userDirectPermissions', 'userBlockedPermissions'));
    }

    /**
     * Update the specified user in storage.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => ['nullable', 'string', Password::defaults()],
            'phone' => ['nullable', 'string', 'max:50'],
            'job_title' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:100'],
            'status' => ['required', 'in:active,inactive,suspended'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp,svg', 'max:2048'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['exists:roles,name'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['exists:permissions,name'],
            'blocked' => ['nullable', 'array'],
            'blocked.*' => ['exists:permissions,name'],
            'access' => ['nullable', 'array'],
            'access.*' => ['in:role,allow,block'],
        ]);

        if (! AdminAccess::canManageAccount($request->user(), $user)) {
            return back()->with('error', 'Only a Super Admin can change a Super Admin account.');
        }
        $blockedBefore = $user->blockedPermissionNames();
        [$direct, $blocked] = $this->requestedAccess($request, $validated, $blockedBefore);
        if ($request->user()->is($user) && array_values(array_diff($blocked, $blockedBefore)) !== []) {
            return back()->withInput()->with('error', 'You cannot block permissions on your own account.');
        }
        if ($refused = $this->refusedAccess(
            $request->user(),
            $user->roles->pluck('name')->all(), $validated['roles'] ?? [],
            $user->permissions->pluck('name')->all(), $direct,
            $blockedBefore, $blocked,
        )) {
            return $refused;
        }

        $updateData = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'job_title' => $validated['job_title'] ?? null,
            'department' => $validated['department'] ?? null,
            'status' => $validated['status'],
            'bio' => $validated['bio'] ?? null,
        ];

        if (! empty($validated['password'])) {
            $updateData['password'] = Hash::make($validated['password']);
        }

        if ($request->hasFile('avatar')) {
            if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }
            $updateData['avatar'] = $request->file('avatar')->store('avatars', 'public');
        }

        $user->update($updateData);

        // Update Roles
        if (isset($validated['roles'])) {
            $user->syncRoles($validated['roles']);
        } else {
            $user->syncRoles([]);
        }

        // Update Direct Permissions, and those blocked for this account only
        $user->syncPermissions($direct);
        $user->syncBlockedPermissions($blocked);

        ActivityLog::log('update', 'Users', "Updated user account: {$user->name} ({$user->email})");

        return redirect()->route('admin.users.index')
            ->with('success', "User '{$user->name}' updated successfully!");
    }

    /**
     * Remove the specified user from storage.
     */
    public function destroy(User $user): RedirectResponse
    {
        if (Auth::id() === $user->id) {
            return back()->with('error', 'You cannot delete your own administrative account.');
        }
        if (! AdminAccess::canManageAccount(Auth::user(), $user)) {
            return back()->with('error', 'Only a Super Admin can delete a Super Admin account.');
        }

        if ($user->hasRole('Super Admin') && User::role('Super Admin')->count() <= 1) {
            return back()->with('error', 'Cannot delete the only Super Admin account in the system.');
        }

        $userName = $user->name;
        $userEmail = $user->email;

        if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
            Storage::disk('public')->delete($user->avatar);
        }

        $user->delete();

        ActivityLog::log('delete', 'Users', "Deleted user account: {$userName} ({$userEmail})");

        return redirect()->route('admin.users.index')
            ->with('success', "User '{$userName}' has been deleted.");
    }

    /**
     * Toggle user active/inactive status.
     */
    public function toggleStatus(User $user): RedirectResponse
    {
        if (Auth::id() === $user->id) {
            return back()->with('error', 'You cannot modify your own status.');
        }
        if (! AdminAccess::canManageAccount(Auth::user(), $user)) {
            return back()->with('error', 'Only a Super Admin can change a Super Admin account.');
        }

        $newStatus = $user->status === 'active' ? 'inactive' : 'active';
        $user->update(['status' => $newStatus]);

        ActivityLog::log('status_change', 'Users', "Changed status for {$user->name} to: {$newStatus}");

        return back()->with('success', 'User status updated to '.ucfirst($newStatus).'.');
    }

    /**
     * Quick reset password.
     */
    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        if (! AdminAccess::canManageAccount($request->user(), $user)) {
            return back()->with('error', 'Only a Super Admin can reset a Super Admin password.');
        }
        $request->validate([
            'new_password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user->update([
            'password' => Hash::make($request->new_password),
        ]);

        ActivityLog::log('password_reset', 'Users', "Reset password for user: {$user->name}");

        return back()->with('success', "Password for {$user->name} has been reset successfully!");
    }

    /**
     * Export users list as CSV.
     */
    public function export(): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="naoero_airlines_users_'.date('Y_m_d_His').'.csv"',
        ];

        return response()->stream(function () {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'Name', 'Email', 'Phone', 'Role(s)', 'Department', 'Job Title', 'Status', 'Last Login', 'Created At']);

            User::with('roles')->chunk(100, function ($users) use ($handle) {
                foreach ($users as $user) {
                    fputcsv($handle, [
                        $user->id,
                        $user->name,
                        $user->email,
                        $user->phone ?? 'N/A',
                        $user->roles->pluck('name')->join(', ') ?: 'No Role',
                        $user->department ?? 'N/A',
                        $user->job_title ?? 'N/A',
                        ucfirst($user->status),
                        $user->last_login_at ? $user->last_login_at->format('Y-m-d H:i') : 'Never',
                        $user->created_at->format('Y-m-d H:i'),
                    ]);
                }
            });

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Refuses role and permission changes the signed-in staff member may
     * not make: a role or permission they do not hold themselves (Super
     * Admin only by a Super Admin), so no one can raise access above their own.
     *
     * @param  list<string>  $rolesBefore
     * @param  list<string>  $rolesAfter
     * @param  list<string>  $permissionsBefore
     * @param  list<string>  $permissionsAfter
     * @param  list<string>  $blockedBefore
     * @param  list<string>  $blockedAfter
     */
    private function refusedAccess(User $actor, array $rolesBefore, array $rolesAfter, array $permissionsBefore, array $permissionsAfter, array $blockedBefore = [], array $blockedAfter = []): ?RedirectResponse
    {
        $roles = AdminAccess::refusedRoleChanges($actor, $rolesBefore, $rolesAfter);
        $permissions = array_values(array_unique([
            ...AdminAccess::refusedPermissionChanges($actor, $permissionsBefore, $permissionsAfter),
            ...AdminAccess::refusedPermissionChanges($actor, $blockedBefore, $blockedAfter),
        ]));
        if ($roles === [] && $permissions === []) {
            return null;
        }

        return back()->withInput()->with('error', 'You can only give or remove access you hold yourself. Not allowed: '
            .implode(', ', array_merge(array_map(fn ($r) => "role {$r}", $roles), $permissions)).'.');
    }

    /**
     * The direct grants and the blocks the form asks for. The edit form sends
     * access[permission] = role (whatever the roles give), allow (on for this
     * account) or block (off for this account, even when a role gives it).
     * Without it, permissions[] are the grants and blocked[] the blocks; blocks
     * not sent at all stay as they are.
     *
     * @param  array<string, mixed>  $validated
     * @param  list<string>  $currentBlocked
     * @return array{0: list<string>, 1: list<string>}
     */
    private function requestedAccess(Request $request, array $validated, array $currentBlocked): array
    {
        if (isset($validated['access'])) {
            $known = Permission::pluck('name')->all();
            $access = array_intersect_key($validated['access'], array_flip($known));

            return [
                array_keys(array_filter($access, fn ($state) => $state === 'allow')),
                array_keys(array_filter($access, fn ($state) => $state === 'block')),
            ];
        }

        return [
            array_values($validated['permissions'] ?? []),
            $request->has('blocked') ? array_values($validated['blocked'] ?? []) : $currentBlocked,
        ];
    }
}
