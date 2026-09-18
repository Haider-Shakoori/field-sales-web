<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, BelongsToTenant;

    protected $guarded = [];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function salesman(): HasOne
    {
        return $this->hasOne(Salesman::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'model_has_roles')
            ->withPivot('tenant_id');
    }

    public function hasPermission(string $permission): bool
    {
        if (in_array($this->role, ['super_admin', 'owner', 'admin', 'company_admin'], true)) {
            return true;
        }

        return $this->roles()
            ->where(function ($query) {
                $query->whereNull('roles.tenant_id')
                    ->orWhere('roles.tenant_id', $this->tenant_id);
            })
            ->whereHas('permissions', fn ($query) => $query->where('slug', $permission))
            ->exists();
    }

    public function hasAnyRole(array $roles): bool
    {
        if (in_array($this->role, $roles, true)) {
            return true;
        }

        return $this->roles()->whereIn('slug', $roles)->exists();
    }
}
