<?php

namespace App\Policies;

use App\Models\CustomerCategory;
use App\Models\User;

class CustomerCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('customer_categories:view');
    }

    public function view(User $user, CustomerCategory $category): bool
    {
        if (! $user->hasPermission('customer_categories:view')) {
            return false;
        }

        return $category->tenant_id === $user->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('customer_categories:create');
    }

    public function update(User $user, CustomerCategory $category): bool
    {
        if (! ($user->hasPermission('customer_categories:update') && $category->tenant_id === $user->tenant_id)) {
            return false;
        }

        return true;
    }

    public function deactivate(User $user, CustomerCategory $category): bool
    {
        if (! ($user->hasPermission('customer_categories:deactivate') && $category->tenant_id === $user->tenant_id)) {
            return false;
        }

        return true;
    }
}
