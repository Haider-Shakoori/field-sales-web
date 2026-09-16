<?php

namespace App\Policies;

use App\Models\Territory;
use App\Models\User;

class TerritoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('territories:view');
    }

    public function view(User $user, Territory $territory): bool
    {
        if (! $user->hasPermission('territories:view')) {
            return false;
        }

        return $territory->tenant_id === $user->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('territories:create');
    }

    public function update(User $user, Territory $territory): bool
    {
        if (! ($user->hasPermission('territories:update') && $territory->tenant_id === $user->tenant_id)) {
            return false;
        }

        return true;
    }

    public function deactivate(User $user, Territory $territory): bool
    {
        if (! ($user->hasPermission('territories:deactivate') && $territory->tenant_id === $user->tenant_id)) {
            return false;
        }

        return true;
    }
}
