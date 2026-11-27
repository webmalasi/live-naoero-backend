<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    use HasRoles {
        HasRoles::hasPermissionTo as protected spatieHasPermissionTo;
        HasRoles::getAllPermissions as protected spatieGetAllPermissions;
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'avatar',
        'job_title',
        'department',
        'status',
        'bio',
        'password',
        'last_login_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /** Permissions switched off for this account even when one of its roles gives them. */
    public function blockedPermissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'user_blocked_permissions')->withPivot('created_at');
    }

    /** @return list<string> */
    public function blockedPermissionNames(): array
    {
        return $this->blockedPermissions->pluck('name')->all();
    }

    /** @param  list<string>  $names */
    public function syncBlockedPermissions(array $names): void
    {
        $ids = Permission::whereIn('name', $names)->where('guard_name', 'web')->pluck('id');
        $this->blockedPermissions()->syncWithPivotValues($ids, ['created_at' => now()]);
        $this->unsetRelation('blockedPermissions');
    }

    /** A blocked permission is refused even when a role or a direct grant gives it. */
    public function hasPermissionTo($permission, ?string $guardName = null): bool
    {
        $name = match (true) {
            $permission instanceof Permission => $permission->name,
            is_string($permission) => $permission,
            is_int($permission) => Permission::find($permission)?->name,
            default => null,
        };
        if ($name !== null && in_array($name, $this->blockedPermissionNames(), true)) {
            return false;
        }

        return $this->spatieHasPermissionTo($permission, $guardName);
    }

    /** Everything the account's roles and direct grants give, minus what is blocked for it. */
    public function getAllPermissions(): Collection
    {
        $blocked = $this->blockedPermissionNames();

        return $this->spatieGetAllPermissions()->reject(fn (Permission $p) => in_array($p->name, $blocked, true))->values();
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function getAvatarUrlAttribute(): string
    {
        if ($this->avatar && file_exists(public_path('storage/'.$this->avatar))) {
            return asset('storage/'.$this->avatar);
        }

        // Return a sleek SVG avatar with user initials matching Naoero theme (#012169 Navy and #FFC631 Gold)
        $name = urlencode($this->name);

        return "https://ui-avatars.com/api/?name={$name}&color=FFC631&background=012169&bold=true&format=svg";
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function getPrimaryRoleAttribute(): string
    {
        return $this->roles->first()?->name ?? 'User';
    }
}
