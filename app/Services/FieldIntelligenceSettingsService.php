<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;

final class FieldIntelligenceSettingsService
{
    public function settingsFor(Tenant $tenant): array
    {
        $settings = $tenant->settings ?? [];

        return [
            'smart_routes_enabled' => $this->boolean(
                data_get($settings, 'intelligence.smart_routes.enabled', true),
                true,
            ),
            'route_nearby_radius_km' => $this->floating(
                data_get($settings, 'intelligence.smart_routes.nearby_radius_km', 5),
                0.5,
                25,
                5,
            ),
            'route_max_opportunities' => $this->integer(
                data_get($settings, 'intelligence.smart_routes.max_opportunities', 10),
                0,
                10,
                10,
            ),
            'route_average_speed_kph' => $this->floating(
                data_get($settings, 'intelligence.smart_routes.average_speed_kph', 25),
                5,
                100,
                25,
            ),
            'route_time_buffer_minutes' => $this->integer(
                data_get($settings, 'intelligence.smart_routes.time_buffer_minutes', 30),
                0,
                180,
                30,
            ),
            'route_enforce_workday_capacity' => $this->boolean(
                data_get($settings, 'intelligence.smart_routes.enforce_workday_capacity', true),
                true,
            ),
            'territory_auto_assign_enabled' => $this->boolean(
                data_get($settings, 'intelligence.territories.auto_assign_customers', true),
                true,
            ),
            'territory_heat_map_enabled' => $this->boolean(
                data_get($settings, 'intelligence.territories.heat_map_enabled', true),
                true,
            ),
            'territory_under_covered_threshold_percent' => $this->integer(
                data_get($settings, 'intelligence.territories.under_covered_threshold_percent', 60),
                1,
                100,
                60,
            ),
            'territory_stale_customer_days' => $this->integer(
                data_get($settings, 'intelligence.territories.stale_customer_days', 30),
                7,
                180,
                30,
            ),
            'territory_stale_attention_percent' => $this->integer(
                data_get($settings, 'intelligence.territories.stale_attention_percent', 40),
                1,
                100,
                40,
            ),
            'territory_geometry_audit_enabled' => $this->boolean(
                data_get($settings, 'intelligence.territories.geometry_audit_enabled', true),
                true,
            ),
            'gamification_enabled' => $this->boolean(
                data_get($settings, 'engagement.gamification.enabled', false),
                false,
            ),
        ];
    }

    public function smartRoutesEnabled(User|Tenant $subject): bool
    {
        return $this->settingsFor($this->tenant($subject))['smart_routes_enabled'];
    }

    public function routeNearbyRadiusKm(User|Tenant $subject): float
    {
        return $this->settingsFor($this->tenant($subject))['route_nearby_radius_km'];
    }

    public function routeMaxOpportunities(User|Tenant $subject): int
    {
        return $this->settingsFor($this->tenant($subject))['route_max_opportunities'];
    }

    public function routeAverageSpeedKph(User|Tenant $subject): float
    {
        return $this->settingsFor($this->tenant($subject))['route_average_speed_kph'];
    }

    public function routeTimeBufferMinutes(User|Tenant $subject): int
    {
        return $this->settingsFor($this->tenant($subject))['route_time_buffer_minutes'];
    }

    public function routeEnforceWorkdayCapacity(User|Tenant $subject): bool
    {
        return $this->settingsFor($this->tenant($subject))['route_enforce_workday_capacity'];
    }

    public function territoryAutoAssignEnabled(User|Tenant $subject): bool
    {
        return $this->settingsFor($this->tenant($subject))['territory_auto_assign_enabled'];
    }

    public function territoryHeatMapEnabled(User|Tenant $subject): bool
    {
        return $this->settingsFor($this->tenant($subject))['territory_heat_map_enabled'];
    }

    public function territoryUnderCoveredThresholdPercent(User|Tenant $subject): int
    {
        return $this->settingsFor($this->tenant($subject))['territory_under_covered_threshold_percent'];
    }

    public function territoryStaleCustomerDays(User|Tenant $subject): int
    {
        return $this->settingsFor($this->tenant($subject))['territory_stale_customer_days'];
    }

    public function territoryStaleAttentionPercent(User|Tenant $subject): int
    {
        return $this->settingsFor($this->tenant($subject))['territory_stale_attention_percent'];
    }

    public function territoryGeometryAuditEnabled(User|Tenant $subject): bool
    {
        return $this->settingsFor($this->tenant($subject))['territory_geometry_audit_enabled'];
    }

    public function gamificationEnabled(User|Tenant $subject): bool
    {
        return $this->settingsFor($this->tenant($subject))['gamification_enabled'];
    }

    private function tenant(User|Tenant $subject): Tenant
    {
        if ($subject instanceof Tenant) {
            return $subject;
        }

        return $subject->loadMissing('tenant')->tenant;
    }

    private function boolean(mixed $value, bool $fallback): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (in_array($value, [1, '1', 'true', 'yes', 'on'], true)) {
            return true;
        }

        if (in_array($value, [0, '0', 'false', 'no', 'off'], true)) {
            return false;
        }

        return $fallback;
    }

    private function integer(mixed $value, int $min, int $max, int $fallback): int
    {
        $value = filter_var($value, FILTER_VALIDATE_INT);

        return $value !== false && $value >= $min && $value <= $max
            ? $value
            : $fallback;
    }

    private function floating(mixed $value, float $min, float $max, float $fallback): float
    {
        if (! is_numeric($value)) {
            return $fallback;
        }

        $value = (float) $value;

        return $value >= $min && $value <= $max ? $value : $fallback;
    }
}
