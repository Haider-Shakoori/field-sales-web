<?php

namespace App\Models;

use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'name',
        'guard_name',
        'tenant_id',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_has_permissions', 'role_id', 'permission_id');
    }

    public function modelHasRoles(): HasMany
    {
        return $this->hasMany(ModelHasRole::class);
    }

    public function hasPermission(string $permission): bool
    {
        return $this->permissions()->where('name', $permission)->exists();
    }

    /**
     * Whether this is a platform/system role shared across all tenants.
     */
    public function isSystem(): bool
    {
        return $this->tenant_id === null;
    }

    /**
     * Roles visible in the current context: every system role plus the
     * caller's own custom roles. In the platform context all roles are shown.
     */
    public function scopeVisibleIn(Builder $query): Builder
    {
        $tenantId = TenantContext::currentId();

        if ($tenantId === null) {
            return $query;
        }

        return $query->where(fn (Builder $q) => $q
            ->whereNull('tenant_id')
            ->orWhere('tenant_id', $tenantId));
    }

    /**
     * Roles that may be assigned to a user of the given tenant: every system
     * role unless it is platform-only, plus that tenant's custom roles. In the
     * platform context (null tenant) every system role including super_admin
     * is assignable.
     */
    public function scopeAssignableIn(Builder $query, ?int $tenantId): Builder
    {
        return $query->where(function (Builder $q) use ($tenantId): void {
            if ($tenantId === null) {
                $q->whereNull('tenant_id');

                return;
            }

            $q->whereNull('tenant_id')
                ->where('name', '!=', 'super_admin')
                ->orWhere('tenant_id', $tenantId);
        });
    }

    /**
     * Resolve a role assignable to the given tenant by name.
     */
    public static function resolveAssignable(string $name, ?int $tenantId): ?Role
    {
        return self::query()->assignableIn($tenantId)->where('name', $name)->first();
    }

    /**
     * Whether this custom role is owned by the given tenant.
     */
    public function isOwnedBy(?int $tenantId): bool
    {
        return $this->tenant_id !== null && $this->tenant_id === $tenantId;
    }

    /**
     * Number of users assigned this role, scoped to the tenant context the
     * role is displayed within.
     */
    public function userCount(): int
    {
        $tenantId = $this->tenant_id ?? TenantContext::currentId();

        return $this->modelHasRoles()
            ->when($tenantId !== null, fn (Builder $q) => $q->where('tenant_id', $tenantId))
            ->count();
    }
}
