<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Support\Tenancy\TenantContext;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'phone', 'role', 'is_active', 'tenant_id', 'branch_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use BelongsToTenant;

    use HasApiTokens;
    use HasFactory;
    use HasUuids;
    use Notifiable;
    use SoftDeletes;

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'is_active' => 'boolean',
        'password' => 'hashed',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function modelRoles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'model_has_roles', 'user_id', 'role_id')
            ->withPivot('tenant_id');
    }

    /**
     * Roles assigned to this user within the current tenant context.
     *
     * When TenantContext has no tenant (platform-level super admin), returns
     * all roles for the user (pivot tenant_id null for super admin).
     */
    public function roles(): BelongsToMany
    {
        $query = $this->modelRoles()->orderBy('name');

        if (TenantContext::currentId() !== null) {
            $query->wherePivot('tenant_id', TenantContext::currentId());
        }

        return $query;
    }

    public function isSuperAdmin(): bool
    {
        return $this->tenant_id === null && $this->hasRole('super_admin');
    }

    public function hasRole(Role|string $role): bool
    {
        $roleName = $role instanceof Role ? $role->name : $role;

        return $this->roles->contains('name', $roleName);
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if ($this->roles->isEmpty()) {
            return false;
        }

        $this->roles->load('permissions');

        return $this->roles->pluck('permissions')
            ->flatten()
            ->pluck('name')
            ->contains($permission);
    }

    /**
     * Assign a single role to this user.
     *
     * Replaces any existing role for this user (one-role-per-user constraint).
     * The pivot tenant_id is set to the current tenant or the explicit $tenantId.
     * The users.role column is mirrored as the denormalized convenience field.
     */
    public function assignRole(Role|string $role, ?int $tenantId = null): void
    {
        $tenantId ??= $this->tenant_id;

        if (is_string($role)) {
            $role = Role::resolveAssignable($role, $tenantId)
                ?? throw new \InvalidArgumentException("Role [{$role}] is not assignable in this tenant.");
        }

        DB::transaction(function () use ($role, $tenantId): void {
            $this->modelRoles()->detach();

            $this->modelRoles()->attach($role->id, [
                'tenant_id' => $tenantId,
                'model_type' => static::class,
                'model_id' => $this->id,
            ]);

            $this->forceFill(['role' => $role->name])->save();
        });
    }

    public function lastLogin(): void
    {
        $this->forceFill(['last_login_at' => now('UTC')])->save();
    }
}
