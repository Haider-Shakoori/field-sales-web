<?php
namespace App\Services;
use App\Models\Tenant; use Carbon\CarbonImmutable; use DateTimeZone;
final class TenantClock { public function timezone(Tenant $tenant): string { $tz=$tenant->timezone; return $tz && in_array($tz, timezone_identifiers_list(), true) ? $tz : config('app.timezone'); } public function now(Tenant $tenant): CarbonImmutable{return CarbonImmutable::now(new DateTimeZone($this->timezone($tenant)));} public function localDate(Tenant $tenant, mixed $instant='now'): string{return CarbonImmutable::parse($instant,'UTC')->setTimezone($this->timezone($tenant))->toDateString();} }
