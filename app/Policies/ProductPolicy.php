<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('products:view');
    }

    public function view(User $user, Product $product): bool
    {
        return $user->hasPermission('products:view') && $product->tenant_id === $user->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('products:create');
    }

    public function update(User $user, Product $product): bool
    {
        return $user->hasPermission('products:update') && $product->tenant_id === $user->tenant_id;
    }

    public function deactivate(User $user, Product $product): bool
    {
        return $user->hasPermission('products:deactivate') && $product->tenant_id === $user->tenant_id;
    }
}
