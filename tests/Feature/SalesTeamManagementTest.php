<?php

use App\Models\AuditLog;
use App\Models\Salesman;
use App\Models\Supervisor;

beforeEach(function (): void {
    seedRbac();
});

it('creates a salesman profile in the tenant with an audit event', function (): void {
    $tenant = makeTenant();
    $owner = makeUser($tenant, 'owner');

    withTenantContext($tenant, function () use ($owner, $tenant): void {
        $response = $this->actingAs($owner)
            ->post(route('salesmen.store'), [
                'first_name' => 'Ahmad',
                'last_name' => 'Zadran',
                'phone' => '+93 70 555 6666',
                'email' => 'ahmad@test.test',
                'hire_date' => '2025-01-01',
                'designation' => 'Field Rep',
            ]);

        $salesman = Salesman::firstOrFail();

        $response->assertRedirect(route('salesmen.show', $salesman));

        expect(Salesman::count())->toBe(1);
        expect($salesman->tenant_id)->toBe($tenant->id);
        expect($salesman->employee_code)->toStartWith('SLM-');
    });

    expect(AuditLog::where('event', 'salesman.created')->exists())->toBeTrue();
});

it('auto-generates sequential employee codes unique per tenant', function (): void {
    $tenantA = makeTenant();
    $tenantB = makeTenant();
    $ownerA = makeUser($tenantA, 'owner');

    withTenantContext($tenantA, function () use ($ownerA): void {
        $this->actingAs($ownerA)->post(route('salesmen.store'), ['first_name' => 'One'])->assertRedirect();
        $this->actingAs($ownerA)->post(route('salesmen.store'), ['first_name' => 'Two'])->assertRedirect();
    });

    $codesA = withTenantContext($tenantA, fn () => Salesman::orderBy('id')->pluck('employee_code')->all());
    withTenantContext($tenantB, fn () => Salesman::factory()->forTenant($tenantB->id)->create([
        'employee_code' => 'SLM-000001',
        'first_name' => 'B-User',
    ]));

    expect($codesA)->toBe(['SLM-000001', 'SLM-000002']);

    withTenantContext($tenantB, fn () => expect(Salesman::value('employee_code'))->toBe('SLM-000001'));
});

it('rejects duplicate employee codes within the same tenant', function (): void {
    $tenant = makeTenant();
    $owner = makeUser($tenant, 'owner');
    withTenantContext($tenant, fn () => Salesman::factory()->forTenant($tenant->id)->create([
        'employee_code' => 'SLM-000042',
        'first_name' => 'Existing',
    ]));

    withTenantContext($tenant, fn () => $this->actingAs($owner)
        ->from(route('salesmen.create'))
        ->post(route('salesmen.store'), [
            'first_name' => 'Duplicate',
            'employee_code' => 'SLM-000042',
        ])
        ->assertSessionHasErrors('employee_code'));
});

it('updates a salesman profile and records the audit diff', function (): void {
    $tenant = makeTenant();
    $owner = makeUser($tenant, 'owner');
    $salesman = withTenantContext($tenant, fn () => Salesman::factory()->forTenant($tenant->id)->create([
        'employee_code' => 'SLM-000007',
        'first_name' => 'Old',
    ]));

    withTenantContext($tenant, function () use ($owner, $salesman): void {
        $this->actingAs($owner)
            ->put(route('salesmen.update', $salesman), [
                'employee_code' => 'SLM-000007',
                'first_name' => 'New',
                'last_name' => 'Name',
                'phone' => '+93 70 999 8888',
            ])
            ->assertRedirect(route('salesmen.show', $salesman));
    });

    expect($salesman->fresh()->first_name)->toBe('New');
    expect(AuditLog::where('event', 'salesman.updated')->where('auditable_id', $salesman->id)->exists())->toBeTrue();
});

it('deactivates a salesman in the tenant', function (): void {
    $tenant = makeTenant();
    $owner = makeUser($tenant, 'owner');
    $salesman = withTenantContext($tenant, fn () => Salesman::factory()->forTenant($tenant->id)->create());

    withTenantContext($tenant, fn () => $this->actingAs($owner)
        ->delete(route('salesmen.deactivate', $salesman))
        ->assertRedirect());

    expect($salesman->fresh()->is_active)->toBeFalse();
});

it('creates a supervisor profile linked to a tenant user', function (): void {
    $tenant = makeTenant();
    $owner = makeUser($tenant, 'owner');
    $supervisorUser = makeUser($tenant, 'supervisor', ['email' => 'sup@test.test']);

    withTenantContext($tenant, function () use ($owner, $supervisorUser): void {
        $response = $this->actingAs($owner)
            ->post(route('supervisors.store'), [
                'user_id' => $supervisorUser->id,
                'first_name' => 'Zabih',
                'last_name' => 'Ghafoori',
                'phone' => '+93 70 222 3333',
            ]);

        $supervisor = Supervisor::firstOrFail();

        $response->assertRedirect(route('supervisors.show', $supervisor));

        expect(Supervisor::count())->toBe(1);
        expect($supervisor->user_id)->toBe($supervisorUser->id);
        expect($supervisor->employee_code)->toStartWith('SUP-');
    });

    expect(AuditLog::where('event', 'supervisor.created')->exists())->toBeTrue();
});

it('rejects a supervisor linked to a user from another tenant', function (): void {
    $tenantA = makeTenant();
    $tenantB = makeTenant();
    $ownerA = makeUser($tenantA, 'owner');
    $foreignUser = makeUser($tenantB, 'salesman', ['email' => 'foreign@test.test']);

    withTenantContext($tenantA, fn () => $this->actingAs($ownerA)
        ->from(route('supervisors.create'))
        ->post(route('supervisors.store'), [
            'user_id' => $foreignUser->id,
            'first_name' => 'Intruder',
        ])
        ->assertSessionHasErrors('user_id'));
});

it('isolates salesman profiles between tenants', function (): void {
    $tenantA = makeTenant();
    $tenantB = makeTenant();
    $ownerA = makeUser($tenantA, 'owner');
    $salesmanB = withTenantContext($tenantB, fn () => Salesman::factory()->forTenant($tenantB->id)->create([
        'employee_code' => 'SLM-000900',
        'first_name' => 'Bob',
    ]));

    withTenantContext($tenantA, fn () => $this->actingAs($ownerA)
        ->get(route('salesmen.show', $salesmanB))
        ->assertNotFound());

    expect(withTenantContext($tenantA, fn () => Salesman::count()))->toBe(0);
});

it('enforces RBAC for salesman management pages', function (): void {
    $tenant = makeTenant();
    $salesmanRoleUser = makeUser($tenant, 'salesman', ['email' => 's-role@test.test']);
    $manager = makeUser($tenant, 'sales_manager', ['email' => 'm-role@test.test']);

    withTenantContext($tenant, fn () => $this->actingAs($salesmanRoleUser)
        ->get(route('salesmen.index'))
        ->assertForbidden());

    withTenantContext($tenant, fn () => $this->actingAs($salesmanRoleUser)
        ->get(route('supervisors.index'))
        ->assertForbidden());

    withTenantContext($tenant, fn () => $this->actingAs($manager)
        ->get(route('salesmen.index'))
        ->assertOk());

    withTenantContext($tenant, fn () => $this->actingAs($manager)
        ->get(route('salesmen.create'))
        ->assertForbidden());
});

it('renders the salesmen, supervisors, and devices list pages', function (): void {
    $tenant = makeTenant();
    $owner = makeUser($tenant, 'owner');
    withTenantContext($tenant, fn () => Salesman::factory()->forTenant($tenant->id)->create());

    withTenantContext($tenant, fn () => $this->actingAs($owner)
        ->get(route('salesmen.index'))
        ->assertOk()
        ->assertSee('Salesmen'));

    withTenantContext($tenant, fn () => $this->actingAs($owner)
        ->get(route('supervisors.index'))
        ->assertOk());

    withTenantContext($tenant, fn () => $this->actingAs($owner)
        ->get(route('devices.index'))
        ->assertOk());
});
