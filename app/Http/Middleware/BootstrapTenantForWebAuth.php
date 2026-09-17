<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class BootstrapTenantForWebAuth
{
    /**
     * Establish a tenant context for session-authenticated web requests.
     *
     * The SessionGuard restores the authenticated user from the session (or the
     * remember-me cookie) by querying the tenant-scoped User model. That query
     * can not run under the Uninitialized state (it fails closed) and must not
     * wait for InitializeTenancy, which also resolves the user and would run
     * into the exact same query. It must also not run under
     * enterSystemContext(), which consults the web guard itself and would
     * reject a company user (or recurse).
     *
     * So the identity is resolved inside the narrow, identity-less
     * withAuthenticationBootstrapScope() — a temporary Platform state that is
     * closed before any further middleware runs — after which the user is
     * cached on the web guard and every later lookup is query-free:
     *
     *   guest        -> leave TenantContext Uninitialized; guest pages continue.
     *   company user -> set their subscribed tenant's context here so any
     *                   middleware that inspects the authenticated user between
     *                   this point and InitializeTenancy never sees a tenant
     *                   model query without context.
     *   super admin  -> leave Uninitialized; InitializeTenancy enters the
     *                   authorized platform context for the user.
     *
     * InitializeTenancy then re-authorizes and re-establishes the final context
     * from the already-cached user, so the temporary bootstrap scope has fully
     * ended before the controller (or any business middleware) runs.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('web');

        $user = app(TenantContext::class)->withAuthenticationBootstrapScope(
            fn (): ?User => $guard->user(),
        );

        if ($user === null) {
            // A stale session identifier that resolves to nothing would make
            // every later guard lookup (e.g. InitializeTenancy's
            // $request->user()) re-query the tenant-scoped User model outside
            // of any bootstrap scope. Drop it so the guard short-circuits to a
            // clean guest instead of failing closed on an orphaned session.
            if ($request->hasSession()) {
                $request->session()->forget($guard->getName());
            }

            return $next($request);
        }

        if ($user->tenant_id !== null) {
            $tenant = Tenant::withTrashed()->find($user->tenant_id);

            if ($tenant !== null && $tenant->isSubscribed()) {
                app(TenantContext::class)->set($tenant);
            }
        }

        return $next($request);
    }
}
