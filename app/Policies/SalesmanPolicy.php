<?php

namespace App\Policies;

use App\Models\Salesman;
use App\Models\User;

class SalesmanPolicy extends TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->can($user, 'sales-team:view');
    }

    public function view(User $user, Salesman $salesman): bool
    {
        return $this->can($user, 'sales-team:view')
            && $this->owns($user, $salesman);
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'sales-team:manage');
    }

    public function update(User $user, Salesman $salesman): bool
    {
        return $this->can($user, 'sales-team:manage')
            && $this->owns($user, $salesman);
    }

    public function delete(User $user, Salesman $salesman): bool
    {
        return $this->update($user, $salesman);
    }
}
