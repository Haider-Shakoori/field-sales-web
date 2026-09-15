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
        if (! ($user->hasPermission('roles:view') || $user->hasPermission('roles:manage'))) {
            return false;
        }

        return $role->isSystem() || $role->isOwnedBy($user->tenant_id);
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermission('roles:manage');
    }

    public function update(User $user, Role $role): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasPermission('roles:manage') && $role->isOwnedBy($user->tenant_id);
    }

    public function delete(User $user, Role $role): bool
    {
        return $this->update($user, $role);
    }

    public function assign(User $user): bool
    {
        return $user->hasPermission('users:manage');
    }
}
