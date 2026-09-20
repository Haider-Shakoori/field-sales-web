<?php

namespace Tests\Feature;

use App\Models\CurrentLocation;
use App\Models\Customer;
use App\Models\CustomerVisit;
use App\Models\Device;
use App\Models\LocationHistory;
use App\Models\Order;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Salesman;
use App\Models\SalesmanAssignment;
use App\Models\Supervisor;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkSession;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class Batch13DashboardLiveMapTest extends TestCase
{
    use RefreshDatabase;

    public function test_live_map_classifies_freshness_and_stays_tenant_scoped(): void
    {
        [$tenant, $admin] = $this->tenantUser(
            'dashboard-admin@example.test',
            ['reports:view', 'tracking:view'],
            'company-admin',
        );

        [$liveSalesman, $liveDevice] = $this->salesman($tenant, 'LIVE-1', 'Live');
        [$staleSalesman, $staleDevice] = $this->salesman($tenant, 'STALE-1', 'Stale');
        [$offlineSalesman] = $this->salesman($tenant, 'OFF-1', 'Offline');

        app(TenantContext::class)->withTenant($tenant, function () use (
            $liveSalesman,
            $liveDevice,
            $staleSalesman,
            $staleDevice,
        ): void {
            CurrentLocation::create([
                'user_id' => $liveSalesman->user_id,
                'salesman_id' => $liveSalesman->id,
                'device_id' => $liveDevice->id,
                'latitude' => 34.5553,
                'longitude' => 69.2075,
                'horizontal_accuracy' => 8,
                'recorded_at' => now()->subMinutes(2),
                'received_at' => now()->subMinutes(2),
            ]);

            CurrentLocation::create([
                'user_id' => $staleSalesman->user_id,
                'salesman_id' => $staleSalesman->id,
                'device_id' => $staleDevice->id,
                'latitude' => 34.5653,
                'longitude' => 69.2175,
                'horizontal_accuracy' => 12,
                'recorded_at' => now()->subMinutes(12),
                'received_at' => now()->subMinutes(12),
            ]);
        });

        [$otherTenant] = $this->tenantUser(
            'other-admin@example.test',
            ['reports:view', 'tracking:view'],
            'company-admin',
            'Other Tenant',
            'other-tenant',
        );
        [$otherSalesman, $otherDevice] = $this->salesman(
            $otherTenant,
            'OTHER-1',
            'Other',
        );
        app(TenantContext::class)->withTenant(
            $otherTenant,
            fn () => CurrentLocation::create([
                'user_id' => $otherSalesman->user_id,
                'salesman_id' => $otherSalesman->id,
                'device_id' => $otherDevice->id,
                'latitude' => 33.0,
                'longitude' => 68.0,
                'horizontal_accuracy' => 9,
                'recorded_at' => now(),
                'received_at' => now(),
            ])
        );

        $response = $this->actingAs($admin)
            ->getJson(route('admin.dashboard.live-locations'))
            ->assertOk()
            ->assertHeader('Cache-Control');

        $rows = collect($response->json('data'))->keyBy('salesman_id');

        $this->assertCount(3, $rows);
        $this->assertSame('live', $rows[$liveSalesman->uuid]['freshness']);
        $this->assertSame('online', $rows[$liveSalesman->uuid]['status']);
        $this->assertSame('LIVE-1', $rows[$liveSalesman->uuid]['employee_code']);
        $this->assertSame('stale', $rows[$staleSalesman->uuid]['freshness']);
        $this->assertSame('idle', $rows[$staleSalesman->uuid]['status']);
        $this->assertSame('offline', $rows[$offlineSalesman->uuid]['freshness']);
        $this->assertSame('offline', $rows[$offlineSalesman->uuid]['status']);
        $this->assertNull($rows[$offlineSalesman->uuid]['location']);
        $this->assertFalse($rows->has($otherSalesman->uuid));
    }

    public function test_supervisor_live_map_only_includes_current_assignments(): void
    {
        [$tenant, $supervisorUser] = $this->tenantUser(
            'supervisor@example.test',
            ['reports:view', 'tracking:view'],
            'supervisor',
        );

        $supervisor = app(TenantContext::class)->withTenant(
            $tenant,
            fn () => Supervisor::create([
                'user_id' => $supervisorUser->id,
                'employee_code' => 'SUP-1',
                'first_name' => 'Field',
                'last_name' => 'Supervisor',
                'is_active' => true,
            ])
        );

        [$assigned] = $this->salesman($tenant, 'ASSIGNED-1', 'Assigned');
        [$unassigned] = $this->salesman($tenant, 'UNASSIGNED-1', 'Unassigned');

        app(TenantContext::class)->withTenant($tenant, function () use (
            $assigned,
            $supervisor,
            $supervisorUser,
        ): void {
            SalesmanAssignment::create([
                'salesman_id' => $assigned->id,
                'supervisor_id' => $supervisor->id,
                'effective_from' => today()->subDay(),
                'created_by' => $supervisorUser->id,
            ]);
        });

        $response = $this->actingAs($supervisorUser)
            ->getJson(route('admin.dashboard.live-locations'))
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('salesman_id');

        $this->assertTrue($ids->contains($assigned->uuid));
        $this->assertFalse($ids->contains($unassigned->uuid));
    }

    public function test_dashboard_requires_reports_and_live_map_requires_tracking(): void
    {
        [, $reportsOnly] = $this->tenantUser(
            'reports-only@example.test',
            ['reports:view'],
            'auditor',
        );

        $this->actingAs($reportsOnly)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Audit operations dashboard')
            ->assertDontSee('Live salesman map');

        $this->actingAs($reportsOnly)
            ->getJson(route('admin.dashboard.live-locations'))
            ->assertForbidden();
    }

    public function test_dashboard_renders_real_analytics_and_recent_activity(): void
    {
        [$tenant, $admin] = $this->tenantUser(
            'dashboard-analytics@example.test',
            ['reports:view', 'tracking:view', 'orders:view', 'visits:view'],
            'company_admin',
            'Analytics Tenant',
            'analytics-tenant',
        );
        [$salesman, $device] = $this->salesman($tenant, 'AN-1', 'Analytics');

        app(TenantContext::class)->withTenant(
            $tenant,
            function () use ($admin, $device, $salesman, $tenant): void {
                $customer = Customer::create([
                    'tenant_id' => $tenant->id,
                    'code' => 'CUST-AN-1',
                    'name' => 'Analytics Customer',
                    'latitude' => 34.5553,
                    'longitude' => 69.2075,
                    'created_by' => $admin->id,
                    'is_active' => true,
                ]);

                Order::create([
                    'tenant_id' => $tenant->id,
                    'user_id' => $salesman->user_id,
                    'salesman_id' => $salesman->id,
                    'device_id' => $device->id,
                    'customer_id' => $customer->id,
                    'order_number' => 'ORD-DASH-1',
                    'ordered_at' => now(),
                    'payment_type' => 'cash',
                    'status' => 'approved',
                    'currency' => 'AFN',
                    'subtotal' => 125,
                    'discount_total' => 0,
                    'grand_total' => 125,
                ]);

                CustomerVisit::create([
                    'tenant_id' => $tenant->id,
                    'user_id' => $salesman->user_id,
                    'salesman_id' => $salesman->id,
                    'device_id' => $device->id,
                    'customer_id' => $customer->id,
                    'is_planned' => false,
                    'status' => 'completed',
                    'outcome' => 'order_placed',
                    'checked_in_at' => now()->subMinutes(20),
                    'checked_out_at' => now()->subMinutes(5),
                    'checkin_latitude' => 34.5553,
                    'checkin_longitude' => 69.2075,
                    'checkin_accuracy' => 8,
                    'checkout_latitude' => 34.5554,
                    'checkout_longitude' => 69.2076,
                    'checkout_accuracy' => 8,
                    'duration_seconds' => 900,
                ]);
            }
        );

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Company operations dashboard')
            ->assertSee('Sales trend')
            ->assertSee('Visit completion')
            ->assertSee('Salesman status')
            ->assertSee('ORD-DASH-1')
            ->assertSee('125')
            ->assertDontSee('CarbonCarbonImmutable');
    }

    public function test_live_map_route_tracks_from_work_session_start_when_requested(): void
    {
        [$tenant, $admin] = $this->tenantUser(
            'track-admin@example.test',
            ['reports:view', 'tracking:view'],
            'company-admin',
            'Track Tenant',
            'track-tenant',
        );
        [$salesman, $device] = $this->salesman($tenant, 'TRACK-1', 'Tracked');

        app(TenantContext::class)->withTenant(
            $tenant,
            function () use ($tenant, $salesman, $device): void {
                WorkSession::create([
                    'tenant_id' => $tenant->id,
                    'uuid' => (string) Str::uuid(),
                    'user_id' => $salesman->user_id,
                    'salesman_id' => $salesman->id,
                    'device_id' => $device->id,
                    'date' => today()->toDateString(),
                    'start_time' => now()->subMinutes(2),
                    'start_latitude' => 34.5000,
                    'start_longitude' => 69.1000,
                    'start_accuracy' => 5,
                    'status' => 'active',
                ]);

                LocationHistory::create([
                    'tenant_id' => $tenant->id,
                    'user_id' => $salesman->user_id,
                    'salesman_id' => $salesman->id,
                    'device_id' => $device->id,
                    'client_uuid' => (string) Str::uuid(),
                    'latitude' => 34.5100,
                    'longitude' => 69.1100,
                    'horizontal_accuracy' => 8,
                    'recorded_at' => now(),
                    'received_at' => now(),
                ]);

                CurrentLocation::create([
                    'tenant_id' => $tenant->id,
                    'user_id' => $salesman->user_id,
                    'salesman_id' => $salesman->id,
                    'device_id' => $device->id,
                    'latitude' => 34.5100,
                    'longitude' => 69.1100,
                    'horizontal_accuracy' => 8,
                    'recorded_at' => now(),
                    'received_at' => now(),
                ]);
            }
        );

        $poll = $this->actingAs($admin)
            ->getJson(route('admin.dashboard.live-locations'))
            ->assertOk();

        $polled = collect($poll->json('data'))->firstWhere('salesman_id', $salesman->uuid);
        $this->assertArrayNotHasKey('track', $polled);

        $response = $this->actingAs($admin)
            ->getJson(route('admin.dashboard.live-locations', ['include_tracks' => 1]))
            ->assertOk();

        $tracked = collect($response->json('data'))->firstWhere('salesman_id', $salesman->uuid);

        $this->assertArrayHasKey('track', $tracked);
        $this->assertGreaterThanOrEqual(2, count($tracked['track']['points']));
        $this->assertSame(34.5, $tracked['track']['points'][0][0]);
        $this->assertSame(69.1, $tracked['track']['points'][0][1]);
        $this->assertSame(34.51, $tracked['track']['points'][count($tracked['track']['points']) - 1][0]);
        $this->assertGreaterThan(0, $tracked['track']['distance_km']);
    }

    private function tenantUser(
        string $email,
        array $permissions,
        string $roleSlug,
        string $tenantName = 'Dashboard Tenant',
        string $tenantSlug = 'dashboard-tenant',
    ): array {
        $tenant = app(TenantContext::class)->withPlatformScope(function () use (
            $tenantName,
            $tenantSlug,
        ): Tenant {
            return Tenant::firstOrCreate(
                ['slug' => $tenantSlug],
                [
                    'uuid' => (string) Str::uuid(),
                    'name' => $tenantName,
                    'timezone' => 'Asia/Kabul',
                ]
            );
        });

        $user = app(TenantContext::class)->withTenant(
            $tenant,
            function () use ($tenant, $email, $permissions, $roleSlug): User {
                $user = User::create([
                    'uuid' => (string) Str::uuid(),
                    'tenant_id' => $tenant->id,
                    'name' => str($roleSlug)->replace('-', ' ')->title(),
                    'email' => $email,
                    'password' => Hash::make('password'),
                    'role' => $roleSlug,
                    'is_active' => true,
                ]);

                $role = Role::create([
                    'tenant_id' => $tenant->id,
                    'name' => str($roleSlug)->replace('-', ' ')->title(),
                    'slug' => $roleSlug,
                    'is_system' => false,
                ]);

                $permissionIds = collect($permissions)->map(
                    fn (string $slug) => Permission::firstOrCreate(
                        ['slug' => $slug],
                        [
                            'name' => str($slug)->replace(':', ' ')->title(),
                            'group' => str($slug)->before(':'),
                        ]
                    )->id
                )->all();

                $role->permissions()->sync($permissionIds);
                $user->syncPrimaryRole($role);

                return $user;
            }
        );

        return [$tenant, $user];
    }

    private function salesman(
        Tenant $tenant,
        string $employeeCode,
        string $name,
    ): array {
        return app(TenantContext::class)->withTenant(
            $tenant,
            function () use ($tenant, $employeeCode, $name): array {
                $user = User::create([
                    'uuid' => (string) Str::uuid(),
                    'tenant_id' => $tenant->id,
                    'name' => $name.' Salesman',
                    'email' => strtolower($employeeCode).'@example.test',
                    'password' => Hash::make('password'),
                    'role' => 'salesman',
                    'is_active' => true,
                ]);

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
                    'is_active' => true,
                ]);

                return [$salesman, $device];
            }
        );
    }
}
