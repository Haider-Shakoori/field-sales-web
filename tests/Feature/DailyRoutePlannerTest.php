<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CompanySetting;
use App\Models\CustomerFollowUp;
use App\Models\CustomerVisit;
use App\Models\Device;
use App\Models\Order;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RouteCustomer;
use App\Models\Salesman;
use App\Models\SalesmanAssignment;
use App\Models\SalesRoute;
use App\Models\Tenant;
use App\Models\Territory;
use App\Models\User;
use App\Services\DailyRoutePlannerService;
use App\Services\RouteExecutionAnalyticsService;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class DailyRoutePlannerTest extends TestCase
{
    use RefreshDatabase;

    public function test_planner_prioritizes_overdue_and_due_follow_up_customers(): void
    {
        [$tenant, $admin, $salesman, $route, $regular, $urgent] = $this->fixture();

        $plan = app(TenantContext::class)->withTenant(
            $tenant,
            fn () => app(DailyRoutePlannerService::class)->planFor(
                $salesman,
                CarbonImmutable::parse('2026-09-23', 'Asia/Kabul'),
            )
        );

        $this->assertSame($route->uuid, $plan['route']['id']);
        $this->assertSame($urgent->uuid, $plan['stops'][0]['customer_id']);
        $this->assertSame('urgent', $plan['stops'][0]['priority']);
        $this->assertSame($regular->uuid, $plan['stops'][1]['customer_id']);
        $this->assertTrue($plan['stops'][0]['recommended_order'] < $plan['stops'][0]['route_sequence']);
        $this->assertSame(1, $plan['summary']['customers_with_overdue_balance']);
        $this->assertSame(1, $plan['summary']['customers_with_due_follow_ups']);

        $this->actingAs($admin)
            ->get(route('admin.daily-planner.index', [
                'salesman' => $salesman->uuid,
                'date' => '2026-09-23',
            ]))
            ->assertOk()
            ->assertSee('Daily planner')
            ->assertSee($urgent->name)
            ->assertSee('AFN 80.00 overdue');
    }

    public function test_planner_falls_back_to_territory_when_no_route_is_assigned(): void
    {
        [$tenant, $admin, $salesman, $branch, $territory] = $this->plannerFoundation(
            'territory-fallback',
        );

        $customer = app(TenantContext::class)->withTenant(
            $tenant,
            function () use ($admin, $branch, $territory): Customer {
                return Customer::create([
                    'branch_id' => $branch->id,
                    'territory_id' => $territory->id,
                    'code' => 'TERR-001',
                    'name' => 'Territory Customer',
                    'latitude' => 34.5200,
                    'longitude' => 69.1800,
                    'credit_currency' => 'AFN',
                    'credit_terms_days' => 30,
                    'created_by' => $admin->id,
                    'is_active' => true,
                ]);
            }
        );

        app(TenantContext::class)->withTenant(
            $tenant,
            fn () => SalesmanAssignment::create([
                'salesman_id' => $salesman->id,
                'branch_id' => $branch->id,
                'territory_id' => $territory->id,
                'effective_from' => '2026-09-01',
                'created_by' => $admin->id,
            ])
        );

        $plan = app(TenantContext::class)->withTenant(
            $tenant,
            fn () => app(DailyRoutePlannerService::class)->planFor(
                $salesman,
                CarbonImmutable::parse('2026-09-23', 'Asia/Kabul'),
            )
        );

        $this->assertNull($plan['route']);
        $this->assertSame('territory', $plan['source']['type']);
        $this->assertSame($territory->uuid, $plan['source']['id']);
        $this->assertSame($customer->uuid, $plan['stops'][0]['customer_id']);
        $this->assertSame(1, $plan['summary']['total_stops']);
        $this->assertContains(
            'distance_estimate_is_straight_line',
            $plan['warnings'],
        );
    }

    public function test_planner_uses_distance_to_break_ties_within_same_priority_tier(): void
    {
        [$tenant, $admin, $salesman, $branch, $territory] = $this->plannerFoundation(
            'distance-order',
        );

        [$first, $far, $near, $route] = app(TenantContext::class)->withTenant(
            $tenant,
            function () use ($admin, $salesman, $branch, $territory): array {
                $route = SalesRoute::create([
                    'branch_id' => $branch->id,
                    'territory_id' => $territory->id,
                    'code' => 'DIST-ROUTE',
                    'name' => 'Distance Route',
                    'weekdays' => ['wed'],
                    'is_active' => true,
                ]);

                $first = Customer::create([
                    'branch_id' => $branch->id,
                    'territory_id' => $territory->id,
                    'code' => 'DIST-001',
                    'name' => 'First Shop',
                    'latitude' => 34.5000,
                    'longitude' => 69.2000,
                    'credit_currency' => 'AFN',
                    'credit_terms_days' => 30,
                    'created_by' => $admin->id,
                    'is_active' => true,
                ]);

                $far = Customer::create([
                    'branch_id' => $branch->id,
                    'territory_id' => $territory->id,
                    'code' => 'DIST-002',
                    'name' => 'Far Shop',
                    'latitude' => 35.0000,
                    'longitude' => 70.0000,
                    'credit_currency' => 'AFN',
                    'credit_terms_days' => 30,
                    'created_by' => $admin->id,
                    'is_active' => true,
                ]);

                $near = Customer::create([
                    'branch_id' => $branch->id,
                    'territory_id' => $territory->id,
                    'code' => 'DIST-003',
                    'name' => 'Near Shop',
                    'latitude' => 34.5005,
                    'longitude' => 69.2005,
                    'credit_currency' => 'AFN',
                    'credit_terms_days' => 30,
                    'created_by' => $admin->id,
                    'is_active' => true,
                ]);

                foreach ([
                    [$first, 1],
                    [$far, 2],
                    [$near, 3],
                ] as [$customer, $sequence]) {
                    RouteCustomer::create([
                        'route_id' => $route->id,
                        'customer_id' => $customer->id,
                        'sequence_number' => $sequence,
                        'planned_visit_minutes' => 10,
                    ]);
                }

                SalesmanAssignment::create([
                    'salesman_id' => $salesman->id,
                    'branch_id' => $branch->id,
                    'territory_id' => $territory->id,
                    'route_id' => $route->id,
                    'effective_from' => '2026-09-01',
                    'created_by' => $admin->id,
                ]);

                return [$first, $far, $near, $route];
            }
        );

        $plan = app(TenantContext::class)->withTenant(
            $tenant,
            fn () => app(DailyRoutePlannerService::class)->planFor(
                $salesman,
                CarbonImmutable::parse('2026-09-23', 'Asia/Kabul'),
            )
        );

        $this->assertSame($route->uuid, $plan['route']['id']);
        $this->assertSame($first->uuid, $plan['stops'][0]['customer_id']);
        $this->assertSame($near->uuid, $plan['stops'][1]['customer_id']);
        $this->assertSame($far->uuid, $plan['stops'][2]['customer_id']);
        $this->assertSame(3, $plan['stops'][1]['route_sequence']);
        $this->assertGreaterThan(0, $plan['approximate_air_distance_km']);
    }

    public function test_planner_uses_salesman_start_position_for_first_stop(): void
    {
        [$tenant, $admin, $salesman, $branch, $territory] = $this->plannerFoundation(
            'current-position',
        );

        [$route, $routeFirst, $nearest] = app(TenantContext::class)->withTenant(
            $tenant,
            function () use ($admin, $salesman, $branch, $territory): array {
                $route = SalesRoute::create([
                    'branch_id' => $branch->id,
                    'territory_id' => $territory->id,
                    'code' => 'POSITION-ROUTE',
                    'name' => 'Position Route',
                    'weekdays' => ['wed'],
                    'is_active' => true,
                ]);

                $routeFirst = Customer::create([
                    'branch_id' => $branch->id,
                    'territory_id' => $territory->id,
                    'code' => 'POSITION-001',
                    'name' => 'Route First',
                    'latitude' => 34.5000,
                    'longitude' => 69.2000,
                    'credit_currency' => 'AFN',
                    'credit_terms_days' => 30,
                    'created_by' => $admin->id,
                    'is_active' => true,
                ]);

                $nearest = Customer::create([
                    'branch_id' => $branch->id,
                    'territory_id' => $territory->id,
                    'code' => 'POSITION-002',
                    'name' => 'Nearest To Salesman',
                    'latitude' => 34.7000,
                    'longitude' => 69.4000,
                    'credit_currency' => 'AFN',
                    'credit_terms_days' => 30,
                    'created_by' => $admin->id,
                    'is_active' => true,
                ]);

                RouteCustomer::create([
                    'route_id' => $route->id,
                    'customer_id' => $routeFirst->id,
                    'sequence_number' => 1,
                    'planned_visit_minutes' => 10,
                ]);

                RouteCustomer::create([
                    'route_id' => $route->id,
                    'customer_id' => $nearest->id,
                    'sequence_number' => 2,
                    'planned_visit_minutes' => 10,
                ]);

                SalesmanAssignment::create([
                    'salesman_id' => $salesman->id,
                    'branch_id' => $branch->id,
                    'territory_id' => $territory->id,
                    'route_id' => $route->id,
                    'effective_from' => '2026-09-01',
                    'created_by' => $admin->id,
                ]);

                return [$route, $routeFirst, $nearest];
            }
        );

        $plan = app(TenantContext::class)->withTenant(
            $tenant,
            fn () => app(DailyRoutePlannerService::class)->planFor(
                $salesman,
                CarbonImmutable::parse('2026-09-23', 'Asia/Kabul'),
                [
                    'latitude' => 34.7001,
                    'longitude' => 69.4001,
                    'accuracy' => 8,
                    'source' => 'device_current',
                ],
            )
        );

        $this->assertSame($route->uuid, $plan['route']['id']);
        $this->assertSame($nearest->uuid, $plan['stops'][0]['customer_id']);
        $this->assertSame(2, $plan['stops'][0]['route_sequence']);
        $this->assertSame('device_current', $plan['start_location']['source']);
        $this->assertLessThan(0.1, $plan['stops'][0]['distance_from_previous_km']);
        $this->assertSame($routeFirst->uuid, $plan['stops'][1]['customer_id']);
    }

    public function test_planner_surfaces_nearby_unplanned_opportunity_and_can_include_it(): void
    {
        [$tenant, $admin, $salesman, $route] = $this->fixture();

        $opportunity = app(TenantContext::class)->withTenant(
            $tenant,
            function () use ($admin, $route): Customer {
                $customer = Customer::create([
                    'branch_id' => $route->branch_id,
                    'territory_id' => $route->territory_id,
                    'code' => 'OPP-001',
                    'name' => 'Nearby Opportunity',
                    'latitude' => 34.5351,
                    'longitude' => 69.1751,
                    'credit_currency' => 'AFN',
                    'credit_terms_days' => 30,
                    'created_by' => $admin->id,
                    'is_active' => true,
                ]);

                CustomerFollowUp::create([
                    'customer_id' => $customer->id,
                    'assigned_salesman_id' => null,
                    'type' => 'payment',
                    'priority' => 'high',
                    'status' => 'pending',
                    'due_at' => '2026-09-23 04:00:00',
                    'created_by' => $admin->id,
                ]);

                return $customer;
            },
        );

        $planner = app(DailyRoutePlannerService::class);
        $date = CarbonImmutable::parse('2026-09-24', 'Asia/Kabul');
        $position = [
            'latitude' => 34.5350,
            'longitude' => 69.1750,
            'accuracy' => 8,
            'source' => 'device_current',
        ];

        $plan = app(TenantContext::class)->withTenant(
            $tenant,
            fn () => $planner->planFor(
                $salesman,
                $date,
                $position,
                [],
                5.0,
            ),
        );

        $this->assertNotContains(
            $opportunity->uuid,
            collect($plan['stops'])->pluck('customer_id')->all(),
        );
        $this->assertContains(
            $opportunity->uuid,
            collect($plan['nearby_opportunities'])->pluck('customer_id')->all(),
        );
        $nearby = collect($plan['nearby_opportunities'])
            ->firstWhere('customer_id', $opportunity->uuid);
        $this->assertTrue($nearby['can_add_to_route']);
        $this->assertLessThan(0.1, $nearby['distance_km']);

        $included = app(TenantContext::class)->withTenant(
            $tenant,
            fn () => $planner->planFor(
                $salesman,
                $date,
                $position,
                [$opportunity->uuid],
                5.0,
            ),
        );

        $includedStop = collect($included['stops'])
            ->firstWhere('customer_id', $opportunity->uuid);
        $this->assertNotNull($includedStop);
        $this->assertTrue($includedStop['is_opportunity']);
        $this->assertContains(
            $opportunity->uuid,
            $included['dynamic_route']['included_opportunity_ids'],
        );
        $this->assertNotContains(
            $opportunity->uuid,
            collect($included['nearby_opportunities'])->pluck('customer_id')->all(),
        );
    }

    public function test_nearby_opportunities_never_escape_salesman_assignment_scope(): void
    {
        [$tenant, $admin, $salesman, $route] = $this->fixture();

        $outside = app(TenantContext::class)->withTenant(
            $tenant,
            function () use ($admin, $route): Customer {
                $otherTerritory = Territory::create([
                    'branch_id' => $route->branch_id,
                    'code' => 'OTHER-TERR',
                    'name' => 'Other Territory',
                    'is_active' => true,
                ]);

                return Customer::create([
                    'branch_id' => $route->branch_id,
                    'territory_id' => $otherTerritory->id,
                    'code' => 'OUT-001',
                    'name' => 'Outside Territory Shop',
                    'latitude' => 34.5352,
                    'longitude' => 69.1752,
                    'credit_currency' => 'AFN',
                    'credit_terms_days' => 30,
                    'created_by' => $admin->id,
                    'is_active' => true,
                ]);
            },
        );

        $plan = app(TenantContext::class)->withTenant(
            $tenant,
            fn () => app(DailyRoutePlannerService::class)->planFor(
                $salesman,
                CarbonImmutable::parse('2026-09-24', 'Asia/Kabul'),
                [
                    'latitude' => 34.5350,
                    'longitude' => 69.1750,
                    'accuracy' => 8,
                    'source' => 'device_current',
                ],
                [],
                5.0,
            ),
        );

        $this->assertNotContains(
            $outside->uuid,
            collect($plan['nearby_opportunities'])->pluck('customer_id')->all(),
        );
    }

    public function test_planner_adds_etas_and_flags_workday_capacity_overflow(): void
    {
        [$tenant, , $salesman] = $this->fixture();

        $tenant->update([
            'settings' => [
                'intelligence' => [
                    'smart_routes' => [
                        'enabled' => true,
                        'average_speed_kph' => 10,
                        'time_buffer_minutes' => 0,
                        'enforce_workday_capacity' => true,
                    ],
                ],
            ],
        ]);

        app(TenantContext::class)->withTenant(
            $tenant,
            function () use ($tenant): void {
                foreach ([
                    'workday_start_time' => '08:00',
                    'workday_end_time' => '08:20',
                ] as $key => $value) {
                    CompanySetting::updateOrCreate(
                        ['tenant_id' => $tenant->id, 'key' => 'tracking.'.$key],
                        ['value' => $value],
                    );
                }
            },
        );

        $plan = app(TenantContext::class)->withTenant(
            $tenant->fresh(),
            fn () => app(DailyRoutePlannerService::class)->planFor(
                $salesman->fresh(),
                CarbonImmutable::parse('2026-09-23', 'Asia/Kabul'),
            ),
        );

        $this->assertSame(10.0, $plan['schedule']['average_speed_kph']);
        $this->assertSame(20, $plan['summary']['available_work_minutes']);
        $this->assertGreaterThan(0, $plan['summary']['estimated_travel_minutes']);
        $this->assertGreaterThan(0, $plan['summary']['overflow_stops']);
        $this->assertFalse($plan['summary']['route_fits_workday']);
        $this->assertNotNull($plan['stops'][0]['estimated_arrival_at']);
        $this->assertNotNull($plan['stops'][0]['estimated_departure_at']);
        $this->assertContains(
            'overflow',
            collect($plan['stops'])->pluck('capacity_status')->all(),
        );
        $this->assertTrue(
            collect($plan['warnings'])->contains(
                fn (string $warning) => str_starts_with(
                    $warning,
                    'workday_capacity_exceeded:',
                ),
            ),
        );
    }

    public function test_route_execution_compares_planned_and_completed_visits(): void
    {
        [$tenant, $admin, $salesman, , $regular] = $this->fixture();

        $device = app(TenantContext::class)->withTenant(
            $tenant,
            fn () => Device::where('salesman_id', $salesman->id)->firstOrFail(),
        );

        app(TenantContext::class)->withTenant(
            $tenant,
            fn () => CustomerVisit::create([
                'user_id' => $salesman->user_id,
                'salesman_id' => $salesman->id,
                'device_id' => $device->id,
                'customer_id' => $regular->id,
                'status' => 'completed',
                'outcome' => 'productive',
                'is_planned' => true,
                'checked_in_at' => CarbonImmutable::parse(
                    '2026-09-23 12:00:00',
                    'Asia/Kabul',
                )->utc(),
                'checked_out_at' => CarbonImmutable::parse(
                    '2026-09-23 12:15:00',
                    'Asia/Kabul',
                )->utc(),
                'checkin_latitude' => 34.53,
                'checkin_longitude' => 69.17,
                'checkin_accuracy' => 8,
                'checkout_latitude' => 34.53,
                'checkout_longitude' => 69.17,
                'checkout_accuracy' => 8,
            ]),
        );

        $payload = app(TenantContext::class)->withTenant(
            $tenant,
            fn () => app(RouteExecutionAnalyticsService::class)->build(
                $admin,
                '2026-09-23',
                $salesman->employee_code,
            ),
        );

        $row = collect($payload['salesmen'])->firstOrFail();

        $this->assertSame(2, $row['assigned_stops']);
        $this->assertSame(1, $row['visited_planned_stops']);
        $this->assertSame(1, $row['remaining_stops']);
        $this->assertSame(1, $row['missed_stops']);
        $this->assertSame(50.0, $row['completion_percent']);
        $this->assertSame('missed_stops', $row['execution_status']);
    }

    private function plannerFoundation(string $slug): array
    {
        $context = app(TenantContext::class);

        $tenant = $context->withPlatformScope(fn () => Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Planner Foundation',
            'slug' => $slug.'-'.Str::lower(Str::random(6)),
            'timezone' => 'Asia/Kabul',
            'subscription_status' => 'active',
        ]));

        return $context->withTenant($tenant, function () use ($tenant, $slug): array {
            $branch = Branch::create([
                'name' => 'Kabul Main',
                'code' => 'KBL',
                'is_active' => true,
            ]);

            $territory = Territory::create([
                'branch_id' => $branch->id,
                'code' => 'KBL-C',
                'name' => 'Kabul Central',
                'is_active' => true,
            ]);

            $admin = User::create([
                'uuid' => (string) Str::uuid(),
                'name' => 'Planner Admin',
                'email' => 'admin-'.$slug.'@example.test',
                'password' => Hash::make('password'),
                'role' => 'company_admin',
                'is_active' => true,
            ]);

            $salesUser = User::create([
                'uuid' => (string) Str::uuid(),
                'name' => 'Planner Salesman',
                'email' => 'sales-'.$slug.'@example.test',
                'password' => Hash::make('password'),
                'role' => 'salesman',
                'is_active' => true,
            ]);

            $salesman = Salesman::create([
                'user_id' => $salesUser->id,
                'employee_code' => 'PLAN-'.strtoupper(substr($slug, 0, 6)),
                'first_name' => 'Planner',
                'last_name' => 'Salesman',
                'is_active' => true,
            ]);

            return [$tenant, $admin, $salesman, $branch, $territory];
        });
    }

    private function fixture(): array
    {
        $context = app(TenantContext::class);

        $tenant = $context->withPlatformScope(fn () => Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Planner Tenant',
            'slug' => 'planner-'.Str::lower(Str::random(6)),
            'timezone' => 'Asia/Kabul',
            'subscription_status' => 'active',
        ]));

        return $context->withTenant($tenant, function () use ($tenant): array {
            $adminRole = Role::create([
                'tenant_id' => $tenant->id,
                'name' => 'Planner Admin',
                'slug' => 'planner-admin',
                'is_system' => false,
            ]);

            $permissions = collect([
                'sales-team:view',
                'customers:view',
            ])->map(fn (string $slug) => Permission::firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => str($slug)->replace(':', ' ')->title(),
                    'group' => str($slug)->before(':'),
                ]
            ));

            $adminRole->permissions()->sync($permissions->pluck('id'));

            $admin = User::create([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'name' => 'Planner Admin',
                'email' => 'planner-admin@example.test',
                'password' => Hash::make('password'),
                'role' => 'company-admin',
                'is_active' => true,
            ]);
            $admin->syncPrimaryRole($adminRole);

            $salesUser = User::create([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'name' => 'Planner Salesman',
                'email' => 'planner-sales@example.test',
                'password' => Hash::make('password'),
                'role' => 'salesman',
                'is_active' => true,
            ]);

            $salesman = Salesman::create([
                'tenant_id' => $tenant->id,
                'user_id' => $salesUser->id,
                'employee_code' => 'PLAN-001',
                'first_name' => 'Planner',
                'last_name' => 'Salesman',
                'is_active' => true,
            ]);

            $device = Device::create([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'user_id' => $salesUser->id,
                'salesman_id' => $salesman->id,
                'device_uuid' => 'planner-device',
                'installation_uuid' => 'planner-installation',
                'is_active' => true,
            ]);

            $branch = Branch::create([
                'tenant_id' => $tenant->id,
                'name' => 'Kabul Main',
                'code' => 'KBL',
                'is_active' => true,
            ]);

            $territory = Territory::create([
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
                'code' => 'KBL-C',
                'name' => 'Kabul Central',
                'is_active' => true,
            ]);

            $route = SalesRoute::create([
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
                'territory_id' => $territory->id,
                'code' => 'KBL-DAY',
                'name' => 'Kabul Daily Route',
                'weekdays' => ['wed'],
                'is_active' => true,
            ]);

            $regular = Customer::create([
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
                'territory_id' => $territory->id,
                'code' => 'REG-1',
                'name' => 'Regular Shop',
                'latitude' => 34.53,
                'longitude' => 69.17,
                'credit_currency' => 'AFN',
                'credit_terms_days' => 30,
                'is_active' => true,
            ]);

            $urgent = Customer::create([
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
                'territory_id' => $territory->id,
                'code' => 'URG-1',
                'name' => 'Urgent Shop',
                'latitude' => 34.54,
                'longitude' => 69.18,
                'credit_currency' => 'AFN',
                'credit_terms_days' => 10,
                'is_active' => true,
            ]);

            RouteCustomer::create([
                'tenant_id' => $tenant->id,
                'route_id' => $route->id,
                'customer_id' => $regular->id,
                'sequence_number' => 1,
                'planned_visit_minutes' => 10,
            ]);

            RouteCustomer::create([
                'tenant_id' => $tenant->id,
                'route_id' => $route->id,
                'customer_id' => $urgent->id,
                'sequence_number' => 2,
                'planned_visit_minutes' => 15,
            ]);

            SalesmanAssignment::create([
                'tenant_id' => $tenant->id,
                'salesman_id' => $salesman->id,
                'branch_id' => $branch->id,
                'territory_id' => $territory->id,
                'route_id' => $route->id,
                'effective_from' => '2026-09-01',
                'created_by' => $admin->id,
            ]);

            Order::create([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'user_id' => $salesUser->id,
                'salesman_id' => $salesman->id,
                'device_id' => $device->id,
                'customer_id' => $urgent->id,
                'order_number' => 'PLAN-ORDER-1',
                'ordered_at' => '2026-08-01 04:00:00',
                'due_date' => '2026-08-11',
                'payment_type' => 'credit',
                'status' => 'approved',
                'currency' => 'AFN',
                'subtotal' => 80,
                'discount_total' => 0,
                'grand_total' => 80,
            ]);

            CustomerFollowUp::create([
                'tenant_id' => $tenant->id,
                'customer_id' => $urgent->id,
                'assigned_salesman_id' => $salesman->id,
                'type' => 'payment',
                'priority' => 'high',
                'status' => 'pending',
                'due_at' => '2026-09-22 04:30:00',
                'created_by' => $admin->id,
            ]);

            return [$tenant, $admin, $salesman, $route, $regular, $urgent];
        });
    }
}
