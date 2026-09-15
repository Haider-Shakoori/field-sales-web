<?php

namespace App\Policies;

use App\Models\CompanySetting;
use App\Models\User;

class CompanySettingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('settings:view');
    }

    public function view(User $user, CompanySetting $setting): bool
    {
        return $user->hasPermission('settings:view') && $setting->tenant_id === $user->tenant_id;
    }

    public function update(User $user, CompanySetting $setting): bool
    {
        return $user->hasPermission('settings:manage') && $setting->tenant_id === $user->tenant_id;
    }
}
