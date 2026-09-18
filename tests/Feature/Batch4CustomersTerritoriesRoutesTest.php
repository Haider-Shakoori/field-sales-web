<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RouteCustomer;
use App\Models\Salesman;
use App\Models\SalesmanAssignment;
use App\Models\SalesRoute;
use App\Models\Supervisor;
use App\Models\Tenant;
use App\Models\Territory;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class Batch4CustomersTerritoriesRoutesTest extends TestCase
{
    use RefreshDatabase;

    private TenantContext $context;

    protected function setUp(): void
    {
        parent::setUp();

        $this->context = app(TenantContext::class);
        $this->context->clear();
    }

    protected function tearDown(): void
    {
        $this->context->clear();

        parent::tearDown();
    }

    public function test_customer_territory_and_route_crud_are_tenant_scoped_and_audited(): void
    {
        $tenant = $this->tenant('batch4-crud');

        [$admin, $branch] = $this->platform(function () use ($tenant): array {
            return [
                $this->userWithRole(
                    $tenant,
                    'admin@batch4.local',
                    ['customers:view', 'customers:manage'],
                    'company_admin',
                ),
                Branch::create([
                    'tenant_id' => $tenant->id,
                    'name' => 'Kabul',
                    'code' => 'KBL',
                    'is_active' => true,
                ]),
            ];
        });

        $this->actingAs($admin)
            ->post(route('admin.territories.store'), [
                'branch_id' => $branch->id,
                'code' => 'kbl-c',
                'name' => 'Kabul Central',
                'description' => 'Central area',
                'polygon' => json_encode(['type' => 'Polygon', 'coordinates' => []]),
                'is_active' => '1',
            ])
            ->assertRedirect();

        $territory = $this->tenantScope(
            $tenant,
            fn () => Territory::where('code', 'KBL-C')->firstOrFail()
        );

        $this->actingAs($admin)
            ->post(route('admin.customers.store'), [
                'branch_id' => $branch->id,
                'territory_id' => $territory->id,
                'code' => 'cus-001',
                'name' => 'Demo Shop',
                'contact_person' => 'Ahmad',
                'phone' => '0700000000',
                'address' => 'Kabul',
                'latitude' => '34.5553',
                'longitude' => '69.2075',
                'geofence_radius_meters' => '125',
                'is_active' => '1',
            ])
            ->assertRedirect();

        $customer = $this->tenantScope(
            $tenant,
            fn () => Customer::where('code', 'CUS-001')->firstOrFail()
        );

        $this->actingAs($admin)
            ->post(route('admin.routes.store'), [
                'branch_id' => $branch->id,
                'territory_id' => $territory->id,
                'code' => 'kbl-r1',
                'name' => 'Central Route',
                'weekdays' => ['sat', 'sun', 'mon'],
                'description' => 'Recurring route',
                'is_active' => '1',
            ])
            ->assertRedirect();

        $route = $this->tenantScope(
            $tenant,
            fn () => SalesRoute::where('code', 'KBL-R1')->firstOrFail()
        );

        $this->actingAs($admin)->get(route('admin.territories.show', $territory))
            ->assertOk()
            ->assertSee('Kabul Central');
        $this->actingAs($admin)->get(route('admin.customers.show', $customer))
            ->assertOk()
            ->assertSee('Demo Shop');
        $this->actingAs($admin)->get(route('admin.routes.show', $route))
            ->assertOk()
            ->assertSee('Central Route');

        $this->assertSame(['sat', 'sun', 'mon'], $route->weekdays);
        $this->assertSame('125', (string) $customer->geofence_radius_meters);

        foreach (['territory.created', 'customer.created', 'route.created'] as $event) {
            $this->assertDatabaseHas('audit_logs', [
                'tenant_id' => $tenant->id,
                'event' => $event,
            ]);
        }
    }

    public function test_cross_tenant_customer_territory_and_route_bindings_are_hidden(): void
    {
        $tenantA = $this->tenant('batch4-a');
        $tenantB = $this->tenant('batch4-b');

        $admin = $this->platform(fn () => $this->userWithRole(
            $tenantA,
            'admin@batch4-a.local',
            ['customers:view', 'customers:manage'],
            'company_admin',
        ));

        [$territory, $customer, $route] = $this->platform(function () use ($tenantB): array {
            $territory = Territory::create([
                'tenant_id' => $tenantB->id,
                'code' => 'B-T',
                'name' => 'Foreign Territory',
                'is_active' => true,
            ]);
            $customer = Customer::create([
                'tenant_id' => $tenantB->id,
                'territory_id' => $territory->id,
                'code' => 'B-C',
                'name' => 'Foreign Customer',
                'geofence_radius_meters' => 100,
                'is_active' => true,
            ]);
            $route = SalesRoute::create([
                'tenant_id' => $tenantB->id,
                'territory_id' => $territory->id,
                'code' => 'B-R',
                'name' => 'Foreign Route',
                'weekdays' => ['mon'],
                'is_active' => true,
            ]);

            return [$territory, $customer, $route];
        });

        $this->actingAs($admin)->get(route('admin.territories.show', $territory))->assertNotFound();
        $this->actingAs($admin)->get(route('admin.customers.show', $customer))->assertNotFound();
        $this->actingAs($admin)->get(route('admin.routes.show', $route))->assertNotFound();
    }

    public function test_route_customer_sequence_can_be_added_reordered_and_removed(): void
    {
        $tenant = $this->tenant('batch4-sequence');
        [$admin, $branch, $territory, $route] = $this->catalogFixture($tenant);

        [$first, $second] = $this->tenantScope($tenant, function () use ($branch, $territory): array {
            return [
                Customer::create([
                    'branch_id' => $branch->id,
                    'territory_id' => $territory->id,
                    'code' => 'C-1',
                    'name' => 'First Customer',
                    'geofence_radius_meters' => 100,
                    'is_active' => true,
                ]),
                Customer::create([
                    'branch_id' => $branch->id,
                    'territory_id' => $territory->id,
                    'code' => 'C-2',
                    'name' => 'Second Customer',
                    'geofence_radius_meters' => 100,
                    'is_active' => true,
                ]),
            ];
        });

        foreach ([$first, $second] as $customer) {
            $this->actingAs($admin)
                ->post(route('admin.routes.customers.store', $route), [
                    'customer_id' => $customer->id,
                    'planned_visit_minutes' => 15,
                ])
                ->assertRedirect();
        }

        $memberships = $this->tenantScope(
            $tenant,
            fn () => RouteCustomer::where('route_id', $route->id)->orderBy('sequence_number')->get()
        );

        $this->assertSame([1, 2], $memberships->pluck('sequence_number')->all());

        $this->actingAs($admin)
            ->patch(route('admin.routes.customers.reorder', $route), [
                'positions' => [
                    $memberships[0]->id => 2,
                    $memberships[1]->id => 1,
                ],
            ])
            ->assertRedirect();

        $this->assertSame(
            [$second->id, $first->id],
            $this->tenantScope(
                $tenant,
                fn () => RouteCustomer::where('route_id', $route->id)
                    ->orderBy('sequence_number')
                    ->pluck('customer_id')
                    ->all()
            )
        );

        $this->actingAs($admin)
            ->delete(route('admin.routes.customers.destroy', [$route, $memberships[0]]))
            ->assertRedirect();

        $remaining = $this->tenantScope(
            $tenant,
            fn () => RouteCustomer::where('route_id', $route->id)->firstOrFail()
        );

        $this->assertSame(1, $remaining->sequence_number);
        $this->assertDatabaseHas('audit_logs', ['event' => 'route.customers_reordered']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'route_customer.deleted']);
    }

    public function test_route_rejects_customer_from_another_branch_or_territory(): void
    {
        $tenant = $this->tenant('batch4-route-scope');
        [$admin, $branch, $territory, $route] = $this->catalogFixture($tenant);

        $otherBranch = $this->tenantScope($tenant, fn () => Branch::create([
            'name' => 'Herat',
            'code' => 'HRT',
            'is_active' => true,
        ]));
        $otherTerritory = $this->tenantScope($tenant, fn () => Territory::create([
            'branch_id' => $otherBranch->id,
            'code' => 'HRT-T',
            'name' => 'Herat',
            'is_active' => true,
        ]));
        $customer = $this->tenantScope($tenant, fn () => Customer::create([
            'branch_id' => $otherBranch->id,
            'territory_id' => $otherTerritory->id,
            'code' => 'HRT-C',
            'name' => 'Herat Customer',
            'geofence_radius_meters' => 100,
            'is_active' => true,
        ]));

        $this->actingAs($admin)
            ->post(route('admin.routes.customers.store', $route), [
                'customer_id' => $customer->id,
                'planned_visit_minutes' => 10,
            ])
            ->assertSessionHasErrors('customer_id');

        $this->assertDatabaseMissing('route_customers', [
            'route_id' => $route->id,
            'customer_id' => $customer->id,
        ]);
    }

    public function test_salesman_assignment_accepts_matching_territory_and_route_and_rejects_foreign_route(): void
    {
        $tenantA = $this->tenant('batch4-assignment-a');
        $tenantB = $this->tenant('batch4-assignment-b');

        [$admin, $branch, $territory, $route] = $this->catalogFixture($tenantA);
        [$salesman, $supervisor] = $this->salesTeam($tenantA);

        $foreignRoute = $this->platform(function () use ($tenantB): SalesRoute {
            return SalesRoute::create([
                'tenant_id' => $tenantB->id,
                'code' => 'FOREIGN',
                'name' => 'Foreign Route',
                'weekdays' => ['mon'],
                'is_active' => true,
            ]);
        });

        $this->actingAs($admin)
            ->post(route('admin.salesman-assignments.store'), [
                'salesman_id' => $salesman->id,
                'branch_id' => $branch->id,
                'territory_id' => $territory->id,
                'route_id' => $route->id,
                'supervisor_id' => $supervisor->id,
                'effective_from' => '2026-09-01',
                'effective_to' => '',
            ])
            ->assertRedirect();

        $assignment = $this->tenantScope(
            $tenantA,
            fn () => SalesmanAssignment::where('salesman_id', $salesman->id)->firstOrFail()
        );

        $this->assertSame($territory->id, $assignment->territory_id);
        $this->assertSame($route->id, $assignment->route_id);

        $this->actingAs($admin)
            ->put(route('admin.salesman-assignments.update', $assignment), [
                'branch_id' => $branch->id,
                'territory_id' => $territory->id,
                'route_id' => $foreignRoute->id,
                'supervisor_id' => $supervisor->id,
                'effective_from' => '2026-09-01',
                'effective_to' => '',
            ])
            ->assertSessionHasErrors('route_id');
    }

    public function test_customer_offline_uuid_is_idempotency_key_within_tenant(): void
    {
        $tenant = $this->tenant('batch4-offline-uuid');
        $offlineUuid = (string) Str::uuid();

        $this->platform(function () use ($tenant, $offlineUuid): void {
            Customer::create([
                'tenant_id' => $tenant->id,
                'code' => 'C-A',
                'name' => 'A',
                'offline_uuid' => $offlineUuid,
                'geofence_radius_meters' => 100,
                'is_active' => true,
            ]);
        });

        $this->expectException(\Illuminate\Database\QueryException::class);

        $this->platform(function () use ($tenant, $offlineUuid): void {
            Customer::create([
                'tenant_id' => $tenant->id,
                'code' => 'C-B',
                'name' => 'B',
                'offline_uuid' => $offlineUuid,
                'geofence_radius_meters' => 100,
                'is_active' => true,
            ]);
        });
    }

    public function test_route_weekday_scope_returns_only_scheduled_routes(): void
    {
        $tenant = $this->tenant('batch4-weekdays');

        $this->platform(function () use ($tenant): void {
            SalesRoute::create([
                'tenant_id' => $tenant->id,
                'code' => 'MON',
                'name' => 'Monday',
                'weekdays' => ['mon', 'wed'],
                'is_active' => true,
            ]);
            SalesRoute::create([
                'tenant_id' => $tenant->id,
                'code' => 'TUE',
                'name' => 'Tuesday',
                'weekdays' => ['tue'],
                'is_active' => true,
            ]);
        });

        $codes = $this->tenantScope(
            $tenant,
            fn () => SalesRoute::forWeekday('mon')->pluck('code')->all()
        );

        $this->assertSame(['MON'], $codes);
    }

    private function catalogFixture(Tenant $tenant): array
    {
        return $this->platform(function () use ($tenant): array {
            $admin = $this->userWithRole(
                $tenant,
                'admin@'.$tenant->slug.'.local',
                ['customers:view', 'customers:manage', 'sales-team:view', 'sales-team:manage'],
                'company_admin',
            );

            $branch = Branch::create([
                'tenant_id' => $tenant->id,
                'name' => 'Main',
                'code' => 'MAIN',
                'is_active' => true,
            ]);

            $territory = Territory::create([
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
                'code' => 'MAIN-T',
                'name' => 'Main Territory',
                'is_active' => true,
            ]);

            $route = SalesRoute::create([
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
                'territory_id' => $territory->id,
                'code' => 'MAIN-R',
                'name' => 'Main Route',
                'weekdays' => ['mon', 'wed'],
                'is_active' => true,
            ]);

            return [$admin, $branch, $territory, $route];
        });
    }

    private function salesTeam(Tenant $tenant): array
    {
        return $this->platform(function () use ($tenant): array {
            $salesUser = $this->plainUser($tenant, 'sales@'.$tenant->slug.'.local');
            $supervisorUser = $this->plainUser($tenant, 'supervisor@'.$tenant->slug.'.local');

            $salesman = Salesman::create([
                'tenant_id' => $tenant->id,
                'user_id' => $salesUser->id,
                'employee_code' => 'SAL-001',
                'first_name' => 'Sales',
                'is_active' => true,
            ]);

            $supervisor = Supervisor::create([
                'tenant_id' => $tenant->id,
                'user_id' => $supervisorUser->id,
                'employee_code' => 'SUP-001',
                'first_name' => 'Supervisor',
                'is_active' => true,
            ]);

            return [$salesman, $supervisor];
        });
    }

    private function tenant(string $slug): Tenant
    {
        return Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => str($slug)->replace('-', ' ')->title(),
            'slug' => $slug,
            'timezone' => 'UTC',
            'subscription_status' => 'active',
        ]);
    }

    private function permission(string $slug): Permission
    {
        return Permission::firstOrCreate(
            ['slug' => $slug],
            [
                'name' => str($slug)->replace(':', ' ')->title(),
                'group' => str($slug)->before(':'),
            ]
        );
    }

    private function role(Tenant $tenant, string $slug, array $permissions): Role
    {
        $role = Role::create([
            'tenant_id' => $tenant->id,
            'name' => str($slug)->replace('_', ' ')->title(),
            'slug' => $slug,
            'is_system' => true,
        ]);

        $role->permissions()->sync(
            collect($permissions)
                ->map(fn (string $permission) => $this->permission($permission)->id)
                ->all()
        );

        return $role;
    }

    private function plainUser(Tenant $tenant, string $email): User
    {
        return User::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'name' => (string) str($email)->before('@')->replace('.', ' ')->title(),
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => 'salesman',
            'is_active' => true,
        ]);
    }

    private function userWithRole(
        Tenant $tenant,
        string $email,
        array $permissions,
        string $roleSlug,
    ): User {
        $role = $this->role($tenant, $roleSlug, $permissions);
        $user = $this->plainUser($tenant, $email);
        $user->syncPrimaryRole($role);

        return $user;
    }

    private function platform(callable $callback): mixed
    {
        return $this->context->withPlatformScope(fn () => $callback());
    }

    private function tenantScope(Tenant $tenant, callable $callback): mixed
    {
        return $this->context->withTenant($tenant, fn () => $callback());
    }
}
