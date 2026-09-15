<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

beforeEach(function (): void {
    seedRbac();
});

it('assigns a single role and mirrors it on the users.role column', function (): void {
    $tenant = makeTenant();
    $user = makeUser($tenant, 'salesman');

    expect($user->role)->toBe('salesman');
    expect($user->roles->pluck('name'))->toContain('salesman');
    expect($user->modelRoles->count())->toBe(1);
});

it('re-assigning a role replaces the previous one', function (): void {
    $tenant = makeTenant();
    $user = makeUser($tenant, 'salesman');

    withTenantContext($tenant, function () use ($user, $tenant): void {
        $user->assignRole('accountant', $tenant->id);
    });

    expect($user->modelRoles->count())->toBe(1);
    expect($user->role)->toBe('accountant');
});

it('checks permissions via the assigned role', function (): void {
    $tenant = makeTenant();
    $salesman = makeUser($tenant, 'salesman');
    $accountant = makeUser($tenant, 'accountant');

    expect($salesman->hasPermission('dashboard:view'))->toBeTrue();
    expect($accountant->hasPermission('dashboard:view'))->toBeTrue();

    expect($salesman->hasPermission('users:manage'))->toBeFalse();
    expect($accountant->hasPermission('users:manage'))->toBeFalse();
    expect($accountant->hasPermission('settings:view'))->toBeTrue();
    expect($accountant->hasPermission('audit:view'))->toBeTrue();
});

it('only sees roles for the current tenant context', function (): void {
    $tenantA = makeTenant();
    $tenantB = makeTenant();

    // Same physical user belongs to two tenants (rare in this app, but the
    // pivot is tenant-scoped so roles must resolve per context).
    $user = withTenantContext($tenantA, function () use ($tenantA): User {
        $user = User::factory()->create(['tenant_id' => $tenantA->id, 'is_active' => true]);
        $user->assignRole('salesman', $tenantA->id);

        return $user;
    });

    withTenantContext($tenantA, function () use ($user): void {
        expect($user->roles->pluck('name'))->toContain('salesman');
        expect($user->hasPermission('dashboard:view'))->toBeTrue();
    });

    $user->unsetRelation('roles');

    withTenantContext($tenantB, function () use ($user): void {
        expect($user->roles)->toBeEmpty();
        expect($user->hasPermission('dashboard:view'))->toBeFalse();
    });
});

it('gives the super admin every permission regardless of context', function (): void {
    $tenant = makeTenant();

    $admin = withPlatformContext(function (): User {
        $admin = User::factory()->create([
            'tenant_id' => null,
            'email' => 'super@platform.test',
            'is_active' => true,
        ]);
        $admin->assignRole('super_admin', null);

        return $admin;
    });

    expect($admin->isSuperAdmin())->toBeTrue();
    expect($admin->hasPermission('tenants:manage'))->toBeTrue();
    expect($admin->hasPermission('anything:at:all'))->toBeTrue();
    expect(Gate::forUser($admin)->allows('tenants:manage'))->toBeTrue();
});

it('enforces permission checks through the Gate for company users', function (): void {
    $tenant = makeTenant();
    $owner = makeUser($tenant, 'owner');
    $salesman = makeUser($tenant, 'salesman');

    withTenantContext($tenant, function () use ($owner, $salesman): void {
        expect(Gate::forUser($owner)->allows('dashboard:view'))->toBeTrue();
        expect(Gate::forUser($owner)->allows('users:manage'))->toBeTrue();

        expect(Gate::forUser($salesman)->allows('dashboard:view'))->toBeTrue();
        expect(Gate::forUser($salesman)->allows('users:manage'))->toBeFalse();
    });
});

it('does not leak superadmin privileges to tenant user id 1', function (): void {
    $tenant = makeTenant();
    $user = makeUser($tenant, 'owner');

    expect($user->isSuperAdmin())->toBeFalse();
    expect(Gate::forUser($user)->allows('tenants:manage'))->toBeFalse();
});
