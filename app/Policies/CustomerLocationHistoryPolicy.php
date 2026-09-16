<?php

namespace App\Policies;

use App\Models\CustomerLocationHistory;
use App\Models\User;

class CustomerLocationHistoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('customers:view');
    }

    public function view(User $user, CustomerLocationHistory $history): bool
    {
        if (! $user->hasPermission('customers:view')) {
            return false;
        }

        return $history->tenant_id === $user->tenant_id;
    }
}
