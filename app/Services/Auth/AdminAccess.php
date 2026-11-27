<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Who may open which part of the admin panel: every admin route needs a
 * permission, checked on the server (EnforceAdminPermission) and used to
 * show or hide the sidebar links, so the two never disagree.
 *
 * Each rule names the permissions that allow reading a section (GET) and
 * those that allow changing it (any other method, and the GET forms that
 * create or edit). Any one of the listed permissions is enough. A route no
 * rule covers is refused, so a new screen is closed until it is mapped.
 * The Super Admin role has every permission (AppServiceProvider).
 */
final class AdminAccess
{
    public const SUPER_ADMIN = 'Super Admin';

    /** Routes every signed-in staff member may open. */
    public const OPEN = [
        'admin.dashboard',
        // Option lists the edit forms of every section load.
        'admin.dropdowns.api-group',
        'admin.locations.countries.states-json',
    ];

    /**
     * Every permission, by module, with what it allows. Shown in the role
     * and user screens; created by the seeder and the migration.
     */
    public const PERMISSIONS = [
        'User Management' => [
            'users.view' => 'View admin users and staff',
            'users.create' => 'Create staff accounts',
            'users.edit' => 'Edit staff accounts and reset their passwords',
            'users.delete' => 'Delete staff accounts',
            'users.status' => 'Activate / deactivate staff accounts',
        ],
        'Role & Access Management' => [
            'roles.view' => 'View roles and the permission matrix',
            'roles.create' => 'Create roles',
            'roles.edit' => 'Edit roles and the permission matrix',
            'roles.delete' => 'Delete roles',
            'permissions.view' => 'View all permissions',
            'permissions.create' => 'Create permissions',
            'permissions.edit' => 'Edit permissions',
            'permissions.delete' => 'Delete permissions',
        ],
        'Website & App Content' => [
            'content.view' => 'View website and app pages, lists, banners, news, legal and travel information',
            'content.manage' => 'Create, edit and publish website and app pages and lists',
            'notices.manage' => 'Manage the notice bar and alerts',
        ],
        'Airline Operations' => [
            'flights.view' => 'View flight schedules',
            'flights.manage' => 'Create and edit flight schedules',
            'fleet.manage' => 'Manage the aircraft fleet',
            'routes.manage' => 'Manage destinations and routes',
            'locations.view' => 'View the locations master (countries, states, cities)',
            'locations.manage' => 'Manage the locations master',
            'bookings.view' => 'View flight bookings and refund requests',
            'bookings.manage' => 'Handle refund requests',
        ],
        'Public Users' => [
            'customers.view' => 'View public users (customers), their bookings, orders and activity',
            'customers.manage' => 'Disable / re-enable customers, sign them out, handle deletion requests',
        ],
        'Shop & Gift Cards' => [
            'shop.products.view' => 'View shop products and merchandise',
            'shop.products.manage' => 'Create and edit shop products, banners and gift cards',
            'shop.sales.view' => 'View shop sales, payments, gift card orders, returns and support',
            'shop.sales.manage' => 'Ship, deliver, cancel orders; handle returns, support and reviews',
            'shop.sales.refund' => 'Refund shop orders',
            'shop.sales.export' => 'Export shop sales',
            'shop.inventory.view' => 'View shop inventory',
            'shop.inventory.manage' => 'Adjust stock and edit variants',
            'shop.inventory.purchasing' => 'Manage suppliers and purchase orders',
            'shop.inventory.count' => 'Run stock counts',
            'shop.inventory.export' => 'Export inventory',
        ],
        'Commercial & Inbox' => [
            'inquiries.view' => 'View customer inquiries',
            'inquiries.manage' => 'Answer, close and delete inquiries',
            'newsletter.view' => 'View newsletter subscribers',
            'newsletter.manage' => 'Manage and export newsletter subscribers',
        ],
        'Careers & HR' => [
            'careers.view' => 'View job openings and applications',
            'careers.manage' => 'Post openings and handle applications',
        ],
        'Reports & Analytics' => [
            'analytics.view' => 'View dashboard analytics',
            'reports.export' => 'Export reports',
        ],
        'System & Settings' => [
            'system.access' => 'MASTER: shows the whole System & Settings menu. Unticked, every screen in it is hidden and refused, whatever else is ticked',
            'system.logs' => 'API, App & Login Logs',
            'system.zenith' => 'Zenith Flight Events',
            'system.push' => 'Push Notifications (view; changing keys needs settings.api)',
            'system.audit' => 'Audit Activity Logs (and the audit trail on the dashboard)',
            'system.profile' => 'Executive Profile (own name, photo and password)',
        ],
        'System Settings' => [
            'settings.general' => 'Site settings and the emergency notice',
            'settings.security' => 'Security settings; clear the audit log',
            'settings.api' => 'Cache & optimizer, push notification keys, integrations',
            'dropdowns.view' => 'View dynamic dropdowns',
            'dropdowns.manage' => 'Manage dynamic dropdowns',
        ],
    ];

    /**
     * Route name pattern => [view, manage]: the permissions (any one) needed
     * to read and to change. The first pattern that matches applies.
     *
     * @var array<string, array{0: list<string>, 1: list<string>}>
     */
    public const RULES = [
        // Staff accounts, roles and permissions.
        'admin.users.create' => [['users.create'], ['users.create']],
        'admin.users.store' => [['users.create'], ['users.create']],
        'admin.users.edit' => [['users.edit'], ['users.edit']],
        'admin.users.update' => [['users.edit'], ['users.edit']],
        'admin.users.reset-password' => [['users.edit'], ['users.edit']],
        'admin.users.toggle-status' => [['users.status'], ['users.status']],
        'admin.users.destroy' => [['users.delete'], ['users.delete']],
        'admin.users.*' => [['users.view'], ['users.edit']],
        'admin.roles.create' => [['roles.create'], ['roles.create']],
        'admin.roles.store' => [['roles.create'], ['roles.create']],
        'admin.roles.destroy' => [['roles.delete'], ['roles.delete']],
        'admin.roles.*' => [['roles.view'], ['roles.edit']],
        'admin.permissions.store' => [['permissions.create'], ['permissions.create']],
        'admin.permissions.update' => [['permissions.edit'], ['permissions.edit']],
        'admin.permissions.destroy' => [['permissions.delete'], ['permissions.delete']],
        'admin.permissions.*' => [['permissions.view'], ['permissions.edit']],

        // Public users, flight bookings and refunds.
        'admin.customers.*' => [['customers.view'], ['customers.manage']],
        'admin.flight-bookings.*' => [['bookings.view'], ['bookings.manage']],
        'admin.refund-requests.*' => [['bookings.view'], ['bookings.manage']],

        // Airline operations.
        'admin.schedules.*' => [['flights.view', 'flights.manage'], ['flights.manage']],
        'admin.fleets.*' => [['content.view', 'flights.view', 'fleet.manage'], ['fleet.manage']],
        'admin.destinations.*' => [['content.view', 'flights.view', 'routes.manage'], ['routes.manage']],
        'admin.locations.*' => [['locations.view', 'locations.manage'], ['locations.manage']],

        // Shop: gift card orders are sales; the controllers check refund, purchasing and counts again.
        'admin.shop.orders*' => [['shop.sales.view'], ['shop.sales.manage']],
        'admin.shop.*' => [['shop.products.view', 'shop.products.manage'], ['shop.products.manage']],
        'admin.sales.*' => [['shop.sales.view'], ['shop.sales.manage', 'shop.sales.refund']],
        'admin.inventory.counts.*' => [['shop.inventory.view'], ['shop.inventory.count', 'shop.inventory.manage']],
        'admin.inventory.purchase-orders.*' => [['shop.inventory.view', 'shop.inventory.purchasing'], ['shop.inventory.purchasing']],
        'admin.inventory.suppliers*' => [['shop.inventory.view', 'shop.inventory.purchasing'], ['shop.inventory.purchasing']],
        'admin.inventory.*' => [['shop.inventory.view'], ['shop.inventory.manage']],

        // Commercial, careers.
        'admin.inquiries.*' => [['inquiries.view', 'inquiries.manage'], ['inquiries.manage']],
        'admin.newsletter.export' => [['newsletter.manage'], ['newsletter.manage']],
        'admin.newsletter.*' => [['newsletter.view', 'newsletter.manage'], ['newsletter.manage']],
        'admin.careers.*' => [['careers.view', 'careers.manage'], ['careers.manage']],
        'admin.job-applications.*' => [['careers.view', 'careers.manage'], ['careers.manage']],

        // Website & app content.
        'admin.notices.*' => [['content.view', 'notices.manage'], ['notices.manage']],
        'admin.content-pages.*' => [['content.view', 'content.manage'], ['content.manage']],
        'admin.banners.*' => [['content.view', 'content.manage'], ['content.manage']],
        'admin.posts.*' => [['content.view', 'content.manage'], ['content.manage']],
        'admin.services.*' => [['content.view', 'content.manage'], ['content.manage']],
        'admin.testimonials.*' => [['content.view', 'content.manage'], ['content.manage']],
        'admin.why-fly.*' => [['content.view', 'content.manage'], ['content.manage']],
        'admin.journey.*' => [['content.view', 'content.manage'], ['content.manage']],
        'admin.team-members.*' => [['content.view', 'content.manage'], ['content.manage']],
        'admin.specials.*' => [['content.view', 'content.manage'], ['content.manage']],
        'admin.faqs.*' => [['content.view', 'content.manage'], ['content.manage']],
        'admin.agencies.*' => [['content.view', 'content.manage'], ['content.manage']],
        'admin.agency-resources.*' => [['content.view', 'content.manage'], ['content.manage']],
        'admin.travel-info.*' => [['content.view', 'content.manage'], ['content.manage']],
        'admin.legal-documents.*' => [['content.view', 'content.manage'], ['content.manage']],
        'admin.gallery.*' => [['content.view', 'content.manage'], ['content.manage']],

        // System & settings (all also need the system.access master, see SECTIONS).
        'admin.system-logs.*' => [['system.logs'], ['system.logs']],
        'admin.zenith-events.*' => [['system.zenith'], ['system.zenith']],
        'admin.push.index' => [['system.push', 'settings.api'], ['settings.api']],
        'admin.push.*' => [['settings.api'], ['settings.api']],
        'admin.activity-logs.clear' => [['settings.security'], ['settings.security']],
        'admin.activity-logs.*' => [['system.audit'], ['settings.security']],
        'admin.profile.*' => [['system.profile'], ['system.profile']],
        'admin.settings.*' => [['settings.general'], ['settings.general']],
        'admin.system.*' => [['settings.api'], ['settings.api']],
        'admin.dropdowns.*' => [['dropdowns.view', 'dropdowns.manage'], ['dropdowns.manage']],
    ];

    /**
     * Master permission => the routes of its sidebar section. These routes
     * need the master on top of their own rule, so unticking the master
     * hides and closes the whole section in one go.
     *
     * @var array<string, list<string>>
     */
    public const SECTIONS = [
        'system.access' => [
            'admin.system-logs.*', 'admin.zenith-events.*', 'admin.push.*', 'admin.dropdowns.*',
            'admin.settings.*', 'admin.activity-logs.*', 'admin.system.*', 'admin.profile.*',
        ],
    ];

    /** The master permission a route's section needs, if any. */
    public static function sectionMaster(string $routeName): ?string
    {
        foreach (self::SECTIONS as $master => $patterns) {
            if (Str::is($patterns, $routeName) && ! self::isOpen($routeName)) {
                return $master;
            }
        }

        return null;
    }

    /** @return array<string, string> permission => what it allows */
    public static function permissionLabels(): array
    {
        return array_merge(...array_values(self::PERMISSIONS));
    }

    public static function isOpen(string $routeName): bool
    {
        return Str::is(self::OPEN, $routeName);
    }

    /**
     * The permissions (any one) a route needs for this method, [] when it is
     * open to all staff, null when no rule covers it (refused).
     *
     * @return ?list<string>
     */
    public static function required(string $routeName, string $method = 'GET'): ?array
    {
        if (self::isOpen($routeName)) {
            return [];
        }
        foreach (self::RULES as $pattern => [$view, $manage]) {
            if (Str::is($pattern, $routeName)) {
                $reads = in_array(strtoupper($method), ['GET', 'HEAD', 'OPTIONS'], true)
                    && ! Str::endsWith($routeName, ['.create', '.edit']);

                return $reads ? $view : $manage;
            }
        }

        return null;
    }

    public static function allows(?User $user, string $routeName, string $method = 'GET'): bool
    {
        if ($user === null) {
            return false;
        }
        $needed = self::required($routeName, $method);
        if ($needed === null) {
            return false;
        }
        if ($needed === []) {
            return true;
        }
        $master = self::sectionMaster($routeName);
        if ($master !== null && ! $user->can($master)) {
            return false;
        }
        foreach ($needed as $permission) {
            if ($user->can($permission)) {
                return true;
            }
        }

        return false;
    }

    public static function allowsRoute(?User $user, Route $route, string $method): bool
    {
        return self::allows($user, (string) $route->getName(), $method);
    }

    public static function isSuperAdmin(?User $user): bool
    {
        return $user !== null && $user->hasRole(self::SUPER_ADMIN);
    }

    /**
     * Whether $actor may give or take away a permission: only one they hold
     * themselves (a Super Admin holds them all), so no one can raise their
     * own access or anyone else's above their own.
     */
    public static function canGrantPermission(User $actor, string $permission): bool
    {
        return self::isSuperAdmin($actor) || $actor->can($permission);
    }

    /**
     * The permissions added or removed between $before and $after that
     * $actor may not give or take away.
     *
     * @param  list<string>  $before
     * @param  list<string>  $after
     * @return list<string>
     */
    public static function refusedPermissionChanges(User $actor, array $before, array $after): array
    {
        $changed = array_merge(array_diff($after, $before), array_diff($before, $after));

        return array_values(array_unique(array_filter($changed, fn (string $p) => ! self::canGrantPermission($actor, $p))));
    }

    /**
     * The roles added or removed between $before and $after that $actor may
     * not give or take away.
     *
     * @param  list<string>  $before
     * @param  list<string>  $after
     * @return list<string>
     */
    public static function refusedRoleChanges(User $actor, array $before, array $after): array
    {
        $changed = array_unique(array_merge(array_diff($after, $before), array_diff($before, $after)));
        $roles = Role::with('permissions')->whereIn('name', $changed)->get();

        return $roles->reject(fn (Role $role) => self::canGrantRole($actor, $role))->pluck('name')->values()->all();
    }

    /** Only a Super Admin may change, disable, reset or delete a Super Admin's account. */
    public static function canManageAccount(User $actor, User $target): bool
    {
        return self::isSuperAdmin($actor) || ! self::isSuperAdmin($target);
    }

    /** A role may be given or taken away only by someone holding all of its permissions; Super Admin only by a Super Admin. */
    public static function canGrantRole(User $actor, Role $role): bool
    {
        if (self::isSuperAdmin($actor)) {
            return true;
        }
        if ($role->name === self::SUPER_ADMIN) {
            return false;
        }
        foreach ($role->permissions as $permission) {
            if (! $actor->can($permission->name)) {
                return false;
            }
        }

        return true;
    }
}
