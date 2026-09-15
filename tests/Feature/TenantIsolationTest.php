<?php

use App\Models\Branch;
use App\Models\CompanySetting;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use App\Support\Tenancy\TenantContextException;
use App\Support\Tenancy\TenantContextMissingException;
use App\Support\Tenancy\TenantContextState;

beforeEach(function (): void {
    seedRbac();
});

it('scopes user queries to the current tenant', function (): void {
    $tenantA = makeTenant();
    $tenantB = makeTenant();
    makeUser($tenantA, 'salesman');
    makeUser($tenantB, 'salesman');

    withTenantContext($tenantA, function () use ($tenantA, $tenantB): void {
        $visible = User::pluck('tenant_id')->unique()->all();

        expect($visible)->toBe([$tenantA->id]);
        expect($visible)->not->toContain($tenantB->id);
    });
});

it('scopes branch queries to the current tenant', function (): void {
    $tenantA = makeTenant();
    $tenantB = makeTenant();
    $branchA = withTenantContext($tenantA, fn () => Branch::factory()->create(['tenant_id' => $tenantA->id]));
    $branchB = withTenantContext($tenantB, fn () => Branch::factory()->create(['tenant_id' => $tenantB->id]));

    withTenantContext($tenantA, function () use ($branchA, $branchB): void {
        expect(Branch::find($branchA->id))->not->toBeNull();
        expect(Branch::find($branchB->id))->toBeNull();
    });

    withTenantContext($tenantB, function () use ($branchA, $branchB): void {
        expect(Branch::find($branchB->id))->not->toBeNull();
        expect(Branch::find($branchA->id))->toBeNull();
    });
});

it('applies the tenant_id default when creating a branch in context', function (): void {
    $tenant = makeTenant();

    $branch = withTenantContext($tenant, fn () => Branch::factory()->create(['tenant_id' => null]));

    expect($branch->tenant_id)->toBe($tenant->id);
});

it('denies cross-tenant policy operations on branches', function (): void {
    $tenantA = makeTenant();
    $tenantB = makeTenant();
    $ownerA = makeUser($tenantA, 'owner');
    $branchB = withTenantContext($tenantB, fn () => Branch::factory()->create());

    withTenantContext($tenantA, function () use ($ownerA, $branchB): void {
        expect($ownerA->can('update', $branchB))->toBeFalse();
        expect($ownerA->can('delete', $branchB))->toBeFalse();
        expect($ownerA->can('view', $branchB))->toBeFalse();
    });
});

it('allows tenants to manage their own branches only', function (): void {
    $tenantA = makeTenant();
    $tenantB = makeTenant();
    $ownerA = makeUser($tenantA, 'owner');
    $branchA = withTenantContext($tenantA, fn () => Branch::factory()->create(['tenant_id' => $tenantA->id]));
    $ownerB = makeUser($tenantB, 'owner');
    $branchB = withTenantContext($tenantB, fn () => Branch::factory()->create(['tenant_id' => $tenantB->id]));

    withTenantContext($tenantA, function () use ($ownerA, $ownerB, $branchA): void {
        expect($ownerA->can('view', $branchA))->toBeTrue();
        expect($ownerA->can('update', $branchA))->toBeTrue();
        expect($ownerB->can('view', $branchA))->toBeFalse();
        expect($ownerB->can('update', $branchA))->toBeFalse();
    });

    $ownerB->unsetRelation('roles');

    withTenantContext($tenantB, function () use ($ownerB, $branchB): void {
        expect($ownerB->can('view', $branchB))->toBeTrue();
        expect($ownerB->can('update', $branchB))->toBeTrue();
    });
});

it('blocks access to the branches index without a tenant context', function (): void {
    $admin = withPlatformContext(function (): User {
        $admin = User::factory()->create(['tenant_id' => null, 'is_active' => true]);
        $admin->assignRole('super_admin', null);

        return $admin;
    });

    $this->actingAs($admin)
        ->get(route('branches.index'))
        ->assertForbidden();
});

it('keeps company settings isolated per tenant', function (): void {
    $tenantA = makeTenant();
    $tenantB = makeTenant();

    withTenantContext($tenantA, fn () => CompanySetting::create(['key' => 'vat_rate', 'value' => ['rate' => 10]]));
    withTenantContext($tenantB, fn () => CompanySetting::create(['key' => 'vat_rate', 'value' => ['rate' => 5]]));

    withTenantContext($tenantA, function (): void {
        $setting = CompanySetting::where('key', 'vat_rate')->first();
        expect($setting->value)->toBe(['rate' => 10]);
    });

    withTenantContext($tenantB, function (): void {
        $setting = CompanySetting::where('key', 'vat_rate')->first();
        expect($setting->value)->toBe(['rate' => 5]);
    });
});

it('exposes the current tenant via the TenantContext singleton', function (): void {
    $tenant = makeTenant();

    withTenantContext($tenant, function () use ($tenant): void {
        expect(TenantContext::tenant()->id)->toBe($tenant->id);
        expect(TenantContext::currentId())->toBe($tenant->id);
        expect(TenantContext::hasContext())->toBeTrue();
    });

    expect(TenantContext::hasContext())->toBeFalse();
});

it('fails closed when a tenant-owned model is queried without context', function (): void {
    $tenantA = makeTenant();
    $tenantB = makeTenant();

    withPlatformContext(function () use ($tenantA, $tenantB): void {
        Branch::factory()->create(['tenant_id' => $tenantA->id]);
        Branch::factory()->create(['tenant_id' => $tenantB->id]);
    });

    expect(TenantContext::currentState())->toBe(TenantContextState::Uninitialized);

    expect(fn () => Branch::count())->toThrow(TenantContextMissingException::class);
    expect(fn () => Branch::all())->toThrow(TenantContextMissingException::class);
});

it('lets an explicit platform context read across tenants', function (): void {
    $tenantA = makeTenant();
    $tenantB = makeTenant();

    withPlatformContext(function () use ($tenantA, $tenantB): void {
        Branch::factory()->create(['tenant_id' => $tenantA->id]);
        Branch::factory()->create(['tenant_id' => $tenantB->id]);
    });

    withPlatformContext(function (): void {
        expect(Branch::count())->toBe(2);
    });
});

it('does not let a company user activate the platform context', function (): void {
    $tenant = makeTenant();
    $owner = makeUser($tenant, 'owner');

    expect(fn () => app(TenantContext::class)->enterPlatformForUser($owner))
        ->toThrow(TenantContextException::class);
});

it('rejects system context activation by an authenticated company user', function (): void {
    $tenant = makeTenant();
    $owner = makeUser($tenant, 'owner');

    $this->actingAs($owner);
    request()->setUserResolver(fn () => $owner);

    expect(fn () => app(TenantContext::class)->enterSystemContext())
        ->toThrow(TenantContextException::class);
});
