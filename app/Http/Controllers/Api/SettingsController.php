<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
        $values = $settings->settingsFor($request->user()->tenant);

        return ApiResponse::success([
            'smart_routes_enabled' => $values['smart_routes_enabled'],
            'route_nearby_radius_km' => $values['route_nearby_radius_km'],
            'route_max_opportunities' => $values['route_max_opportunities'],
            'route_average_speed_kph' => $values['route_average_speed_kph'],
            'route_time_buffer_minutes' => $values['route_time_buffer_minutes'],
            'route_enforce_workday_capacity' => $values['route_enforce_workday_capacity'],
            'territory_auto_assign_enabled' => $values['territory_auto_assign_enabled'],
            'territory_heat_map_enabled' => $values['territory_heat_map_enabled'],
        ]);
    }
}
