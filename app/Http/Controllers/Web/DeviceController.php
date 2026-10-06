<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\RevokeDeviceRequest;
use App\Models\Device;
use App\Services\AuditLogger;
use App\Services\DeviceActivityService;
use App\Services\DeviceOperationalTestService;
use App\Services\DeviceSettingsService;
use App\Services\MobileAppPolicy;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DeviceController extends Controller
{
    public function index(
        MobileAppPolicy $mobilePolicy,
        DeviceSettingsService $deviceSettings,
    ): View {
        Gate::authorize('viewAny', Device::class);

        $activeDevices = Device::query()
            ->where('is_active', true)
            ->whereNull('revoked_at')
            ->get([
                'pending_sync_count',
                'failed_sync_count',
                'blocked_sync_count',
                'last_sync_at',
            ]);

        $attentionDevices = $activeDevices->filter(
            fn (Device $device): bool => (int) $device->pending_sync_count > 0
                || (int) $device->failed_sync_count > 0
                || (int) $device->blocked_sync_count > 0
                || $device->last_sync_at === null
                || $device->last_sync_at->lt(now()->subMinutes(30)),
        );

        return view('admin.devices.index', [
            'mobilePolicy' => $mobilePolicy,
            'deviceSettings' => $deviceSettings->get(request()->user()->tenant),
            'syncSummary' => [
                'active' => $activeDevices->count(),
                'healthy' => max(0, $activeDevices->count() - $attentionDevices->count()),
                'attention' => $attentionDevices->count(),
                'pending' => (int) $activeDevices->sum('pending_sync_count'),
                'failed' => (int) $activeDevices->sum('failed_sync_count'),
                'blocked' => (int) $activeDevices->sum('blocked_sync_count'),
            ],
            'devices' => Device::with(['user', 'salesman'])
                ->latest('registered_at')
                ->paginate(min(100, max(10, request()->integer('per_page', 30))))
                ->withQueryString(),
        ]);
    }

    public function show(
        Device $device,
        MobileAppPolicy $mobilePolicy,
        DeviceSettingsService $deviceSettings,
    ): View {
        Gate::authorize('view', $device);

        $device->load([
            'user',
            'salesman',
            'revoker',
            'approver',
            'managementActor',
            'tenant',
        ]);
        $policy = $deviceSettings->get($device->tenant);

        return view('admin.devices.show', [
            'device' => $device,
            'mobilePolicy' => $mobilePolicy,
            'deviceSettings' => $policy,
            'recentDiagnostics' => $device->diagnostics()
                ->latest('occurred_at')
                ->limit(8)
                ->get(),
            'deviceActivity' => $policy['activity_history_enabled']
                ? $device->activityLogs()
                    ->with('actor')
                    ->latest('occurred_at')
                    ->limit(30)
                    ->get()
                : collect(),
        ]);
    }

    public function approve(
        Request $request,
        Device $device,
        DeviceSettingsService $settings,
        DeviceActivityService $activity,
        AuditLogger $audit,
    ): RedirectResponse {
        Gate::authorize('approve', $device);
        $this->requireFeature($device, $settings, 'approval_required');

        if ($device->isApproved()) {
            return back()->with('status', 'Device is already approved.');
        }

        $before = $this->auditValues($device);

        $device->forceFill([
            'approval_status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $request->user()->id,
        ])->save();

        $audit->record('device.approved', $device, $before, $this->auditValues($device));
        $activity->record(
            $device,
            'device.approved',
            [],
            $request->user(),
        );

        return back()->with(
            'status',
            'Device approved. The user can sign in again from this phone.',
        );
    }

    public function forceLogout(
        Request $request,
        Device $device,
        AuditLogger $audit,
        DeviceActivityService $activity,
    ): RedirectResponse {
        Gate::authorize('forceLogout', $device);

        $before = $this->auditValues($device);

        $device->user?->tokens()
            ->where('name', 'mobile-'.$device->uuid)
            ->delete();

        $device->forceFill([
            'push_token' => null,
        ])->save();

        $audit->record(
            'device.force_logout',
            $device,
            $before,
            $this->auditValues($device),
        );
        $activity->record(
            $device,
            'device.force_logout',
            [],
            $request->user(),
            'warning',
        );

        return back()->with(
            'status',
            'Device signed out remotely. The next mobile request will require login again.',
        );
    }

    public function markStatus(
        Request $request,
        Device $device,
        DeviceSettingsService $settings,
        DeviceActivityService $activity,
        AuditLogger $audit,
    ): RedirectResponse {
        Gate::authorize('manageStatus', $device);
        $this->requireFeature($device, $settings, 'lost_device_workflow_enabled');

        $validated = $request->validate([
            'status' => ['required', Rule::in(['lost', 'stolen'])],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $before = $this->auditValues($device);
        $reason = trim((string) ($validated['reason'] ?? ''));

        $device->forceFill([
            'management_status' => $validated['status'],
            'management_status_at' => now(),
            'management_status_by' => $request->user()->id,
            'management_status_reason' => $reason !== '' ? $reason : null,
            'is_active' => false,
            'revoked_at' => now(),
            'revoked_by' => $request->user()->id,
            'revocation_reason' => 'marked_'.$validated['status']
                .($reason !== '' ? ': '.$reason : ''),
            'push_token' => null,
        ])->save();

        $device->user?->tokens()
            ->where('name', 'mobile-'.$device->uuid)
            ->delete();

        if ($device->is_primary) {
            $replacement = Device::query()
                ->where('user_id', $device->user_id)
                ->whereKeyNot($device->id)
                ->where('is_active', true)
                ->whereNull('revoked_at')
                ->latest('last_seen_at')
                ->first();

            if ($replacement) {
                $replacement->forceFill(['is_primary' => true])->save();
                $device->forceFill(['is_primary' => false])->save();
            }
        }

        $audit->record(
            'device.marked_'.$validated['status'],
            $device,
            $before,
            $this->auditValues($device),
        );
        $activity->record(
            $device,
            'device.marked_'.$validated['status'],
            ['reason' => $reason !== '' ? $reason : null],
            $request->user(),
            'critical',
        );

        return back()->with(
            'status',
            'Device marked '.$validated['status'].' and its mobile access was revoked.',
        );
    }

    public function requestDiagnostics(
        Request $request,
        Device $device,
        DeviceSettingsService $settings,
        NotificationService $notifications,
        DeviceActivityService $activity,
    ): RedirectResponse {
        Gate::authorize('runDeviceAction', $device);
        $this->requireFeature($device, $settings, 'remote_diagnostics_enabled');

        $notification = $notifications->notifyDeviceSafely(
            $device,
            'device.diagnostics_request',
            'FieldPulse device check',
            'An administrator requested a fresh device-health report.',
            [
                'action' => 'request_device_health',
                'device_id' => $device->uuid,
            ],
            'high',
        );
        $delivery = $notification?->deliveries?->first();

        $activity->record(
            $device,
            'device.diagnostics_requested',
            ['delivery_status' => $delivery?->status],
            $request->user(),
        );

        return back()->with(
            'status',
            $this->deliveryMessage(
                $delivery?->status,
                'Diagnostics request queued for this device.',
            ),
        );
    }

    public function testPush(
        Request $request,
        Device $device,
        DeviceSettingsService $settings,
        NotificationService $notifications,
        DeviceActivityService $activity,
    ): RedirectResponse {
        Gate::authorize('runDeviceAction', $device);
        $this->requireFeature($device, $settings, 'push_test_enabled');

        $notification = $notifications->notifyDeviceSafely(
            $device,
            'device.push_test',
            'FieldPulse push test',
            'This is a test notification from the FieldPulse device console.',
            [
                'action' => 'device_push_test',
                'device_id' => $device->uuid,
            ],
            'high',
        );
        $delivery = $notification?->deliveries?->first();

        $activity->record(
            $device,
            'device.push_test',
            ['delivery_status' => $delivery?->status],
            $request->user(),
        );

        return back()->with(
            'status',
            $this->deliveryMessage(
                $delivery?->status,
                'Push notification test queued.',
            ),
        );
    }

    public function testGpsBackground(
        Request $request,
        Device $device,
        DeviceSettingsService $settings,
        DeviceOperationalTestService $tests,
        DeviceActivityService $activity,
    ): RedirectResponse {
        Gate::authorize('runDeviceAction', $device);
        $this->requireFeature($device, $settings, 'gps_background_test_enabled');

        $result = $tests->gpsBackground($device);

        $activity->record(
            $device,
            'device.gps_background_test',
            $result,
            $request->user(),
            $result['status'] === 'failed' ? 'warning' : 'info',
        );

        return back()
            ->with('device_test_name', 'GPS & background test')
            ->with('device_test_result', $result);
    }

    public function testSync(
        Request $request,
        Device $device,
        DeviceSettingsService $settings,
        DeviceOperationalTestService $tests,
        DeviceActivityService $activity,
    ): RedirectResponse {
        Gate::authorize('runDeviceAction', $device);
        $this->requireFeature($device, $settings, 'sync_test_enabled');

        $result = $tests->sync($device);

        $activity->record(
            $device,
            'device.sync_test',
            $result,
            $request->user(),
            $result['status'] === 'failed' ? 'warning' : 'info',
        );

        return back()
            ->with('device_test_name', 'Sync test')
            ->with('device_test_result', $result);
    }

    public function revoke(
        RevokeDeviceRequest $request,
        Device $device,
        AuditLogger $audit,
        DeviceActivityService $activity,
    ): RedirectResponse {
        if ($device->isRevoked()) {
            return back()->with('status', 'Device is already revoked.');
        }

        $before = $this->auditValues($device);

        $device->update([
            'is_active' => false,
            'revoked_at' => now(),
            'revoked_by' => $request->user()->id,
            'revocation_reason' => $request->validated('reason'),
            'management_status' => 'revoked',
            'management_status_at' => now(),
            'management_status_by' => $request->user()->id,
        ]);

        $device->user->tokens()
            ->where('name', 'mobile-'.$device->uuid)
            ->delete();

        $audit->record(
            'device.revoked',
            $device,
            $before,
            $this->auditValues($device),
        );
        $activity->record(
            $device,
            'device.revoked',
            ['reason' => $request->validated('reason')],
            $request->user(),
            'warning',
        );

        return back()->with(
            'status',
            'Device revoked and its mobile token invalidated.',
        );
    }

    private function requireFeature(
        Device $device,
        DeviceSettingsService $settings,
        string $key,
    ): void {
        $device->loadMissing('tenant');

        abort_unless(
            (bool) ($settings->get($device->tenant)[$key] ?? false),
            404,
        );
    }

    private function deliveryMessage(
        ?string $status,
        string $queuedMessage,
    ): string {
        return match ($status) {
            'pending' => $queuedMessage,
            'sent' => 'Push delivery completed successfully.',
            'skipped' => 'The action could not be pushed because push delivery is disabled or this device has no push token.',
            'failed' => 'The push provider reported a delivery failure.',
            default => 'The device action could not be queued.',
        };
    }

    private function auditValues(Device $device): array
    {
        return [
            'user_id' => $device->user_id,
            'salesman_id' => $device->salesman_id,
            'device_uuid' => $device->device_uuid,
            'installation_uuid' => $device->installation_uuid,
            'platform' => $device->platform,
            'app_version' => $device->app_version,
            'is_active' => $device->is_active,
            'approval_status' => $device->approval_status,
            'approved_at' => $device->approved_at?->toIso8601String(),
            'approved_by' => $device->approved_by,
            'is_primary' => $device->is_primary,
            'management_status' => $device->management_status,
            'management_status_at' => $device->management_status_at?->toIso8601String(),
            'management_status_by' => $device->management_status_by,
            'management_status_reason' => $device->management_status_reason,
            'revoked_at' => $device->revoked_at?->toIso8601String(),
            'revoked_by' => $device->revoked_by,
            'revocation_reason' => $device->revocation_reason,
        ];
    }
}
