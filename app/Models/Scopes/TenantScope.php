<?php

namespace App\Models\Scopes;

use App\Support\Tenancy\TenantContext;
use App\Support\Tenancy\TenantContextMissingException;
use App\Support\Tenancy\TenantContextState;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    /**
     * Apply the tenant isolation filter.
     *
     * Three-state behavior (fail-closed):
     *  - Tenant: filter by current tenant_id.
     *  - Platform: deliberate bypass (explicit, intentional).
     *  - Uninitialized: throw (a forgotten tenant context must never silently
     *    expose cross-tenant data).
     */
    public function apply(Builder $builder, Model $model): void
    {
        switch (TenantContext::currentState()) {
            case TenantContextState::Tenant:
                $builder->where($model->qualifyColumn('tenant_id'), TenantContext::currentId());

                return;

            case TenantContextState::Platform:
                return;

            case TenantContextState::Uninitialized:
                throw new TenantContextMissingException(sprintf(
                    'Tenant-owned model [%s] was queried without an initialized tenant or explicit platform context.',
                    $model::class,
                ));
        }
    }
}
