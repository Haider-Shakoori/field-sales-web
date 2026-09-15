<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\User;

class BranchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('branches:view');
    }

    public function view(User $user, Branch $branch): bool
    {
        return $branch->tenant_id === $user->tenant_id
            && ($user->hasPermission('branches:view') || $user->hasPermission('branches:manage'));
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('branches:manage');
    }

    public function update(User $user, Branch $branch): bool
    {
        return $user->hasPermission('branches:manage') && $branch->tenant_id === $user->tenant_id;
    }

    public function delete(User $user, Branch $branch): bool
    {
        return $user->hasPermission('branches:manage') && $branch->tenant_id === $user->tenant_id;
    }
}
