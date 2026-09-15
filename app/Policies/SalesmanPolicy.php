<?php

namespace App\Policies;

use App\Models\Salesman;
use App\Models\User;

class SalesmanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('salesmen:view') || $user->hasPermission('salesmen:manage');
    }

    public function view(User $user, Salesman $salesman): bool
    {
        if (! $user->hasPermission('salesmen:view')) {
            return false;
        }

        return $salesman->tenant_id === $user->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('salesmen:create');
    }

    public function update(User $user, Salesman $salesman): bool
    {
        if (! ($user->hasPermission('salesmen:update') && $salesman->tenant_id === $user->tenant_id)) {
            return false;
        }

        return true;
    }

    public function deactivate(User $user, Salesman $salesman): bool
    {
        if (! ($user->hasPermission('salesmen:deactivate')
            && $salesman->tenant_id === $user->tenant_id
            && $salesman->isNot($user->salesmanProfile))) {
            return false;
        }

        return true;
    }
}
