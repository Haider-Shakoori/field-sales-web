<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Services\DeviceActivityService;
use App\Services\DeviceHealthService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceHealthController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        /** @var Device|null $device */
        $device = $request->attributes->get('device');
        abort_unless($device, 404);

        return ApiResponse::success($this->payload($device->fresh()));
    }

    public function store(
        Request $request,
        DeviceHealthService $healthService,
        DeviceActivityService $activity,
    ): JsonResponse {
        /** @var Device|null $device */
        $device = $request->attributes->get('device');
        abort_unless($device, 404);

        $validated = $request->validate([
            'battery_level' => ['nullable', 'integer', 'between:0,100'],
            'is_charging' => ['nullable', 'boolean'],
            'power_save_mode' => ['nullable', 'boolean'],
            'battery_optimization_exempt' => ['nullable', 'boolean'],
            'background_restricted' => ['nullable', 'boolean'],

            'location_services_enabled' => ['nullable', 'boolean'],
            'location_permission' => ['nullable', 'string', 'max:30'],
            'background_location_permission' => ['nullable', 'string', 'max:30'],
            'notification_permission' => ['nullable', 'string', 'max:30'],
            'background_tracking_active' => ['nullable', 'boolean'],
            'workday_active' => ['required', 'boolean'],

            'network_type' => ['nullable', 'string', 'max:30'],
            'storage_free_mb' => ['nullable', 'integer', 'min:0'],
            'storage_total_mb' => ['nullable', 'integer', 'min:0'],

            'pending_sync_count' => ['required', 'integer', 'min:0', 'max:1000000'],
            'failed_sync_count' => ['required', 'integer', 'min:0', 'max:1000000'],
            'blocked_sync_count' => ['required', 'integer', 'min:0', 'max:1000000'],
            'last_sync_at' => ['nullable', 'date'],

            'is_physical_device' => ['nullable', 'boolean'],
            'root_signal_detected' => ['nullable', 'boolean'],
            'mock_location_detected' => ['nullable', 'boolean'],
            'last_gps_fix_at' => ['nullable', 'date'],
        ]);

        $previousStatus = $device->effectiveHealthStatus();
        $health = $healthService->classify($validated);

        $device->forceFill([
            ...$validated,
            'health_status' => $health['status'],
            'health_issues' => $health['issues'],
            'health_reported_at' => now(),
            'last_seen_at' => now(),
        ])->save();

        if ($previousStatus !== $health['status']) {
            $activity->record(
                $device,
                'device.health_changed',
                [
                    'from' => $previousStatus,
                    'to' => $health['status'],
                    'issue_codes' => collect($health['issues'])->pluck('code')->values()->all(),
                ],
                $request->user(),
                $health['status'] === 'critical' ? 'critical' : 'info',
            );
        }

        return ApiResponse::success($this->payload($device->fresh()));
    }

    private function payload(Device $device): array
    {
        return [
            'device_id' => $device->uuid,
            'health_status' => $device->effectiveHealthStatus(),
            'health_issues' => $device->health_issues ?? [],
            'health_reported_at' => $device->health_reported_at?->toISOString(),
            'battery' => [
                'level' => $device->battery_level,
                'is_charging' => $device->is_charging,
                'power_save_mode' => $device->power_save_mode,
                'optimization_exempt' => $device->battery_optimization_exempt,
            ],
            'location' => [
                'services_enabled' => $device->location_services_enabled,
                'permission' => $device->location_permission,
                'background_permission' => $device->background_location_permission,
                'background_tracking_active' => $device->background_tracking_active,
                'last_gps_fix_at' => $device->last_gps_fix_at?->toISOString(),
                'mock_location_detected' => $device->mock_location_detected,
            ],
            'network_type' => $device->network_type,
            'sync' => [
                'pending' => $device->pending_sync_count,
                'failed' => $device->failed_sync_count,
                'blocked' => $device->blocked_sync_count,
                'last_sync_at' => $device->last_sync_at?->toISOString(),
            ],
            'storage' => [
                'free_mb' => $device->storage_free_mb,
                'total_mb' => $device->storage_total_mb,
            ],
            'permissions' => [
                'notifications' => $device->notification_permission,
            ],
            'security' => [
                'is_physical_device' => $device->is_physical_device,
                'root_signal_detected' => $device->root_signal_detected,
            ],
            'background_restricted' => $device->background_restricted,
            'workday_active' => $device->workday_active,
        ];
    }
}
