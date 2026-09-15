<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('roles:view');
    }

    public function view(User $user, Role $role): bool
    {
        return $user->hasPermission('roles:view') || $user->hasPermission('roles:manage');
    }

    public function update(User $user, Role $role): bool
    {
        return $user->isSuperAdmin() || $user->hasPermission('roles:manage');
    }

    public function assign(User $user): bool
    {
        return $user->hasPermission('users:manage');
    }
}
