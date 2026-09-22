<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerFollowUp;
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
use App\Models\WorkSession;
use App\Services\DailyBeatPlanner;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class SmartDailyBeatPlanningTest extends TestCase
{
    use RefreshDatabase;

    private ?string $salesmanToken = null;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow('2026-09-24T05:30:00Z');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_generator_prioritizes_overdue_payment_follow_up_before_routine_route_order(): void
    {
        $actor = $this->routeActor();

        $plan = app(TenantContext::class)->withTenant(
            $actor['tenant'],
            fn () => app(DailyBeatPlanner::class)->generate(
                $actor['salesman'],
                CarbonImmutable::parse('2026-09-24', 'Asia/Kabul'),
                $actor['admin'],
            )
        );

        $first = $plan->stops->first();

        $this->assertSame($actor['urgent']->id, $first->customer_id);
        $this->assertSame('urgent', $first->priority_tier);
        $this->assertContains('overdue_credit', $first->reason_codes);
        $this->assertContains('payment_follow_up', $first->reason_codes);
        $this->assertSame(3, $plan->total_stops);
        $this->assertSame('route', $plan->source_type);
        $this->assertSame(DailyBeatPlanner::ALGORITHM_VERSION, $plan->algorithm_version);
        $this->assertGreaterThan(0, $plan->estimated_distance_m);
    }

    public function test_salesman_mobile_api_returns_only_his_generated_plan(): void
    {
        $actor = $this->routeActor();

        $plan = app(TenantContext::class)->withTenant(
            $actor['tenant'],
            fn () => app(DailyBeatPlanner::class)->generate(
                $actor['salesman'],
                CarbonImmutable::parse('2026-09-24', 'Asia/Kabul'),
                $actor['admin'],
            )
        );

        $this->getJson('/api/v1/beat-plans/today', $this->mobileHeaders($actor))
            ->assertOk()
            ->assertJsonPath('data.plan.id', $plan->uuid)
            ->assertJsonPath('data.plan.date', '2026-09-24')
            ->assertJsonPath('data.plan.summary.total_stops', 3)
            ->assertJsonPath('data.plan.stops.0.customer.id', $actor['urgent']->uuid)
            ->assertJsonPath('data.plan.stops.0.priority_tier', 'urgent');
    }

    public function test_territory_daily_plan_marks_visit_planned_and_completes_stop_on_checkout(): void
    {
        $actor = $this->territoryActor();

        $plan = app(TenantContext::class)->withTenant(
            $actor['tenant'],
            fn () => app(DailyBeatPlanner::class)->generate(
                $actor['salesman'],
                CarbonImmutable::parse('2026-09-24', 'Asia/Kabul'),
                $actor['admin'],
            )
        );

        app(TenantContext::class)->withTenant(
            $actor['tenant'],
            fn () => WorkSession::create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $actor['salesman_user']->id,
                'salesman_id' => $actor['salesman']->id,
                'device_id' => $actor['device']->id,
                'date' => '2026-09-24',
                'start_time' => '2026-09-24 04:00:00',
                'end_time' => '2026-09-24 10:00:00',
                'start_latitude' => 34.5,
                'start_longitude' => 69.2,
                'start_accuracy' => 5,
                'status' => 'completed',
            ])
        );

        $visitUuid = (string) Str::uuid();

        $this->postJson('/api/v1/visits/check-in', [
            'offline_uuid' => $visitUuid,
            'customer_id' => $actor['customer']->uuid,
            'latitude' => 34.50001,
            'longitude' => 69.20001,
            'accuracy' => 8,
            'checked_in_at' => '2026-09-24T05:00:00Z',
        ], $this->mobileHeaders($actor))
            ->assertCreated()
            ->assertJsonPath('data.is_planned', true);

        $this->postJson('/api/v1/visits/'.$visitUuid.'/check-out', [
            'latitude' => 34.50002,
            'longitude' => 69.20002,
            'accuracy' => 7,
            'checked_out_at' => '2026-09-24T05:10:00Z',
            'outcome' => 'order_placed',
        ], $this->mobileHeaders($actor))
            ->assertOk();

        $stop = app(TenantContext::class)->withTenant(
            $actor['tenant'],
            fn () => $plan->stops()->firstOrFail()
        );

        $this->assertNotNull($stop->completed_visit_id);
        $this->assertNotNull($stop->completed_at);
    }

    private function routeActor(): array
    {
        $actor = $this->baseActor('smart-route');

        return app(TenantContext::class)->withTenant(
            $actor['tenant'],
            function () use ($actor): array {
                $territory = Territory::create([
                    'branch_id' => $actor['branch']->id,
                    'code' => 'KBL-C',
                    'name' => 'Kabul Central',
                    'is_active' => true,
                ]);

                $route = SalesRoute::create([
                    'branch_id' => $actor['branch']->id,
                    'territory_id' => $territory->id,
                    'code' => 'R-001',
                    'name' => 'Central Beat',
                    'weekdays' => ['thursday'],
                    'is_active' => true,
                ]);

                $routineOne = $this->customer(
                    $actor,
                    $territory,
                    'CUS-001',
                    'Routine One',
                    34.5000,
                    69.2000,
                );
                $routineTwo = $this->customer(
                    $actor,
                    $territory,
                    'CUS-002',
                    'Routine Two',
                    34.5100,
                    69.2100,
                );
                $urgent = $this->customer(
                    $actor,
                    $territory,
                    'CUS-003',
                    'Urgent Credit Shop',
                    34.5050,
                    69.2050,
                );

                foreach ([
                    [$routineOne, 1],
                    [$routineTwo, 2],
                    [$urgent, 3],
                ] as [$customer, $sequence]) {
                    RouteCustomer::create([
                        'route_id' => $route->id,
                        'customer_id' => $customer->id,
                        'sequence_number' => $sequence,
                        'planned_visit_minutes' => 10,
                    ]);
                }

                SalesmanAssignment::create([
                    'salesman_id' => $actor['salesman']->id,
                    'branch_id' => $actor['branch']->id,
                    'territory_id' => $territory->id,
                    'route_id' => $route->id,
                    'effective_from' => '2026-09-01',
                    'created_by' => $actor['admin']->id,
                ]);

                CustomerFollowUp::create([
                    'customer_id' => $urgent->id,
                    'assigned_salesman_id' => $actor['salesman']->id,
                    'type' => 'payment',
                    'priority' => 'high',
                    'status' => 'pending',
                    'due_at' => '2026-09-23 05:00:00',
                    'created_by' => $actor['admin']->id,
                ]);

                Order::create([
                    'uuid' => (string) Str::uuid(),
                    'user_id' => $actor['salesman_user']->id,
                    'salesman_id' => $actor['salesman']->id,
                    'device_id' => $actor['device']->id,
                    'customer_id' => $urgent->id,
                    'order_number' => 'BEAT-OVERDUE-1',
                    'ordered_at' => '2026-08-01 05:00:00',
                    'due_date' => '2026-08-15',
                    'payment_type' => 'credit',
                    'status' => 'approved',
                    'currency' => 'AFN',
                    'subtotal' => 5000,
                    'discount_total' => 0,
                    'grand_total' => 5000,
                ]);

                return [
                    ...$actor,
                    'territory' => $territory,
                    'route' => $route,
                    'urgent' => $urgent,
                    'routine_one' => $routineOne,
                    'routine_two' => $routineTwo,
                ];
            }
        );
    }

    private function territoryActor(): array
    {
        $actor = $this->baseActor('smart-territory');

        return app(TenantContext::class)->withTenant(
            $actor['tenant'],
            function () use ($actor): array {
                $territory = Territory::create([
                    'branch_id' => $actor['branch']->id,
                    'code' => 'KBL-N',
                    'name' => 'Kabul North',
                    'is_active' => true,
                ]);

                $customer = $this->customer(
                    $actor,
                    $territory,
                    'CUS-T-001',
                    'Territory Shop',
                    34.5,
                    69.2,
                );

                SalesmanAssignment::create([
                    'salesman_id' => $actor['salesman']->id,
                    'branch_id' => $actor['branch']->id,
                    'territory_id' => $territory->id,
                    'effective_from' => '2026-09-01',
                    'created_by' => $actor['admin']->id,
                ]);

                return [
                    ...$actor,
                    'territory' => $territory,
                    'customer' => $customer,
                ];
            }
        );
    }

    private function baseActor(string $slug): array
    {
        $tenant = app(TenantContext::class)->withPlatformScope(fn () => Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Smart Beat Tenant',
            'slug' => $slug,
            'timezone' => 'Asia/Kabul',
            'subscription_status' => 'active',
        ]));

        [$admin, $salesmanUser, $salesman, $branch, $device] = app(TenantContext::class)
            ->withTenant($tenant, function () use ($tenant, $slug): array {
                $branch = Branch::create([
                    'tenant_id' => $tenant->id,
                    'code' => 'KBL',
                    'name' => 'Kabul Main',
                    'is_active' => true,
                ]);

                $admin = $this->userWithPermissions(
                    $tenant,
                    'admin-'.$slug.'@example.test',
                    'company_admin',
                    ['sales-team:view', 'sales-team:manage', 'customers:view'],
                    $branch,
                );

                $salesmanUser = $this->userWithPermissions(
                    $tenant,
                    'salesman-'.$slug.'@example.test',
                    'salesman',
                    ['customers:view', 'visits:view'],
                    $branch,
                );

                $salesman = Salesman::create([
                    'tenant_id' => $tenant->id,
                    'user_id' => $salesmanUser->id,
                    'employee_code' => strtoupper(substr($slug, 0, 8)),
                    'first_name' => 'Field',
                    'last_name' => 'Salesman',
                    'is_active' => true,
                ]);

                $device = Device::create([
                    'tenant_id' => $tenant->id,
                    'user_id' => $salesmanUser->id,
                    'salesman_id' => $salesman->id,
                    'device_uuid' => 'device-'.$slug,
                    'installation_uuid' => 'install-'.$slug,
                    'is_active' => true,
                ]);

                return [$admin, $salesmanUser, $salesman, $branch, $device];
            });

        $this->salesmanToken = app(TenantContext::class)->withTenant(
            $tenant,
            fn () => $salesmanUser->createToken('mobile-'.$device->uuid)->plainTextToken
        );

        return compact(
            'tenant',
            'admin',
            'salesmanUser',
            'salesman',
            'branch',
            'device',
        );
    }

    private function customer(
        array $actor,
        Territory $territory,
        string $code,
        string $name,
        float $latitude,
        float $longitude,
    ): Customer {
        return Customer::create([
            'branch_id' => $actor['branch']->id,
            'territory_id' => $territory->id,
            'code' => $code,
            'name' => $name,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'geofence_radius_meters' => 100,
            'credit_currency' => 'AFN',
            'credit_terms_days' => 30,
            'created_by' => $actor['admin']->id,
            'is_active' => true,
        ]);
    }

    private function userWithPermissions(
        Tenant $tenant,
        string $email,
        string $roleSlug,
        array $permissions,
        Branch $branch,
    ): User {
        $user = User::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'name' => str($roleSlug)->replace('_', ' ')->title(),
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => $roleSlug,
            'is_active' => true,
        ]);

        $role = Role::create([
            'tenant_id' => $tenant->id,
            'name' => str($roleSlug)->replace('_', ' ')->title(),
            'slug' => $roleSlug,
            'is_system' => true,
        ]);

        $permissionIds = collect($permissions)
            ->map(fn (string $slug) => Permission::firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => str($slug)->replace(':', ' ')->title(),
                    'group' => str($slug)->before(':'),
                ],
            )->id)
            ->all();

        $role->permissions()->sync($permissionIds);
        $user->syncPrimaryRole($role);

        return $user;
    }

    private function mobileHeaders(array $actor): array
    {
        return [
            'Authorization' => 'Bearer '.$this->salesmanToken,
            'X-Device-UUID' => $actor['device']->device_uuid,
            'X-Installation-UUID' => $actor['device']->installation_uuid,
            'X-App-Version' => '1.0',
            'X-Platform' => 'android',
            'X-OS-Version' => '16',
        ];
    }
}
