<?php

namespace App\Http\Middleware;

use App\Models\Device;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveDevice
{
    /**
     * When a request carries an X-Device-UUID header, the device must belong to
     * the authenticated user within the current tenant and be active.
     * Revoked or foreign devices are rejected with 403.
     *
     * Requests without the header pass through (best-effort device tracking).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $deviceUuid = $request->header('X-Device-UUID');

        if ($deviceUuid === null) {
            return $next($request);
        }

        $user = $request->user();

        if ($user === null || ! TenantContext::hasContext()) {
            abort(403, 'Device cannot be validated.');
        }

        $device = Device::query()
            ->where('device_uuid', $deviceUuid)
            ->where('user_id', $user->id)
            ->first();

        abort_if($device === null, 404, 'Device not found.');

        abort_if(! $device->isActive(), 403, 'This device has been revoked. Please reinstall the app.');

        $request->attributes->set('device', $device);

        return $next($request);
    }
}
