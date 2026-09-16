<?php

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Route;
use App\Models\RouteCustomer;
use App\Models\Territory;

beforeEach(function (): void {
    seedRbac();
});

/**
 * TERRITORIES / ROUTES
 */
test('territory tenant isolation on API list', function (): void {
    $tenantA = makeTenant();
    $tenantB = makeTenant();
    $ownerA = makeUser($tenantA, 'owner');
    $ownerB = makeUser($tenantB, 'owner');
    $branchA = withTenantContext($tenantA, fn () => Branch::factory()->forTenant($tenantA->id)->create());
    $branchB = withTenantContext($tenantB, fn () => Branch::factory()->forTenant($tenantB->id)->create());

    withTenantContext($tenantA, function () use ($ownerA, $branchA): void {
        $this->actingAs($ownerA)
            ->postJson('/api/v1/territories', [
                'branch_id' => $branchA->id,
                'name' => 'Tenant A Territory',
                'code' => 'TA-001',
            ])->assertCreated();
    });

    withTenantContext($tenantB, function () use ($ownerB, $branchB): void {
        $this->actingAs($ownerB)
            ->postJson('/api/v1/territories', [
                'branch_id' => $branchB->id,
                'name' => 'Tenant B Territory',
                'code' => 'TB-001',
            ])->assertCreated();
    });

    withTenantContext($tenantA, function () use ($ownerA): void {
        $response = $this->actingAs($ownerA)->getJson('/api/v1/territories');
        $response->assertOk();
        expect($response->json('data'))->toHaveCount(1);
        expect($response->json('data.0.name'))->toBe('Tenant A Territory');
    });
});

test('unauthorized role cannot update territory', function (): void {
    $tenant = makeTenant();
    $salesmanUser = makeUser($tenant, 'salesman', ['email' => 'salesman@test.test']);
    $branch = withTenantContext($tenant, fn () => Branch::factory()->forTenant($tenant->id)->create());
    $territory = withTenantContext($tenant, fn () => Territory::factory()->forTenant($tenant->id)->forBranch($branch->id)->create());

    withTenantContext($tenant, function () use ($salesmanUser, $branch, $territory): void {
        $this->actingAs($salesmanUser)
            ->putJson("/api/v1/territories/{$territory->id}", [
                'branch_id' => $branch->id,
                'name' => 'Updated',
                'code' => 'UPD-001',
            ])->assertForbidden();
    });
});

test('route tenant isolation on API list', function (): void {
    $tenantA = makeTenant();
    $tenantB = makeTenant();
    $ownerA = makeUser($tenantA, 'owner');
    $ownerB = makeUser($tenantB, 'owner');
    $branchA = withTenantContext($tenantA, fn () => Branch::factory()->forTenant($tenantA->id)->create());
    $branchB = withTenantContext($tenantB, fn () => Branch::factory()->forTenant($tenantB->id)->create());
    $territoryA = withTenantContext($tenantA, fn () => Territory::factory()->forTenant($tenantA->id)->forBranch($branchA->id)->create());
    $territoryB = withTenantContext($tenantB, fn () => Territory::factory()->forTenant($tenantB->id)->forBranch($branchB->id)->create());

    withTenantContext($tenantA, function () use ($ownerA, $territoryA): void {
        $this->actingAs($ownerA)
            ->postJson('/api/v1/routes', [
                'territory_id' => $territoryA->id,
                'name' => 'Tenant A Route',
                'code' => 'RTA-001',
            ])->assertCreated();
    });

    withTenantContext($tenantB, function () use ($ownerB, $territoryB): void {
        $this->actingAs($ownerB)
            ->postJson('/api/v1/routes', [
                'territory_id' => $territoryB->id,
                'name' => 'Tenant B Route',
                'code' => 'RTB-001',
            ])->assertCreated();
    });

    withTenantContext($tenantA, function () use ($ownerA): void {
        $response = $this->actingAs($ownerA)->getJson('/api/v1/routes');
        $response->assertOk();
        expect($response->json('data'))->toHaveCount(1);
        expect($response->json('data.0.name'))->toBe('Tenant A Route');
    });
});

test('unauthorized role cannot update route', function (): void {
    $tenant = makeTenant();
    $salesmanUser = makeUser($tenant, 'salesman', ['email' => 'salesman@test.test']);
    $branch = withTenantContext($tenant, fn () => Branch::factory()->forTenant($tenant->id)->create());
    $territory = withTenantContext($tenant, fn () => Territory::factory()->forTenant($tenant->id)->forBranch($branch->id)->create());
    $route = withTenantContext($tenant, fn () => Route::factory()->forTenant($tenant->id)->forTerritory($territory->id)->create());

    withTenantContext($tenant, function () use ($salesmanUser, $territory, $route): void {
        $this->actingAs($salesmanUser)
            ->putJson("/api/v1/routes/{$route->id}", [
                'territory_id' => $territory->id,
                'name' => 'Updated',
                'code' => 'UPD-001',
            ])->assertForbidden();
    });
});

test('validates weekday between 0 and 6', function (): void {
    $tenant = makeTenant();
    $owner = makeUser($tenant, 'owner');
    $branch = withTenantContext($tenant, fn () => Branch::factory()->forTenant($tenant->id)->create());
    $territory = withTenantContext($tenant, fn () => Territory::factory()->forTenant($tenant->id)->forBranch($branch->id)->create());

    withTenantContext($tenant, function () use ($owner, $territory): void {
        $this->actingAs($owner)
            ->postJson('/api/v1/routes', [
                'territory_id' => $territory->id,
                'name' => 'Invalid Weekday',
                'code' => 'INV-001',
                'weekday' => 7,
            ])->assertStatus(422);

        $this->actingAs($owner)
            ->postJson('/api/v1/routes', [
                'territory_id' => $territory->id,
                'name' => 'Invalid Weekday 2',
                'code' => 'INV-002',
                'weekday' => -1,
            ])->assertStatus(422);
    });
});

test('allows null weekday for any day', function (): void {
    $tenant = makeTenant();
    $owner = makeUser($tenant, 'owner');
    $branch = withTenantContext($tenant, fn () => Branch::factory()->forTenant($tenant->id)->create());
    $territory = withTenantContext($tenant, fn () => Territory::factory()->forTenant($tenant->id)->forBranch($branch->id)->create());

    withTenantContext($tenant, function () use ($owner, $territory): void {
        $response = $this->actingAs($owner)
            ->postJson('/api/v1/routes', [
                'territory_id' => $territory->id,
                'name' => 'Any Day Route',
                'code' => 'ANY-001',
                'weekday' => null,
            ])->assertCreated();

        expect($response->json('data.weekday'))->toBeNull();
    });
});

test('ordered route customers with visit_order', function (): void {
    $tenant = makeTenant();
    $owner = makeUser($tenant, 'owner');
    $branch = withTenantContext($tenant, fn () => Branch::factory()->forTenant($tenant->id)->create());
    $territory = withTenantContext($tenant, fn () => Territory::factory()->forTenant($tenant->id)->forBranch($branch->id)->create());
    $route = withTenantContext($tenant, fn () => Route::factory()->forTenant($tenant->id)->forTerritory($territory->id)->create());
    $customer1 = withTenantContext($tenant, fn () => Customer::factory()->forTenant($tenant->id)->forTerritory($territory->id)->create());
    $customer2 = withTenantContext($tenant, fn () => Customer::factory()->forTenant($tenant->id)->forTerritory($territory->id)->create());

    withTenantContext($tenant, function () use ($owner, $route, $customer1, $customer2, $tenant): void {
        RouteCustomer::create([
            'tenant_id' => $tenant->id,
            'route_id' => $route->id,
            'customer_id' => $customer1->id,
            'visit_order' => 2,
            'effective_from' => now()->toDateString(),
        ]);

        RouteCustomer::create([
            'tenant_id' => $tenant->id,
            'route_id' => $route->id,
            'customer_id' => $customer2->id,
            'visit_order' => 1,
            'effective_from' => now()->toDateString(),
        ]);

        $response = $this->actingAs($owner)->getJson("/api/v1/routes/{$route->id}/customers");
        $response->assertOk();

        $customers = $response->json('data');
        expect($customers[0]['visit_order'])->toBe(1);
        expect($customers[1]['visit_order'])->toBe(2);
    });
});

test('prevents duplicate active route membership for same customer', function (): void {
    $tenant = makeTenant();
    $owner = makeUser($tenant, 'owner');
    $branch = withTenantContext($tenant, fn () => Branch::factory()->forTenant($tenant->id)->create());
    $territory = withTenantContext($tenant, fn () => Territory::factory()->forTenant($tenant->id)->forBranch($branch->id)->create());
    $route1 = withTenantContext($tenant, fn () => Route::factory()->forTenant($tenant->id)->forTerritory($territory->id)->create());
    $route2 = withTenantContext($tenant, fn () => Route::factory()->forTenant($tenant->id)->forTerritory($territory->id)->create());
    $customer = withTenantContext($tenant, fn () => Customer::factory()->forTenant($tenant->id)->forTerritory($territory->id)->create());

    withTenantContext($tenant, function () use ($route1, $customer, $tenant): void {
        RouteCustomer::create([
            'tenant_id' => $tenant->id,
            'route_id' => $route1->id,
            'customer_id' => $customer->id,
            'visit_order' => 1,
            'effective_from' => now()->toDateString(),
        ]);
    });

    withTenantContext($tenant, function () use ($owner, $route2, $customer): void {
        $this->actingAs($owner)
            ->postJson("/api/v1/admin/routes/{$route2->id}/customers", [
                'customer_id' => $customer->id,
                'visit_order' => 1,
                'effective_from' => now()->toDateString(),
            ])->assertStatus(422);
    });
});

test('rejects cross-territory customer/route assignment', function (): void {
    $tenant = makeTenant();
    $owner = makeUser($tenant, 'owner');
    $branch = withTenantContext($tenant, fn () => Branch::factory()->forTenant($tenant->id)->create());
    $territoryA = withTenantContext($tenant, fn () => Territory::factory()->forTenant($tenant->id)->forBranch($branch->id)->create());
    $territoryB = withTenantContext($tenant, fn () => Territory::factory()->forTenant($tenant->id)->forBranch($branch->id)->create());
    $routeA = withTenantContext($tenant, fn () => Route::factory()->forTenant($tenant->id)->forTerritory($territoryA->id)->create());
    $customerB = withTenantContext($tenant, fn () => Customer::factory()->forTenant($tenant->id)->forTerritory($territoryB->id)->create());

    withTenantContext($tenant, function () use ($owner, $routeA, $customerB): void {
        $this->actingAs($owner)
            ->postJson("/api/v1/admin/routes/{$routeA->id}/customers", [
                'customer_id' => $customerB->id,
                'visit_order' => 1,
                'effective_from' => now()->toDateString(),
            ])->assertStatus(422);
    });
});

test('audit event for route customer assigned', function (): void {
    $tenant = makeTenant();
    $owner = makeUser($tenant, 'owner');
    $branch = withTenantContext($tenant, fn () => Branch::factory()->forTenant($tenant->id)->create());
    $territory = withTenantContext($tenant, fn () => Territory::factory()->forTenant($tenant->id)->forBranch($branch->id)->create());
    $route = withTenantContext($tenant, fn () => Route::factory()->forTenant($tenant->id)->forTerritory($territory->id)->create());
    $customer = withTenantContext($tenant, fn () => Customer::factory()->forTenant($tenant->id)->forTerritory($territory->id)->create());

    withTenantContext($tenant, function () use ($owner, $route, $customer): void {
        $this->actingAs($owner)
            ->postJson("/api/v1/admin/routes/{$route->id}/customers", [
                'customer_id' => $customer->id,
                'visit_order' => 1,
                'effective_from' => now()->toDateString(),
            ])->assertCreated();

        expect(AuditLog::where('event', 'route.customer_assigned')->exists())->toBeTrue();
    });
});
