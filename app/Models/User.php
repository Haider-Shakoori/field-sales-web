<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Tenancy\TenantContextMismatchException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use BelongsToTenant, HasApiTokens, HasFactory, Notifiable;

    protected $guarded = [];

    protected $hidden = ['password', 'remember_token'];

    /**
     * Request-lifetime RBAC caches.
     *
     * Navigation and policy checks can ask for many permissions during a single
     * render. Loading roles + permissions once prevents one EXISTS query per
     * permission check while keeping the database as the source of truth.
     *
     * @var array<int, string>|null
     */
    private ?array $permissionSlugCache = null;

    /** @var array<int, string>|null */
    private ?array $roleSlugCache = null;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function salesman(): HasOne
    {
        return $this->hasOne(Salesman::class);
    }

    public function supervisor(): HasOne
    {
        return $this->hasOne(Supervisor::class);
    }

    public function operationalNotifications(): HasMany
    {
        return $this->hasMany(OperationalNotification::class);
    }

    public function notificationPreference(): HasOne
    {
        return $this->hasOne(NotificationPreference::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'model_has_roles')
            ->withPivot('tenant_id')
            ->wherePivot('tenant_id', $this->tenant_id);
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissionSlugs(), true);
    }

    public function hasAnyPermission(array $permissions): bool
    {
        if ($permissions === []) {
            return false;
        }

        return array_intersect($permissions, $this->permissionSlugs()) !== [];
    }

    public function hasAnyRole(array $roles): bool
    {
        if ($roles === []) {
            return false;
        }

        return array_intersect($roles, $this->roleSlugs()) !== [];
    }

    public function syncPrimaryRole(Role $role): void
    {
        if ((int) $role->tenant_id !== (int) $this->tenant_id) {
            throw new TenantContextMismatchException(
                'Cannot assign a role that belongs to another tenant.'
            );
        }

        $this->roles()->sync([
            $role->id => ['tenant_id' => $this->tenant_id],
        ]);

        // Compatibility/display mirror only. Authorization always uses
        // model_has_roles + role_permissions.
        $this->forceFill(['role' => $role->slug])->save();
        $this->unsetRelation('roles');
        $this->permissionSlugCache = null;
        $this->roleSlugCache = null;
    }

    /** @return array<int, string> */
    private function permissionSlugs(): array
    {
        if ($this->permissionSlugCache !== null) {
            return $this->permissionSlugCache;
        }

        $this->loadMissing('roles.permissions');

        return $this->permissionSlugCache = $this->roles
            ->flatMap(fn (Role $role) => $role->permissions->pluck('slug'))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /** @return array<int, string> */
    private function roleSlugs(): array
    {
        if ($this->roleSlugCache !== null) {
            return $this->roleSlugCache;
        }

        $this->loadMissing('roles');

        return $this->roleSlugCache = $this->roles
            ->pluck('slug')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
