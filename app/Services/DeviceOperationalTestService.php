<?php

namespace App\Services;

use App\Models\Device;

final class DeviceOperationalTestService
{
    public function gpsBackground(Device $device): array
    {
        $checks = [];

        $this->check(
            $checks,
            'Health heartbeat',
            $device->health_reported_at !== null
                && $device->health_reported_at->gte(now()->subMinutes(15)),
            $device->health_reported_at
                ? 'Last report '.$device->health_reported_at->diffForHumans()
                : 'No device health report has been received.',
        );

        $this->check(
            $checks,
            'Location services',
            $device->location_services_enabled !== false,
            $device->location_services_enabled === null
                ? 'Unknown'
                : ($device->location_services_enabled ? 'Enabled' : 'Disabled'),
        );

        $permission = strtolower((string) $device->location_permission);
        $this->check(
            $checks,
            'Location permission',
            ! in_array($permission, ['denied', 'denied_forever', 'restricted'], true),
            $device->location_permission ?: 'Unknown',
        );

        $backgroundPermission = strtolower((string) $device->background_location_permission);
        $this->check(
            $checks,
            'Background permission',
            ! $device->workday_active
                || in_array($backgroundPermission, ['granted', 'always'], true),
            $device->background_location_permission ?: 'Unknown',
        );

        $this->check(
            $checks,
            'Background tracking',
            ! $device->workday_active || $device->background_tracking_active === true,
            $device->background_tracking_active === null
                ? 'Unknown'
                : ($device->background_tracking_active ? 'Active' : 'Inactive'),
        );

        $gpsFresh = $device->last_gps_fix_at !== null
            && $device->last_gps_fix_at->gte(now()->subMinutes(10));
        $this->check(
            $checks,
            'GPS freshness',
            ! $device->workday_active || $gpsFresh,
            $device->last_gps_fix_at
                ? 'Last fix '.$device->last_gps_fix_at->diffForHumans()
                : 'No GPS fix recorded.',
        );

        return $this->result($checks);
    }

    public function sync(Device $device): array
    {
        $checks = [];

        $this->check(
            $checks,
            'Blocked sync items',
            (int) $device->blocked_sync_count === 0,
            number_format((int) $device->blocked_sync_count).' blocked',
        );

        $this->check(
            $checks,
            'Failed sync items',
            (int) $device->failed_sync_count === 0,
            number_format((int) $device->failed_sync_count).' failed',
            'warning',
        );

        $this->check(
            $checks,
            'Pending backlog',
            (int) $device->pending_sync_count < 100,
            number_format((int) $device->pending_sync_count).' pending',
            'warning',
        );

        $hasRecentSync = $device->last_sync_at !== null
            && $device->last_sync_at->gte(now()->subHours(12));
        $this->check(
            $checks,
            'Recent successful sync',
            (int) $device->pending_sync_count === 0 || $hasRecentSync,
            $device->last_sync_at
                ? 'Last sync '.$device->last_sync_at->diffForHumans()
                : 'No successful sync timestamp recorded.',
            'warning',
        );

        return $this->result($checks);
    }

    private function check(
        array &$checks,
        string $name,
        bool $passed,
        string $detail,
        string $failureSeverity = 'critical',
    ): void {
        $checks[] = [
            'name' => $name,
            'passed' => $passed,
            'severity' => $passed ? 'ok' : $failureSeverity,
            'detail' => $detail,
        ];
    }

    private function result(array $checks): array
    {
        $status = collect($checks)->contains(
            fn (array $check): bool => ! $check['passed']
                && $check['severity'] === 'critical',
        )
            ? 'failed'
            : (collect($checks)->contains(
                fn (array $check): bool => ! $check['passed'],
            ) ? 'warning' : 'passed');

        return [
            'status' => $status,
            'checks' => $checks,
        ];
    }
}
