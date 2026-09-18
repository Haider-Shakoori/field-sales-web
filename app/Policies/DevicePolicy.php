<?php

namespace App\Policies;

use App\Models\Device;
use App\Models\User;

class DevicePolicy extends TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->can($user, 'sales-team:view');
    }

    public function view(User $user, Device $device): bool
    {
        return $this->can($user, 'sales-team:view')
            && $this->owns($user, $device);
    }

    public function revoke(User $user, Device $device): bool
    {
        return $this->can($user, 'sales-team:manage')
            && $this->owns($user, $device);
    }
}
