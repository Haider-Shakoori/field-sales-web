<?php

namespace App\Support\Tracking;

use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Resolves tenant-local work dates while the database stores UTC timestamps.
 *
 * A tenant's configured timezone (e.g. Asia/Kabul) decides which calendar day a
 * work session belongs to. The configured application timezone is the documented
 * fallback when a tenant has no valid timezone.
 */
final class TenantClock
{
    public static function timezoneFor(?User $user): string
    {
        $timezone = $user?->tenant?->timezone;

        if (is_string($timezone) && in_array($timezone, timezone_identifiers_list(), true)) {
            return $timezone;
        }

        return config('app.timezone', 'UTC');
    }

    public static function dateFor(?User $user, ?CarbonInterface $instant = null): string
    {
        $instant ??= CarbonImmutable::now('UTC');

        return $instant->setTimezone(self::timezoneFor($user))->toDateString();
    }

    /**
     * Convert a tenant-local calendar date into its UTC range [start, end).
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function utcRangeForLocalDate(?User $user, string $localDate): array
    {
        $timezone = self::timezoneFor($user);

        $start = CarbonImmutable::parse($localDate, $timezone)->startOfDay()->utc();
        $end = CarbonImmutable::parse($localDate, $timezone)->addDay()->startOfDay()->utc();

        return [$start, $end];
    }
}
