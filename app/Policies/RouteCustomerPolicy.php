<?php

namespace App\Policies;

use App\Models\RouteCustomer;
use App\Models\User;

class RouteCustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('routes:view') || $user->hasPermission('customers:view');
    }

    public function view(User $user, RouteCustomer $routeCustomer): bool
    {
        if (! $user->hasPermission('routes:view') && ! $user->hasPermission('customers:view')) {
            return false;
        }

        return $routeCustomer->tenant_id === $user->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('routes:create') || $user->hasPermission('customers:create');
    }

    public function update(User $user, RouteCustomer $routeCustomer): bool
    {
        if (! $user->hasPermission('routes:update') && ! $user->hasPermission('customers:update')) {
            return false;
        }

        return $routeCustomer->tenant_id === $user->tenant_id;
    }

    public function delete(User $user, RouteCustomer $routeCustomer): bool
    {
        if (! $user->hasPermission('routes:manage') && ! $user->hasPermission('customers:manage')) {
            return false;
        }

        return $routeCustomer->tenant_id === $user->tenant_id;
    }
}
