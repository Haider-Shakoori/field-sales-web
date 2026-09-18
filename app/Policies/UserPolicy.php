<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy extends TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->can($user, 'users:view');
    }

    public function view(User $user, User $target): bool
    {
        return $this->can($user, 'users:view') && $this->owns($user, $target);
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'users:manage');
    }

    public function update(User $user, User $target): bool
    {
        return $this->can($user, 'users:manage') && $this->owns($user, $target);
    }

    public function delete(User $user, User $target): bool
    {
        return $this->can($user, 'users:manage')
            && $this->owns($user, $target)
            && $user->isNot($target);
    }
}
