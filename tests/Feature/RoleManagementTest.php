<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;

beforeEach(function (): void {
    seedRbac();
});

it('cannot view a custom role owned by another tenant', function (): void {
    $tenantA = makeTenant();
    $tenantB = makeTenant();
    $ownerA = makeUser($tenantA, 'owner');
    $foreignRole = withTenantContext($tenantB, fn () => Role::factory()->forTenant($tenantB->id)->create([
        'name' => 'foreign_role',
    ]));

    withTenantContext($tenantA, function () use ($ownerA, $foreignRole): void {
        expect($ownerA->can('view', $foreignRole))->toBeFalse();
        expect($ownerA->can('update', $foreignRole))->toBeFalse();
    });
});

it('cannot create a role whose name shadows a system role', function (): void {
    $tenant = makeTenant();
    $owner = makeUser($tenant, 'owner');

    withTenantContext($tenant, function () use ($owner): void {
        $permissionIds = Permission::pluck('id')->slice(0, 2)->all();

        $this->actingAs($owner)
            ->from(route('settings.roles.create'))
            ->post(route('settings.roles.store'), [
                'name' => 'owner',
                'permissions' => $permissionIds,
            ])
            ->assertSessionHasErrors('name');
    });
});

it('can create and edit a custom role within its tenant', function (): void {
    $tenant = makeTenant();
    $owner = makeUser($tenant, 'owner');
    $permissionIds = Permission::whereNotIn('name', config('tenancy.platform_permissions'))->pluck('id')->all();

    withTenantContext($tenant, function () use ($owner, $permissionIds, $tenant): void {
        $this->actingAs($owner)
            ->post(route('settings.roles.store'), [
                'name' => 'custom_manager',
                'permissions' => [$permissionIds[0]],
            ])
            ->assertRedirect(route('settings.roles.index'));

        $role = Role::where('name', 'custom_manager')->where('tenant_id', $tenant->id)->first();
        expect($role)->not->toBeNull();
        expect($role->permissions()->count())->toBe(1);

        $this->actingAs($owner)
            ->put(route('settings.roles.update', $role), [
                'name' => 'custom_manager',
                'permissions' => $permissionIds,
            ])
            ->assertRedirect(route('settings.roles.index'));

        expect($role->fresh()->permissions()->count())->toBe(count($permissionIds));
    });
});

it('does not allow granting platform permissions to custom roles', function (): void {
    $tenant = makeTenant();
    $owner = makeUser($tenant, 'owner');
    $platformPerm = Permission::where('name', 'tenants:manage')->first();

    withTenantContext($tenant, function () use ($owner, $platformPerm): void {
        $this->actingAs($owner)
            ->post(route('settings.roles.store'), [
                'name' => 'platform_thief',
                'permissions' => [$platformPerm->id],
            ])
            ->assertSessionHasErrors('permissions.*');
    });
});

it('does not let a company admin assign super_admin to users via update', function (): void {
    $tenant = makeTenant();
    $owner = makeUser($tenant, 'owner');
    $salesman = makeUser($tenant, 'salesman', ['email' => 'no.super@test.test']);

    withTenantContext($tenant, function () use ($owner, $salesman, $tenant): void {
        expect(Role::resolveAssignable('super_admin', $tenant->id))->toBeNull();

        $this->actingAs($owner)
            ->put(route('users.update', $salesman), [
                'name' => $salesman->name,
                'email' => $salesman->email,
                'role' => 'super_admin',
            ])
            ->assertSessionHasErrors('role');
    });
});

it('assigns custom role and mirrors it on the users.role column', function (): void {
    $tenant = makeTenant();
    $owner = makeUser($tenant, 'owner');
    $email = 'custom.role.'.str()->random(5).'@test.test';

    withTenantContext($tenant, function () use ($tenant, $email): void {
        Role::factory()->forTenant($tenant->id)->create(['name' => 'regional_lead']);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => $email,
            'password' => 'Password123!',
        ]);

        $user->assignRole('regional_lead', $tenant->id);
    });

    $user = withTenantContext($tenant, fn () => User::where('email', $email)->firstOrFail());

    withTenantContext($tenant, function () use ($user): void {
        expect($user->roles->pluck('name'))->toContain('regional_lead');
    });

    expect($user->role)->toBe('regional_lead');
});
