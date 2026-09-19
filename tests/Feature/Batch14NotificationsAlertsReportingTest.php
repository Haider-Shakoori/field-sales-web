<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerVisit;
use App\Models\Device;
use App\Models\LocationHistory;
use App\Models\NotificationDelivery;
use App\Models\OperationalNotification;
use App\Models\Order;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Salesman;
use App\Models\SalesmanAssignment;
use App\Models\Supervisor;
use App\Models\Tenant;
use App\Models\Territory;
use App\Models\User;
use App\Models\VisitSuspiciousFlag;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class Batch14NotificationsAlertsReportingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-09-19T08:00:00Z');
        config()->set('push.enabled', false);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_order_review_creates_notification_push_outbox_and_mobile_preferences_work(): void
    {
        $tenant = $this->tenant('batch14-notifications');
        $admin = $this->user(
            $tenant,
            'batch14-admin@example.test',
            'company_admin',
            ['orders:view', 'orders:manage', 'reports:view'],
        );
        [$salesmanUser, $salesman, $device] = $this->salesman(
            $tenant,
            'NOTIFY-1',
            'Notify',
            ['orders:view'],
            'push-token-1',
        );
        [$branch, $territory] = $this->branchTerritory($tenant);
        $customer = $this->customer($tenant, $admin, $branch, $territory, 'Notify Customer');

        $order = app(TenantContext::class)->withTenant(
            $tenant,
            fn () => Order::create([
                'user_id' => $salesmanUser->id,
                'salesman_id' => $salesman->id,
                'device_id' => $device->id,
                'customer_id' => $customer->id,
                'order_number' => 'ORD-B14-1',
                'ordered_at' => now()->subHour(),
                'payment_type' => 'cash',
                'status' => 'pending',
                'currency' => 'AFN',
                'subtotal' => 250,
                'discount_total' => 0,
                'grand_total' => 250,
            ])
        );

        $this->actingAs($admin)
            ->patch(route('admin.orders.status', $order), ['status' => 'approved'])
            ->assertRedirect(route('admin.orders.show', $order));

        $notification = app(TenantContext::class)->withTenant(
            $tenant,
            fn () => OperationalNotification::where('user_id', $salesmanUser->id)->firstOrFail()
        );

        $this->assertSame('order_updates', $notification->category);
        $this->assertSame('ORD-B14-1', $notification->data['order_id'] === $order->uuid
            ? $order->order_number
            : null);

        $delivery = app(TenantContext::class)->withTenant(
            $tenant,
            fn () => NotificationDelivery::where('notification_id', $notification->id)->firstOrFail()
        );
        $this->assertSame('skipped', $delivery->status);
        $this->assertSame($device->id, $delivery->device_id);

        $token = app(TenantContext::class)->withTenant(
            $tenant,
            fn () => $salesmanUser->createToken('mobile-'.$device->uuid)->plainTextToken,
        );

        auth('web')->logout();
        app('auth')->forgetGuards();
        $headers = array_merge(
            $this->deviceHeaders($device),
            ['Authorization' => 'Bearer '.$token],
        );

        $this->getJson('/api/v1/notifications', $headers)
            ->assertOk()
            ->assertJsonPath('data.0.id', $notification->uuid)
            ->assertJsonPath('data.0.category', 'order_updates');

        $this->patchJson('/api/v1/notifications/'.$notification->uuid.'/read', [], $headers)
            ->assertOk();

        $this->assertDatabaseMissing('operational_notifications', [
            'id' => $notification->id,
            'read_at' => null,
        ]);

        $this->putJson('/api/v1/notification-preferences', [
            'database_enabled' => true,
            'push_enabled' => false,
            'order_updates' => false,
            'collection_updates' => true,
            'expense_updates' => true,
            'suspicious_alerts' => true,
        ], $headers)
            ->assertOk()
            ->assertJsonPath('data.push_enabled', false)
            ->assertJsonPath('data.order_updates', false);
    }

    public function test_alerts_are_evidence_based_tenant_scoped_and_reviewable(): void
    {
        $tenant = $this->tenant('batch14-alerts');
        $admin = $this->user(
            $tenant,
            'alerts-admin@example.test',
            'company_admin',
            ['reports:view', 'visits:view', 'visits:manage'],
        );
        [$salesmanUser, $salesman, $device] = $this->salesman($tenant, 'ALERT-1', 'Alert');
        [$branch, $territory] = $this->branchTerritory($tenant);
        $customer = $this->customer($tenant, $admin, $branch, $territory, 'Alert Customer');

        [$visit, $flag] = app(TenantContext::class)->withTenant(
            $tenant,
            function () use ($customer, $device, $salesman, $salesmanUser): array {
                $visit = CustomerVisit::create([
                    'user_id' => $salesmanUser->id,
                    'salesman_id' => $salesman->id,
                    'device_id' => $device->id,
                    'customer_id' => $customer->id,
                    'status' => 'completed',
                    'checked_in_at' => now()->subHour(),
                    'checked_out_at' => now()->subMinutes(50),
                    'checkin_latitude' => 34.5553,
                    'checkin_longitude' => 69.2075,
                    'checkin_accuracy' => 7,
                ]);

                $flag = VisitSuspiciousFlag::create([
                    'visit_id' => $visit->id,
                    'reason_code' => 'location_mismatch',
                    'severity' => 'high',
                    'details' => ['distance_meters' => 420],
                ]);

                LocationHistory::create([
                    'user_id' => $salesmanUser->id,
                    'salesman_id' => $salesman->id,
                    'device_id' => $device->id,
                    'client_uuid' => (string) Str::uuid(),
                    'latitude' => 34.5553,
                    'longitude' => 69.2075,
                    'horizontal_accuracy' => 8,
                    'is_mock_location' => true,
                    'recorded_at' => now()->subMinutes(30),
                    'received_at' => now()->subMinutes(29),
                ]);

                return [$visit, $flag];
            }
        );

        $otherTenant = $this->tenant('batch14-alerts-other');
        $otherAdmin = $this->user(
            $otherTenant,
            'other-alert-admin@example.test',
            'company_admin',
            ['reports:view', 'visits:view'],
        );
        [$otherUser, $otherSalesman, $otherDevice] = $this->salesman(
            $otherTenant,
            'OTHER-ALERT',
            'OtherAlert',
        );
        [$otherBranch, $otherTerritory] = $this->branchTerritory($otherTenant);
        $otherCustomer = $this->customer(
            $otherTenant,
            $otherAdmin,
            $otherBranch,
            $otherTerritory,
            'Other Tenant Secret Customer',
        );

        app(TenantContext::class)->withTenant($otherTenant, function () use (
            $otherCustomer,
            $otherDevice,
            $otherSalesman,
            $otherUser,
        ): void {
            $visit = CustomerVisit::create([
                'user_id' => $otherUser->id,
                'salesman_id' => $otherSalesman->id,
                'device_id' => $otherDevice->id,
                'customer_id' => $otherCustomer->id,
                'status' => 'completed',
                'checked_in_at' => now()->subHour(),
                'checked_out_at' => now()->subMinutes(50),
                'checkin_latitude' => 33.0,
                'checkin_longitude' => 68.0,
                'checkin_accuracy' => 8,
            ]);

            VisitSuspiciousFlag::create([
                'visit_id' => $visit->id,
                'reason_code' => 'too_short_duration',
                'severity' => 'medium',
            ]);
        });

        $this->actingAs($admin)
            ->get(route('admin.alerts.index'))
            ->assertOk()
            ->assertSee('Location Mismatch')
            ->assertSee('Alert Customer')
            ->assertSee('Mock-location GPS evidence')
            ->assertDontSee('Other Tenant Secret Customer');

        $this->actingAs($admin)
            ->patch(route('admin.alerts.review', $flag), [
                'review_notes' => 'Verified against field evidence.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('visit_suspicious_flags', [
            'id' => $flag->id,
            'reviewed_by' => $admin->id,
            'review_notes' => 'Verified against field evidence.',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'visit_suspicious_flag.reviewed',
            'subject_id' => $flag->id,
        ]);
    }

    public function test_reports_use_authoritative_records_filters_and_csv(): void
    {
        $tenant = $this->tenant('batch14-reports');
        $admin = $this->user(
            $tenant,
            'reports-admin@example.test',
            'company_admin',
            ['reports:view'],
        );
        [$salesmanUser, $salesman, $device] = $this->salesman($tenant, 'REPORT-1', 'Report');
        [$branch, $territory] = $this->branchTerritory($tenant);
        $customer = $this->customer($tenant, $admin, $branch, $territory, 'Report Customer');

        app(TenantContext::class)->withTenant($tenant, function () use (
            $customer,
            $device,
            $salesman,
            $salesmanUser,
        ): void {
            Order::create([
                'user_id' => $salesmanUser->id,
                'salesman_id' => $salesman->id,
                'device_id' => $device->id,
                'customer_id' => $customer->id,
                'order_number' => 'ORD-REPORT-APPROVED',
                'ordered_at' => now()->subHours(2),
                'payment_type' => 'cash',
                'status' => 'approved',
                'currency' => 'AFN',
                'subtotal' => 125,
                'discount_total' => 0,
                'grand_total' => 125,
            ]);

            Order::create([
                'user_id' => $salesmanUser->id,
                'salesman_id' => $salesman->id,
                'device_id' => $device->id,
                'customer_id' => $customer->id,
                'order_number' => 'ORD-REPORT-PENDING',
                'ordered_at' => now()->subHour(),
                'payment_type' => 'cash',
                'status' => 'pending',
                'currency' => 'AFN',
                'subtotal' => 900,
                'discount_total' => 0,
                'grand_total' => 900,
            ]);

            CustomerVisit::create([
                'user_id' => $salesmanUser->id,
                'salesman_id' => $salesman->id,
                'device_id' => $device->id,
                'customer_id' => $customer->id,
                'status' => 'completed',
                'checked_in_at' => now()->subHours(3),
                'checked_out_at' => now()->subHours(2),
                'checkin_latitude' => 34.5553,
                'checkin_longitude' => 69.2075,
                'checkin_accuracy' => 8,
            ]);

            LocationHistory::create([
                'user_id' => $salesmanUser->id,
                'salesman_id' => $salesman->id,
                'device_id' => $device->id,
                'client_uuid' => (string) Str::uuid(),
                'latitude' => 34.5553,
                'longitude' => 69.2075,
                'horizontal_accuracy' => 9,
                'is_mock_location' => true,
                'recorded_at' => now()->subMinutes(20),
                'received_at' => now()->subMinutes(19),
            ]);
        });

        $query = [
            'type' => 'sales',
            'date_from' => '2026-09-19',
            'date_to' => '2026-09-19',
            'salesman' => $salesman->uuid,
        ];

        $this->actingAs($admin)
            ->get(route('admin.reports.index', $query))
            ->assertOk()
            ->assertSee('Sales report')
            ->assertSee('125.00')
            ->assertDontSee('900.00');

        $csv = $this->actingAs($admin)
            ->get(route('admin.reports.csv', $query))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $this->assertStringContainsString('125', $csv->streamedContent());
        $this->assertStringNotContainsString('900', $csv->streamedContent());

        $this->actingAs($admin)
            ->get(route('admin.reports.index', [
                'type' => 'gps',
                'date_from' => '2026-09-19',
                'date_to' => '2026-09-19',
            ]))
            ->assertOk()
            ->assertSee('Mock-location points')
            ->assertSee('Report Salesman');
    }

    public function test_supervisor_reports_only_currently_assigned_salesmen(): void
    {
        $tenant = $this->tenant('batch14-supervisor');
        $supervisorUser = $this->user(
            $tenant,
            'batch14-supervisor@example.test',
            'supervisor',
            ['reports:view'],
        );
        $admin = $this->user(
            $tenant,
            'batch14-supervisor-admin@example.test',
            'company_admin',
            ['reports:view'],
        );

        $supervisor = app(TenantContext::class)->withTenant(
            $tenant,
            fn () => Supervisor::create([
                'user_id' => $supervisorUser->id,
                'employee_code' => 'SUP-B14',
                'first_name' => 'Batch',
                'last_name' => 'Supervisor',
                'is_active' => true,
            ])
        );

        [$assignedUser, $assigned, $assignedDevice] = $this->salesman(
            $tenant,
            'ASSIGNED-B14',
            'Assigned',
        );
        [$unassignedUser, $unassigned, $unassignedDevice] = $this->salesman(
            $tenant,
            'UNASSIGNED-B14',
            'Unassigned',
        );
        [$branch, $territory] = $this->branchTerritory($tenant);
        $assignedCustomer = $this->customer(
            $tenant,
            $admin,
            $branch,
            $territory,
            'Assigned Customer',
        );
        $unassignedCustomer = $this->customer(
            $tenant,
            $admin,
            $branch,
            $territory,
            'Unassigned Customer',
        );

        app(TenantContext::class)->withTenant($tenant, function () use (
            $assigned,
            $assignedCustomer,
            $assignedDevice,
            $assignedUser,
            $branch,
            $supervisor,
            $supervisorUser,
            $territory,
            $unassigned,
            $unassignedCustomer,
            $unassignedDevice,
            $unassignedUser,
        ): void {
            SalesmanAssignment::create([
                'salesman_id' => $assigned->id,
                'branch_id' => $branch->id,
                'territory_id' => $territory->id,
                'supervisor_id' => $supervisor->id,
                'effective_from' => today()->subDay(),
                'created_by' => $supervisorUser->id,
            ]);

            foreach ([
                [$assignedUser, $assigned, $assignedDevice, $assignedCustomer, 'ORD-ASSIGNED', 100],
                [$unassignedUser, $unassigned, $unassignedDevice, $unassignedCustomer, 'ORD-UNASSIGNED', 500],
            ] as [$user, $salesman, $device, $customer, $number, $amount]) {
                Order::create([
                    'user_id' => $user->id,
                    'salesman_id' => $salesman->id,
                    'device_id' => $device->id,
                    'customer_id' => $customer->id,
                    'order_number' => $number,
                    'ordered_at' => now()->subHour(),
                    'payment_type' => 'cash',
                    'status' => 'approved',
                    'currency' => 'AFN',
                    'subtotal' => $amount,
                    'discount_total' => 0,
                    'grand_total' => $amount,
                ]);
            }
        });

        $this->actingAs($supervisorUser)
            ->get(route('admin.reports.index', [
                'type' => 'sales',
                'date_from' => '2026-09-19',
                'date_to' => '2026-09-19',
            ]))
            ->assertOk()
            ->assertSee('Assigned Salesman')
            ->assertDontSee('Unassigned Salesman')
            ->assertSee('100.00')
            ->assertDontSee('500.00');
    }

    private function tenant(string $slug): Tenant
    {
        return app(TenantContext::class)->withPlatformScope(
            fn () => Tenant::create([
                'uuid' => (string) Str::uuid(),
                'name' => str($slug)->replace('-', ' ')->title(),
                'slug' => $slug,
                'timezone' => 'Asia/Kabul',
            ])
        );
    }

    private function user(
        Tenant $tenant,
        string $email,
        string $roleSlug,
        array $permissions = [],
    ): User {
        return app(TenantContext::class)->withTenant(
            $tenant,
            function () use ($email, $permissions, $roleSlug, $tenant): User {
                $user = User::create([
                    'uuid' => (string) Str::uuid(),
                    'tenant_id' => $tenant->id,
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
                    'is_system' => false,
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
        );
    }

    private function salesman(
        Tenant $tenant,
        string $employeeCode,
        string $name,
        array $permissions = [],
        ?string $pushToken = null,
    ): array {
        $user = $this->user(
            $tenant,
            strtolower($employeeCode).'@example.test',
            'salesman-'.$employeeCode,
            $permissions,
        );

        return app(TenantContext::class)->withTenant(
            $tenant,
            function () use ($employeeCode, $name, $pushToken, $tenant, $user): array {
                $salesman = Salesman::create([
                    'tenant_id' => $tenant->id,
                    'user_id' => $user->id,
                    'employee_code' => $employeeCode,
                    'first_name' => $name,
                    'last_name' => 'Salesman',
                    'is_active' => true,
                ]);

                $device = Device::create([
                    'tenant_id' => $tenant->id,
                    'user_id' => $user->id,
                    'salesman_id' => $salesman->id,
                    'device_uuid' => 'device-'.strtolower($employeeCode),
                    'installation_uuid' => (string) Str::uuid(),
                    'push_token' => $pushToken,
                    'is_active' => true,
                    'last_seen_at' => now(),
                ]);

                return [$user, $salesman, $device];
            }
        );
    }

    private function branchTerritory(Tenant $tenant): array
    {
        return app(TenantContext::class)->withTenant(
            $tenant,
            function (): array {
                $branch = Branch::create([
                    'code' => 'B-'.Str::upper(Str::random(5)),
                    'name' => 'Kabul Branch',
                    'is_active' => true,
                ]);

                $territory = Territory::create([
                    'branch_id' => $branch->id,
                    'code' => 'T-'.Str::upper(Str::random(5)),
                    'name' => 'Central Territory',
                    'is_active' => true,
                ]);

                return [$branch, $territory];
            }
        );
    }

    private function customer(
        Tenant $tenant,
        User $creator,
        Branch $branch,
        Territory $territory,
        string $name,
    ): Customer {
        return app(TenantContext::class)->withTenant(
            $tenant,
            fn () => Customer::create([
                'branch_id' => $branch->id,
                'territory_id' => $territory->id,
                'code' => 'C-'.Str::upper(Str::random(6)),
                'name' => $name,
                'created_by' => $creator->id,
                'is_active' => true,
            ])
        );
    }

    private function deviceHeaders(Device $device): array
    {
        return [
            'X-Device-UUID' => $device->device_uuid,
            'X-Installation-UUID' => $device->installation_uuid,
            'X-App-Version' => '1.0',
            'X-Platform' => 'android',
            'X-OS-Version' => '16',
        ];
    }
}
