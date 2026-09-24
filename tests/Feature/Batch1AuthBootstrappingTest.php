<?php

namespace Tests\Feature;

use App\Models\CompanySetting;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantContextState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class Batch1AuthBootstrappingTest extends TestCase
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

    public function test_public_login_page_is_reachable_without_tenant_context(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Welcome back');

        $this->assertSame(TenantContextState::Uninitialized, $this->context->state());
    }

    public function test_stale_web_session_identifier_is_purged_without_leaking_platform_scope(): void
    {
        $guardName = Auth::guard()->getName();

        $this->withSession([$guardName => 999999])
            ->get('/login')
            ->assertOk()
            ->assertSessionMissing($guardName);

        $this->assertSame(TenantContextState::Uninitialized, $this->context->state());
    }

    public function test_admin_login_bootstraps_tenant_and_next_request_resolves_same_tenant(): void
    {
        $tenant = $this->tenant('admin-company');

        $this->platform(function () use ($tenant): void {
            $user = User::create([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'name' => 'Admin',
                'email' => 'admin@test.local',
                'password' => Hash::make('password'),
                'role' => 'company_admin',
                'is_active' => true,
            ]);

            $permission = Permission::create([
                'name' => 'Settings View',
                'slug' => 'settings:view',
                'group' => 'settings',
            ]);

            $role = Role::create([
                'tenant_id' => $tenant->id,
                'name' => 'Company Admin',
                'slug' => 'company_admin',
                'is_system' => true,
            ]);

            $role->permissions()->sync([$permission->id]);
            $user->syncPrimaryRole($role);

            CompanySetting::create([
                'tenant_id' => $tenant->id,
                'key' => 'tracking.work_session_start_mode',
                'value' => 'manual',
            ]);
        });

        $this->post('/login', [
            'email' => 'admin@test.local',
            'password' => 'password',
        ])->assertRedirect('/admin/tracking-settings');

        $this->assertSame(TenantContextState::Uninitialized, $this->context->state());

        // Simulate a new real HTTP request: the authenticated User object is
        // no longer cached in memory and must be restored from the session id.
        Auth::forgetGuards();
        $this->context->clear();

        $this->get('/admin/tracking-settings')
            ->assertOk()
            ->assertSee('Attendance')
            ->assertSee('data-theme-toggle', false)
            ->assertSee('data-sidebar-collapse', false)
            ->assertSee('data-sidebar-collapsed="false"', false);

        $this->assertSame(TenantContextState::Uninitialized, $this->context->state());
    }

    public function test_mobile_login_requires_tenant_disambiguation_when_email_exists_in_multiple_companies(): void
    {
        $tenantA = $this->tenant('company-a');
        $tenantB = $this->tenant('company-b');

        $this->platform(function () use ($tenantA, $tenantB): void {
            foreach ([$tenantA, $tenantB] as $tenant) {
                User::create([
                    'uuid' => (string) Str::uuid(),
                    'tenant_id' => $tenant->id,
                    'name' => 'Shared User',
                    'email' => 'shared@test.local',
                    'password' => Hash::make('password'),
                    'role' => 'salesman',
                    'is_active' => true,
                ]);
            }
        });

        $this->withHeader('X-Installation-UUID', (string) Str::uuid())
            ->postJson('/api/v1/auth/login', [
                'email' => 'shared@test.local',
                'password' => 'password',
                'device_uuid' => (string) Str::uuid(),
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'TENANT_REQUIRED');

        $this->assertSame(TenantContextState::Uninitialized, $this->context->state());
    }

    public function test_bearer_token_bootstrap_sets_correct_tenant_before_scoped_api_queries(): void
    {
        $tenantA = $this->tenant('api-a');
        $tenantB = $this->tenant('api-b');

        [$userA, $token] = $this->platform(function () use ($tenantA, $tenantB): array {
            $userA = User::create([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $tenantA->id,
                'name' => 'User A',
                'email' => 'a@test.local',
                'password' => Hash::make('password'),
                'role' => 'salesman',
                'is_active' => true,
            ]);

            User::create([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $tenantB->id,
                'name' => 'User B',
                'email' => 'b@test.local',
                'password' => Hash::make('password'),
                'role' => 'salesman',
                'is_active' => true,
            ]);

            CompanySetting::create([
                'tenant_id' => $tenantA->id,
                'key' => 'tracking.work_session_start_mode',
                'value' => 'automatic',
            ]);

            CompanySetting::create([
                'tenant_id' => $tenantB->id,
                'key' => 'tracking.work_session_start_mode',
                'value' => 'manual',
            ]);

            return [$userA, $userA->createToken('batch1-test')->plainTextToken];
        });

        $this->withToken($token)
            ->getJson('/api/v1/settings/attendance-tracking')
            ->assertOk()
            ->assertJsonPath('data.work_session_start_mode', 'automatic');

        $this->assertSame($tenantA->id, $userA->tenant_id);
        $this->assertSame(TenantContextState::Uninitialized, $this->context->state());
    }

    private function platform(callable $callback): mixed
    {
        return $this->context->withPlatformScope(fn () => $callback());
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
}
