<?php

namespace App\Support\Tracking;

use App\Models\CompanySetting;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;

/**
 * Company-controlled attendance & GPS tracking policy.
 *
 * Persisted through the existing company_settings key/value store under the
 * `tracking.*` namespace — no parallel settings framework. Effective values
 * always fall back to safe defaults, so mobile clients can never receive null
 * configuration. Manual start mode is the safe default.
 */
final class AttendanceTrackingSettings
{
    public const START_MODE_MANUAL = 'manual';

    public const START_MODE_AUTOMATIC = 'automatic';

    public const POLICY_VERSION = '1';

    /**
     * @var list<string>
     */
    public const KEYS = [
        'work_session_start_mode',
        'workday_start_time',
        'workday_end_time',
        'auto_end_session',
        'gps_tracking_enabled',
        'gps_moving_interval_seconds',
        'gps_stationary_interval_seconds',
        'gps_stale_after_minutes',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'work_session_start_mode' => self::START_MODE_MANUAL,
            'workday_start_time' => '08:00',
            'workday_end_time' => '17:00',
            'auto_end_session' => false,
            'gps_tracking_enabled' => true,
            'gps_moving_interval_seconds' => (int) config('tenancy.tracking.moving_interval_seconds', 15),
            'gps_stationary_interval_seconds' => (int) config('tenancy.tracking.stationary_interval_seconds', 60),
            'gps_stale_after_minutes' => (int) config('tenancy.tracking.current_location_stale_after_minutes', 15),
        ];
    }

    public static function settingKey(string $key): string
    {
        return "tracking.{$key}";
    }

    /**
     * Resolve the effective policy for a tenant, merging stored values over the
     * safe defaults.
     *
     * @return array{values: array<string, mixed>, updated_at: ?CarbonImmutable}
     */
    public static function effective(?Tenant $tenant = null): array
    {
        $tenant ??= TenantContext::tenant();

        $defaults = self::defaults();

        if ($tenant === null) {
            return ['values' => $defaults, 'updated_at' => null];
        }

        $rows = CompanySetting::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('key', array_map([self::class, 'settingKey'], self::KEYS))
            ->get(['key', 'value', 'updated_at']);

        $values = $defaults;
        $updatedAt = null;

        foreach ($rows as $row) {
            $shortKey = substr($row->key, strlen('tracking.'));

            if (! array_key_exists($shortKey, $defaults)) {
                continue;
            }

            $values[$shortKey] = self::normalize($shortKey, $row->value);

            if ($row->updated_at !== null && ($updatedAt === null || $row->updated_at->greaterThan($updatedAt))) {
                $updatedAt = $row->updated_at;
            }
        }

        return ['values' => $values, 'updated_at' => $updatedAt];
    }

    /**
     * Persist changed policy values and return only the fields that changed.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public static function update(Tenant $tenant, array $values): array
    {
        $before = self::effective($tenant)['values'];
        $changed = [];

        foreach (self::KEYS as $key) {
            if (! array_key_exists($key, $values)) {
                continue;
            }

            $new = self::normalize($key, $values[$key]);

            if ($before[$key] !== $new) {
                $changed[$key] = $new;
            }
        }

        foreach ($changed as $key => $value) {
            $tenant->setSetting(self::settingKey($key), $value);
        }

        return $changed;
    }

    /**
     * @return array<string, mixed>
     */
    public static function beforeValuesFor(Tenant $tenant, array $keys): array
    {
        $effective = self::effective($tenant)['values'];

        return collect($keys)
            ->mapWithKeys(fn ($key) => [$key => $effective[$key] ?? null])
            ->all();
    }

    private static function normalize(string $key, mixed $value): mixed
    {
        return match ($key) {
            'work_session_start_mode' => $value === self::START_MODE_AUTOMATIC
                ? self::START_MODE_AUTOMATIC
                : self::START_MODE_MANUAL,
            'workday_start_time', 'workday_end_time' => is_string($value) && $value !== ''
                ? substr($value, 0, 5)
                : null,
            'auto_end_session', 'gps_tracking_enabled' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'gps_moving_interval_seconds', 'gps_stationary_interval_seconds', 'gps_stale_after_minutes' => (int) $value,
            default => $value,
        };
    }
}
