<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\User;

class BranchPolicy extends TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->can($user, 'branches:view');
    }

    public function view(User $user, Branch $branch): bool
    {
        return $this->can($user, 'branches:view') && $this->owns($user, $branch);
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'branches:manage');
    }

    public function update(User $user, Branch $branch): bool
    {
        return $this->can($user, 'branches:manage') && $this->owns($user, $branch);
    }

    public function delete(User $user, Branch $branch): bool
    {
        return $this->update($user, $branch) && ! $branch->users()->exists();
    }
}
