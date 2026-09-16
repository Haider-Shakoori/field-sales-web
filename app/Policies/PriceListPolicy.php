<?php

namespace App\Policies;

use App\Models\PriceList;
use App\Models\User;

class PriceListPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('price_lists:view');
    }

    public function view(User $user, PriceList $priceList): bool
    {
        return $user->hasPermission('price_lists:view') && $priceList->tenant_id === $user->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('price_lists:create');
    }

    public function update(User $user, PriceList $priceList): bool
    {
        return $user->hasPermission('price_lists:update') && $priceList->tenant_id === $user->tenant_id;
    }

    public function deactivate(User $user, PriceList $priceList): bool
    {
        return $user->hasPermission('price_lists:deactivate') && $priceList->tenant_id === $user->tenant_id;
    }

    public function managePrices(User $user, PriceList $priceList): bool
    {
        return $user->hasPermission('price_lists:manage_prices') && $priceList->tenant_id === $user->tenant_id;
    }
}
