<?php

namespace App\Policies;

use App\Models\SalesRoute;
use App\Models\User;

class SalesRoutePolicy extends TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->can($user, 'customers:view');
    }

    public function view(User $user, SalesRoute $route): bool
    {
        return $this->can($user, 'customers:view') && $this->owns($user, $route);
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'customers:manage');
    }

    public function update(User $user, SalesRoute $route): bool
    {
        return $this->can($user, 'customers:manage') && $this->owns($user, $route);
    }

    public function delete(User $user, SalesRoute $route): bool
    {
        return $this->update($user, $route);
    }
}
