<?php

namespace App\Support\Tenancy;

use App\Models\Tenant;
use App\Models\User;

/**
 * Holds the tenant execution state for the duration of a request or process.
 *
 * Three states:
 *  - Tenant: a concrete tenant is selected; TenantScope filters every query.
 *  - Platform: a deliberate, guarded bypass for super-admin / system work.
 *  - Uninitialized: no context; tenant-owned model access throws instead of
 *    silently escaping isolation (fail-closed).
 */
class TenantContext
{
    protected TenantContextState $state = TenantContextState::Uninitialized;

    protected ?Tenant $tenant = null;

    public function set(Tenant $tenant): void
    {
        $this->state = TenantContextState::Tenant;
        $this->tenant = $tenant;
    }

    /**
     * Enter platform context from the authenticated super admin in the HTTP stack.
     */
    public function enterPlatformForUser(User $user): void
    {
        if (! $user->isSuperAdmin()) {
            throw new TenantContextException('Only a verified platform super admin may enter the platform context.');
        }

        $this->state = TenantContextState::Platform;
        $this->tenant = null;
    }

    /**
     * Enter the explicit platform/system context from trusted, identity-less
     * code paths (seeders, console commands, queued jobs, auth bootstrap).
     *
     * The guard inspects the web session guard only: a browser-authenticated
     * company user must never pivot into platform scope. The default (token)
     * guard is deliberately not consulted — during auth bootstrap no identity
     * has been resolved yet, and resolving it here would recurse or pick up a
     * stale cached user from the shared request container.
     *
     * @throws TenantContextException when an authenticated company user attempts this
     */
    public function enterSystemContext(): void
    {
        $user = auth('web')->user();

        if ($user !== null && ! $user->isSuperAdmin()) {
            throw new TenantContextException('Platform/system context cannot be activated by a company user.');
        }

        $this->state = TenantContextState::Platform;
        $this->tenant = null;
    }

    public function clear(): void
    {
        $this->state = TenantContextState::Uninitialized;
        $this->tenant = null;
    }

    public function state(): TenantContextState
    {
        return $this->state;
    }

    /**
     * True only when a concrete tenant is active.
     */
    public function has(): bool
    {
        return $this->state === TenantContextState::Tenant;
    }

    public function isPlatform(): bool
    {
        return $this->state === TenantContextState::Platform;
    }

    public function current(): ?Tenant
    {
        return $this->state === TenantContextState::Tenant ? $this->tenant : null;
    }

    public function id(): ?int
    {
        return $this->current()?->id;
    }

    /**
     * Run a callback inside a concrete tenant context, restoring previous state afterwards.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function withTenant(Tenant $tenant, callable $callback): mixed
    {
        $state = $this->state;
        $previous = $this->tenant;
        $this->set($tenant);

        try {
            return $callback();
        } finally {
            $this->restore($state, $previous);
        }
    }

    /**
     * Run a callback inside the explicit platform/system context, restoring previous state.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function withSystemContext(callable $callback): mixed
    {
        $state = $this->state;
        $previous = $this->tenant;

        $this->enterSystemContext();

        try {
            return $callback();
        } finally {
            $this->restore($state, $previous);
        }
    }

    /**
     * Run a callback inside a temporary, identity-less platform bootstrap scope,
     * restoring the previous state afterwards.
     *
     * This is the ONLY safe primitive to use when resolving an authentication
     * identity before any tenant context exists (e.g. restoring the web user
     * from the session or remember-me cookie in the HTTP stack).
     *
     * Unlike enterSystemContext(), this method never consults the auth guard
     * itself: Consultating it would recurse, because resolving that identity is
     * exactly what the callback is for. The guard also deliberately skipped, so
     * an authenticated company user can not be rejected here during bootstrap.
     *
     * The state set by this scope is anonymous and temporary. It must never
     * wrap application/business logic and must never outlive the callback:
     * controllers, services, and tenant-owned queries must only ever run under
     * Tenant, Platform (authorized), or Uninitialized (guest) state.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function withAuthenticationBootstrapScope(callable $callback): mixed
    {
        $state = $this->state;
        $previous = $this->tenant;

        $this->state = TenantContextState::Platform;
        $this->tenant = null;

        try {
            return $callback();
        } finally {
            $this->restore($state, $previous);
        }
    }

    private function restore(TenantContextState $state, ?Tenant $tenant): void
    {
        $this->state = $state;
        $this->tenant = $tenant;
    }

    /* ------------------------------------------------------------------
     * Static bridges (resolve from the service container)
     * ----------------------------------------------------------------*/

    public static function currentId(): ?int
    {
        return app(static::class)->id();
    }

    public static function hasContext(): bool
    {
        return app(static::class)->has();
    }

    public static function tenant(): ?Tenant
    {
        return app(static::class)->current();
    }

    public static function currentState(): TenantContextState
    {
        return app(static::class)->state();
    }

    public static function withSystemScope(callable $callback): mixed
    {
        return app(static::class)->withSystemContext($callback);
    }
}
