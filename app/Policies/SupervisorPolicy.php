<?php

namespace App\Policies;

use App\Models\Supervisor;
use App\Models\User;

class SupervisorPolicy extends TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->can($user, 'sales-team:view');
    }

    public function view(User $user, Supervisor $supervisor): bool
    {
        return $this->can($user, 'sales-team:view')
            && $this->owns($user, $supervisor);
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'sales-team:manage');
    }

    public function update(User $user, Supervisor $supervisor): bool
    {
        return $this->can($user, 'sales-team:manage')
            && $this->owns($user, $supervisor);
    }

    public function delete(User $user, Supervisor $supervisor): bool
    {
        return $this->update($user, $supervisor);
    }
}
