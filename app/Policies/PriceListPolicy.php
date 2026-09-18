<?php

namespace App\Policies;

use App\Models\PriceList;
use App\Models\User;

class PriceListPolicy extends TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->can($user, 'catalog:view');
    }

    public function view(User $user, PriceList $priceList): bool
    {
        return $this->can($user, 'catalog:view') && $this->owns($user, $priceList);
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'catalog:manage');
    }

    public function update(User $user, PriceList $priceList): bool
    {
        return $this->can($user, 'catalog:manage') && $this->owns($user, $priceList);
    }

    public function delete(User $user, PriceList $priceList): bool
    {
        return $this->update($user, $priceList);
    }
}
