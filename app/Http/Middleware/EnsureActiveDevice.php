<?php

namespace App\Http\Middleware;

use App\Exceptions\DeviceException;
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
            throw DeviceException::mismatch();
        }

        $device = Device::query()
            ->where('device_uuid', $deviceUuid)
            ->where('user_id', $user->id)
            ->first();

        if ($device === null) {
            throw DeviceException::notFound();
        }

        if (! $device->isActive()) {
            throw DeviceException::revoked();
        }

        $request->attributes->set('device', $device);

        return $next($request);
    }
}
