<?php

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Route;
use App\Models\Salesman;
use App\Models\SalesmanAssignment;
use App\Models\Supervisor;
use App\Models\SupervisorAssignment;
use App\Models\Territory;

beforeEach(function (): void {
    seedRbac();
});

/**
 * SALESMAN ASSIGNMENTS
 */
test('creates salesman assignment', function (): void {
    $tenant = makeTenant();
    $owner = makeUser($tenant, 'owner');
    $salesman = withTenantContext($tenant, fn () => Salesman::factory()->forTenant($tenant->id)->create());
    $branch = withTenantContext($tenant, fn () => Branch::factory()->forTenant($tenant->id)->create());
    $territory = withTenantContext($tenant, fn () => Territory::factory()->forTenant($tenant->id)->forBranch($branch->id)->create());
    $route = withTenantContext($tenant, fn () => Route::factory()->forTenant($tenant->id)->forTerritory($territory->id)->create());
    $supervisor = withTenantContext($tenant, fn () => Supervisor::factory()->forTenant($tenant->id)->create());

    withTenantContext($tenant, function () use ($owner, $salesman, $branch, $territory, $route, $supervisor): void {
        $this->actingAs($owner)
            ->postJson('/api/v1/admin/salesman-assignments', [
                'salesman_id' => $salesman->id,
                'branch_id' => $branch->id,
                'territory_id' => $territory->id,
                'route_id' => $route->id,
                'supervisor_id' => $supervisor->id,
                'effective_from' => now()->toDateString(),
            ])->assertCreated();

        expect(AuditLog::where('event', 'salesman.assignment_created')->exists())->toBeTrue();
    });
});

test('previous active assignment closed when new one created', function (): void {
    $tenant = makeTenant();
    $owner = makeUser($tenant, 'owner');
    $salesman = withTenantContext($tenant, fn () => Salesman::factory()->forTenant($tenant->id)->create());
    $branch = withTenantContext($tenant, fn () => Branch::factory()->forTenant($tenant->id)->create());
    $territory1 = withTenantContext($tenant, fn () => Territory::factory()->forTenant($tenant->id)->forBranch($branch->id)->create());
    $territory2 = withTenantContext($tenant, fn () => Territory::factory()->forTenant($tenant->id)->forBranch($branch->id)->create());
    $route = withTenantContext($tenant, fn () => Route::factory()->forTenant($tenant->id)->forTerritory($territory1->id)->create());
    $supervisor = withTenantContext($tenant, fn () => Supervisor::factory()->forTenant($tenant->id)->create());

    $assignment1Id = withTenantContext($tenant, function () use ($owner, $salesman, $branch, $territory1, $route, $supervisor): int {
        // First assignment
        $this->actingAs($owner)
            ->postJson('/api/v1/admin/salesman-assignments', [
                'salesman_id' => $salesman->id,
                'branch_id' => $branch->id,
                'territory_id' => $territory1->id,
                'route_id' => $route->id,
                'supervisor_id' => $supervisor->id,
                'effective_from' => now()->toDateString(),
            ])->assertCreated();

        return SalesmanAssignment::where('salesman_id', $salesman->id)->firstOrFail()->id;
    });

    withTenantContext($tenant, function () use ($owner, $salesman, $branch, $territory2, $assignment1Id): void {
        // Second assignment - should close the first
        $this->actingAs($owner)
            ->postJson('/api/v1/admin/salesman-assignments', [
                'salesman_id' => $salesman->id,
                'branch_id' => $branch->id,
                'territory_id' => $territory2->id,
                'effective_from' => now()->addDay()->toDateString(),
            ])->assertCreated();

        $assignment1 = SalesmanAssignment::find($assignment1Id);
        $assignment2 = SalesmanAssignment::where('salesman_id', $salesman->id)->latest('effective_from')->first();

        // First assignment should have effective_to set
        expect($assignment1->effective_to)->not->toBeNull();
        // Second assignment should be current
        expect($assignment2->isCurrentlyActive())->toBeTrue();
    });
});

test('current assignment helper works', function (): void {
    $tenant = makeTenant();
    $owner = makeUser($tenant, 'owner');
    $salesman = withTenantContext($tenant, fn () => Salesman::factory()->forTenant($tenant->id)->create());
    $branch = withTenantContext($tenant, fn () => Branch::factory()->forTenant($tenant->id)->create());
    $territory = withTenantContext($tenant, fn () => Territory::factory()->forTenant($tenant->id)->forBranch($branch->id)->create());
    $route = withTenantContext($tenant, fn () => Route::factory()->forTenant($tenant->id)->forTerritory($territory->id)->create());
    $supervisor = withTenantContext($tenant, fn () => Supervisor::factory()->forTenant($tenant->id)->create());

    withTenantContext($tenant, function () use ($owner, $salesman, $branch, $territory, $route, $supervisor): void {
        $this->actingAs($owner)
            ->postJson('/api/v1/admin/salesman-assignments', [
                'salesman_id' => $salesman->id,
                'branch_id' => $branch->id,
                'territory_id' => $territory->id,
                'route_id' => $route->id,
                'supervisor_id' => $supervisor->id,
                'effective_from' => now()->toDateString(),
            ])->assertCreated();

        // Refresh salesman and check current assignment
        $salesman = $salesman->fresh();
        $current = $salesman->currentAssignment;

        expect($current)->not->toBeNull();
        expect($current->territory_id)->toBe($territory->id);
        expect($current->route_id)->toBe($route->id);
        expect($current->supervisor_id)->toBe($supervisor->id);
    });
});

test('rejects cross-tenant salesman_id on assignment', function (): void {
    $tenantA = makeTenant();
    $tenantB = makeTenant();
    $ownerA = makeUser($tenantA, 'owner');
    $salesmanB = withTenantContext($tenantB, fn () => Salesman::factory()->forTenant($tenantB->id)->create());
    $branchA = withTenantContext($tenantA, fn () => Branch::factory()->forTenant($tenantA->id)->create());
    $territoryA = withTenantContext($tenantA, fn () => Territory::factory()->forTenant($tenantA->id)->forBranch($branchA->id)->create());

    withTenantContext($tenantA, function () use ($ownerA, $salesmanB, $branchA, $territoryA): void {
        $this->actingAs($ownerA)
            ->postJson('/api/v1/admin/salesman-assignments', [
                'salesman_id' => $salesmanB->id,
                'branch_id' => $branchA->id,
                'territory_id' => $territoryA->id,
                'effective_from' => now()->toDateString(),
            ])->assertStatus(422);
    });
});

test('rejects cross-tenant territory_id on assignment', function (): void {
    $tenantA = makeTenant();
    $tenantB = makeTenant();
    $ownerA = makeUser($tenantA, 'owner');
    $salesmanA = withTenantContext($tenantA, fn () => Salesman::factory()->forTenant($tenantA->id)->create());
    $branchA = withTenantContext($tenantA, fn () => Branch::factory()->forTenant($tenantA->id)->create());
    $territoryB = withTenantContext($tenantB, fn () => Territory::factory()->forTenant($tenantB->id)->create());

    withTenantContext($tenantA, function () use ($ownerA, $salesmanA, $branchA, $territoryB): void {
        $this->actingAs($ownerA)
            ->postJson('/api/v1/admin/salesman-assignments', [
                'salesman_id' => $salesmanA->id,
                'branch_id' => $branchA->id,
                'territory_id' => $territoryB->id,
                'effective_from' => now()->toDateString(),
            ])->assertStatus(422);
    });
});

/**
 * SUPERVISOR ASSIGNMENTS
 */
test('creates supervisor assignment', function (): void {
    $tenant = makeTenant();
    $owner = makeUser($tenant, 'owner');
    $supervisor = withTenantContext($tenant, fn () => Supervisor::factory()->forTenant($tenant->id)->create());
    $branch = withTenantContext($tenant, fn () => Branch::factory()->forTenant($tenant->id)->create());
    $territory = withTenantContext($tenant, fn () => Territory::factory()->forTenant($tenant->id)->forBranch($branch->id)->create());

    withTenantContext($tenant, function () use ($owner, $supervisor, $branch, $territory): void {
        $this->actingAs($owner)
            ->postJson('/api/v1/admin/supervisor-assignments', [
                'supervisor_id' => $supervisor->id,
                'branch_id' => $branch->id,
                'territory_id' => $territory->id,
                'effective_from' => now()->toDateString(),
            ])->assertCreated();

        expect(AuditLog::where('event', 'supervisor.assignment_created')->exists())->toBeTrue();
    });
});

test('preserves historical supervisor assignment rows', function (): void {
    $tenant = makeTenant();
    $owner = makeUser($tenant, 'owner');
    $supervisor = withTenantContext($tenant, fn () => Supervisor::factory()->forTenant($tenant->id)->create());
    $branch = withTenantContext($tenant, fn () => Branch::factory()->forTenant($tenant->id)->create());
    $territory1 = withTenantContext($tenant, fn () => Territory::factory()->forTenant($tenant->id)->forBranch($branch->id)->create());
    $territory2 = withTenantContext($tenant, fn () => Territory::factory()->forTenant($tenant->id)->forBranch($branch->id)->create());

    withTenantContext($tenant, function () use ($owner, $supervisor, $branch, $territory1): void {
        $this->actingAs($owner)
            ->postJson('/api/v1/admin/supervisor-assignments', [
                'supervisor_id' => $supervisor->id,
                'branch_id' => $branch->id,
                'territory_id' => $territory1->id,
                'effective_from' => now()->toDateString(),
            ])->assertCreated();
    });

    withTenantContext($tenant, function () use ($owner, $supervisor, $branch, $territory2, $tenant): void {
        $this->actingAs($owner)
            ->postJson('/api/v1/admin/supervisor-assignments', [
                'supervisor_id' => $supervisor->id,
                'branch_id' => $branch->id,
                'territory_id' => $territory2->id,
                'effective_from' => now()->addDay()->toDateString(),
            ])->assertCreated();

        $count = withTenantContext($tenant, fn () => SupervisorAssignment::where('supervisor_id', $supervisor->id)->count());
        expect($count)->toBe(2);
    });
});

test('supervisor supports multiple active territories', function (): void {
    $tenant = makeTenant();
    $owner = makeUser($tenant, 'owner');
    $supervisor = withTenantContext($tenant, fn () => Supervisor::factory()->forTenant($tenant->id)->create());
    $branch = withTenantContext($tenant, fn () => Branch::factory()->forTenant($tenant->id)->create());
    $territory1 = withTenantContext($tenant, fn () => Territory::factory()->forTenant($tenant->id)->forBranch($branch->id)->create());
    $territory2 = withTenantContext($tenant, fn () => Territory::factory()->forTenant($tenant->id)->forBranch($branch->id)->create());

    withTenantContext($tenant, function () use ($owner, $supervisor, $branch, $territory1): void {
        $this->actingAs($owner)
            ->postJson('/api/v1/admin/supervisor-assignments', [
                'supervisor_id' => $supervisor->id,
                'branch_id' => $branch->id,
                'territory_id' => $territory1->id,
                'effective_from' => now()->toDateString(),
            ])->assertCreated();
    });

    withTenantContext($tenant, function () use ($owner, $supervisor, $branch, $territory2, $tenant): void {
        $this->actingAs($owner)
            ->postJson('/api/v1/admin/supervisor-assignments', [
                'supervisor_id' => $supervisor->id,
                'branch_id' => $branch->id,
                'territory_id' => $territory2->id,
                'effective_from' => now()->toDateString(),
            ])->assertCreated();

        $activeCount = withTenantContext($tenant, fn () => SupervisorAssignment::query()
            ->where('supervisor_id', $supervisor->id)
            ->active()
            ->count());

        expect($activeCount)->toBe(2);
    });
});

test('prevents duplicate conflicting supervisor assignment', function (): void {
    $tenant = makeTenant();
    $owner = makeUser($tenant, 'owner');
    $supervisor = withTenantContext($tenant, fn () => Supervisor::factory()->forTenant($tenant->id)->create());
    $branch = withTenantContext($tenant, fn () => Branch::factory()->forTenant($tenant->id)->create());
    $territory = withTenantContext($tenant, fn () => Territory::factory()->forTenant($tenant->id)->forBranch($branch->id)->create());

    withTenantContext($tenant, function () use ($owner, $supervisor, $branch, $territory): void {
        $this->actingAs($owner)
            ->postJson('/api/v1/admin/supervisor-assignments', [
                'supervisor_id' => $supervisor->id,
                'branch_id' => $branch->id,
                'territory_id' => $territory->id,
                'effective_from' => now()->toDateString(),
            ])->assertCreated();
    });

    withTenantContext($tenant, function () use ($owner, $supervisor, $branch, $territory): void {
        $this->actingAs($owner)
            ->postJson('/api/v1/admin/supervisor-assignments', [
                'supervisor_id' => $supervisor->id,
                'branch_id' => $branch->id,
                'territory_id' => $territory->id,
                'effective_from' => now()->toDateString(),
            ])->assertStatus(422);
    });
});

test('rejects cross-tenant supervisor_id on assignment', function (): void {
    $tenantA = makeTenant();
    $tenantB = makeTenant();
    $ownerA = makeUser($tenantA, 'owner');
    $supervisorB = withTenantContext($tenantB, fn () => Supervisor::factory()->forTenant($tenantB->id)->create());
    $branchA = withTenantContext($tenantA, fn () => Branch::factory()->forTenant($tenantA->id)->create());
    $territoryA = withTenantContext($tenantA, fn () => Territory::factory()->forTenant($tenantA->id)->forBranch($branchA->id)->create());

    withTenantContext($tenantA, function () use ($ownerA, $supervisorB, $branchA, $territoryA): void {
        $this->actingAs($ownerA)
            ->postJson('/api/v1/admin/supervisor-assignments', [
                'supervisor_id' => $supervisorB->id,
                'branch_id' => $branchA->id,
                'territory_id' => $territoryA->id,
                'effective_from' => now()->toDateString(),
            ])->assertStatus(422);
    });
});

test('rejects cross-tenant territory_id on supervisor assignment', function (): void {
    $tenantA = makeTenant();
    $tenantB = makeTenant();
    $ownerA = makeUser($tenantA, 'owner');
    $supervisorA = withTenantContext($tenantA, fn () => Supervisor::factory()->forTenant($tenantA->id)->create());
    $branchA = withTenantContext($tenantA, fn () => Branch::factory()->forTenant($tenantA->id)->create());
    $territoryB = withTenantContext($tenantB, fn () => Territory::factory()->forTenant($tenantB->id)->create());

    withTenantContext($tenantA, function () use ($ownerA, $supervisorA, $branchA, $territoryB): void {
        $this->actingAs($ownerA)
            ->postJson('/api/v1/admin/supervisor-assignments', [
                'supervisor_id' => $supervisorA->id,
                'branch_id' => $branchA->id,
                'territory_id' => $territoryB->id,
                'effective_from' => now()->toDateString(),
            ])->assertStatus(422);
    });
});

test('audit event for supervisor assignment created', function (): void {
    $tenant = makeTenant();
    $owner = makeUser($tenant, 'owner');
    $supervisor = withTenantContext($tenant, fn () => Supervisor::factory()->forTenant($tenant->id)->create());
    $branch = withTenantContext($tenant, fn () => Branch::factory()->forTenant($tenant->id)->create());
    $territory = withTenantContext($tenant, fn () => Territory::factory()->forTenant($tenant->id)->forBranch($branch->id)->create());

    withTenantContext($tenant, function () use ($owner, $supervisor, $branch, $territory): void {
        $this->actingAs($owner)
            ->postJson('/api/v1/admin/supervisor-assignments', [
                'supervisor_id' => $supervisor->id,
                'branch_id' => $branch->id,
                'territory_id' => $territory->id,
                'effective_from' => now()->toDateString(),
            ])->assertCreated();

        expect(AuditLog::where('event', 'supervisor.assignment_created')->exists())->toBeTrue();
    });
});
