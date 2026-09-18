<?php

namespace App\Concerns;

use App\Tenancy\TenantContext;
use App\Tenancy\TenantContextMissingException;
use App\Tenancy\TenantContextMismatchException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder): void {
            $context = app(TenantContext::class);

            if ($context->isPlatform()) {
                return;
            }

            if (! $context->hasTenant()) {
                throw new TenantContextMissingException(
                    'A tenant-scoped model query was attempted before tenant resolution.'
                );
            }

            $builder->where(
                $builder->getModel()->qualifyColumn('tenant_id'),
                $context->tenantId()
            );
        });

        static::creating(function (Model $model): void {
            $context = app(TenantContext::class);

            if ($context->hasTenant()) {
                $tenantId = $context->tenantId();

                if ($model->getAttribute('tenant_id') === null) {
                    $model->setAttribute('tenant_id', $tenantId);

                    return;
                }

                if ((int) $model->getAttribute('tenant_id') !== $tenantId) {
                    throw new TenantContextMismatchException(
                        'Refusing to create a tenant-owned model for a different tenant.'
                    );
                }

                return;
            }

            if ($context->isPlatform()) {
                if ($model->getAttribute('tenant_id') === null) {
                    throw new TenantContextMissingException(
                        'Platform-scope writes to tenant-owned models must provide tenant_id explicitly.'
                    );
                }

                return;
            }

            throw new TenantContextMissingException(
                'Refusing to create a tenant-owned model without tenant context.'
            );
        });

        static::updating(function (Model $model): void {
            $context = app(TenantContext::class);

            if (! $model->isDirty('tenant_id')) {
                return;
            }

            if ($context->hasTenant() && (int) $model->getAttribute('tenant_id') !== $context->tenantId()) {
                throw new TenantContextMismatchException(
                    'Refusing to move a tenant-owned model to another tenant.'
                );
            }

            if (! $context->isPlatform() && ! $context->hasTenant()) {
                throw new TenantContextMissingException(
                    'Refusing to update tenant ownership without tenant context.'
                );
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Tenant::class);
    }
}
