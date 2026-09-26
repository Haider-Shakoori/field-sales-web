<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\MobileDiagnostic;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Salesman;
use App\Models\SalesmanAssignment;
use App\Models\Supervisor;
use App\Models\SupervisorAssignment;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class MobileManagementDiagnosticsTest extends TestCase
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

    public function test_sales_manager_can_sign_in_without_salesman_profile_and_view_team(): void
    {
        [$tenant, $manager, $salesman] = $this->managementFixture();

        $headers = $this->deviceHeaders('manager-device', 'manager-install');
        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $manager->email,
            'password' => 'password',
            'device_model' => 'Manager Phone',
        ], $headers)
            ->assertOk()
            ->assertJsonPath('data.profile_type', 'sales_manager')
            ->assertJsonPath('data.salesman', null);

        $token = $login->json('data.token');

        $this->assertDatabaseHas('devices', [
            'tenant_id' => $tenant->id,
            'user_id' => $manager->id,
            'salesman_id' => null,
            'is_active' => 1,
        ]);

        $this->withToken($token)
            ->getJson('/api/v1/mobile/team/overview', $headers)
            ->assertOk()
            ->assertJsonPath('data.summary.team_size', 1)
            ->assertJsonPath('data.members.0.id', $salesman->uuid)
            ->assertJsonPath('data.members.0.name', $salesman->full_name);
    }

    public function test_supervisor_can_sign_in_and_mobile_diagnostic_is_visible_to_management(): void
    {
        [$tenant, $manager, , $supervisor, $supervisorUser] = $this->managementFixture();

        $headers = $this->deviceHeaders('supervisor-device', 'supervisor-install');
        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $supervisorUser->email,
            'password' => 'password',
            'device_model' => 'Supervisor Phone',
        ], $headers)
            ->assertOk()
            ->assertJsonPath('data.profile_type', 'supervisor')
            ->assertJsonPath('data.supervisor.id', $supervisor->uuid);

        $this->withToken($login->json('data.token'))
            ->postJson('/api/v1/mobile/diagnostics', [
                'severity' => 'error',
                'area' => 'attendance.end_day',
                'code' => 'LOCAL_LOCATION_FALLBACK',
                'message' => 'End Day recovered from cached location data.',
                'context' => [
                    'screen' => 'home',
                    'operation' => 'end_day',
                    'app_version' => '1.0.0',
                    'ignored_private_value' => 'not stored',
                ],
            ], $headers)
            ->assertCreated()
            ->assertJsonPath('data.stored', true);

        $diagnostic = $this->tenantScope(
            $tenant,
            fn () => MobileDiagnostic::firstOrFail()
        );

        $this->assertSame('attendance.end_day', $diagnostic->area);
        $this->assertSame('home', $diagnostic->context['screen']);
        $this->assertArrayNotHasKey('ignored_private_value', $diagnostic->context);

        $this->actingAs($manager)
            ->get(route('admin.mobile-diagnostics.index'))
            ->assertOk()
            ->assertSee('Mobile diagnostics')
            ->assertSee('attendance.end_day')
            ->assertSee('End Day recovered from cached location data.');
    }

    private function managementFixture(): array
    {
        return $this->platform(function (): array {
            $tenant = Tenant::create([
                'uuid' => (string) Str::uuid(),
                'name' => 'Mobile Management Co',
                'slug' => 'mobile-management',
                'timezone' => 'Asia/Kabul',
                'subscription_status' => 'active',
            ]);

            $branch = Branch::create([
                'tenant_id' => $tenant->id,
                'name' => 'Kabul',
                'code' => 'KBL',
                'is_active' => true,
            ]);

            $manager = $this->userWithRole(
                $tenant,
                'manager@example.test',
                'sales_manager',
                ['sales-team:view']
            );
            $manager->update(['branch_id' => $branch->id]);

            $supervisorUser = $this->userWithRole(
                $tenant,
                'supervisor@example.test',
                'supervisor',
                ['sales-team:view']
            );
            $supervisorUser->update(['branch_id' => $branch->id]);

            $supervisor = Supervisor::create([
                'tenant_id' => $tenant->id,
                'user_id' => $supervisorUser->id,
                'employee_code' => 'SUP-001',
                'first_name' => 'Team',
                'last_name' => 'Supervisor',
                'is_active' => true,
            ]);

            SupervisorAssignment::create([
                'tenant_id' => $tenant->id,
                'supervisor_id' => $supervisor->id,
                'sales_manager_id' => $manager->id,
                'branch_id' => $branch->id,
                'effective_from' => now('Asia/Kabul')->subDay()->toDateString(),
                'created_by' => $manager->id,
            ]);

            $salesUser = $this->userWithRole(
                $tenant,
                'sales@example.test',
                'salesman',
                ['attendance:view']
            );
            $salesUser->update(['branch_id' => $branch->id]);

            $salesman = Salesman::create([
                'tenant_id' => $tenant->id,
                'user_id' => $salesUser->id,
                'employee_code' => 'SAL-001',
                'first_name' => 'Field',
                'last_name' => 'Salesman',
                'is_active' => true,
            ]);

            SalesmanAssignment::create([
                'tenant_id' => $tenant->id,
                'salesman_id' => $salesman->id,
                'branch_id' => $branch->id,
                'supervisor_id' => $supervisor->id,
                'effective_from' => now('Asia/Kabul')->subDay()->toDateString(),
                'created_by' => $manager->id,
            ]);

            return [$tenant, $manager, $salesman, $supervisor, $supervisorUser];
        });
    }

    private function userWithRole(
        Tenant $tenant,
        string $email,
        string $roleSlug,
        array $permissions,
    ): User {
        $role = Role::firstOrCreate(
            ['tenant_id' => $tenant->id, 'slug' => $roleSlug],
            [
                'uuid' => (string) Str::uuid(),
                'name' => str($roleSlug)->replace('_', ' ')->title(),
                'is_system' => true,
            ]
        );

        $permissionIds = collect($permissions)
            ->map(function (string $slug): int {
                return Permission::firstOrCreate(
                    ['slug' => $slug],
                    [
                        'name' => str($slug)->replace(':', ' ')->title(),
                        'group' => str($slug)->before(':'),
                    ]
                )->id;
            })
            ->all();

        $role->permissions()->sync($permissionIds);

        $user = User::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'name' => (string) str($email)->before('@')->replace('.', ' ')->title(),
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => $roleSlug,
            'is_active' => true,
        ]);
        $user->syncPrimaryRole($role);

        return $user;
    }

    private function deviceHeaders(string $device, string $installation): array
    {
        return [
            'X-Device-UUID' => $device,
            'X-Installation-UUID' => $installation,
            'X-App-Version' => '1.0',
            'X-Platform' => 'android',
            'X-OS-Version' => '16',
        ];
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
