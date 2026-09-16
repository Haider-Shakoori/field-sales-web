<?php

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Route;
use App\Models\Salesman;
use App\Models\SalesmanAssignment;
use App\Models\Supervisor;
use App\Models\Territory;

beforeEach(function (): void {
    seedRbac();
});

/**
 * RBAC / SCOPE
 */
test('unauthorized role cannot create customer', function (): void {
    $tenant = makeTenant();
    $salesmanUser = makeUser($tenant, 'salesman', ['email' => 'salesman@test.test']);
    $branch = withTenantContext($tenant, fn () => Branch::factory()->forTenant($tenant->id)->create());
    $territory = withTenantContext($tenant, fn () => Territory::factory()->forTenant($tenant->id)->forBranch($branch->id)->create());

    withTenantContext($tenant, function () use ($salesmanUser, $branch, $territory): void {
        $this->actingAs($salesmanUser)
            ->postJson('/api/v1/customers', [
                'branch_id' => $branch->id,
                'territory_id' => $territory->id,
                'name' => 'Unauthorized',
            ])->assertForbidden();
    });
});

test('unauthorized role cannot update customer', function (): void {
    $tenant = makeTenant();
    $owner = makeUser($tenant, 'owner');
    $salesmanUser = makeUser($tenant, 'salesman', ['email' => 'salesman@test.test']);
    $branch = withTenantContext($tenant, fn () => Branch::factory()->forTenant($tenant->id)->create());
    $territory = withTenantContext($tenant, fn () => Territory::factory()->forTenant($tenant->id)->forBranch($branch->id)->create());

    $customerId = withTenantContext($tenant, function () use ($owner, $branch, $territory): int {
        $response = $this->actingAs($owner)
            ->postJson('/api/v1/customers', [
                'branch_id' => $branch->id,
                'territory_id' => $territory->id,
                'name' => 'Owner Customer',
            ])->assertCreated();

        return $response->json('data.id');
    });

    withTenantContext($tenant, function () use ($salesmanUser, $branch, $territory, $customerId): void {
        $this->actingAs($salesmanUser)
            ->putJson("/api/v1/customers/{$customerId}", [
                'branch_id' => $branch->id,
                'territory_id' => $territory->id,
                'name' => 'Hacked',
            ])->assertForbidden();
    });
});

test('unauthorized role cannot create territory', function (): void {
    $tenant = makeTenant();
    $salesmanUser = makeUser($tenant, 'salesman', ['email' => 'salesman@test.test']);
    $branch = withTenantContext($tenant, fn () => Branch::factory()->forTenant($tenant->id)->create());

    withTenantContext($tenant, function () use ($salesmanUser, $branch): void {
        $this->actingAs($salesmanUser)
            ->postJson('/api/v1/territories', [
                'branch_id' => $branch->id,
                'name' => 'Unauthorized',
                'code' => 'UN-001',
            ])->assertForbidden();
    });
});

test('unauthorized role cannot create route', function (): void {
    $tenant = makeTenant();
    $salesmanUser = makeUser($tenant, 'salesman', ['email' => 'salesman@test.test']);
    $branch = withTenantContext($tenant, fn () => Branch::factory()->forTenant($tenant->id)->create());
    $territory = withTenantContext($tenant, fn () => Territory::factory()->forTenant($tenant->id)->forBranch($branch->id)->create());

    withTenantContext($tenant, function () use ($salesmanUser, $territory): void {
        $this->actingAs($salesmanUser)
            ->postJson('/api/v1/routes', [
                'territory_id' => $territory->id,
                'name' => 'Unauthorized',
                'code' => 'UN-001',
            ])->assertForbidden();
    });
});

test('supervisor cannot escape assigned branch/territory scope', function (): void {
    $tenant = makeTenant();
    $owner = makeUser($tenant, 'owner');
    $supervisorUser = makeUser($tenant, 'supervisor', ['email' => 'supervisor@test.test']);
    $supervisor = withTenantContext($tenant, fn () => Supervisor::factory()->forTenant($tenant->id)->forUser($supervisorUser->id)->create());
    $branchA = withTenantContext($tenant, fn () => Branch::factory()->forTenant($tenant->id)->create());
    $branchB = withTenantContext($tenant, fn () => Branch::factory()->forTenant($tenant->id)->create());
    $territoryA = withTenantContext($tenant, fn () => Territory::factory()->forTenant($tenant->id)->forBranch($branchA->id)->create());
    $territoryB = withTenantContext($tenant, fn () => Territory::factory()->forTenant($tenant->id)->forBranch($branchB->id)->create());

    // Assign supervisor to territory A only
    withTenantContext($tenant, function () use ($owner, $supervisor, $branchA, $territoryA): void {
        $this->actingAs($owner)
            ->postJson('/api/v1/admin/supervisor-assignments', [
                'supervisor_id' => $supervisor->id,
                'branch_id' => $branchA->id,
                'territory_id' => $territoryA->id,
                'effective_from' => now()->toDateString(),
            ])->assertCreated();
    });

    // Create customers in both territories
    withTenantContext($tenant, function () use ($branchA, $territoryA, $tenant): void {
        $customerA = Customer::factory()->forTenant($tenant->id)->forBranch($branchA->id)->forTerritory($territoryA->id)->create();
    });

    withTenantContext($tenant, function () use ($branchB, $territoryB, $tenant): void {
        $customerB = Customer::factory()->forTenant($tenant->id)->forBranch($branchB->id)->forTerritory($territoryB->id)->create();
    });

    // Supervisor should only see customer in their assigned territory
    withTenantContext($tenant, function () use ($supervisorUser): void {
        $response = $this->actingAs($supervisorUser)->getJson('/api/v1/customers');
        $response->assertOk();
        // Should only see customer in territory A
        expect($response->json('data'))->toHaveCount(1);
    });
});

test('salesman sees only permitted customer data', function (): void {
    $tenant = makeTenant();
    $owner = makeUser($tenant, 'owner');
    $salesmanUser = makeUser($tenant, 'salesman', ['email' => 'salesman@test.test']);
    $salesman = withTenantContext($tenant, fn () => Salesman::factory()->forTenant($tenant->id)->forUser($salesmanUser->id)->create());
    $otherSalesman = withTenantContext($tenant, fn () => Salesman::factory()->forTenant($tenant->id)->create());
    $branch = withTenantContext($tenant, fn () => Branch::factory()->forTenant($tenant->id)->create());
    $territory = withTenantContext($tenant, fn () => Territory::factory()->forTenant($tenant->id)->forBranch($branch->id)->create());
    $route = withTenantContext($tenant, fn () => Route::factory()->forTenant($tenant->id)->forTerritory($territory->id)->create());

    // Create customer assigned to salesman
    withTenantContext($tenant, function () use ($branch, $territory, $salesman, $tenant): void {
        $customer1 = Customer::factory()->forTenant($tenant->id)->forBranch($branch->id)->forTerritory($territory->id)->forSalesman($salesman->id)->create();
    });

    // Create customer in same territory but assigned to other salesman
    withTenantContext($tenant, function () use ($branch, $territory, $otherSalesman, $tenant): void {
        $customer2 = Customer::factory()->forTenant($tenant->id)->forBranch($branch->id)->forTerritory($territory->id)->forSalesman($otherSalesman->id)->create();
    });

    // Create customer in different territory
    $otherTerritory = withTenantContext($tenant, fn () => Territory::factory()->forTenant($tenant->id)->forBranch($branch->id)->create());
    withTenantContext($tenant, function () use ($branch, $otherTerritory, $tenant): void {
        $customer3 = Customer::factory()->forTenant($tenant->id)->forBranch($branch->id)->forTerritory($otherTerritory->id)->create();
    });

    // Salesman should only see their assigned customer + customers in their territory/route
    withTenantContext($tenant, function () use ($salesmanUser, $salesman, $branch, $territory, $route, $tenant): void {
        // First assign salesman to territory and route
        SalesmanAssignment::create([
            'tenant_id' => $tenant->id,
            'salesman_id' => $salesman->id,
            'branch_id' => $branch->id,
            'territory_id' => $territory->id,
            'route_id' => $route->id,
            'effective_from' => now()->toDateString(),
        ]);

        $response = $this->actingAs($salesmanUser)->getJson('/api/v1/customers');
        $response->assertOk();
        // Should see customer1 (assigned) and customer2 (same territory)
        // But not customer3 (different territory)
        expect($response->json('data'))->toHaveCount(2);
    });
});

test('salesman can see routes in their territory', function (): void {
    $tenant = makeTenant();
    $owner = makeUser($tenant, 'owner');
    $salesmanUser = makeUser($tenant, 'salesman', ['email' => 'salesman@test.test']);
    $salesman = withTenantContext($tenant, fn () => Salesman::factory()->forTenant($tenant->id)->forUser($salesmanUser->id)->create());
    $branch = withTenantContext($tenant, fn () => Branch::factory()->forTenant($tenant->id)->create());
    $territory = withTenantContext($tenant, fn () => Territory::factory()->forTenant($tenant->id)->forBranch($branch->id)->create());
    $route1 = withTenantContext($tenant, fn () => Route::factory()->forTenant($tenant->id)->forTerritory($territory->id)->create());
    $otherTerritory = withTenantContext($tenant, fn () => Territory::factory()->forTenant($tenant->id)->forBranch($branch->id)->create());
    $route2 = withTenantContext($tenant, fn () => Route::factory()->forTenant($tenant->id)->forTerritory($otherTerritory->id)->create());

    withTenantContext($tenant, function () use ($salesman, $branch, $territory, $route1, $tenant): void {
        SalesmanAssignment::create([
            'tenant_id' => $tenant->id,
            'salesman_id' => $salesman->id,
            'branch_id' => $branch->id,
            'territory_id' => $territory->id,
            'route_id' => $route1->id,
            'effective_from' => now()->toDateString(),
        ]);
    });

    withTenantContext($tenant, function () use ($salesmanUser, $route1): void {
        $response = $this->actingAs($salesmanUser)->getJson('/api/v1/routes');
        $response->assertOk();
        // Should only see route1 (in their territory)
        expect($response->json('data'))->toHaveCount(1);
        expect($response->json('data.0.id'))->toBe($route1->id);
    });
});
