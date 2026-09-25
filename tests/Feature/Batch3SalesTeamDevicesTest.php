<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Device;
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

class Batch3SalesTeamDevicesTest extends TestCase
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

    public function test_salesman_and_supervisor_profiles_are_tenant_scoped_and_manageable(): void
    {
        $tenant = $this->tenant('profiles');

        [$admin, $salesUser, $supervisorUser] = $this->platform(function () use ($tenant): array {
            $admin = $this->userWithRole(
                $tenant,
                'admin@profiles.local',
                ['sales-team:view', 'sales-team:manage'],
                'company_admin',
            );

            $salesUser = $this->plainUser($tenant, 'sales@profiles.local');
            $supervisorUser = $this->plainUser($tenant, 'supervisor@profiles.local');

            return [$admin, $salesUser, $supervisorUser];
        });

        $this->actingAs($admin)
            ->post(route('admin.salesmen.store'), [
                'user_id' => $salesUser->id,
                'employee_code' => 'SAL-100',
                'first_name' => 'Field',
                'last_name' => 'Rep',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.salesmen.index'));

        $this->actingAs($admin)
            ->post(route('admin.supervisors.store'), [
                'user_id' => $supervisorUser->id,
                'employee_code' => 'SUP-100',
                'first_name' => 'Team',
                'last_name' => 'Lead',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.supervisors.index'));

        $salesman = $this->tenantScope(
            $tenant,
            fn () => Salesman::where('employee_code', 'SAL-100')->firstOrFail()
        );
        $supervisor = $this->tenantScope(
            $tenant,
            fn () => Supervisor::where('employee_code', 'SUP-100')->firstOrFail()
        );

        $this->actingAs($admin)
            ->get(route('admin.salesmen.show', $salesman))
            ->assertOk()
            ->assertSee('Field Rep');

        $this->actingAs($admin)
            ->get(route('admin.supervisors.show', $supervisor))
            ->assertOk()
            ->assertSee('Team Lead');

        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $tenant->id,
            'event' => 'salesman.created',
            'subject_id' => $salesman->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $tenant->id,
            'event' => 'supervisor.created',
            'subject_id' => $supervisor->id,
        ]);
    }

    public function test_same_user_cannot_hold_salesman_and_supervisor_profiles(): void
    {
        $tenant = $this->tenant('dual-profile');

        [$admin, $user] = $this->platform(function () use ($tenant): array {
            return [
                $this->userWithRole(
                    $tenant,
                    'admin@dual.local',
                    ['sales-team:view', 'sales-team:manage'],
                    'company_admin',
                ),
                $this->plainUser($tenant, 'worker@dual.local'),
            ];
        });

        $this->tenantScope($tenant, fn () => Salesman::create([
            'user_id' => $user->id,
            'employee_code' => 'SAL-DUAL',
            'first_name' => 'Dual',
            'is_active' => true,
        ]));

        $this->actingAs($admin)
            ->post(route('admin.supervisors.store'), [
                'user_id' => $user->id,
                'employee_code' => 'SUP-DUAL',
                'first_name' => 'Dual',
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('user_id');

        $this->assertDatabaseMissing('supervisors', ['user_id' => $user->id]);
    }

    public function test_cross_tenant_sales_team_route_binding_is_hidden(): void
    {
        $tenantA = $this->tenant('team-a');
        $tenantB = $this->tenant('team-b');

        $admin = $this->platform(fn () => $this->userWithRole(
            $tenantA,
            'admin@team-a.local',
            ['sales-team:view', 'sales-team:manage'],
            'company_admin',
        ));

        $foreignSalesman = $this->platform(function () use ($tenantB): Salesman {
            $user = $this->plainUser($tenantB, 'sales@team-b.local');

            return Salesman::create([
                'tenant_id' => $tenantB->id,
                'user_id' => $user->id,
                'employee_code' => 'SAL-B',
                'first_name' => 'Foreign',
                'is_active' => true,
            ]);
        });

        $this->actingAs($admin)
            ->get(route('admin.salesmen.show', $foreignSalesman))
            ->assertNotFound();
    }

    public function test_new_salesman_assignment_closes_previous_open_window_and_audits_end(): void
    {
        $tenant = $this->tenant('history');

        [$admin, $salesman, $supervisor, $branch] = $this->salesTeamFixture($tenant);

        $old = $this->tenantScope($tenant, fn () => SalesmanAssignment::create([
            'salesman_id' => $salesman->id,
            'branch_id' => $branch->id,
            'supervisor_id' => $supervisor->id,
            'effective_from' => '2026-01-01',
            'created_by' => $admin->id,
        ]));

        $this->actingAs($admin)
            ->post(route('admin.salesman-assignments.store'), [
                'salesman_id' => $salesman->id,
                'branch_id' => $branch->id,
                'supervisor_id' => $supervisor->id,
                'effective_from' => '2026-02-01',
                'effective_to' => '',
            ])
            ->assertRedirect();

        $old = $this->tenantScope($tenant, fn () => $old->fresh());

        $this->assertSame('2026-01-31', $old->effective_to?->toDateString());

        $new = $this->tenantScope(
            $tenant,
            fn () => SalesmanAssignment::whereDate('effective_from', '2026-02-01')->firstOrFail()
        );

        $this->assertNull($new->effective_to);
        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $tenant->id,
            'event' => 'salesman_assignment.ended',
            'subject_id' => $old->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $tenant->id,
            'event' => 'salesman_assignment.created',
            'subject_id' => $new->id,
        ]);
    }

    public function test_salesman_assignment_rejects_overlapping_future_window_without_mutating_history(): void
    {
        $tenant = $this->tenant('overlap');

        [$admin, $salesman, $supervisor, $branch] = $this->salesTeamFixture($tenant);

        $current = $this->tenantScope($tenant, fn () => SalesmanAssignment::create([
            'salesman_id' => $salesman->id,
            'branch_id' => $branch->id,
            'supervisor_id' => $supervisor->id,
            'effective_from' => '2026-01-01',
            'created_by' => $admin->id,
        ]));

        $this->tenantScope($tenant, fn () => SalesmanAssignment::create([
            'salesman_id' => $salesman->id,
            'branch_id' => $branch->id,
            'supervisor_id' => $supervisor->id,
            'effective_from' => '2026-10-01',
            'created_by' => $admin->id,
        ]));

        $this->actingAs($admin)
            ->post(route('admin.salesman-assignments.store'), [
                'salesman_id' => $salesman->id,
                'branch_id' => $branch->id,
                'supervisor_id' => $supervisor->id,
                'effective_from' => '2026-09-01',
                'effective_to' => '',
            ])
            ->assertSessionHasErrors('effective_to');

        $current = $this->tenantScope($tenant, fn () => $current->fresh());

        $this->assertNull($current->effective_to);
    }

    public function test_supervisor_assignment_rejects_duplicate_overlapping_branch_window(): void
    {
        $tenant = $this->tenant('supervisor-window');
        [$admin, , $supervisor, $branch, $manager] = $this->salesTeamFixture($tenant);

        $this->tenantScope($tenant, fn () => SupervisorAssignment::create([
            'supervisor_id' => $supervisor->id,
            'sales_manager_id' => $manager->id,
            'branch_id' => $branch->id,
            'effective_from' => '2026-01-01',
            'effective_to' => '2026-12-31',
            'created_by' => $admin->id,
        ]));

        $this->actingAs($admin)
            ->post(route('admin.supervisor-assignments.store'), [
                'supervisor_id' => $supervisor->id,
                'sales_manager_id' => $manager->id,
                'branch_id' => $branch->id,
                'effective_from' => '2026-06-01',
                'effective_to' => '2026-06-30',
            ])
            ->assertSessionHasErrors('effective_from');

        $this->assertDatabaseCount('supervisor_assignments', 1);
    }

    public function test_assignment_pages_cover_index_create_show_and_edit(): void
    {
        $tenant = $this->tenant('assignment-pages');
        [$admin, $salesman, $supervisor, $branch, $manager] = $this->salesTeamFixture($tenant);

        [$salesAssignment, $supervisorAssignment] = $this->tenantScope($tenant, function () use ($admin, $salesman, $supervisor, $branch, $manager): array {
            return [
                SalesmanAssignment::create([
                    'salesman_id' => $salesman->id,
                    'branch_id' => $branch->id,
                    'supervisor_id' => $supervisor->id,
                    'effective_from' => '2026-01-01',
                    'created_by' => $admin->id,
                ]),
                SupervisorAssignment::create([
                    'supervisor_id' => $supervisor->id,
                    'sales_manager_id' => $manager->id,
                    'branch_id' => $branch->id,
                    'effective_from' => '2026-01-01',
                    'created_by' => $admin->id,
                ]),
            ];
        });

        foreach ([
            route('admin.salesman-assignments.index'),
            route('admin.salesman-assignments.create'),
            route('admin.salesman-assignments.show', $salesAssignment),
            route('admin.salesman-assignments.edit', $salesAssignment),
            route('admin.supervisor-assignments.index'),
            route('admin.supervisor-assignments.create'),
            route('admin.supervisor-assignments.show', $supervisorAssignment),
            route('admin.supervisor-assignments.edit', $supervisorAssignment),
        ] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_same_device_login_rotates_only_its_token(): void
    {
        $tenant = $this->tenant('device-rotate');
        $salesmanUser = $this->mobileSalesman($tenant, 'sales@rotate.local');

        $headers = $this->deviceHeaders('device-a', 'install-a', '1.0');

        $first = $this->postJson('/api/v1/auth/login', [
            'email' => $salesmanUser->email,
            'password' => 'password',
            'device_model' => 'Pixel',
        ], $headers)->assertOk();

        $firstToken = $first->json('data.token');

        $second = $this->postJson('/api/v1/auth/login', [
            'email' => $salesmanUser->email,
            'password' => 'password',
            'device_model' => 'Pixel',
        ], $headers)->assertOk();

        $secondToken = $second->json('data.token');

        $this->assertNotSame($firstToken, $secondToken);
        $this->assertDatabaseCount('devices', 1);
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_one_active_device_limit_revoke_and_replacement_flow(): void
    {
        $tenant = $this->tenant('device-limit');

        [$admin, $salesmanUser] = $this->platform(function () use ($tenant): array {
            $admin = $this->userWithRole(
                $tenant,
                'admin@device.local',
                ['sales-team:view', 'sales-team:manage'],
                'company_admin',
            );
            $salesmanUser = $this->mobileSalesman($tenant, 'sales@device.local');

            return [$admin, $salesmanUser];
        });

        $firstHeaders = $this->deviceHeaders('device-a', 'install-a', '1.0');

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $salesmanUser->email,
            'password' => 'password',
        ], $firstHeaders)->assertOk();

        $token = $login->json('data.token');

        $this->postJson('/api/v1/auth/login', [
            'email' => $salesmanUser->email,
            'password' => 'password',
        ], $this->deviceHeaders('device-b', 'install-b', '1.0'))
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'DEVICE_LIMIT_REACHED');

        $device = $this->tenantScope(
            $tenant,
            fn () => Device::where('installation_uuid', 'install-a')->firstOrFail()
        );

        $this->actingAs($admin)
            ->post(route('admin.devices.revoke', $device), ['reason' => 'Replacement'])
            ->assertRedirect();

        $this->assertDatabaseHas('devices', [
            'id' => $device->id,
            'is_active' => 0,
            'revocation_reason' => 'Replacement',
        ]);
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $salesmanUser->id,
            'name' => 'mobile-'.$device->uuid,
        ]);

        auth()->logout();
        app('auth')->forgetGuards();

        $this->withToken($token)
            ->getJson('/api/v1/auth/me', $firstHeaders)
            ->assertUnauthorized();

        $this->postJson('/api/v1/auth/login', [
            'email' => $salesmanUser->email,
            'password' => 'password',
        ], $this->deviceHeaders('device-b', 'install-b', '1.0'))
            ->assertOk();
    }

    public function test_device_required_rejects_old_app_and_token_device_mismatch(): void
    {
        $tenant = $this->tenant('device-policy');
        $salesmanUser = $this->mobileSalesman($tenant, 'sales@policy.local');

        config(['tenancy.mobile.minimum_version' => '2.0']);

        $this->postJson('/api/v1/auth/login', [
            'email' => $salesmanUser->email,
            'password' => 'password',
        ], $this->deviceHeaders('device-a', 'install-a', '1.0'))
            ->assertStatus(426)
            ->assertJsonPath('error.code', 'APP_UPGRADE_REQUIRED')
            ->assertJsonPath('error.details.required_version', '2.0');

        config(['tenancy.mobile.minimum_version' => '1.0']);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $salesmanUser->email,
            'password' => 'password',
        ], $this->deviceHeaders('device-a', 'install-a', '1.0'))
            ->assertOk();

        $device = $this->tenantScope(
            $tenant,
            fn () => Device::where('installation_uuid', 'install-a')->firstOrFail()
        );

        $wrongToken = $this->tenantScope(
            $tenant,
            fn () => $salesmanUser->createToken('mobile-'.Str::uuid())->plainTextToken
        );

        $this->withToken($wrongToken)
            ->getJson('/api/v1/auth/me', $this->deviceHeaders('device-a', 'install-a', '1.0'))
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'DEVICE_TOKEN_MISMATCH');

        app('auth')->forgetGuards();

        $this->withToken($login->json('data.token'))
            ->getJson('/api/v1/auth/me', $this->deviceHeaders('device-a', 'install-a', '1.0'))
            ->assertOk()
            ->assertJsonPath('data.device.id', $device->uuid);
    }

    private function salesTeamFixture(Tenant $tenant): array
    {
        return $this->platform(function () use ($tenant): array {
            $admin = $this->userWithRole(
                $tenant,
                'admin@'.$tenant->slug.'.local',
                ['sales-team:view', 'sales-team:manage'],
                'company_admin',
            );

            $branch = Branch::create([
                'tenant_id' => $tenant->id,
                'name' => 'Main',
                'code' => 'MAIN',
                'is_active' => true,
            ]);

            $salesUser = $this->plainUser($tenant, 'sales@'.$tenant->slug.'.local');
            $supervisorUser = $this->plainUser($tenant, 'supervisor@'.$tenant->slug.'.local');
            $manager = $this->userWithRole(
                $tenant,
                'manager@'.$tenant->slug.'.local',
                ['sales-team:view', 'sales-team:manage'],
                'sales_manager',
            );

            $salesman = Salesman::create([
                'tenant_id' => $tenant->id,
                'user_id' => $salesUser->id,
                'employee_code' => 'SAL-001',
                'first_name' => 'Demo',
                'last_name' => 'Salesman',
                'is_active' => true,
            ]);

            $supervisor = Supervisor::create([
                'tenant_id' => $tenant->id,
                'user_id' => $supervisorUser->id,
                'employee_code' => 'SUP-001',
                'first_name' => 'Demo',
                'last_name' => 'Supervisor',
                'is_active' => true,
            ]);

            return [$admin, $salesman, $supervisor, $branch, $manager];
        });
    }

    private function mobileSalesman(Tenant $tenant, string $email): User
    {
        return $this->platform(function () use ($tenant, $email): User {
            $role = $this->role(
                $tenant,
                'salesman',
                ['attendance:view', 'attendance:manage', 'gps:upload'],
            );
            $user = $this->plainUser($tenant, $email);
            $user->syncPrimaryRole($role);

            Salesman::create([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'employee_code' => 'SAL-'.strtoupper(Str::random(5)),
                'first_name' => 'Mobile',
                'last_name' => 'Salesman',
                'is_active' => true,
            ]);

            return $user;
        });
    }

    private function deviceHeaders(string $device, string $installation, string $appVersion): array
    {
        return [
            'X-Device-UUID' => $device,
            'X-Installation-UUID' => $installation,
            'X-App-Version' => $appVersion,
            'X-Platform' => 'android',
            'X-OS-Version' => '16',
        ];
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

    private function role(
        Tenant $tenant,
        string $slug,
        array $permissions,
    ): Role {
        $role = Role::firstOrCreate(
            ['tenant_id' => $tenant->id, 'slug' => $slug],
            [
                'name' => str($slug)->replace('_', ' ')->title(),
                'is_system' => true,
            ]
        );

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
