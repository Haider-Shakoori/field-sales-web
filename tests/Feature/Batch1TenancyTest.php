<?php

namespace Tests\Feature;

use App\Models\CompanySetting;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantContextMissingException;
use App\Tenancy\TenantContextMismatchException;
use App\Tenancy\TenantContextState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class Batch1TenancyTest extends TestCase
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

    public function test_tenant_owned_queries_fail_closed_when_context_is_missing(): void
    {
        $this->expectException(TenantContextMissingException::class);

        CompanySetting::query()->count();
    }

    public function test_tenant_context_scopes_queries_and_auto_fills_tenant_id(): void
    {
        [$tenantA, $tenantB] = $this->tenants();

        $this->context->withPlatformScope(function () use ($tenantA, $tenantB): void {
            CompanySetting::create([
                'tenant_id' => $tenantA->id,
                'key' => 'demo.a',
                'value' => 'A',
            ]);

            CompanySetting::create([
                'tenant_id' => $tenantB->id,
                'key' => 'demo.b',
                'value' => 'B',
            ]);
        });

        $this->context->initializeTenant($tenantA);

        $this->assertSame(1, CompanySetting::query()->count());
        $this->assertSame('A', CompanySetting::query()->firstOrFail()->value);

        $created = CompanySetting::create([
            'key' => 'demo.auto',
            'value' => 'auto',
        ]);

        $this->assertSame($tenantA->id, $created->tenant_id);
        $this->assertSame(2, CompanySetting::query()->count());
    }

    public function test_cross_tenant_write_is_rejected_in_tenant_scope(): void
    {
        [$tenantA, $tenantB] = $this->tenants();

        $this->context->initializeTenant($tenantA);

        $this->expectException(TenantContextMismatchException::class);

        CompanySetting::create([
            'tenant_id' => $tenantB->id,
            'key' => 'wrong.tenant',
            'value' => 'blocked',
        ]);
    }

    public function test_platform_scope_requires_explicit_tenant_for_writes(): void
    {
        $this->expectException(TenantContextMissingException::class);

        $this->context->withPlatformScope(function (): void {
            CompanySetting::create([
                'key' => 'missing.tenant',
                'value' => 'blocked',
            ]);
        });
    }

    public function test_platform_scope_is_temporary_and_restores_fail_closed_state(): void
    {
        [$tenantA, $tenantB] = $this->tenants();

        $this->context->withPlatformScope(function () use ($tenantA, $tenantB): void {
            CompanySetting::create([
                'tenant_id' => $tenantA->id,
                'key' => 'a',
                'value' => '1',
            ]);
            CompanySetting::create([
                'tenant_id' => $tenantB->id,
                'key' => 'b',
                'value' => '2',
            ]);

            $this->assertSame(2, CompanySetting::query()->count());
        });

        $this->assertSame(TenantContextState::Uninitialized, $this->context->state());

        $this->expectException(TenantContextMissingException::class);

        CompanySetting::query()->count();
    }

    public function test_authentication_bootstrap_scope_never_broadens_an_existing_tenant_context(): void
    {
        [$tenantA, $tenantB] = $this->tenants();

        $this->context->withPlatformScope(function () use ($tenantA, $tenantB): void {
            CompanySetting::create(['tenant_id' => $tenantA->id, 'key' => 'a', 'value' => '1']);
            CompanySetting::create(['tenant_id' => $tenantB->id, 'key' => 'b', 'value' => '2']);
        });

        $this->context->initializeTenant($tenantA);

        $count = $this->context->withAuthenticationBootstrapScope(
            fn () => CompanySetting::query()->count()
        );

        $this->assertSame(1, $count);
        $this->assertSame($tenantA->id, $this->context->tenantId());
    }

    public function test_tenant_context_refuses_in_request_switching(): void
    {
        [$tenantA, $tenantB] = $this->tenants();

        $this->context->initializeTenant($tenantA);

        $this->expectException(TenantContextMismatchException::class);

        $this->context->initializeTenant($tenantB);
    }

    private function tenants(): array
    {
        $tenantA = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Tenant A',
            'slug' => 'tenant-a',
            'timezone' => 'UTC',
            'subscription_status' => 'active',
        ]);

        $tenantB = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Tenant B',
            'slug' => 'tenant-b',
            'timezone' => 'UTC',
            'subscription_status' => 'active',
        ]);

        return [$tenantA, $tenantB];
    }
}
