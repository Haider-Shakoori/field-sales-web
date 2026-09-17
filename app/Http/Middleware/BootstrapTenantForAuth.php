<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class BootstrapTenantForAuth
{
    /**
     * Resolve Sanctum token authentication outside of any tenant context.
     *
     * Sanctum's guard loads the tokenable user eagerly before tenant-aware
     * middleware runs. Because the user model carries a tenant scope, token
     * resolution must happen under the explicit system scope (a trusted,
     * identity-less bootstrap path — see TenantContext::enterSystemContext),
     * after which InitializeTenancy activates the concrete tenant context.
     *
     * The system scope is deliberately limited to identity resolution only:
     * the resolved user is cached on the sanctum guard, so the auth:sanctum
     * middleware reuses it without re-querying, and the context is reverted to
     * a clean, Uninitialized state before $next runs. No middleware or
     * controller in the downstream pipeline executes under the platform scope,
     * so a tenant-owned query performed outside InitializeTenancy fails closed
     * (TenantContextMissingException) instead of silently leaking cross-tenant
     * data under the platform bypass.
     *
     * The auth manager also caches guard instances that keep their resolved
     * user across requests in a long-lived worker (tests, Octane). When a
     * bearer-token request comes in, reset that state so the token is
     * resolved fresh — otherwise a token revoked by a previous request would
     * keep authenticating. Session/actingAs-backed requests (no bearer
     * header) keep their existing identity untouched.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->bearerToken() === null) {
            return $next($request);
        }

        Auth::forgetGuards();
        Auth::shouldUse(config('auth.defaults.guard', 'web'));

        $context = app(TenantContext::class);
        $context->enterSystemContext();

        try {
            Auth::guard('sanctum')->user();
        } finally {
            $context->clear();
        }

        return $next($request);
    }
}
