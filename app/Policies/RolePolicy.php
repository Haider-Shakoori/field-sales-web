<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

class RolePolicy extends TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->can($user, 'roles:view');
    }

    public function view(User $user, Role $role): bool
    {
        return $this->can($user, 'roles:view') && $this->owns($user, $role);
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'roles:manage');
    }

    public function update(User $user, Role $role): bool
    {
        return $this->can($user, 'roles:manage')
            && $this->owns($user, $role)
            && ! $role->is_system;
    }

    public function delete(User $user, Role $role): bool
    {
        return $this->update($user, $role) && ! $role->users()->exists();
    }
}
