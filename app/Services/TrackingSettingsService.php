<?php

namespace App\Services;

use App\Models\CompanySetting;
use App\Models\Tenant;

final class TrackingSettingsService
{
    public function get(Tenant $tenant): array
    {
        $defaults = config('tenancy.defaults');
        $rows = CompanySetting::where('tenant_id', $tenant->id)
            ->where('key', 'like', 'tracking.%')
            ->pluck('value', 'key');

        $get = fn (string $key) => $rows->get('tracking.'.$key, $defaults[$key] ?? null);

        $moving = $this->int($get('gps_moving_interval_seconds'), 10, 3600, 15);
        $stationary = $this->int($get('gps_stationary_interval_seconds'), $moving, 7200, max(60, $moving));

        return [
            'work_session_start_mode' => in_array($get('work_session_start_mode'), ['manual', 'automatic'], true)
                ? $get('work_session_start_mode')
                : 'manual',
            'workday_start_time' => $this->time($get('workday_start_time'), '08:00'),
            'workday_end_time' => $this->time($get('workday_end_time'), '17:00'),
            'auto_end_session' => $this->bool($get('auto_end_session')),
            'gps_tracking_enabled' => $this->bool($get('gps_tracking_enabled')),
            'gps_moving_interval_seconds' => $moving,
            'gps_stationary_interval_seconds' => $stationary,
            'gps_stale_after_minutes' => $this->int($get('gps_stale_after_minutes'), 1, 720, 15),
            'privacy_policy_version' => (string) ($get('privacy_policy_version') ?: '1'),
            'tracking_auto_reminder_enabled' => $this->bool($get('tracking_auto_reminder_enabled')),
            'tracking_reminder_delay_minutes' => $this->int($get('tracking_reminder_delay_minutes'), 1, 240, 15),
            'tracking_reminder_repeat_minutes' => $this->int($get('tracking_reminder_repeat_minutes'), 15, 1440, 30),
            'tracking_reminder_daily_limit' => $this->int($get('tracking_reminder_daily_limit'), 1, 20, 3),
            'gps_retention_days' => $this->int($get('gps_retention_days'), 7, 3650, 90),
            'timezone' => (new TenantClock)->timezone($tenant),
            'updated_at' => CompanySetting::where('tenant_id', $tenant->id)
                ->where('key', 'like', 'tracking.%')
                ->max('updated_at'),
        ];
    }

    private function bool(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private function int(mixed $value, int $min, int $max, int $fallback): int
    {
        $integer = (int) $value;

        return $integer >= $min && $integer <= $max ? $integer : $fallback;
    }

    private function time(mixed $value, string $fallback): string
    {
        return is_string($value) && preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value)
            ? $value
            : $fallback;
    }
}
