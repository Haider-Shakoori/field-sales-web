<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy extends TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->can($user, 'catalog:view');
    }

    public function view(User $user, Product $product): bool
    {
        return $this->can($user, 'catalog:view') && $this->owns($user, $product);
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'catalog:manage');
    }

    public function update(User $user, Product $product): bool
    {
        return $this->can($user, 'catalog:manage') && $this->owns($user, $product);
    }

    public function delete(User $user, Product $product): bool
    {
        return $this->update($user, $product);
    }
}
