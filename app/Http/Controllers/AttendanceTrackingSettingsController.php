<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateAttendanceTrackingSettingsRequest;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use App\Support\Tracking\AttendanceTrackingSettings;
use App\Support\Tracking\TenantClock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Settings → Attendance & Tracking (company policy).
 *
 * Viewing requires settings:view; changing policy requires settings:manage.
 * Laravel owns policy only — actual automatic session start/end is executed by
 * the mobile app through the existing attendance endpoints.
 */
class AttendanceTrackingSettingsController extends Controller
{
    public function edit(Request $request): View
    {
        abort_unless($request->user()->hasPermission('settings:view'), 403);

        $tenant = TenantContext::tenant();
        abort_if($tenant === null, 403);

        $effective = AttendanceTrackingSettings::effective($tenant);

        return view('pages.settings.attendance-tracking', [
            'settings' => $effective['values'],
            'settingsUpdatedAt' => $effective['updated_at'],
            'timezone' => TenantClock::timezoneFor($request->user()),
            'canManage' => $request->user()->hasPermission('settings:manage'),
        ]);
    }

    public function update(UpdateAttendanceTrackingSettingsRequest $request): RedirectResponse
    {
        $tenant = TenantContext::tenant();
        abort_if($tenant === null, 403);

        $data = $request->validated();
        $data['auto_end_session'] = $request->boolean('auto_end_session');
        $data['gps_tracking_enabled'] = $request->boolean('gps_tracking_enabled');

        $before = AttendanceTrackingSettings::effective($tenant)['values'];
        $changed = AttendanceTrackingSettings::update($tenant, $data);

        if ($changed !== []) {
            $oldValues = collect($changed)
                ->mapWithKeys(fn ($value, $key) => [$key => $before[$key]])
                ->all();

            AuditLogger::log('tracking.settings.updated', $tenant, $oldValues, $changed);
        }

        return back()->with('status', 'Attendance & tracking settings updated.');
    }
}
