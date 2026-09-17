<?php

use App\Models\Branch;
use App\Models\Route;
use App\Models\Salesman;
use App\Models\SalesmanAssignment;
use App\Models\Supervisor;
use App\Models\SupervisorAssignment;
use App\Models\Tenant;
use App\Models\Territory;
use App\Models\User;

beforeEach(function (): void {
    seedRbac();
});

/**
 * Build a complete sales-team fixture inside the given tenant.
 *
 * @return array{owner: User, branch: Branch, territory: Territory, route: Route, salesman: Salesman, supervisor: Supervisor}
 */
function makeBatch3AssignmentTeam(Tenant $tenant, string $suffix = ''): array
{
    return withTenantContext($tenant, function () use ($tenant, $suffix): array {
        $owner = makeUser($tenant, 'owner');

        $branch = Branch::factory()->forTenant($tenant->id)->create(['name' => 'Main Branch'.$suffix]);
        $territory = Territory::factory()->forTenant($tenant->id)->forBranch($branch->id)->create(['name' => 'North Territory'.$suffix]);
        $route = Route::factory()->forTenant($tenant->id)->forTerritory($territory->id)->create(['name' => 'Route A'.$suffix]);
        $salesman = Salesman::factory()->forTenant($tenant->id)->create(['first_name' => 'Ali', 'last_name' => 'Khan'.$suffix]);
        $supervisor = Supervisor::factory()->forTenant($tenant->id)->create(['first_name' => 'Sara', 'last_name' => 'Ahmadi'.$suffix]);

        return compact('owner', 'branch', 'territory', 'route', 'salesman', 'supervisor');
    });
}

it('renders the salesman assignment index with current and historical assignments', function (): void {
    $tenant = makeTenant();
    $team = makeBatch3AssignmentTeam($tenant);

    withTenantContext($tenant, function () use ($tenant, $team): void {
        SalesmanAssignment::create([
            'tenant_id' => $tenant->id,
            'salesman_id' => $team['salesman']->id,
            'branch_id' => $team['branch']->id,
            'territory_id' => $team['territory']->id,
            'route_id' => $team['route']->id,
            'supervisor_id' => $team['supervisor']->id,
            'effective_from' => now()->subMonths(2)->toDateString(),
            'effective_to' => now()->subMonth()->toDateString(),
            'created_by' => $team['owner']->id,
        ]);

        SalesmanAssignment::create([
            'tenant_id' => $tenant->id,
            'salesman_id' => $team['salesman']->id,
            'branch_id' => $team['branch']->id,
            'territory_id' => $team['territory']->id,
            'effective_from' => now()->subWeek()->toDateString(),
        ]);

        $this->actingAs($team['owner'])
            ->get(route('salesman-assignments.index'))
            ->assertOk()
            ->assertSee('Ali Khan')
            ->assertSee('Ended')
            ->assertSee('Current');
    });
});

it('completes the salesman assignment web lifecycle', function (): void {
    $tenant = makeTenant();
    $team = makeBatch3AssignmentTeam($tenant);

    withTenantContext($tenant, function () use ($team): void {
        $this->actingAs($team['owner'])
            ->get(route('salesman-assignments.create'))
            ->assertOk()
            ->assertSee('New salesman assignment');

        $this->actingAs($team['owner'])
            ->post(route('salesman-assignments.store'), [
                'salesman_id' => $team['salesman']->id,
                'branch_id' => $team['branch']->id,
                'territory_id' => $team['territory']->id,
                'route_id' => $team['route']->id,
                'supervisor_id' => $team['supervisor']->id,
                'effective_from' => now()->toDateString(),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $assignment = SalesmanAssignment::latest('id')->firstOrFail();

        $this->actingAs($team['owner'])
            ->get(route('salesman-assignments.show', $assignment))
            ->assertOk()
            ->assertSee('Ali Khan')
            ->assertSee('Route A')
            ->assertSee('Sara Ahmadi');

        $this->actingAs($team['owner'])
            ->get(route('salesman-assignments.edit', $assignment))
            ->assertOk()
            ->assertSee('Edit salesman assignment');

        $this->actingAs($team['owner'])
            ->put(route('salesman-assignments.update', $assignment), [
                'salesman_id' => $team['salesman']->id,
                'branch_id' => $team['branch']->id,
                'territory_id' => $team['territory']->id,
                'route_id' => null,
                'supervisor_id' => $team['supervisor']->id,
                'effective_from' => now()->subDay()->toDateString(),
                'effective_to' => now()->addMonth()->toDateString(),
            ])
            ->assertRedirect(route('salesman-assignments.show', $assignment))
            ->assertSessionHasNoErrors();

        expect($assignment->fresh()->route_id)->toBeNull();
    });
});

it('validates salesman assignment input', function (): void {
    $tenant = makeTenant();
    $team = makeBatch3AssignmentTeam($tenant);

    withTenantContext($tenant, function () use ($team): void {
        $this->actingAs($team['owner'])
            ->post(route('salesman-assignments.store'), [
                'branch_id' => $team['branch']->id,
                'territory_id' => $team['territory']->id,
                'effective_from' => now()->toDateString(),
            ])
            ->assertSessionHasErrors('salesman_id');
    });
});

it('completes the supervisor assignment web lifecycle', function (): void {
    $tenant = makeTenant();
    $team = makeBatch3AssignmentTeam($tenant);

    withTenantContext($tenant, function () use ($team): void {
        $this->actingAs($team['owner'])
            ->get(route('supervisor-assignments.index'))
            ->assertOk();

        $this->actingAs($team['owner'])
            ->get(route('supervisor-assignments.create'))
            ->assertOk()
            ->assertSee('New supervisor assignment');

        $this->actingAs($team['owner'])
            ->post(route('supervisor-assignments.store'), [
                'supervisor_id' => $team['supervisor']->id,
                'branch_id' => $team['branch']->id,
                'territory_id' => $team['territory']->id,
                'effective_from' => now()->toDateString(),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $assignment = SupervisorAssignment::latest('id')->firstOrFail();

        $this->actingAs($team['owner'])
            ->get(route('supervisor-assignments.show', $assignment))
            ->assertOk()
            ->assertSee('Sara Ahmadi')
            ->assertSee('North Territory');

        $this->actingAs($team['owner'])
            ->get(route('supervisor-assignments.edit', $assignment))
            ->assertOk()
            ->assertSee('Edit supervisor assignment');

        $this->actingAs($team['owner'])
            ->put(route('supervisor-assignments.update', $assignment), [
                'supervisor_id' => $team['supervisor']->id,
                'branch_id' => $team['branch']->id,
                'territory_id' => null,
                'effective_from' => now()->subDay()->toDateString(),
                'effective_to' => now()->addMonth()->toDateString(),
            ])
            ->assertRedirect(route('supervisor-assignments.show', $assignment))
            ->assertSessionHasNoErrors();

        expect($assignment->fresh()->territory_id)->toBeNull();
    });
});

it('keeps assignment pages and selectors tenant isolated', function (): void {
    $tenantA = makeTenant();
    $tenantB = makeTenant();
    $teamA = makeBatch3AssignmentTeam($tenantA);
    $teamB = makeBatch3AssignmentTeam($tenantB, ' B');

    $assignmentB = withTenantContext($tenantB, fn () => SalesmanAssignment::create([
        'tenant_id' => $tenantB->id,
        'salesman_id' => $teamB['salesman']->id,
        'branch_id' => $teamB['branch']->id,
        'territory_id' => $teamB['territory']->id,
        'effective_from' => now()->toDateString(),
    ]));

    withTenantContext($tenantA, function () use ($teamA, $teamB, $assignmentB): void {
        $this->actingAs($teamA['owner'])
            ->get(route('salesman-assignments.index'))
            ->assertOk()
            ->assertSee('Ali Khan');

        $this->actingAs($teamA['owner'])
            ->get(route('salesman-assignments.create'))
            ->assertOk()
            ->assertSee('Ali Khan')
            ->assertDontSee('Khan B')
            ->assertDontSee('Main Branch B')
            ->assertDontSee('North Territory B');

        $this->actingAs($teamA['owner'])
            ->get(route('salesman-assignments.show', $assignmentB))
            ->assertNotFound();

        $this->actingAs($teamA['owner'])
            ->get(route('salesman-assignments.edit', $assignmentB))
            ->assertNotFound();

        $this->actingAs($teamA['owner'])
            ->post(route('salesman-assignments.store'), [
                'salesman_id' => $teamB['salesman']->id,
                'branch_id' => $teamA['branch']->id,
                'territory_id' => $teamA['territory']->id,
                'effective_from' => now()->toDateString(),
            ])
            ->assertSessionHasErrors('salesman_id');
    });
});

it('still renders historical assignments for inactive related records', function (): void {
    $tenant = makeTenant();
    $team = makeBatch3AssignmentTeam($tenant);

    $assignment = withTenantContext($tenant, function () use ($tenant, $team): SalesmanAssignment {
        $assignment = SalesmanAssignment::create([
            'tenant_id' => $tenant->id,
            'salesman_id' => $team['salesman']->id,
            'branch_id' => $team['branch']->id,
            'territory_id' => $team['territory']->id,
            'effective_from' => now()->subMonths(6)->toDateString(),
            'effective_to' => now()->subMonths(5)->toDateString(),
        ]);

        $team['salesman']->update(['is_active' => false]);
        $team['supervisor']->update(['is_active' => false]);

        return $assignment;
    });

    withTenantContext($tenant, function () use ($team, $assignment): void {
        $this->actingAs($team['owner'])
            ->get(route('salesman-assignments.index'))
            ->assertOk()
            ->assertSee('Ali Khan')
            ->assertSee('Ended');

        $this->actingAs($team['owner'])
            ->get(route('salesman-assignments.show', $assignment))
            ->assertOk()
            ->assertSee('Ali Khan');
    });

    withTenantContext($tenant, function () use ($team): void {
        $this->actingAs($team['owner'])
            ->get(route('salesman-assignments.create'))
            ->assertOk()
            ->assertDontSee('Ali Khan');
    });
});

it('enforces assignment permissions through the web routes', function (): void {
    $tenant = makeTenant();
    $team = makeBatch3AssignmentTeam($tenant);
    $viewer = makeUser($tenant, 'sales_manager');
    $outsider = makeUser($tenant, 'salesman');

    $assignment = withTenantContext($tenant, fn () => SalesmanAssignment::create([
        'tenant_id' => $tenant->id,
        'salesman_id' => $team['salesman']->id,
        'branch_id' => $team['branch']->id,
        'territory_id' => $team['territory']->id,
        'effective_from' => now()->toDateString(),
    ]));

    withTenantContext($tenant, function () use ($viewer, $outsider, $assignment): void {
        $this->actingAs($viewer)
            ->get(route('salesman-assignments.index'))
            ->assertOk();

        $this->actingAs($viewer)
            ->get(route('salesman-assignments.create'))
            ->assertForbidden();

        $this->actingAs($viewer)
            ->put(route('salesman-assignments.update', $assignment), [])
            ->assertForbidden();

        $this->actingAs($outsider)
            ->get(route('salesman-assignments.index'))
            ->assertForbidden();

        $this->actingAs($outsider)
            ->get(route('supervisor-assignments.index'))
            ->assertForbidden();
    });
});
