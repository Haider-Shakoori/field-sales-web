<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('users:view');
    }

    public function view(User $user, User $subject): bool
    {
        return $subject->tenant_id === $user->tenant_id
            && ($user->hasPermission('users:view') || $user->hasPermission('users:manage'));
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('users:manage');
    }

    public function update(User $user, User $subject): bool
    {
        return $user->hasPermission('users:manage') && $subject->tenant_id === $user->tenant_id;
    }

    public function deactivate(User $user, User $subject): bool
    {
        return $user->hasPermission('users:manage')
            && $subject->tenant_id === $user->tenant_id
            && $subject->isNot($user);
    }
}
