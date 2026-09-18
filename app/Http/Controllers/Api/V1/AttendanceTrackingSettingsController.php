<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use App\Support\Tenancy\TenantContext;
use App\Support\Tracking\AttendanceTrackingSettings;
use App\Support\Tracking\TenantClock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only mobile configuration for attendance & tracking policy.
 *
 * Flutter consumes this to evaluate automatic work-session windows and GPS
 * collection intervals. Mobile clients can never update company policy here.
 */
class AttendanceTrackingSettingsController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $tenant = TenantContext::tenant();
        abort_if($tenant === null, 403, 'A tenant context is required.');

        $effective = AttendanceTrackingSettings::effective($tenant);
        $values = $effective['values'];

        return ApiResponse::success([
            'work_session_start_mode' => $values['work_session_start_mode'],
            'workday_start_time' => $values['workday_start_time'],
            'workday_end_time' => $values['workday_end_time'],
            'auto_end_session' => (bool) $values['auto_end_session'],
            'gps_tracking_enabled' => (bool) $values['gps_tracking_enabled'],
            'gps_moving_interval_seconds' => (int) $values['gps_moving_interval_seconds'],
            'gps_stationary_interval_seconds' => (int) $values['gps_stationary_interval_seconds'],
            'gps_stale_after_minutes' => (int) $values['gps_stale_after_minutes'],
            'timezone' => TenantClock::timezoneFor($request->user()),
            'privacy_policy_version' => AttendanceTrackingSettings::POLICY_VERSION,
            'updated_at' => $effective['updated_at']?->toIso8601String(),
        ]);
    }
}
