<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CompanySetting;
use App\Services\AuditService;
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

    public function update(Request $request, TrackingSettingsService $settings, AuditService $audit)
    {
        $validated = $request->validate([
            'work_session_start_mode' => ['required', 'in:manual,automatic'],
            'workday_start_time' => ['required', 'date_format:H:i'],
            'workday_end_time' => ['required', 'date_format:H:i'],
            'auto_end_session' => ['nullable', 'boolean'],
            'gps_tracking_enabled' => ['nullable', 'boolean'],
            'gps_moving_interval_seconds' => ['required', 'integer', 'min:10', 'max:3600'],
            'gps_stationary_interval_seconds' => ['required', 'integer', 'min:10', 'max:7200', 'gte:gps_moving_interval_seconds'],
            'gps_stale_after_minutes' => ['required', 'integer', 'min:1', 'max:720'],
            'privacy_policy_version' => ['required', 'string', 'max:50'],
            'tracking_auto_reminder_enabled' => ['nullable', 'boolean'],
            'tracking_reminder_delay_minutes' => ['required', 'integer', 'min:1', 'max:240'],
            'tracking_reminder_repeat_minutes' => ['required', 'integer', 'min:15', 'max:1440'],
            'tracking_reminder_daily_limit' => ['required', 'integer', 'min:1', 'max:20'],
            'gps_retention_days' => ['required', 'integer', 'min:7', 'max:3650'],
        ]);

        $before = $settings->get($request->user()->tenant);

        foreach ($validated as $key => $value) {
            CompanySetting::updateOrCreate(
                ['tenant_id' => $request->user()->tenant_id, 'key' => 'tracking.'.$key],
                ['value' => is_bool($value) ? ($value ? '1' : '0') : (string) $value]
            );
        }

        $after = $settings->get($request->user()->tenant);
        $changes = [];

        foreach ($validated as $key => $_) {
            if (($before[$key] ?? null) !== ($after[$key] ?? null)) {
                $changes[$key] = [
                    'before' => $before[$key] ?? null,
                    'after' => $after[$key] ?? null,
                ];
            }
        }

        if ($changes !== []) {
            $audit->record('tracking.settings.updated', $request->user()->tenant, ['changes' => $changes], $request);
        }

        return back()->with('status', 'Attendance, GPS and reminder policy updated. Mobile devices will receive it on refresh.');
    }
}
