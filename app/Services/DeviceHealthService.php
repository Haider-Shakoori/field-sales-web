<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use DateTimeInterface;

class DeviceHealthService
{
    public function classify(array $health): array
    {
        $issues = [];
        $workdayActive = (bool) ($health['workday_active'] ?? false);
        $charging = (bool) ($health['is_charging'] ?? false);
        $battery = $this->intOrNull($health['battery_level'] ?? null);
        $storageFree = $this->intOrNull($health['storage_free_mb'] ?? null);
        $pending = max(0, (int) ($health['pending_sync_count'] ?? 0));
        $failed = max(0, (int) ($health['failed_sync_count'] ?? 0));
        $blocked = max(0, (int) ($health['blocked_sync_count'] ?? 0));

        if ($battery !== null && ! $charging) {
            if ($battery <= 5) {
                $this->add($issues, 'battery_critical', 'critical', 'Battery is critically low.');
            } elseif ($battery <= 15) {
                $this->add($issues, 'battery_low', 'warning', 'Battery is low.');
            }
        }

        if (($health['power_save_mode'] ?? false) === true) {
            $this->add($issues, 'power_save_mode', 'warning', 'Battery saver is enabled and may delay background work.');
        }

        if ($workdayActive && ($health['battery_optimization_exempt'] ?? null) === false) {
            $this->add($issues, 'battery_optimization', 'warning', 'Android battery optimization can restrict FieldPulse background tracking.');
        }

        if (($health['background_restricted'] ?? false) === true) {
            $this->add($issues, 'background_restricted', 'warning', 'Android is restricting FieldPulse background activity.');
        }

        if ($workdayActive && ($health['location_services_enabled'] ?? null) === false) {
            $this->add($issues, 'location_services_disabled', 'critical', 'Location services are disabled during an active work day.');
        }

        $locationPermission = strtolower((string) ($health['location_permission'] ?? 'unknown'));
        if ($workdayActive && in_array($locationPermission, ['denied', 'denied_forever', 'restricted', 'unknown'], true)) {
            $this->add($issues, 'location_permission', 'critical', 'Location permission is not usable during an active work day.');
        }

        $backgroundPermission = strtolower((string) ($health['background_location_permission'] ?? 'unknown'));
        if ($workdayActive && ! in_array($backgroundPermission, ['granted', 'always'], true)) {
            $this->add($issues, 'background_location_permission', 'critical', 'Background location permission is not fully enabled.');
        }

        if ($workdayActive && ($health['background_tracking_active'] ?? null) === false) {
            $this->add($issues, 'background_tracking_inactive', 'critical', 'Background location tracking is not active during the work day.');
        }

        $lastGpsFixAt = $this->dateOrNull($health['last_gps_fix_at'] ?? null);
        if ($workdayActive) {
            if ($lastGpsFixAt === null) {
                $this->add($issues, 'gps_fix_missing', 'critical', 'No usable GPS fix is available for the active work day.');
            } elseif ($lastGpsFixAt->lt(now()->subMinutes(10))) {
                $this->add($issues, 'gps_fix_stale', 'critical', 'The latest GPS fix is more than 10 minutes old.');
            } elseif ($lastGpsFixAt->lt(now()->subMinutes(5))) {
                $this->add($issues, 'gps_fix_delayed', 'warning', 'The latest GPS fix is delayed.');
            }
        }

        if (strtolower((string) ($health['notification_permission'] ?? 'unknown')) === 'denied') {
            $this->add($issues, 'notifications_denied', 'warning', 'Notification permission is disabled.');
        }

        if ($storageFree !== null) {
            if ($storageFree < 50) {
                $this->add($issues, 'storage_critical', 'critical', 'Device storage is critically low.');
            } elseif ($storageFree < 250) {
                $this->add($issues, 'storage_low', 'warning', 'Device storage is running low.');
            }
        }

        if ($blocked > 0) {
            $this->add($issues, 'sync_blocked', 'critical', $blocked.' sync item(s) require intervention.');
        }

        if ($failed > 0) {
            $this->add($issues, 'sync_failures', 'warning', $failed.' sync item(s) are waiting after a failure.');
        }

        if ($pending >= 100) {
            $this->add($issues, 'sync_backlog', 'warning', $pending.' items are waiting to synchronize.');
        }

        if (($health['mock_location_detected'] ?? false) === true) {
            $this->add($issues, 'mock_location_signal', 'warning', 'A mock-location signal was reported by the device.');
        }

        if (($health['root_signal_detected'] ?? false) === true) {
            $this->add($issues, 'root_signal', 'warning', 'The device reported a root/integrity warning signal.');
        }

        if (($health['is_physical_device'] ?? null) === false) {
            $this->add($issues, 'non_physical_device', 'info', 'The app appears to be running on an emulator or simulator.');
        }

        $network = strtolower((string) ($health['network_type'] ?? 'unknown'));
        if ($network === 'offline') {
            $this->add($issues, 'network_offline', 'warning', 'The device reported no active network connection.');
        }

        $status = collect($issues)->contains(fn (array $issue): bool => $issue['severity'] === 'critical')
            ? 'critical'
            : (collect($issues)->contains(fn (array $issue): bool => $issue['severity'] === 'warning')
                ? 'warning'
                : 'healthy');

        return [
            'status' => $status,
            'issues' => $issues,
        ];
    }

    private function add(array &$issues, string $code, string $severity, string $message): void
    {
        $issues[] = compact('code', 'severity', 'message');
    }

    private function intOrNull(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private function dateOrNull(mixed $value): ?CarbonImmutable
    {
        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value);
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
