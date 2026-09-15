<?php

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Role;

beforeEach(function (): void {
    seedRbac();
});

it('cannot view users of another tenant', function (): void {
    $tenantA = makeTenant();
    $tenantB = makeTenant();
    $ownerA = makeUser($tenantA, 'owner');
    $userB = makeUser($tenantB, 'salesman', ['email' => 'sales.'.$tenantB->id.'@test.test']);

    withTenantContext($tenantA, fn () => $this->actingAs($ownerA)
        ->get(route('users.show', $userB))
        ->assertNotFound());
});

it('only lists users from the current tenant', function (): void {
    $tenantA = makeTenant();
    $tenantB = makeTenant();
    $ownerA = makeUser($tenantA, 'owner', ['name' => 'Unique Alice']);
    makeUser($tenantB, 'salesman', ['name' => 'Unique Bob', 'email' => 'bob.'.$tenantB->id.'@test.test']);
    withTenantContext($tenantA, fn () => Branch::factory()->create(['tenant_id' => $tenantA->id]));

    withTenantContext($tenantA, fn () => $this->actingAs($ownerA)
        ->get(route('users.index', ['search' => 'Unique']))
        ->assertOk()
        ->assertSee('Unique Alice')
        ->assertDontSee('Unique Bob'));
});

it('cannot assign a role owned by another tenant', function (): void {
    $tenantA = makeTenant();
    $tenantB = makeTenant();
    $ownerA = makeUser($tenantA, 'owner');
    $foreignRole = withTenantContext($tenantB, fn () => Role::factory()->create([
        'name' => 'bogus_'.str()->random(6),
        'tenant_id' => $tenantB->id,
    ]));

    withTenantContext($tenantA, function () use ($ownerA, $foreignRole, $tenantA): void {
        expect(Role::resolveAssignable($foreignRole->name, $tenantA->id))->toBeNull();

        $this->actingAs($ownerA)
            ->from(route('users.create'))
            ->post(route('users.store'), [
                'name' => 'Intruder',
                'email' => 'intruder@test.test',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
                'role' => $foreignRole->name,
            ])
            ->assertSessionHasErrors('role');
    });
});

it('cannot assign a branch owned by another tenant', function (): void {
    $tenantA = makeTenant();
    $tenantB = makeTenant();
    $ownerA = makeUser($tenantA, 'owner');
    $foreignBranch = withTenantContext($tenantB, fn () => Branch::factory()->create([
        'tenant_id' => $tenantB->id,
        'code' => 'XL-'.$tenantB->id,
    ]));

    withTenantContext($tenantA, fn () => $this->actingAs($ownerA)
        ->from(route('users.create'))
        ->post(route('users.store'), [
            'name' => 'Mary',
            'email' => 'mary@test.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => 'salesman',
            'branch_id' => $foreignBranch->id,
        ])
        ->assertSessionHasErrors('branch_id'));
});

it('cannot assign the platform super_admin role', function (): void {
    $tenant = makeTenant();
    $owner = makeUser($tenant, 'owner');

    withTenantContext($tenant, fn () => $this->actingAs($owner)
        ->from(route('users.create'))
        ->post(route('users.store'), [
            'name' => 'Hacker',
            'email' => 'hacker@test.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => 'super_admin',
        ])
        ->assertSessionHasErrors('role'));
});

it('records audit events for user role and branch changes', function (): void {
    $tenant = makeTenant();
    $owner = makeUser($tenant, 'owner');
    $branch = withTenantContext($tenant, fn () => Branch::factory()->create(['tenant_id' => $tenant->id]));
    $salesman = makeUser($tenant, 'salesman', ['email' => 'role.toggle@test.test']);

    withTenantContext($tenant, function () use ($owner, $salesman, $branch): void {
        $this->actingAs($owner)
            ->put(route('users.update', $salesman), [
                'name' => $salesman->name,
                'email' => $salesman->email,
                'phone' => $salesman->phone,
                'role' => 'accountant',
                'branch_id' => $branch->id,
            ])
            ->assertRedirect(route('users.show', $salesman));
    });

    expect($salesman->fresh()->role)->toBe('accountant');
    expect(AuditLog::where('event', 'user.role.changed')->where('auditable_id', $salesman->id)->exists())->toBeTrue();
    expect(AuditLog::where('event', 'user.branch.changed')->where('auditable_id', $salesman->id)->exists())->toBeTrue();
});

it('rejects an inactive user at login', function (): void {
    $tenant = makeTenant();
    $user = makeUser($tenant, 'salesman', ['is_active' => false, 'email' => 'inactive@test.test']);

    $this->post(route('login'), [
        'email' => 'inactive@test.test',
        'password' => 'Password123!',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('prevents a user from changing their own role', function (): void {
    $tenant = makeTenant();
    $owner = makeUser($tenant, 'owner');

    withTenantContext($tenant, fn () => $this->actingAs($owner)
        ->put(route('users.update', $owner), [
            'name' => $owner->name,
            'email' => $owner->email,
            'role' => 'salesman',
        ])
        ->assertForbidden());
});

it('cannot deactivate the last active owner', function (): void {
    $tenant = makeTenant();
    $owner = makeUser($tenant, 'owner');
    $manager = makeUser($tenant, 'sales_manager');

    withTenantContext($tenant, fn () => $this->actingAs($manager)
        ->delete(route('users.deactivate', $owner))
        ->assertForbidden());
});

it('cannot change the role of the last active owner', function (): void {
    $tenant = makeTenant();
    $owner = makeUser($tenant, 'owner');
    $manager = makeUser($tenant, 'sales_manager');

    withTenantContext($tenant, fn () => $this->actingAs($manager)
        ->put(route('users.update', $owner), [
            'name' => $owner->name,
            'email' => $owner->email,
            'role' => 'salesman',
        ])
        ->assertForbidden());
});
