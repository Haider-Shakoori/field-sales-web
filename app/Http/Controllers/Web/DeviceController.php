<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\RevokeDeviceRequest;
use App\Models\Device;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DeviceController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Device::class);

        return view('admin.devices.index', [
            'devices' => Device::with(['user', 'salesman'])
                ->latest('registered_at')
                ->paginate(30),
        ]);
    }

    public function show(Device $device): View
    {
        Gate::authorize('view', $device);

        return view('admin.devices.show', [
            'device' => $device->load(['user', 'salesman', 'revoker']),
        ]);
    }

    public function revoke(
        RevokeDeviceRequest $request,
        Device $device,
        AuditLogger $audit,
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
        ]);

        $device->user->tokens()
            ->where('name', 'mobile-'.$device->uuid)
            ->delete();

        $audit->record('device.revoked', $device, $before, $this->auditValues($device));

        return back()->with('status', 'Device revoked and its mobile token invalidated.');
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
            'revoked_at' => $device->revoked_at?->toIso8601String(),
            'revoked_by' => $device->revoked_by,
            'revocation_reason' => $device->revocation_reason,
        ];
    }
}
