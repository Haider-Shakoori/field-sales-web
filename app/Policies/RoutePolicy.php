<?php

namespace App\Policies;

use App\Models\Route;
use App\Models\User;

class RoutePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('routes:view');
    }

    public function view(User $user, Route $route): bool
    {
        if (! $user->hasPermission('routes:view')) {
            return false;
        }

        return $route->tenant_id === $user->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('routes:create');
    }

    public function update(User $user, Route $route): bool
    {
        if (! ($user->hasPermission('routes:update') && $route->tenant_id === $user->tenant_id)) {
            return false;
        }

        return true;
    }

    public function deactivate(User $user, Route $route): bool
    {
        if (! ($user->hasPermission('routes:deactivate') && $route->tenant_id === $user->tenant_id)) {
            return false;
        }

        return true;
    }
}
