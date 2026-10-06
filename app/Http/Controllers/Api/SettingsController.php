<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DeviceSettingsService;
use App\Services\FieldIntelligenceSettingsService;
use App\Services\TrackingSettingsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function show(
        Request $request,
        TrackingSettingsService $settings,
    ): JsonResponse {
        return ApiResponse::success(
            $settings->get($request->user()->tenant),
        );
    }

    public function features(
        Request $request,
        FieldIntelligenceSettingsService $settings,
    ): JsonResponse {
        return ApiResponse::success(
            $this->featurePayload($settings->settingsFor($request->user()->tenant)),
        );
    }

    public function sync(
        Request $request,
        TrackingSettingsService $trackingSettings,
        FieldIntelligenceSettingsService $featureSettings,
        DeviceSettingsService $deviceSettings,
    ): JsonResponse {
        $tenant = $request->user()->loadMissing('tenant')->tenant;
        $tracking = $trackingSettings->get($tenant);
        $features = $this->featurePayload($featureSettings->settingsFor($tenant));
        $device = $deviceSettings->get($tenant);

        $versionPayload = [
            'tracking' => $tracking,
            'features' => $features,
            'device' => $device,
        ];

        $updatedAt = collect([
            $tracking['updated_at'] ?? null,
            $device['updated_at'] ?? null,
            $tenant->updated_at?->toISOString(),
        ])->filter()->map(fn ($value) => (string) $value)->sort()->last();

        return ApiResponse::success([
            'configuration_version' => hash(
                'sha256',
                json_encode($versionPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ),
            'server_time' => now()->toISOString(),
            'updated_at' => $updatedAt,
            'tracking' => $tracking,
            'features' => $features,
            'device' => $device,
        ]);
    }

    private function featurePayload(array $values): array
    {
        return [
            'smart_routes_enabled' => $values['smart_routes_enabled'],
            'route_nearby_radius_km' => $values['route_nearby_radius_km'],
            'route_max_opportunities' => $values['route_max_opportunities'],
            'route_average_speed_kph' => $values['route_average_speed_kph'],
            'route_time_buffer_minutes' => $values['route_time_buffer_minutes'],
            'route_enforce_workday_capacity' => $values['route_enforce_workday_capacity'],
            'territory_auto_assign_enabled' => $values['territory_auto_assign_enabled'],
            'territory_heat_map_enabled' => $values['territory_heat_map_enabled'],
            'territory_under_covered_threshold_percent' => $values['territory_under_covered_threshold_percent'],
            'territory_stale_customer_days' => $values['territory_stale_customer_days'],
            'territory_stale_attention_percent' => $values['territory_stale_attention_percent'],
            'territory_geometry_audit_enabled' => $values['territory_geometry_audit_enabled'],
            'gamification_enabled' => $values['gamification_enabled'],
        ];
    }
}
