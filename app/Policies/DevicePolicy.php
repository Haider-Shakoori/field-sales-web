<?php

namespace App\Policies;

use App\Models\Device;
use App\Models\User;

class DevicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('devices:view');
    }

    public function view(User $user, Device $device): bool
    {
        if (! $user->hasPermission('devices:view')) {
            return false;
        }

        return $device->tenant_id === $user->tenant_id;
    }

    public function revoke(User $user, Device $device): bool
    {
        if (! ($user->hasPermission('devices:revoke') && $device->tenant_id === $user->tenant_id)) {
            return false;
        }

        return true;
    }

    public function heartbeat(User $user, Device $device): bool
    {
        return $device->tenant_id === $user->tenant_id && $device->user_id === $user->id;
    }

    public function useDevice(User $user, Device $device): bool
    {
        return $device->tenant_id === $user->tenant_id && $device->user_id === $user->id;
    }
}
