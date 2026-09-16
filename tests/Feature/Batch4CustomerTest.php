<?php

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerCategory;
use App\Models\CustomerLocationHistory;
use App\Models\Route;
use App\Models\Salesman;
use App\Models\Territory;

beforeEach(function (): void {
    seedRbac();
});

/**
 * CUSTOMERS
 */
test('creates a customer with server-derived tenant_id', function (): void {
    $tenant = makeTenant();
    $owner = makeUser($tenant, 'owner');
    $branch = withTenantContext($tenant, fn () => Branch::factory()->forTenant($tenant->id)->create());
    $territory = withTenantContext($tenant, fn () => Territory::factory()->forTenant($tenant->id)->forBranch($branch->id)->create());

    withTenantContext($tenant, function () use ($owner, $branch, $territory, $tenant): void {
        $response = $this->actingAs($owner)
            ->postJson('/api/v1/customers', [
                'branch_id' => $branch->id,
                'territory_id' => $territory->id,
                'name' => 'Test Customer',
                'business_name' => 'Test Business',
            ]);

        $response->assertCreated();
        $customer = Customer::firstOrFail();
        expect($customer->tenant_id)->toBe($tenant->id);
    });
});

test('auto-generates tenant-unique customer code on creation', function (): void {
    $tenant = makeTenant();
    $owner = makeUser($tenant, 'owner');
    $branch = withTenantContext($tenant, fn () => Branch::factory()->forTenant($tenant->id)->create());
    $territory = withTenantContext($tenant, fn () => Territory::factory()->forTenant($tenant->id)->forBranch($branch->id)->create());

    withTenantContext($tenant, function () use ($owner, $branch, $territory, $tenant): void {
        $this->actingAs($owner)
            ->postJson('/api/v1/customers', [
                'branch_id' => $branch->id,
                'territory_id' => $territory->id,
                'name' => 'Customer 1',
            ])->assertCreated();

        $this->actingAs($owner)
            ->postJson('/api/v1/customers', [
                'branch_id' => $branch->id,
                'territory_id' => $territory->id,
                'name' => 'Customer 2',
            ])->assertCreated();

        $codes = withTenantContext($tenant, fn () => Customer::orderBy('id')->pluck('code')->all());
        expect($codes[0])->toStartWith('CUS-');
        expect($codes[1])->toStartWith('CUS-');
        expect($codes[0])->not->toBe($codes[1]);
    });
});

test('allows manual customer code if unique within tenant', function (): void {
    $tenant = makeTenant();
    $owner = makeUser($tenant, 'owner');
    $branch = withTenantContext($tenant, fn () => Branch::factory()->forTenant($tenant->id)->create());
    $territory = withTenantContext($tenant, fn () => Territory::factory()->forTenant($tenant->id)->forBranch($branch->id)->create());

    withTenantContext($tenant, function () use ($owner, $branch, $territory): void {
        $response = $this->actingAs($owner)
            ->postJson('/api/v1/customers', [
                'branch_id' => $branch->id,
                'territory_id' => $territory->id,
                'name' => 'Manual Code',
                'code' => 'MANUAL-001',
            ]);

        $response->assertCreated();
        expect($response->json('data.code'))->toBe('MANUAL-001');
    });
});

test('rejects duplicate customer code within same tenant', function (): void {
    $tenant = makeTenant();
    $owner = makeUser($tenant, 'owner');
    $branch = withTenantContext($tenant, fn () => Branch::factory()->forTenant($tenant->id)->create());
    $territory = withTenantContext($tenant, fn () => Territory::factory()->forTenant($tenant->id)->forBranch($branch->id)->create());

    withTenantContext($tenant, function () use ($owner, $branch, $territory): void {
        $this->actingAs($owner)
            ->postJson('/api/v1/customers', [
                'branch_id' => $branch->id,
                'territory_id' => $territory->id,
                'name' => 'First',
                'code' => 'DUP-001',
            ])->assertCreated();

        $this->actingAs($owner)
            ->postJson('/api/v1/customers', [
                'branch_id' => $branch->id,
                'territory_id' => $territory->id,
                'name' => 'Second',
                'code' => 'DUP-001',
            ])->assertUnprocessable();
    });
});

test('rejects cross-tenant branch_id on customer creation', function (): void {
    $tenantA = makeTenant();
    $tenantB = makeTenant();
    $ownerA = makeUser($tenantA, 'owner');
    $branchB = withTenantContext($tenantB, fn () => Branch::factory()->forTenant($tenantB->id)->create());
    $territoryA = withTenantContext($tenantA, fn () => Territory::factory()->forTenant($tenantA->id)->create());

    withTenantContext($tenantA, function () use ($ownerA, $branchB, $territoryA): void {
        $this->actingAs($ownerA)
            ->postJson('/api/v1/customers', [
                'branch_id' => $branchB->id,
                'territory_id' => $territoryA->id,
                'name' => 'Cross-tenant',
            ])->assertStatus(422);
    });
});

test('rejects cross-tenant territory_id on customer creation', function (): void {
    $tenantA = makeTenant();
    $tenantB = makeTenant();
    $ownerA = makeUser($tenantA, 'owner');
    $branchA = withTenantContext($tenantA, fn () => Branch::factory()->forTenant($tenantA->id)->create());
    $territoryB = withTenantContext($tenantB, fn () => Territory::factory()->forTenant($tenantB->id)->forBranch($branchA->id)->create());

    withTenantContext($tenantA, function () use ($ownerA, $branchA, $territoryB): void {
        $this->actingAs($ownerA)
            ->postJson('/api/v1/customers', [
                'branch_id' => $branchA->id,
                'territory_id' => $territoryB->id,
                'name' => 'Cross-tenant',
            ])->assertStatus(422);
    });
});

test('rejects cross-tenant route_id on customer creation', function (): void {
    $tenantA = makeTenant();
    $tenantB = makeTenant();
    $ownerA = makeUser($tenantA, 'owner');
    $branchA = withTenantContext($tenantA, fn () => Branch::factory()->forTenant($tenantA->id)->create());
    $territoryA = withTenantContext($tenantA, fn () => Territory::factory()->forTenant($tenantA->id)->forBranch($branchA->id)->create());
    $routeB = withTenantContext($tenantB, fn () => Route::factory()->forTenant($tenantB->id)->forTerritory($territoryA->id)->create());

    withTenantContext($tenantA, function () use ($ownerA, $branchA, $territoryA, $routeB): void {
        $this->actingAs($ownerA)
            ->postJson('/api/v1/customers', [
                'branch_id' => $branchA->id,
                'territory_id' => $territoryA->id,
                'route_id' => $routeB->id,
                'name' => 'Cross-tenant',
            ])->assertStatus(422);
    });
});

test('rejects cross-tenant category_id on customer creation', function (): void {
    $tenantA = makeTenant();
    $tenantB = makeTenant();
    $ownerA = makeUser($tenantA, 'owner');
    $branchA = withTenantContext($tenantA, fn () => Branch::factory()->forTenant($tenantA->id)->create());
    $territoryA = withTenantContext($tenantA, fn () => Territory::factory()->forTenant($tenantA->id)->forBranch($branchA->id)->create());
    $categoryB = withTenantContext($tenantB, fn () => CustomerCategory::factory()->forTenant($tenantB->id)->create());

    withTenantContext($tenantA, function () use ($ownerA, $branchA, $territoryA, $categoryB): void {
        $this->actingAs($ownerA)
            ->postJson('/api/v1/customers', [
                'branch_id' => $branchA->id,
                'territory_id' => $territoryA->id,
                'category_id' => $categoryB->id,
                'name' => 'Cross-tenant',
            ])->assertStatus(422);
    });
});

test('rejects cross-tenant assigned_salesman_id on customer creation', function (): void {
    $tenantA = makeTenant();
    $tenantB = makeTenant();
    $ownerA = makeUser($tenantA, 'owner');
    $branchA = withTenantContext($tenantA, fn () => Branch::factory()->forTenant($tenantA->id)->create());
    $territoryA = withTenantContext($tenantA, fn () => Territory::factory()->forTenant($tenantA->id)->forBranch($branchA->id)->create());
    $salesmanB = withTenantContext($tenantB, fn () => Salesman::factory()->forTenant($tenantB->id)->create());

    withTenantContext($tenantA, function () use ($ownerA, $branchA, $territoryA, $salesmanB): void {
        $this->actingAs($ownerA)
            ->postJson('/api/v1/customers', [
                'branch_id' => $branchA->id,
                'territory_id' => $territoryA->id,
                'assigned_salesman_id' => $salesmanB->id,
                'name' => 'Cross-tenant',
            ])->assertStatus(422);
    });
});

test('validates territory belongs to selected branch', function (): void {
    $tenant = makeTenant();
    $owner = makeUser($tenant, 'owner');
    $branchA = withTenantContext($tenant, fn () => Branch::factory()->forTenant($tenant->id)->create());
    $branchB = withTenantContext($tenant, fn () => Branch::factory()->forTenant($tenant->id)->create());
    $territoryB = withTenantContext($tenant, fn () => Territory::factory()->forTenant($tenant->id)->forBranch($branchB->id)->create());

    withTenantContext($tenant, function () use ($owner, $branchA, $territoryB): void {
        $this->actingAs($owner)
            ->postJson('/api/v1/customers', [
                'branch_id' => $branchA->id,
                'territory_id' => $territoryB->id,
                'name' => 'Invalid territory',
            ])->assertStatus(422);
    });
});

test('validates route belongs to selected territory', function (): void {
    $tenant = makeTenant();
    $owner = makeUser($tenant, 'owner');
    $branch = withTenantContext($tenant, fn () => Branch::factory()->forTenant($tenant->id)->create());
    $territoryA = withTenantContext($tenant, fn () => Territory::factory()->forTenant($tenant->id)->forBranch($branch->id)->create());
    $territoryB = withTenantContext($tenant, fn () => Territory::factory()->forTenant($tenant->id)->forBranch($branch->id)->create());
    $routeB = withTenantContext($tenant, fn () => Route::factory()->forTenant($tenant->id)->forTerritory($territoryB->id)->create());

    withTenantContext($tenant, function () use ($owner, $branch, $territoryA, $routeB): void {
        $this->actingAs($owner)
            ->postJson('/api/v1/customers', [
                'branch_id' => $branch->id,
                'territory_id' => $territoryA->id,
                'route_id' => $routeB->id,
                'name' => 'Invalid route',
            ])->assertStatus(422);
    });
});

test('validates latitude between -90 and 90', function (): void {
    $tenant = makeTenant();
    $owner = makeUser($tenant, 'owner');
    $branch = withTenantContext($tenant, fn () => Branch::factory()->forTenant($tenant->id)->create());
    $territory = withTenantContext($tenant, fn () => Territory::factory()->forTenant($tenant->id)->forBranch($branch->id)->create());

    withTenantContext($tenant, function () use ($owner, $branch, $territory): void {
        $this->actingAs($owner)
            ->postJson('/api/v1/customers', [
                'branch_id' => $branch->id,
                'territory_id' => $territory->id,
                'name' => 'Invalid lat',
                'latitude' => 91,
            ])->assertStatus(422);

        $this->actingAs($owner)
            ->postJson('/api/v1/customers', [
                'branch_id' => $branch->id,
                'territory_id' => $territory->id,
                'name' => 'Invalid lat 2',
                'latitude' => -91,
            ])->assertStatus(422);
    });
});

test('validates longitude between -180 and 180', function (): void {
    $tenant = makeTenant();
    $owner = makeUser($tenant, 'owner');
    $branch = withTenantContext($tenant, fn () => Branch::factory()->forTenant($tenant->id)->create());
    $territory = withTenantContext($tenant, fn () => Territory::factory()->forTenant($tenant->id)->forBranch($branch->id)->create());

    withTenantContext($tenant, function () use ($owner, $branch, $territory): void {
        $this->actingAs($owner)
            ->postJson('/api/v1/customers', [
                'branch_id' => $branch->id,
                'territory_id' => $territory->id,
                'name' => 'Invalid lng',
                'longitude' => 181,
            ])->assertStatus(422);
    });
});

test('validates geofence_radius positive and max 5000', function (): void {
    $tenant = makeTenant();
    $owner = makeUser($tenant, 'owner');
    $branch = withTenantContext($tenant, fn () => Branch::factory()->forTenant($tenant->id)->create());
    $territory = withTenantContext($tenant, fn () => Territory::factory()->forTenant($tenant->id)->forBranch($branch->id)->create());

    withTenantContext($tenant, function () use ($owner, $branch, $territory): void {
        $this->actingAs($owner)
            ->postJson('/api/v1/customers', [
                'branch_id' => $branch->id,
                'territory_id' => $territory->id,
                'name' => 'Invalid radius',
                'geofence_radius' => 0,
            ])->assertStatus(422);

        $this->actingAs($owner)
            ->postJson('/api/v1/customers', [
                'branch_id' => $branch->id,
                'territory_id' => $territory->id,
                'name' => 'Invalid radius 2',
                'geofence_radius' => 5001,
            ])->assertStatus(422);
    });
});

test('customer tenant isolation on API list', function (): void {
    $tenantA = makeTenant();
    $tenantB = makeTenant();
    $ownerA = makeUser($tenantA, 'owner');
    $branchA = withTenantContext($tenantA, fn () => Branch::factory()->forTenant($tenantA->id)->create());
    $territoryA = withTenantContext($tenantA, fn () => Territory::factory()->forTenant($tenantA->id)->forBranch($branchA->id)->create());

    withTenantContext($tenantA, function () use ($ownerA, $branchA, $territoryA): void {
        $this->actingAs($ownerA)
            ->postJson('/api/v1/customers', [
                'branch_id' => $branchA->id,
                'territory_id' => $territoryA->id,
                'name' => 'Tenant A Customer',
            ])->assertCreated();
    });

    $ownerB = makeUser($tenantB, 'owner');
    $branchB = withTenantContext($tenantB, fn () => Branch::factory()->forTenant($tenantB->id)->create());
    $territoryB = withTenantContext($tenantB, fn () => Territory::factory()->forTenant($tenantB->id)->forBranch($branchB->id)->create());

    withTenantContext($tenantB, function () use ($ownerB, $branchB, $territoryB): void {
        $this->actingAs($ownerB)
            ->postJson('/api/v1/customers', [
                'branch_id' => $branchB->id,
                'territory_id' => $territoryB->id,
                'name' => 'Tenant B Customer',
            ])->assertCreated();
    });

    withTenantContext($tenantA, function () use ($ownerA): void {
        $response = $this->actingAs($ownerA)->getJson('/api/v1/customers');
        $response->assertOk();
        expect($response->json('data'))->toHaveCount(1);
        expect($response->json('data.0.name'))->toBe('Tenant A Customer');
    });
});

test('customer API filters narrow results by search, category and status', function (): void {
    $tenant = makeTenant();
    $owner = makeUser($tenant, 'owner');
    $branch = withTenantContext($tenant, fn () => Branch::factory()->forTenant($tenant->id)->create());
    $territory = withTenantContext($tenant, fn () => Territory::factory()->forTenant($tenant->id)->forBranch($branch->id)->create());
    $category = withTenantContext($tenant, fn () => CustomerCategory::factory()->forTenant($tenant->id)->create());
    withTenantContext($tenant, fn () => Customer::factory()->forTenant($tenant->id)->create([
        'branch_id' => $branch->id,
        'territory_id' => $territory->id,
        'category_id' => $category->id,
        'business_name' => 'Kabul Trading Co',
        'code' => 'CUST-001',
    ]));
    withTenantContext($tenant, fn () => Customer::factory()->forTenant($tenant->id)->inactive()->create([
        'branch_id' => $branch->id,
        'territory_id' => $territory->id,
        'business_name' => 'Herat Trading Co',
        'code' => 'CUST-002',
    ]));
    $categoryId = $category->id;

    withTenantContext($tenant, function () use ($owner, $categoryId): void {
        $search = $this->actingAs($owner)->getJson('/api/v1/customers?filter[search]=Kabul');
        $search->assertOk();
        expect($search->json('data'))->toHaveCount(1);
        expect($search->json('data.0.code'))->toBe('CUST-001');

        $active = $this->actingAs($owner)->getJson('/api/v1/customers?filter[is_active]=true');
        $active->assertOk();
        expect($active->json('data'))->toHaveCount(1);
        expect($active->json('data.0.code'))->toBe('CUST-001');

        $inactive = $this->actingAs($owner)->getJson('/api/v1/customers?filter[is_active]=false');
        $inactive->assertOk();
        expect($inactive->json('data'))->toHaveCount(1);
        expect($inactive->json('data.0.code'))->toBe('CUST-002');

        $byCategory = $this->actingAs($owner)->getJson("/api/v1/customers?filter[category_id]={$categoryId}");
        $byCategory->assertOk();
        expect($byCategory->json('data'))->toHaveCount(1);
        expect($byCategory->json('data.0.code'))->toBe('CUST-001');
    });
});

test('creates location history when customer location changes', function (): void {
    $tenant = makeTenant();
    $owner = makeUser($tenant, 'owner');
    $branch = withTenantContext($tenant, fn () => Branch::factory()->forTenant($tenant->id)->create());
    $territory = withTenantContext($tenant, fn () => Territory::factory()->forTenant($tenant->id)->forBranch($branch->id)->create());

    $customerId = withTenantContext($tenant, function () use ($owner, $branch, $territory): int {
        $response = $this->actingAs($owner)
            ->postJson('/api/v1/customers', [
                'branch_id' => $branch->id,
                'territory_id' => $territory->id,
                'name' => 'Location Test',
                'latitude' => 34.5553,
                'longitude' => 69.2075,
                'address' => 'Old Address',
            ])->assertCreated();

        return $response->json('data.id');
    });

    // Update location
    withTenantContext($tenant, function () use ($owner, $branch, $territory, $customerId): void {
        $this->actingAs($owner)
            ->putJson("/api/v1/customers/{$customerId}", [
                'branch_id' => $branch->id,
                'territory_id' => $territory->id,
                'name' => 'Location Test Updated',
                'latitude' => 34.5600,
                'longitude' => 69.2100,
                'address' => 'New Address',
            ])->assertOk();
    });

    $historyCount = withTenantContext($tenant, fn () => CustomerLocationHistory::where('customer_id', $customerId)->count());
    expect($historyCount)->toBe(1);

    $history = withTenantContext($tenant, fn () => CustomerLocationHistory::where('customer_id', $customerId)->first());
    expect((float) $history->latitude)->toBe(34.5553);
    expect((float) $history->longitude)->toBe(69.2075);
    expect($history->address)->toBe('Old Address');
});

test('does not create location history when location unchanged', function (): void {
    $tenant = makeTenant();
    $owner = makeUser($tenant, 'owner');
    $branch = withTenantContext($tenant, fn () => Branch::factory()->forTenant($tenant->id)->create());
    $territory = withTenantContext($tenant, fn () => Territory::factory()->forTenant($tenant->id)->forBranch($branch->id)->create());

    $customerId = withTenantContext($tenant, function () use ($owner, $branch, $territory): int {
        $response = $this->actingAs($owner)
            ->postJson('/api/v1/customers', [
                'branch_id' => $branch->id,
                'territory_id' => $territory->id,
                'name' => 'No Location Change',
                'latitude' => 34.5553,
                'longitude' => 69.2075,
                'address' => 'Same Address',
            ])->assertCreated();

        return $response->json('data.id');
    });

    // Update without changing location
    withTenantContext($tenant, function () use ($owner, $branch, $territory, $customerId): void {
        $this->actingAs($owner)
            ->putJson("/api/v1/customers/{$customerId}", [
                'branch_id' => $branch->id,
                'territory_id' => $territory->id,
                'name' => 'No Location Change Updated',
                'latitude' => 34.5553,
                'longitude' => 69.2075,
                'address' => 'Same Address',
            ])->assertOk();
    });

    $historyCount = withTenantContext($tenant, fn () => CustomerLocationHistory::where('customer_id', $customerId)->count());
    expect($historyCount)->toBe(0);
});

test('offline_uuid idempotency - repeated same UUID returns existing customer', function (): void {
    $tenant = makeTenant();
    $owner = makeUser($tenant, 'owner');
    $branch = withTenantContext($tenant, fn () => Branch::factory()->forTenant($tenant->id)->create());
    $territory = withTenantContext($tenant, fn () => Territory::factory()->forTenant($tenant->id)->forBranch($branch->id)->create());
    $offlineUuid = '550e8400-e29b-41d4-a716-446655440000';

    withTenantContext($tenant, function () use ($owner, $branch, $territory, $offlineUuid): void {
        $response1 = $this->actingAs($owner)
            ->postJson('/api/v1/customers', [
                'branch_id' => $branch->id,
                'territory_id' => $territory->id,
                'name' => 'Idempotent Test',
                'offline_uuid' => $offlineUuid,
            ])->assertCreated();

        $customerId1 = $response1->json('data.id');

        $response2 = $this->actingAs($owner)
            ->postJson('/api/v1/customers', [
                'branch_id' => $branch->id,
                'territory_id' => $territory->id,
                'name' => 'Idempotent Test Different',
                'offline_uuid' => $offlineUuid,
            ])->assertOk();

        $customerId2 = $response2->json('data.id');

        expect($customerId1)->toBe($customerId2);
        expect(Customer::count())->toBe(1);
    });
});

test('offline_uuid cross-tenant isolation', function (): void {
    $tenantA = makeTenant();
    $tenantB = makeTenant();
    $ownerA = makeUser($tenantA, 'owner');
    $ownerB = makeUser($tenantB, 'owner');
    $branchA = withTenantContext($tenantA, fn () => Branch::factory()->forTenant($tenantA->id)->create());
    $territoryA = withTenantContext($tenantA, fn () => Territory::factory()->forTenant($tenantA->id)->forBranch($branchA->id)->create());
    $branchB = withTenantContext($tenantB, fn () => Branch::factory()->forTenant($tenantB->id)->create());
    $territoryB = withTenantContext($tenantB, fn () => Territory::factory()->forTenant($tenantB->id)->forBranch($branchB->id)->create());
    $offlineUuid = '550e8400-e29b-41d4-a716-446655440001';

    withTenantContext($tenantA, function () use ($ownerA, $branchA, $territoryA, $offlineUuid): void {
        $this->actingAs($ownerA)
            ->postJson('/api/v1/customers', [
                'branch_id' => $branchA->id,
                'territory_id' => $territoryA->id,
                'name' => 'Tenant A Customer',
                'offline_uuid' => $offlineUuid,
            ])->assertCreated();
    });

    withTenantContext($tenantB, function () use ($ownerB, $branchB, $territoryB, $offlineUuid): void {
        $response = $this->actingAs($ownerB)
            ->postJson('/api/v1/customers', [
                'branch_id' => $branchB->id,
                'territory_id' => $territoryB->id,
                'name' => 'Tenant B Customer',
                'offline_uuid' => $offlineUuid,
            ])->assertCreated();

        expect($response->json('data.name'))->toBe('Tenant B Customer');
    });
});

test('salesman cannot assign customer to another salesman', function (): void {
    $tenant = makeTenant();
    $owner = makeUser($tenant, 'owner');
    $salesmanUser = makeUser($tenant, 'salesman', ['email' => 'salesman@test.test']);
    $salesman = withTenantContext($tenant, fn () => Salesman::factory()->forTenant($tenant->id)->forUser($salesmanUser->id)->create());
    $otherSalesman = withTenantContext($tenant, fn () => Salesman::factory()->forTenant($tenant->id)->create());
    $branch = withTenantContext($tenant, fn () => Branch::factory()->forTenant($tenant->id)->create());
    $territory = withTenantContext($tenant, fn () => Territory::factory()->forTenant($tenant->id)->forBranch($branch->id)->create());

    $customerId = withTenantContext($tenant, function () use ($owner, $branch, $territory, $salesman): int {
        $response = $this->actingAs($owner)
            ->postJson('/api/v1/customers', [
                'branch_id' => $branch->id,
                'territory_id' => $territory->id,
                'name' => 'Assigned to Salesman',
                'assigned_salesman_id' => $salesman->id,
            ])->assertCreated();

        return $response->json('data.id');
    });

    withTenantContext($tenant, function () use ($salesmanUser, $branch, $territory, $otherSalesman, $customerId): void {
        $this->actingAs($salesmanUser)
            ->putJson("/api/v1/customers/{$customerId}", [
                'branch_id' => $branch->id,
                'territory_id' => $territory->id,
                'name' => 'Assigned to Salesman',
                'assigned_salesman_id' => $otherSalesman->id,
            ])->assertForbidden();
    });
});

test('customer update API works', function (): void {
    $tenant = makeTenant();
    $owner = makeUser($tenant, 'owner');
    $branch = withTenantContext($tenant, fn () => Branch::factory()->forTenant($tenant->id)->create());
    $territory = withTenantContext($tenant, fn () => Territory::factory()->forTenant($tenant->id)->forBranch($branch->id)->create());

    withTenantContext($tenant, function () use ($owner, $branch, $territory): void {
        $response = $this->actingAs($owner)
            ->postJson('/api/v1/customers', [
                'branch_id' => $branch->id,
                'territory_id' => $territory->id,
                'name' => 'Original Name',
            ])->assertCreated();

        $customerId = $response->json('data.id');

        $updateResponse = $this->actingAs($owner)
            ->putJson("/api/v1/customers/{$customerId}", [
                'branch_id' => $branch->id,
                'territory_id' => $territory->id,
                'name' => 'Updated Name',
                'contact_person' => 'John Doe',
            ])->assertOk();

        expect($updateResponse->json('data.name'))->toBe('Updated Name');
        expect($updateResponse->json('data.contact_person'))->toBe('John Doe');
    });
});

test('audit event created for customer creation', function (): void {
    $tenant = makeTenant();
    $owner = makeUser($tenant, 'owner');
    $branch = withTenantContext($tenant, fn () => Branch::factory()->forTenant($tenant->id)->create());
    $territory = withTenantContext($tenant, fn () => Territory::factory()->forTenant($tenant->id)->forBranch($branch->id)->create());

    withTenantContext($tenant, function () use ($owner, $branch, $territory): void {
        $this->actingAs($owner)
            ->postJson('/api/v1/customers', [
                'branch_id' => $branch->id,
                'territory_id' => $territory->id,
                'name' => 'Audit Test',
            ])->assertCreated();

        expect(AuditLog::where('event', 'customer.created')->exists())->toBeTrue();
    });
});
