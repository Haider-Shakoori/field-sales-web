<?php

namespace App\Tenancy;

use App\Models\Tenant;
use Closure;

final class TenantContext
{
    private TenantContextState $state = TenantContextState::Uninitialized;

    private ?int $tenantId = null;

    public function state(): TenantContextState
    {
        return $this->state;
    }

    public function isInitialized(): bool
    {
        return $this->state !== TenantContextState::Uninitialized;
    }

    public function hasTenant(): bool
    {
        return $this->state === TenantContextState::Tenant;
    }

    public function isPlatform(): bool
    {
        return $this->state === TenantContextState::Platform;
    }

    public function tenantId(): int
    {
        if (! $this->hasTenant() || $this->tenantId === null) {
            throw new TenantContextMissingException(
                'A tenant-scoped operation was attempted without a tenant context.'
            );
        }

        return $this->tenantId;
    }

    public function initializeTenant(Tenant|int $tenant): void
    {
        $tenantId = $tenant instanceof Tenant ? (int) $tenant->getKey() : (int) $tenant;

        if ($tenantId <= 0) {
            throw new TenantContextMissingException('A valid tenant id is required.');
        }

        if ($this->state === TenantContextState::Tenant && $this->tenantId !== $tenantId) {
            throw new TenantContextMismatchException(
                "Tenant context is already initialized for tenant {$this->tenantId}; refusing to switch to tenant {$tenantId}."
            );
        }

        if ($this->state === TenantContextState::Platform) {
            throw new TenantContextMismatchException(
                'Refusing to replace an active platform context with a tenant context.'
            );
        }

        $this->state = TenantContextState::Tenant;
        $this->tenantId = $tenantId;
    }

    public function initializePlatform(): void
    {
        if ($this->state === TenantContextState::Tenant) {
            throw new TenantContextMismatchException(
                'Refusing to broaden an active tenant context to platform scope.'
            );
        }

        $this->state = TenantContextState::Platform;
        $this->tenantId = null;
    }

    public function clear(): void
    {
        $this->state = TenantContextState::Uninitialized;
        $this->tenantId = null;
    }

    public function withTenant(Tenant|int $tenant, Closure $callback): mixed
    {
        $tenantId = $tenant instanceof Tenant ? (int) $tenant->getKey() : (int) $tenant;

        return $this->temporarily(TenantContextState::Tenant, $tenantId, $callback);
    }

    public function withPlatformScope(Closure $callback): mixed
    {
        return $this->temporarily(TenantContextState::Platform, null, $callback);
    }

    public function withAuthenticationBootstrapScope(Closure $callback): mixed
    {
        if ($this->state !== TenantContextState::Uninitialized) {
            return $callback();
        }

        return $this->temporarily(TenantContextState::Platform, null, $callback);
    }

    private function temporarily(TenantContextState $state, ?int $tenantId, Closure $callback): mixed
    {
        $previousState = $this->state;
        $previousTenantId = $this->tenantId;

        $this->state = $state;
        $this->tenantId = $tenantId;

        try {
            return $callback();
        } finally {
            $this->state = $previousState;
            $this->tenantId = $previousTenantId;
        }
    }
}
