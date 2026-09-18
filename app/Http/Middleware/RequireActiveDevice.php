<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mutation endpoints (attendance start/end, GPS upload) require a bound device.
 *
 * Reuses the existing device validation semantics: the device must belong to
 * the authenticated user within the current tenant and must not be revoked.
 * Unlike the best-effort `EnsureActiveDevice`, a missing X-Device-UUID header is
 * rejected because device identity is part of the canonical write contract.
 */
class RequireActiveDevice extends EnsureActiveDevice
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->header('X-Device-UUID') === null) {
            abort(403, 'Device identification is required.');
        }

        if ($request->attributes->get('device') !== null) {
            return $next($request);
        }

        return parent::handle($request, $next);
    }
}
