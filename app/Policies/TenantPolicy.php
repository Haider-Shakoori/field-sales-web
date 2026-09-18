<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

abstract class TenantPolicy
{
    protected function can(User $user, string $permission): bool
    {
        return $user->hasPermission($permission);
    }

    protected function owns(User $user, Model $model): bool
    {
        return (int) $model->getAttribute('tenant_id') === (int) $user->tenant_id;
    }
}
