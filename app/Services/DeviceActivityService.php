<?php

namespace App\Services;

use App\Models\Device;
use App\Models\DeviceActivityLog;
use App\Models\User;

class DeviceActivityService
{
    public function __construct(
        private readonly DeviceSettingsService $settings,
    ) {}

    public function record(
        Device $device,
        string $event,
        array $context = [],
        ?User $actor = null,
        string $severity = 'info',
    ): ?DeviceActivityLog {
        $device->loadMissing('tenant');

        if (! $device->tenant
            || ! $this->settings->get($device->tenant)['activity_history_enabled']) {
            return null;
        }

        return DeviceActivityLog::create([
            'tenant_id' => $device->tenant_id,
            'device_id' => $device->id,
            'user_id' => $device->user_id,
            'actor_user_id' => $actor?->id,
            'event' => $event,
            'severity' => $severity,
            'context' => $context ?: null,
            'occurred_at' => now(),
        ]);
    }
}
