<?php

namespace Tests\Feature;

use App\Models\Collection;
use App\Models\Customer;
use App\Models\CustomerFollowUp;
use App\Models\CustomerVisit;
use App\Models\Device;
use App\Models\Order;
use App\Models\RouteCustomer;
use App\Models\Salesman;
use App\Models\SalesmanAssignment;
use App\Models\SalesRoute;
use App\Models\SalesTarget;
use App\Models\Supervisor;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkSession;
use App\Services\ManagementIntelligenceService;
use App\Services\TenantProvisioningService;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class ManagementIntelligenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow(
            '2026-09-24T10:30:00Z',
        );
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_management_intelligence_combines_live_team_exceptions_and_respects_supervisor_scope(): void
    {
        $fixture = $this->fixture();

        $payload = app(TenantContext::class)->withTenant(
            $fixture['tenant'],
            fn () => app(
                ManagementIntelligenceService::class,
            )->build(
                $fixture['supervisorUser'],
                '2026-09-24',
            ),
        );

        $this->assertSame(1, $payload['summary']['salesmen']);
        $this->assertSame(1, $payload['summary']['started']);
        $this->assertSame(1, $payload['summary']['late_starts']);
        $this->assertSame(2, $payload['summary']['assigned_stops']);
        $this->assertSame(
            1,
            $payload['summary']['visited_planned_stops'],
        );
        $this->assertSame(1, $payload['summary']['remaining_stops']);
        $this->assertSame(1, $payload['summary']['behind_routes']);
        $this->assertSame(1, $payload['summary']['pending_orders']);
        $this->assertSame(
            1,
            $payload['summary']['pending_collections'],
        );
        $this->assertSame(
            1,
            $payload['summary']['overdue_follow_ups'],
        );
        $this->assertSame(1, $payload['summary']['below_target']);
        $this->assertSame(1, $payload['summary']['attention_salesmen']);
        $this->assertGreaterThan(
            70,
            $payload['summary'][
                'expected_route_progress_percent'
            ],
        );

        $row = $payload['rows']->first();

        $this->assertSame(
            $fixture['assigned']->id,
            $row['salesman']->id,
        );
        $this->assertTrue($row['route_behind']);
        $this->assertSame(50.0, $row['route']['completion_percent']);
        $this->assertSame(50.0, $row['target_average_percent']);
        $this->assertSame(1, $row['pending_orders']);
        $this->assertSame(1, $row['pending_collections']);
        $this->assertContains(
            'late_start',
            $row['attention_reasons'],
        );
        $this->assertContains(
            'route_behind_expected_progress',
            $row['attention_reasons'],
        );
        $this->assertContains(
            'pending_collection_verification',
            $row['attention_reasons'],
        );
        $this->assertContains(
            'overdue_follow_ups',
            $row['attention_reasons'],
        );
        $this->assertContains(
            'target_below_attention_threshold',
            $row['attention_reasons'],
        );
        $this->assertSame('high', $row['attention_level']);

        $this->actingAs($fixture['supervisorUser'])
            ->get(
                route('admin.management-intelligence.index', [
                    'date' => '2026-09-24',
                ]),
            )
            ->assertOk()
            ->assertSee('Management intelligence')
            ->assertSee('Assigned Salesman')
            ->assertDontSee('Unassigned Salesman')
            ->assertSee('Route progress is behind the workday pace')
            ->assertSee('Collections waiting for verification');
    }

    private function fixture(): array
    {
        $tenant = app(TenantContext::class)->withPlatformScope(
            fn () => Tenant::create([
                'uuid' => (string) Str::uuid(),
                'name' => 'Management Intelligence Tenant',
                'slug' => 'management-intelligence-'.Str::lower(
                    Str::random(6),
                ),
                'timezone' => 'Asia/Kabul',
                'subscription_status' => 'active',
            ]),
        );

        return app(TenantContext::class)->withTenant(
            $tenant,
            function () use ($tenant): array {
                $roles = app(
                    TenantProvisioningService::class,
                )->provisionRbac($tenant);

                $admin = User::create([
                    'uuid' => (string) Str::uuid(),
                    'name' => 'Management Admin',
                    'email' => 'management-admin@example.test',
                    'password' => Hash::make('password'),
                    'role' => 'company_admin',
                    'is_active' => true,
                ]);
                $admin->syncPrimaryRole(
                    $roles['company_admin'],
                );

                $supervisorUser = User::create([
                    'uuid' => (string) Str::uuid(),
                    'name' => 'Management Supervisor',
                    'email' => 'management-supervisor@example.test',
                    'password' => Hash::make('password'),
                    'role' => 'supervisor',
                    'is_active' => true,
                ]);
                $supervisorUser->syncPrimaryRole(
                    $roles['supervisor'],
                );

                $supervisor = Supervisor::create([
                    'user_id' => $supervisorUser->id,
                    'employee_code' => 'SUP-MGT-1',
                    'first_name' => 'Management',
                    'last_name' => 'Supervisor',
                    'is_active' => true,
                ]);

                [$assignedUser, $assigned] = $this->salesman(
                    $roles['salesman'],
                    'Assigned Salesman',
                    'SAL-MGT-1',
                    'assigned-management@example.test',
                );
                [, $unassigned] = $this->salesman(
                    $roles['salesman'],
                    'Unassigned Salesman',
                    'SAL-MGT-2',
                    'unassigned-management@example.test',
                );

                $route = SalesRoute::create([
                    'code' => 'ROUTE-MGT-1',
                    'name' => 'Management Route',
                    'weekdays' => ['thu'],
                    'is_active' => true,
                ]);

                $firstCustomer = Customer::create([
                    'code' => 'MGT-CUS-1',
                    'name' => 'Management Customer One',
                    'created_by' => $admin->id,
                    'is_active' => true,
                ]);
                $secondCustomer = Customer::create([
                    'code' => 'MGT-CUS-2',
                    'name' => 'Management Customer Two',
                    'created_by' => $admin->id,
                    'is_active' => true,
                ]);

                RouteCustomer::create([
                    'route_id' => $route->id,
                    'customer_id' => $firstCustomer->id,
                    'sequence_number' => 1,
                    'planned_visit_minutes' => 15,
                ]);
                RouteCustomer::create([
                    'route_id' => $route->id,
                    'customer_id' => $secondCustomer->id,
                    'sequence_number' => 2,
                    'planned_visit_minutes' => 15,
                ]);

                SalesmanAssignment::create([
                    'salesman_id' => $assigned->id,
                    'supervisor_id' => $supervisor->id,
                    'route_id' => $route->id,
                    'effective_from' => '2026-09-01',
                    'created_by' => $admin->id,
                ]);

                $device = Device::create([
                    'user_id' => $assignedUser->id,
                    'salesman_id' => $assigned->id,
                    'device_uuid' => 'management-device',
                    'installation_uuid' => 'management-install',
                    'platform' => 'android',
                    'is_active' => true,
                ]);

                WorkSession::create([
                    'uuid' => (string) Str::uuid(),
                    'user_id' => $assignedUser->id,
                    'salesman_id' => $assigned->id,
                    'device_id' => $device->id,
                    'date' => '2026-09-24',
                    'start_time' => '2026-09-24 03:45:00',
                    'start_latitude' => 34.5,
                    'start_longitude' => 69.2,
                    'start_accuracy' => 8,
                    'status' => 'active',
                    'is_late_start' => true,
                ]);

                $visit = CustomerVisit::create([
                    'uuid' => (string) Str::uuid(),
                    'user_id' => $assignedUser->id,
                    'salesman_id' => $assigned->id,
                    'device_id' => $device->id,
                    'customer_id' => $firstCustomer->id,
                    'is_planned' => true,
                    'status' => 'completed',
                    'outcome' => 'order_placed',
                    'checked_in_at' => '2026-09-24 04:30:00',
                    'checked_out_at' => '2026-09-24 04:40:00',
                    'checkin_latitude' => 34.5,
                    'checkin_longitude' => 69.2,
                    'checkin_accuracy' => 8,
                    'checkout_latitude' => 34.5,
                    'checkout_longitude' => 69.2,
                    'checkout_accuracy' => 8,
                    'duration_seconds' => 600,
                ]);

                Order::create([
                    'uuid' => (string) Str::uuid(),
                    'user_id' => $assignedUser->id,
                    'salesman_id' => $assigned->id,
                    'device_id' => $device->id,
                    'customer_id' => $firstCustomer->id,
                    'visit_id' => $visit->id,
                    'order_number' => 'MGT-ORD-APPROVED',
                    'ordered_at' => '2026-09-24 04:35:00',
                    'payment_type' => 'cash',
                    'status' => 'approved',
                    'currency' => 'AFN',
                    'subtotal' => 500,
                    'grand_total' => 500,
                ]);

                Order::create([
                    'uuid' => (string) Str::uuid(),
                    'user_id' => $assignedUser->id,
                    'salesman_id' => $assigned->id,
                    'device_id' => $device->id,
                    'customer_id' => $secondCustomer->id,
                    'order_number' => 'MGT-ORD-PENDING',
                    'ordered_at' => '2026-09-24 05:00:00',
                    'payment_type' => 'cash',
                    'status' => 'pending',
                    'currency' => 'AFN',
                    'subtotal' => 100,
                    'grand_total' => 100,
                ]);

                Collection::create([
                    'uuid' => (string) Str::uuid(),
                    'user_id' => $assignedUser->id,
                    'salesman_id' => $assigned->id,
                    'device_id' => $device->id,
                    'customer_id' => $firstCustomer->id,
                    'receipt_number' => 'MGT-REC-PENDING',
                    'collected_at' => '2026-09-24 05:10:00',
                    'currency' => 'AFN',
                    'amount' => 100,
                    'payment_method' => 'cash',
                    'status' => 'pending',
                    'latitude' => 34.5,
                    'longitude' => 69.2,
                    'accuracy' => 8,
                ]);

                CustomerFollowUp::create([
                    'customer_id' => $secondCustomer->id,
                    'assigned_salesman_id' => $assigned->id,
                    'type' => 'payment',
                    'priority' => 'high',
                    'status' => 'pending',
                    'due_at' => '2026-09-23 06:00:00',
                    'notes' => 'Management test follow-up.',
                    'created_by' => $admin->id,
                ]);

                SalesTarget::create([
                    'salesman_id' => $assigned->id,
                    'target_type' => 'sales_amount',
                    'currency' => 'AFN',
                    'target_value' => 1000,
                    'period_start' => '2026-09-01',
                    'period_end' => '2026-09-30',
                    'created_by' => $admin->id,
                ]);

                return compact(
                    'tenant',
                    'admin',
                    'supervisorUser',
                    'supervisor',
                    'assigned',
                    'unassigned',
                );
            },
        );
    }

    private function salesman(
        $role,
        string $name,
        string $employeeCode,
        string $email,
    ): array {
        $parts = explode(' ', $name, 2);

        $user = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => $name,
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => 'salesman',
            'is_active' => true,
        ]);
        $user->syncPrimaryRole($role);

        $salesman = Salesman::create([
            'user_id' => $user->id,
            'employee_code' => $employeeCode,
            'first_name' => $parts[0],
            'last_name' => $parts[1] ?? null,
            'is_active' => true,
        ]);

        return [$user, $salesman];
    }
}
