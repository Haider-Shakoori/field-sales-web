<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CompanySetting;
use App\Services\TrackingSettingsService;
use Illuminate\Http\Request;

class TrackingSettingsController extends Controller
{
    public function edit(Request $request, TrackingSettingsService $settings)
    {
        return view('admin.tracking-settings', [
            'settings' => $settings->get($request->user()->tenant),
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
            'privacy_policy_version' => 'required|string|max:50',
        ]);

        foreach ($validated as $key => $value) {
            CompanySetting::updateOrCreate(
                ['tenant_id' => $request->user()->tenant_id, 'key' => 'tracking.'.$key],
                ['value' => is_bool($value) ? ($value ? '1' : '0') : (string) $value],
            );
        }

        return back()->with('status', 'Tracking policy updated. Mobile devices will receive it on the next refresh.');
    }
}
