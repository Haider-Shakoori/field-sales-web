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

            if ($context->hasTenant() && $model->getAttribute('tenant_id') === null) {
                $model->setAttribute('tenant_id', $context->tenantId());
            }

            static::assertTenantWriteAllowed($model, $context);
        });

        static::updating(function (Model $model): void {
            static::assertTenantWriteAllowed($model, app(TenantContext::class));
        });

        static::deleting(function (Model $model): void {
            static::assertTenantWriteAllowed($model, app(TenantContext::class));
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Tenant::class);
    }

    private static function assertTenantWriteAllowed(Model $model, TenantContext $context): void
    {
        $modelTenantId = $model->getAttribute('tenant_id');

        if ($context->hasTenant()) {
            if ($modelTenantId === null || (int) $modelTenantId !== $context->tenantId()) {
                throw new TenantContextMismatchException(
                    'Refusing a tenant-owned model write outside the active tenant.'
                );
            }

            if ($model->exists && $model->isDirty('tenant_id')) {
                throw new TenantContextMismatchException(
                    'Tenant ownership is immutable inside tenant scope.'
                );
            }

            return;
        }

        if ($context->isPlatform()) {
            if ($modelTenantId === null) {
                throw new TenantContextMissingException(
                    'Platform-scope writes to tenant-owned models must provide tenant_id explicitly.'
                );
            }

            return;
        }

        throw new TenantContextMissingException(
            'Refusing to write a tenant-owned model without tenant context.'
        );
    }
}
