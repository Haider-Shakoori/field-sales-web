<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InitializeTenancy
{
    /**
     * Resolve the tenant execution context for the current request.
     *
     * Process:
     *  1. Always clear stale state from any previous request in the same worker.
     *  2. Guest request — leave context as Uninitialized (fail-closed on any
     *     accidental tenant-owned query).
     *  3. Authenticated company user (tenant_id !== null) — find the tenant,
     *     verify subscription, set concrete tenant context.
     *  4. Authenticated super admin with null tenant_id — activate explicit
     *     platform context (HTTP bypass).
     *  5. Anything else — 403 (a company user without a valid tenant is an
     *     unrecoverable account state).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $context = app(TenantContext::class);
        $context->clear();

        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        if (! $user->is_active) {
            abort(403, 'Your account has been deactivated. Please contact support.');
        }

        if ($user->tenant_id !== null) {
            $tenant = Tenant::withTrashed()->find($user->tenant_id);

            if ($tenant === null || ! $tenant->isSubscribed()) {
                abort(403, 'Your company subscription is not active. Please contact support.');
            }

            $context->set($tenant);

            return $next($request);
        }

        if ($user->isSuperAdmin()) {
            $context->enterPlatformForUser($user);

            return $next($request);
        }

        abort(403, 'Unauthorized platform access.');
    }
}
