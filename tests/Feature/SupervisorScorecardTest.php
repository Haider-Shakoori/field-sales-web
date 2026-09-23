<?php

namespace Tests\Feature;

use App\Models\Collection;
use App\Models\Customer;
use App\Models\CustomerFollowUp;
use App\Models\CustomerVisit;
use App\Models\Device;
use App\Models\Order;
use App\Models\SalesTarget;
use App\Models\Salesman;
use App\Models\SalesmanAssignment;
use App\Models\Supervisor;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VisitSuspiciousFlag;
use App\Models\WorkSession;
use App\Services\SupervisorScorecardService;
use App\Services\TenantProvisioningService;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class SupervisorScorecardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-09-24T06:00:00Z');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_supervisor_scorecard_is_assignment_scoped_and_aggregates_authoritative_kpis(): void
    {
        $actor = $this->actor();

        $scorecard = app(TenantContext::class)->withTenant(
            $actor['tenant'],
            fn () => app(SupervisorScorecardService::class)->build(
                $actor['supervisorUser'],
                '2026-09-01',
                '2026-09-30',
            ),
        );

        $this->assertSame(1, $scorecard['summary']['salesmen']);
        $this->assertSame(1, $scorecard['summary']['attendance_days']);
        $this->assertSame(480, $scorecard['summary']['worked_minutes']);
        $this->assertSame(1, $scorecard['summary']['completed_visits']);
        $this->assertSame(1, $scorecard['summary']['productive_visits']);
        $this->assertSame(1, $scorecard['summary']['approved_orders']);
        $this->assertSame(1, $scorecard['summary']['verified_collections']);
        $this->assertSame(1, $scorecard['summary']['overdue_follow_ups']);
        $this->assertSame(1, $scorecard['summary']['unresolved_flags']);
        $this->assertSame([
            ['currency' => 'AFN', 'total' => 500.0],
        ], $scorecard['summary']['order_totals']);
        $this->assertSame([
            ['currency' => 'AFN', 'total' => 200.0],
        ], $scorecard['summary']['collection_totals']);

        $row = $scorecard['rows']->first();

        $this->assertSame($actor['assigned']->id, $row['salesman']->id);
        $this->assertSame(1, $row['visits']['completed']);
        $this->assertSame(1, $row['visits']['productive']);
        $this->assertSame(500.0, $row['orders']['totals'][0]['total']);
        $this->assertSame(200.0, $row['collections']['totals'][0]['total']);
        $this->assertSame(1, $row['follow_ups']['overdue']);
        $this->assertSame(1, $row['unresolved_flags']);
        $this->assertSame(50.0, $row['target_average_percent']);

        $this->assertFalse(
            $scorecard['rows']->contains(
                fn (array $candidate) => $candidate['salesman']->id === $actor['unassigned']->id
            ),
        );
    }

    public function test_scorecard_page_respects_supervisor_visibility_and_manager_filter(): void
    {
        $actor = $this->actor();

        $this->actingAs($actor['supervisorUser'])
            ->get('/admin/scorecards?date_from=2026-09-01&date_to=2026-09-30')
            ->assertOk()
            ->assertSee('Assigned Salesman')
            ->assertDontSee('Unassigned Salesman')
            ->assertSee('500.00 AFN')
            ->assertSee('200.00 AFN');

        auth('web')->logout();
        app('auth')->forgetGuards();

        $this->actingAs($actor['admin'])
            ->get(
                '/admin/scorecards?date_from=2026-09-01&date_to=2026-09-30&supervisor='
                .$actor['supervisor']->uuid
            )
            ->assertOk()
            ->assertSee('Assigned Salesman')
            ->assertDontSee('Unassigned Salesman');
    }

    private function actor(): array
    {
        $tenant = app(TenantContext::class)->withPlatformScope(fn () => Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Scorecard Tenant',
            'slug' => 'scorecard-'.Str::lower(Str::random(6)),
            'timezone' => 'Asia/Kabul',
            'subscription_status' => 'active',
        ]));

        return app(TenantContext::class)->withTenant(
            $tenant,
            function () use ($tenant): array {
                $roles = app(TenantProvisioningService::class)->provisionRbac($tenant);

                $admin = User::create([
                    'uuid' => (string) Str::uuid(),
                    'name' => 'Scorecard Admin',
                    'email' => 'scorecard-admin@example.test',
                    'password' => Hash::make('password'),
                    'role' => 'company_admin',
                    'is_active' => true,
                ]);
                $admin->syncPrimaryRole($roles['company_admin']);

                $supervisorUser = User::create([
                    'uuid' => (string) Str::uuid(),
                    'name' => 'Team Supervisor',
                    'email' => 'scorecard-supervisor@example.test',
                    'password' => Hash::make('password'),
                    'role' => 'supervisor',
                    'is_active' => true,
                ]);
                $supervisorUser->syncPrimaryRole($roles['supervisor']);

                $supervisor = Supervisor::create([
                    'user_id' => $supervisorUser->id,
                    'employee_code' => 'SUP-SCORE-1',
                    'first_name' => 'Team',
                    'last_name' => 'Supervisor',
                    'is_active' => true,
                ]);

                [$assignedUser, $assigned] = $this->salesman(
                    $roles['salesman'],
                    'Assigned Salesman',
                    'SAL-SCORE-1',
                    'assigned-scorecard@example.test',
                );
                [$unassignedUser, $unassigned] = $this->salesman(
                    $roles['salesman'],
                    'Unassigned Salesman',
                    'SAL-SCORE-2',
                    'unassigned-scorecard@example.test',
                );

                SalesmanAssignment::create([
                    'salesman_id' => $assigned->id,
                    'supervisor_id' => $supervisor->id,
                    'effective_from' => '2026-09-01',
                    'created_by' => $admin->id,
                ]);

                $device = Device::create([
                    'user_id' => $assignedUser->id,
                    'salesman_id' => $assigned->id,
                    'device_uuid' => 'scorecard-device',
                    'installation_uuid' => 'scorecard-install',
                    'platform' => 'android',
                    'is_active' => true,
                ]);

                $customer = Customer::create([
                    'code' => 'SCORE-CUS-1',
                    'name' => 'Scorecard Customer',
                    'created_by' => $admin->id,
                    'is_active' => true,
                ]);

                WorkSession::create([
                    'uuid' => (string) Str::uuid(),
                    'user_id' => $assignedUser->id,
                    'salesman_id' => $assigned->id,
                    'device_id' => $device->id,
                    'date' => '2026-09-24',
                    'start_time' => '2026-09-24 03:30:00',
                    'end_time' => '2026-09-24 11:30:00',
                    'start_latitude' => 34.5,
                    'start_longitude' => 69.2,
                    'start_accuracy' => 8,
                    'end_latitude' => 34.5,
                    'end_longitude' => 69.2,
                    'end_accuracy' => 8,
                    'status' => 'completed',
                    'duration_minutes' => 480,
                    'is_late_start' => true,
                ]);

                $visit = CustomerVisit::create([
                    'uuid' => (string) Str::uuid(),
                    'user_id' => $assignedUser->id,
                    'salesman_id' => $assigned->id,
                    'device_id' => $device->id,
                    'customer_id' => $customer->id,
                    'is_planned' => true,
                    'status' => 'completed',
                    'outcome' => 'order_placed',
                    'checked_in_at' => '2026-09-24 04:00:00',
                    'checked_out_at' => '2026-09-24 04:10:00',
                    'checkin_latitude' => 34.5,
                    'checkin_longitude' => 69.2,
                    'checkin_accuracy' => 8,
                    'checkout_latitude' => 34.5,
                    'checkout_longitude' => 69.2,
                    'checkout_accuracy' => 8,
                    'duration_seconds' => 600,
                ]);

                VisitSuspiciousFlag::create([
                    'visit_id' => $visit->id,
                    'reason_code' => 'scorecard_test_flag',
                    'severity' => 'medium',
                ]);

                Order::create([
                    'uuid' => (string) Str::uuid(),
                    'user_id' => $assignedUser->id,
                    'salesman_id' => $assigned->id,
                    'device_id' => $device->id,
                    'customer_id' => $customer->id,
                    'visit_id' => $visit->id,
                    'order_number' => 'SCORE-ORD-1',
                    'ordered_at' => '2026-09-24 04:05:00',
                    'payment_type' => 'cash',
                    'status' => 'approved',
                    'currency' => 'AFN',
                    'subtotal' => 500,
                    'grand_total' => 500,
                ]);

                Collection::create([
                    'uuid' => (string) Str::uuid(),
                    'user_id' => $assignedUser->id,
                    'salesman_id' => $assigned->id,
                    'device_id' => $device->id,
                    'customer_id' => $customer->id,
                    'visit_id' => $visit->id,
                    'receipt_number' => 'SCORE-REC-1',
                    'collected_at' => '2026-09-24 04:08:00',
                    'currency' => 'AFN',
                    'amount' => 200,
                    'payment_method' => 'cash',
                    'status' => 'verified',
                    'latitude' => 34.5,
                    'longitude' => 69.2,
                    'accuracy' => 8,
                ]);

                CustomerFollowUp::create([
                    'customer_id' => $customer->id,
                    'assigned_salesman_id' => $assigned->id,
                    'type' => 'payment',
                    'priority' => 'high',
                    'status' => 'pending',
                    'due_at' => '2026-09-23 06:00:00',
                    'notes' => 'Overdue collection follow-up.',
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

                $unassignedDevice = Device::create([
                    'user_id' => $unassignedUser->id,
                    'salesman_id' => $unassigned->id,
                    'device_uuid' => 'scorecard-device-2',
                    'installation_uuid' => 'scorecard-install-2',
                    'platform' => 'android',
                    'is_active' => true,
                ]);

                Order::create([
                    'uuid' => (string) Str::uuid(),
                    'user_id' => $unassignedUser->id,
                    'salesman_id' => $unassigned->id,
                    'device_id' => $unassignedDevice->id,
                    'customer_id' => $customer->id,
                    'order_number' => 'SCORE-ORD-2',
                    'ordered_at' => '2026-09-24 04:20:00',
                    'payment_type' => 'cash',
                    'status' => 'approved',
                    'currency' => 'AFN',
                    'subtotal' => 999,
                    'grand_total' => 999,
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
