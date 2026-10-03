<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CompanySetting;
use App\Services\DeviceSettingsService;
use App\Services\TrackingSettingsService;
use Illuminate\Http\Request;

class TrackingSettingsController extends Controller
{
    public function edit(
        Request $request,
        TrackingSettingsService $settings,
        DeviceSettingsService $deviceSettings,
    ) {
        return view('admin.tracking-settings', [
            'settings' => $settings->get($request->user()->tenant),
            'deviceSettings' => $deviceSettings->get($request->user()->tenant),
            'tenant' => $request->user()->tenant,
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'work_session_start_mode' => 'required|in:manual,automatic',
            'workday_start_time' => 'required|date_format:H:i',
            'workday_end_time' => 'required|date_format:H:i',
            'auto_end_session' => 'nullable|boolean',
            'gps_tracking_enabled' => 'nullable|boolean',
            'gps_moving_interval_seconds' => 'required|integer|min:10|max:3600',
            'gps_stationary_interval_seconds' => 'required|integer|min:10|max:7200|gte:gps_moving_interval_seconds',
            'gps_stale_after_minutes' => 'required|integer|min:1|max:720',
            'idle_alerts_enabled' => 'nullable|boolean',
            'idle_alert_after_minutes' => 'required|integer|min:5|max:240',
            'idle_alert_repeat_minutes' => 'required|integer|min:15|max:480',
            'idle_escalation_enabled' => 'nullable|boolean',
            'idle_escalate_after_minutes' => 'required|integer|min:15|max:480',
            'privacy_policy_version' => 'required|string|max:50',
            'device_restriction_enabled' => 'nullable|boolean',
            'device_approval_required' => 'nullable|boolean',
            'secondary_device_enabled' => 'nullable|boolean',
            'max_active_devices' => 'nullable|integer|min:2|max:3',
            'lost_device_workflow_enabled' => 'nullable|boolean',
            'device_activity_history_enabled' => 'nullable|boolean',
            'remote_diagnostics_enabled' => 'nullable|boolean',
            'push_test_enabled' => 'nullable|boolean',
            'gps_background_test_enabled' => 'nullable|boolean',
            'sync_test_enabled' => 'nullable|boolean',
        ]);

        $deviceKeys = [
            'device_restriction_enabled' => 'device_restriction_enabled',
            'device_approval_required' => 'approval_required',
            'secondary_device_enabled' => 'secondary_device_enabled',
            'max_active_devices' => 'max_active_devices',
            'lost_device_workflow_enabled' => 'lost_device_workflow_enabled',
            'device_activity_history_enabled' => 'activity_history_enabled',
            'remote_diagnostics_enabled' => 'remote_diagnostics_enabled',
            'push_test_enabled' => 'push_test_enabled',
            'gps_background_test_enabled' => 'gps_background_test_enabled',
            'sync_test_enabled' => 'sync_test_enabled',
        ];

        foreach ($validated as $key => $value) {
            $settingKey = array_key_exists($key, $deviceKeys)
                ? 'device.'.$deviceKeys[$key]
                : 'tracking.'.$key;

            CompanySetting::updateOrCreate(
                ['tenant_id' => $request->user()->tenant_id, 'key' => $settingKey],
                ['value' => is_bool($value) ? ($value ? '1' : '0') : (string) $value],
            );
        }

        return back()->with('status', 'Tracking and optional device controls updated.');
    }
}
