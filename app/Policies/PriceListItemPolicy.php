<?php

namespace App\Policies;

use App\Models\PriceListItem;
use App\Models\User;

class PriceListItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('price_lists:manage_prices');
    }

    public function view(User $user, PriceListItem $item): bool
    {
        return $user->hasPermission('price_lists:manage_prices') && $item->tenant_id === $user->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('price_lists:manage_prices');
    }

    public function update(User $user, PriceListItem $item): bool
    {
        return $user->hasPermission('price_lists:manage_prices') && $item->tenant_id === $user->tenant_id;
    }

    public function delete(User $user, PriceListItem $item): bool
    {
        return $user->hasPermission('price_lists:manage_prices') && $item->tenant_id === $user->tenant_id;
    }
}
