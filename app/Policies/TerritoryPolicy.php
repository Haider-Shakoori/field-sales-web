<?php

namespace App\Policies;

use App\Models\Territory;
use App\Models\User;

class TerritoryPolicy extends TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->can($user, 'customers:view');
    }

    public function view(User $user, Territory $territory): bool
    {
        return $this->can($user, 'customers:view') && $this->owns($user, $territory);
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'customers:manage');
    }

    public function update(User $user, Territory $territory): bool
    {
        return $this->can($user, 'customers:manage') && $this->owns($user, $territory);
    }

    public function delete(User $user, Territory $territory): bool
    {
        return $this->update($user, $territory);
    }
}
