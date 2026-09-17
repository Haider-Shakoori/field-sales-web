<?php

use App\Models\Branch;
use App\Models\CustomerCategory;
use App\Models\Salesman;
use App\Models\Supervisor;

beforeEach(function (): void {
    seedRbac();
});

it('renders the customers page with only active lookup records', function (): void {
    $tenant = makeTenant();
    $owner = makeUser($tenant, 'owner');

    withTenantContext($tenant, function () use ($tenant): void {
        Branch::factory()->forTenant($tenant->id)->create(['name' => 'Active Branch']);
        Branch::factory()->forTenant($tenant->id)->inactive()->create(['name' => 'Retired Branch']);

        CustomerCategory::factory()->forTenant($tenant->id)->create(['name' => 'Active Category']);
        CustomerCategory::factory()->forTenant($tenant->id)->inactive()->create(['name' => 'Retired Category']);

        Salesman::factory()->forTenant($tenant->id)->create(['first_name' => 'Active', 'last_name' => 'Salesman']);
        Salesman::factory()->forTenant($tenant->id)->inactive()->create(['first_name' => 'Retired', 'last_name' => 'Salesman']);
    });

    withTenantContext($tenant, function () use ($owner): void {
        $this->actingAs($owner)
            ->get(route('customers.index'))
            ->assertOk()
            ->assertSee('Active Branch')
            ->assertDontSee('Retired Branch')
            ->assertSee('Active Category')
            ->assertDontSee('Retired Category')
            ->assertSee('Active Salesman')
            ->assertDontSee('Retired Salesman');

        $this->actingAs($owner)
            ->get(route('customers.create'))
            ->assertOk();
    });
});

it('renders the territories page with only active branches', function (): void {
    $tenant = makeTenant();
    $owner = makeUser($tenant, 'owner');

    withTenantContext($tenant, function () use ($tenant, $owner): void {
        Branch::factory()->forTenant($tenant->id)->create(['name' => 'Active Branch']);
        Branch::factory()->forTenant($tenant->id)->inactive()->create(['name' => 'Retired Branch']);

        $this->actingAs($owner)
            ->get(route('territories.index'))
            ->assertOk()
            ->assertSee('Active Branch')
            ->assertDontSee('Retired Branch');

        $this->actingAs($owner)
            ->get(route('territories.create'))
            ->assertOk();
    });
});

it('keeps active lookup queries tenant isolated', function (): void {
    $tenantA = makeTenant();
    $tenantB = makeTenant();
    $ownerA = makeUser($tenantA, 'owner');

    withTenantContext($tenantB, function () use ($tenantB): void {
        Branch::factory()->forTenant($tenantB->id)->create(['name' => 'Bravo Branch']);
        CustomerCategory::factory()->forTenant($tenantB->id)->create(['name' => 'Bravo Category']);
    });

    withTenantContext($tenantA, function () use ($tenantA, $ownerA): void {
        Branch::factory()->forTenant($tenantA->id)->create(['name' => 'Alpha Branch']);
        CustomerCategory::factory()->forTenant($tenantA->id)->create(['name' => 'Alpha Category']);

        $this->actingAs($ownerA)
            ->get(route('customers.index'))
            ->assertOk()
            ->assertSee('Alpha Branch')
            ->assertSee('Alpha Category')
            ->assertDontSee('Bravo Branch')
            ->assertDontSee('Bravo Category');
    });
});

it('filters inactive supervisors through the active scope', function (): void {
    $tenant = makeTenant();

    withTenantContext($tenant, function () use ($tenant): void {
        Supervisor::factory()->forTenant($tenant->id)->create(['first_name' => 'Current']);
        Supervisor::factory()->forTenant($tenant->id)->inactive()->create(['first_name' => 'Former']);

        expect(Supervisor::query()->active()->pluck('first_name')->all())->toBe(['Current']);
    });
});
