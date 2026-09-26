<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Salesman;
use App\Models\SalesmanAssignment;
use App\Models\Supervisor;
use App\Models\SupervisorAssignment;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantProvisioningService;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class MobileLeadershipModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_supervisor_can_login_without_salesman_profile_and_only_sees_assigned_team(): void
    {
        $fixture = $this->fixture();
        $headers = $this->deviceHeaders('supervisor-install', 'supervisor-device');

        $login = $this->withHeaders($headers)
            ->postJson('/api/v1/auth/login', [
                'email' => $fixture['supervisorUser']->email,
                'password' => 'password',
                'device_uuid' => 'supervisor-device',
                'app_version' => '1.0.0',
            ])
            ->assertOk()
            ->assertJsonPath('data.user.role', 'supervisor')
            ->assertJsonPath('data.salesman', null);

        $token = $login->json('data.token');

        $device = app(TenantContext::class)->withTenant(
            $fixture['tenant'],
            fn () => Device::where('user_id', $fixture['supervisorUser']->id)->firstOrFail(),
        );

        $this->assertNull($device->salesman_id);

        $this->withHeaders([...$headers, 'Authorization' => 'Bearer '.$token])
            ->getJson('/api/v1/team/overview')
            ->assertOk()
            ->assertJsonPath('data.role', 'supervisor')
            ->assertJsonPath('data.summary.salesmen.total', 1)
            ->assertJsonPath('data.hierarchy.0.supervisor_name', 'Team Supervisor')
            ->assertJsonPath('data.hierarchy.0.salesmen.0.employee_code', 'SAL-ASSIGNED')
            ->assertJsonMissing(['employee_code' => 'SAL-OUTSIDE']);
    }

    public function test_same_installation_can_switch_from_salesman_to_supervisor(): void
    {
        $fixture = $this->fixture();
        $headers = $this->deviceHeaders('shared-install', 'shared-device');

        $salesmanLogin = $this->withHeaders($headers)
            ->postJson('/api/v1/auth/login', [
                'email' => $fixture['assignedUser']->email,
                'password' => 'password',
                'device_uuid' => 'shared-device',
                'app_version' => '1.0.0',
            ])
            ->assertOk()
            ->assertJsonPath('data.user.role', 'salesman');

        $salesmanToken = $salesmanLogin->json('data.token');
        $salesmanDeviceUuid = $salesmanLogin->json('data.device.id');

        $this->withHeaders($headers)
            ->postJson('/api/v1/auth/login', [
                'email' => $fixture['supervisorUser']->email,
                'password' => 'password',
                'device_uuid' => 'shared-device',
                'app_version' => '1.0.0',
            ])
            ->assertOk()
            ->assertJsonPath('data.user.role', 'supervisor')
            ->assertJsonPath('data.salesman', null)
            ->assertJsonPath('data.device.installation_uuid', 'shared-install');

        app(TenantContext::class)->withTenant(
            $fixture['tenant'],
            function () use ($fixture, $salesmanDeviceUuid): void {
                $retired = Device::where('uuid', $salesmanDeviceUuid)->firstOrFail();
                $current = Device::where('user_id', $fixture['supervisorUser']->id)
                    ->where('installation_uuid', 'shared-install')
                    ->firstOrFail();

                $this->assertFalse($retired->is_active);
                $this->assertNotNull($retired->revoked_at);
                $this->assertStringStartsWith('reassigned-', $retired->installation_uuid);
                $this->assertNull($current->salesman_id);
                $this->assertTrue($current->is_active);
            },
        );

        $this->withHeaders([...$headers, 'Authorization' => 'Bearer '.$salesmanToken])
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }

    public function test_sales_manager_mobile_team_is_scoped_to_reporting_supervisors(): void
    {
        $fixture = $this->fixture();
        $headers = $this->deviceHeaders('manager-install', 'manager-device');

        $login = $this->withHeaders($headers)
            ->postJson('/api/v1/auth/login', [
                'email' => $fixture['manager']->email,
                'password' => 'password',
                'device_uuid' => 'manager-device',
                'app_version' => '1.0.0',
            ])
            ->assertOk()
            ->assertJsonPath('data.user.role', 'sales_manager')
            ->assertJsonPath('data.salesman', null);

        $token = $login->json('data.token');

        $this->withHeaders([...$headers, 'Authorization' => 'Bearer '.$token])
            ->getJson('/api/v1/team/overview')
            ->assertOk()
            ->assertJsonPath('data.role', 'sales_manager')
            ->assertJsonPath('data.summary.salesmen.total', 1)
            ->assertJsonCount(1, 'data.hierarchy')
            ->assertJsonPath('data.hierarchy.0.supervisor_name', 'Team Supervisor')
            ->assertJsonPath('data.hierarchy.0.salesmen.0.employee_code', 'SAL-ASSIGNED')
            ->assertJsonMissing(['employee_code' => 'SAL-OUTSIDE'])
            ->assertJsonMissing(['supervisor_name' => 'Outside Supervisor']);
    }

    private function fixture(): array
    {
        $tenant = app(TenantContext::class)->withPlatformScope(fn () => Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Mobile Leadership',
            'slug' => 'mobile-leadership',
            'timezone' => 'Asia/Kabul',
            'subscription_status' => 'active',
        ]));

        return app(TenantContext::class)->withTenant(
            $tenant,
            function () use ($tenant): array {
                $roles = app(TenantProvisioningService::class)->provisionRbac($tenant);
                app(TenantProvisioningService::class)->seedTrackingDefaults($tenant);

                $manager = $this->user($tenant, 'Sales Manager', 'manager@example.test', 'sales_manager');
                $manager->syncPrimaryRole($roles['sales_manager']);

                $supervisorUser = $this->user(
                    $tenant,
                    'Team Supervisor',
                    'supervisor@example.test',
                    'supervisor',
                );
                $supervisorUser->syncPrimaryRole($roles['supervisor']);
                $supervisor = Supervisor::create([
                    'user_id' => $supervisorUser->id,
                    'employee_code' => 'SUP-TEAM',
                    'first_name' => 'Team',
                    'last_name' => 'Supervisor',
                    'is_active' => true,
                ]);

                $outsideSupervisorUser = $this->user(
                    $tenant,
                    'Outside Supervisor',
                    'outside-supervisor@example.test',
                    'supervisor',
                );
                $outsideSupervisorUser->syncPrimaryRole($roles['supervisor']);
                $outsideSupervisor = Supervisor::create([
                    'user_id' => $outsideSupervisorUser->id,
                    'employee_code' => 'SUP-OUT',
                    'first_name' => 'Outside',
                    'last_name' => 'Supervisor',
                    'is_active' => true,
                ]);

                SupervisorAssignment::create([
                    'supervisor_id' => $supervisor->id,
                    'sales_manager_id' => $manager->id,
                    'effective_from' => today()->subDay(),
                    'created_by' => $manager->id,
                ]);

                [$assignedUser, $assigned] = $this->salesman(
                    $tenant,
                    $roles['salesman'],
                    'SAL-ASSIGNED',
                    'Assigned',
                );
                [$outsideUser, $outside] = $this->salesman(
                    $tenant,
                    $roles['salesman'],
                    'SAL-OUTSIDE',
                    'Outside',
                );

                SalesmanAssignment::create([
                    'salesman_id' => $assigned->id,
                    'supervisor_id' => $supervisor->id,
                    'effective_from' => today()->subDay(),
                    'created_by' => $manager->id,
                ]);

                SalesmanAssignment::create([
                    'salesman_id' => $outside->id,
                    'supervisor_id' => $outsideSupervisor->id,
                    'effective_from' => today()->subDay(),
                    'created_by' => $manager->id,
                ]);

                return compact(
                    'tenant',
                    'manager',
                    'supervisorUser',
                    'supervisor',
                    'outsideSupervisor',
                    'assignedUser',
                    'assigned',
                    'outsideUser',
                    'outside',
                );
            },
        );
    }

    private function user(
        Tenant $tenant,
        string $name,
        string $email,
        string $role,
    ): User {
        return User::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'name' => $name,
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function salesman(
        Tenant $tenant,
        $role,
        string $employeeCode,
        string $firstName,
    ): array {
        $user = $this->user(
            $tenant,
            $firstName.' Salesman',
            strtolower($employeeCode).'@example.test',
            'salesman',
        );
        $user->syncPrimaryRole($role);

        $salesman = Salesman::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'employee_code' => $employeeCode,
            'first_name' => $firstName,
            'last_name' => 'Salesman',
            'is_active' => true,
        ]);

        return [$user, $salesman];
    }

    private function deviceHeaders(string $installation, string $device): array
    {
        return [
            'Accept' => 'application/json',
            'X-Installation-UUID' => $installation,
            'X-Device-UUID' => $device,
            'X-App-Version' => '1.0.0',
            'X-Platform' => 'android',
            'X-OS-Version' => '15',
        ];
    }
}
