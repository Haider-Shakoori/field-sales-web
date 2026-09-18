<?php

namespace Tests\Feature;

use App\Models\CompanySetting;
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

    public function test_admin_login_resolves_identity_in_bootstrap_scope_then_returns_to_fail_closed_state(): void
    {
        $tenant = $this->tenant('admin-company');
        $admin = $this->platform(function () use ($tenant) {
            return User::create([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'name' => 'Company Admin',
                'email' => 'admin@test.local',
                'password' => Hash::make('password'),
                'role' => 'company_admin',
                'is_active' => true,
            ]);
        });

        $this->post('/login', [
            'email' => 'admin@test.local',
            'password' => 'password',
        ])->assertRedirect('/admin/dashboard');

        $this->assertSame(TenantContextState::Uninitialized, $this->context->state());

        // The authenticated session must subsequently bootstrap the same tenant.
        $this->get('/admin/dashboard')->assertOk();

        $this->assertSame(TenantContextState::Uninitialized, $this->context->state());
        $this->assertSame($tenant->id, $admin->tenant_id);
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
