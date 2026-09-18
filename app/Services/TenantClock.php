<?php

namespace App\Services;

use App\Models\Tenant;
use Carbon\CarbonImmutable;
use DateTimeZone;

final class TenantClock
{
    public function timezone(Tenant $tenant): string
    {
        $timezone = $tenant->timezone;

        return $timezone && in_array($timezone, timezone_identifiers_list(), true)
            ? $timezone
            : config('app.timezone');
    }

    public function now(Tenant $tenant): CarbonImmutable
    {
        return CarbonImmutable::now(new DateTimeZone($this->timezone($tenant)));
    }

    public function localDate(Tenant $tenant, mixed $instant = 'now'): string
    {
        return CarbonImmutable::parse($instant, 'UTC')
            ->setTimezone($this->timezone($tenant))
            ->toDateString();
    }

    public function dayBoundsUtc(Tenant $tenant, string $localDate): array
    {
        $start = CarbonImmutable::parse(
            $localDate.' 00:00:00',
            new DateTimeZone($this->timezone($tenant))
        )->utc();

        return [$start, $start->addDay()];
    }
}
