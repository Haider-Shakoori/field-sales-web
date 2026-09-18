<?php

use App\Models\Branch;
use App\Models\CurrentLocation;
use App\Models\SalesmanAssignment;
use App\Models\Supervisor;
use App\Models\Tenant;
use App\Models\WorkSession;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    seedRbac();
});

function batch7SeedSession(Tenant $tenant, array $mobile, string $date, array $overrides = []): WorkSession
{
    return withTenantContext($tenant, fn () => WorkSession::factory()
        ->forTenant($tenant->id)
        ->forUser($mobile['user']->id)
        ->forSalesman($mobile['salesman']->id)
        ->forDevice($mobile['device']->id)
        ->onDate($date)
        ->create($overrides));
}

it('shows attendance records to authorized admins', function (): void {
    $tenant = makeTenant(['timezone' => 'UTC']);
    $owner = makeUser($tenant, 'owner');
    $mobile = makeMobileSalesman($tenant);

    batch7SeedSession($tenant, $mobile, now('UTC')->toDateString(), [
        'start_time' => now('UTC')->subHours(2),
    ]);

    withTenantContext($tenant, function () use ($owner, $mobile): void {
        $this->actingAs($owner)
            ->get(route('attendance.index'))
            ->assertOk()
            ->assertSee(trim($mobile['salesman']->first_name.' '.$mobile['salesman']->last_name))
            ->assertSee('Active', false);
    });
});

it('shows an empty state when no attendance records exist', function (): void {
    $tenant = makeTenant(['timezone' => 'UTC']);
    $owner = makeUser($tenant, 'owner');

    withTenantContext($tenant, function () use ($owner): void {
        $this->actingAs($owner)
            ->get(route('attendance.index'))
            ->assertOk()
            ->assertSee('No attendance records');
    });
});

it('denies attendance pages to roles without permission', function (): void {
    $tenant = makeTenant(['timezone' => 'UTC']);
    $salesmanRole = makeUser($tenant, 'salesman');
    $accountant = makeUser($tenant, 'accountant');
    $mobile = makeMobileSalesman($tenant);

    $session = batch7SeedSession($tenant, $mobile, now('UTC')->toDateString());

    withTenantContext($tenant, function () use ($salesmanRole, $accountant, $session): void {
        $this->actingAs($salesmanRole)->get(route('attendance.index'))->assertForbidden();
        $this->actingAs($accountant)->get(route('attendance.index'))->assertForbidden();
        $this->actingAs($accountant)->get(route('attendance.show', $session))->assertForbidden();
    });
});

it('restricts supervisors to their assigned salesmen', function (): void {
    $tenant = makeTenant(['timezone' => 'UTC']);

    $supervisorUser = makeUser($tenant, 'supervisor');
    $supervisorProfile = withTenantContext($tenant, fn () => Supervisor::factory()
        ->forTenant($tenant->id)
        ->create(['user_id' => $supervisorUser->id]));

    $teamMember = makeMobileSalesman($tenant);
    $outsider = makeMobileSalesman($tenant);

    withTenantContext($tenant, fn () => SalesmanAssignment::factory()
        ->forTenant($tenant->id)
        ->forSalesman($teamMember['salesman']->id)
        ->withSupervisor($supervisorProfile->id)
        ->create(['effective_from' => now('UTC')->subMonth()->toDateString()]));

    $teamSession = batch7SeedSession($tenant, $teamMember, now('UTC')->toDateString());
    $outsiderSession = batch7SeedSession($tenant, $outsider, now('UTC')->toDateString());

    withTenantContext($tenant, function () use ($supervisorUser, $teamMember, $outsider, $teamSession, $outsiderSession): void {
        $this->actingAs($supervisorUser)
            ->get(route('attendance.index'))
            ->assertOk()
            ->assertSee(trim($teamMember['salesman']->first_name.' '.$teamMember['salesman']->last_name))
            ->assertDontSee(trim($outsider['salesman']->first_name.' '.$outsider['salesman']->last_name));

        $this->actingAs($supervisorUser)->get(route('attendance.show', $teamSession))->assertOk();
        $this->actingAs($supervisorUser)->get(route('attendance.show', $outsiderSession))->assertForbidden();
    });
});

it('blocks cross-tenant attendance route access', function (): void {
    $tenantA = makeTenant(['timezone' => 'UTC']);
    $tenantB = makeTenant(['timezone' => 'UTC']);

    $ownerA = makeUser($tenantA, 'owner');
    $mobileB = makeMobileSalesman($tenantB);
    $sessionB = batch7SeedSession($tenantB, $mobileB, now('UTC')->toDateString());

    withTenantContext($tenantA, function () use ($ownerA, $sessionB): void {
        $this->actingAs($ownerA)->get(route('attendance.show', $sessionB))->assertNotFound();
    });
});

it('filters attendance by work date and historical branch', function (): void {
    $tenant = makeTenant(['timezone' => 'UTC']);
    $owner = makeUser($tenant, 'owner');
    $mobile = makeMobileSalesman($tenant);

    [$branchOne, $branchTwo] = withTenantContext($tenant, fn () => [
        Branch::factory()->forTenant($tenant->id)->create(['name' => 'Branch One']),
        Branch::factory()->forTenant($tenant->id)->create(['name' => 'Branch Two']),
    ]);

    withTenantContext($tenant, fn () => SalesmanAssignment::factory()
        ->forTenant($tenant->id)
        ->forSalesman($mobile['salesman']->id)
        ->create([
            'branch_id' => $branchOne->id,
            'effective_from' => '2026-01-01',
            'effective_to' => null,
        ]));

    batch7SeedSession($tenant, $mobile, '2026-01-10', [
        'start_time' => CarbonImmutable::parse('2026-01-10 08:00:00', 'UTC'),
    ]);

    withTenantContext($tenant, function () use ($owner, $branchOne, $branchTwo): void {
        $this->actingAs($owner)
            ->get(route('attendance.index', ['date' => '2026-01-10']))
            ->assertOk()
            ->assertSee('Jan 10, 2026');

        $this->actingAs($owner)
            ->get(route('attendance.index', ['date' => '2026-01-11']))
            ->assertOk()
            ->assertSee('No attendance records');

        $this->actingAs($owner)
            ->get(route('attendance.index', ['branch_id' => $branchOne->id]))
            ->assertOk()
            ->assertSee('Jan 10, 2026');

        $this->actingAs($owner)
            ->get(route('attendance.index', ['branch_id' => $branchTwo->id]))
            ->assertOk()
            ->assertSee('No attendance records');
    });
});

it('renders the attendance detail with work, duration, and GPS data', function (): void {
    $tenant = makeTenant(['timezone' => 'UTC']);
    $owner = makeUser($tenant, 'owner');
    $mobile = makeMobileSalesman($tenant);

    $session = batch7SeedSession($tenant, $mobile, '2026-01-10', [
        'start_time' => CarbonImmutable::parse('2026-01-10 08:00:00', 'UTC'),
        'end_time' => CarbonImmutable::parse('2026-01-10 10:05:00', 'UTC'),
        'status' => WorkSession::STATUS_COMPLETED,
        'duration_minutes' => 125,
        'end_latitude' => 34.56,
        'end_longitude' => 69.21,
    ]);

    withTenantContext($tenant, function () use ($owner, $session): void {
        $this->actingAs($owner)
            ->get(route('attendance.show', $session))
            ->assertOk()
            ->assertSee('2h 5m')
            ->assertSee('34.5600000')
            ->assertSee('GPS check-in');
    });
});

it('shows current locations to tracking viewers only and flags mock points', function (): void {
    $tenant = makeTenant(['timezone' => 'UTC']);
    $owner = makeUser($tenant, 'owner');
    $accountant = makeUser($tenant, 'accountant');
    $salesmanRole = makeUser($tenant, 'salesman');
    $mobile = makeMobileSalesman($tenant);

    withTenantContext($tenant, fn () => CurrentLocation::factory()
        ->forTenant($tenant->id)
        ->forUser($mobile['user']->id)
        ->forSalesman($mobile['salesman']->id)
        ->create([
            'device_id' => $mobile['device']->id,
            'is_mock_location' => true,
            'recorded_at' => now('UTC'),
        ]));

    withTenantContext($tenant, function () use ($owner, $accountant, $salesmanRole): void {
        $this->actingAs($owner)
            ->get(route('current-locations.index'))
            ->assertOk()
            ->assertSee('Mock', false);

        $this->actingAs($accountant)->get(route('current-locations.index'))->assertForbidden();
        $this->actingAs($salesmanRole)->get(route('current-locations.index'))->assertForbidden();
    });
});

it('exposes tracking navigation only to permitted roles', function (): void {
    $tenant = makeTenant(['timezone' => 'UTC']);
    $owner = makeUser($tenant, 'owner');
    $accountant = makeUser($tenant, 'accountant');

    withTenantContext($tenant, function () use ($owner, $accountant): void {
        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('attendance.index'), false)
            ->assertSee(route('current-locations.index'), false);

        $this->actingAs($accountant)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(route('attendance.index'), false)
            ->assertDontSee(route('current-locations.index'), false);
    });
});
