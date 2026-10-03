<?php

namespace App\Services;

use App\Models\CompanySetting;
use App\Models\Tenant;

final class DeviceSettingsService
{
    public function get(Tenant $tenant): array
    {
        $rows = CompanySetting::query()
            ->where('tenant_id', $tenant->id)
            ->where('key', 'like', 'device.%')
            ->pluck('value', 'key');

        $legacyRestriction = data_get(
            $tenant->settings,
            'security.device_restriction_enabled',
            true,
        );

        $get = fn (string $key, mixed $fallback) => $rows->get('device.'.$key, $fallback);

        $secondaryEnabled = $this->bool($get('secondary_device_enabled', false));

        return [
            'device_restriction_enabled' => $this->bool(
                $get('device_restriction_enabled', $legacyRestriction),
            ),
            'approval_required' => $this->bool($get('approval_required', false)),
            'secondary_device_enabled' => $secondaryEnabled,
            'max_active_devices' => $secondaryEnabled
                ? $this->int($get('max_active_devices', 2), 2, 3, 2)
                : 1,
            'lost_device_workflow_enabled' => $this->bool(
                $get('lost_device_workflow_enabled', false),
            ),
            'activity_history_enabled' => $this->bool(
                $get('activity_history_enabled', false),
            ),
            'remote_diagnostics_enabled' => $this->bool(
                $get('remote_diagnostics_enabled', false),
            ),
            'push_test_enabled' => $this->bool(
                $get('push_test_enabled', false),
            ),
            'gps_background_test_enabled' => $this->bool(
                $get('gps_background_test_enabled', false),
            ),
            'sync_test_enabled' => $this->bool(
                $get('sync_test_enabled', false),
            ),
            'updated_at' => CompanySetting::query()
                ->where('tenant_id', $tenant->id)
                ->where('key', 'like', 'device.%')
                ->max('updated_at'),
        ];
    }

    private function bool(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private function int(
        mixed $value,
        int $min,
        int $max,
        int $fallback,
    ): int {
        $integer = (int) $value;

        return $integer >= $min && $integer <= $max
            ? $integer
            : $fallback;
    }
}
