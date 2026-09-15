<?php

namespace App\Policies;

use App\Models\Supervisor;
use App\Models\User;

class SupervisorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('supervisors:view') || $user->hasPermission('supervisors:manage');
    }

    public function view(User $user, Supervisor $supervisor): bool
    {
        if (! $user->hasPermission('supervisors:view')) {
            return false;
        }

        return $supervisor->tenant_id === $user->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('supervisors:create');
    }

    public function update(User $user, Supervisor $supervisor): bool
    {
        if (! ($user->hasPermission('supervisors:update') && $supervisor->tenant_id === $user->tenant_id)) {
            return false;
        }

        return true;
    }

    public function deactivate(User $user, Supervisor $supervisor): bool
    {
        if (! ($user->hasPermission('supervisors:deactivate')
            && $supervisor->tenant_id === $user->tenant_id
            && $supervisor->isNot($user->supervisorProfile))) {
            return false;
        }

        return true;
    }
}
