<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DeviceController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Device::class);

        $query = Device::query()->with(['tenant', 'user', 'salesman']);

        if (TenantContext::hasContext()) {
            $query->where('tenant_id', TenantContext::currentId());
        }

        if ($search = $request->string('search')->trim()->toString()) {
            $query->where(fn ($q) => $q
                ->where('device_uuid', 'like', "%{$search}%")
                ->orWhere('installation_uuid', 'like', "%{$search}%")
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")));
        }

        if ($status = $request->string('status')->toString()) {
            if ($status === 'active') {
                $query->active();
            } elseif ($status === 'revoked') {
                $query->revoked();
            }
        }

        $devices = $query->orderByDesc('last_seen_at')->paginate(20)->withQueryString();

        return view('pages.devices.index', compact('devices'));
    }

    public function show(Device $device): View
    {
        $this->authorize('view', $device);

        $device->load(['user', 'salesman']);

        return view('pages.devices.show', compact('device'));
    }

    public function revoke(Device $device): RedirectResponse
    {
        $this->authorize('revoke', $device);

        abort_if(TenantContext::currentId() === null || $device->tenant_id !== TenantContext::currentId(), 403);

        $device->revoke();

        $this->revokeSanctumTokens($device);

        AuditLogger::log('device.revoked', $device, [], [
            'device_uuid' => $device->device_uuid,
            'installation_uuid' => $device->installation_uuid,
            'user_id' => $device->user_id,
            'salesman_id' => $device->salesman_id,
        ]);

        return back()->with('status', 'Device revoked.');
    }

    /**
     * Delete Sanctum tokens bound to this device so the app is force-logged out.
     */
    private function revokeSanctumTokens(Device $device): void
    {
        $device->user()->firstOrFail()->tokens()
            ->where('name', 'device:'.$device->device_uuid)
            ->delete();
    }
}
