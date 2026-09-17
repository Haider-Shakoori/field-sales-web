<?php

use App\Http\Middleware\BootstrapTenantForAuth;
use App\Http\Middleware\InitializeTenancy;
use App\Models\Customer;
use App\Support\Tenancy\TenantContext;
use App\Support\Tenancy\TenantContextMissingException;
use App\Support\Tenancy\TenantContextState;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

beforeEach(function (): void {
    seedRbac();

    Route::middleware([
        BootstrapTenantForAuth::class,
        'auth:sanctum',
        InitializeTenancy::class,
    ])->prefix('api/v1')->group(function () {
        Route::get('test/tenant-state', function () {
            return response()->json([
                'state' => TenantContext::currentState()->value,
                'tenant_id' => TenantContext::currentId(),
            ]);
        });
    });

    Route::middleware([
        BootstrapTenantForAuth::class,
        'auth:sanctum',
    ])->prefix('api/v1')->group(function () {
        Route::get('test/tenant-without-init', function () {
            $state = TenantContext::currentState()->value;

            try {
                Customer::query()->limit(1)->get();

                return response()->json(['fails_closed' => false, 'state' => $state]);
            } catch (TenantContextMissingException) {
                return response()->json(['fails_closed' => true, 'state' => $state]);
            }
        });
    });
});

function tenantLoginPayload(array $overrides = []): array
{
    return array_merge([
        'email' => 'owner@test.test',
        'password' => 'Password123!',
        'device_uuid' => 'device-'.Str::uuid(),
        'device_model' => 'Samsung Galaxy S24',
        'manufacturer' => 'Samsung',
        'android_version' => '14',
        'app_version' => '1.0.0',
        'push_token' => 'fcm_'.Str::random(40),
    ], $overrides);
}

function tenantPostLogin(array $payload): TestResponse
{
    return test()->postJson('/api/v1/auth/login', $payload, [
        'X-Installation-UUID' => 'install-'.Str::uuid(),
        'X-App-Version' => '1.0.0',
    ]);
}

function tenantStateProbe(string $token)
{
    return test()->withToken($token)
        ->withHeader('X-App-Version', '1.0.0')
        ->getJson('/api/v1/test/tenant-state');
}

it('executes the controller under the concrete tenant context for a bearer request', function (): void {
    $tenant = makeTenant();
    $owner = makeUser($tenant, 'owner', ['email' => 'probe.local@test.test']);

    $token = tenantPostLogin(tenantLoginPayload([
        'email' => 'probe.local@test.test',
        'device_uuid' => 'probe-device',
    ]))->json('data.token');

    $response = tenantStateProbe($token)->assertOk();

    expect($response->json('state'))->toBe(TenantContextState::Tenant->value);
    expect($response->json('tenant_id'))->toBe($tenant->id);
});

it('keeps the TenantContext at Uninitialized when InitializeTenancy is missing', function (): void {
    $tenant = makeTenant();
    makeUser($tenant, 'owner', ['email' => 'probe.noinit@test.test']);

    $token = tenantPostLogin(tenantLoginPayload([
        'email' => 'probe.noinit@test.test',
        'device_uuid' => 'noinit-device',
    ]))->json('data.token');

    $response = test()->withToken($token)
        ->withHeader('X-App-Version', '1.0.0')
        ->getJson('/api/v1/test/tenant-without-init')
        ->assertOk();

    expect($response->json('fails_closed'))->toBeTrue();
    expect($response->json('state'))->toBe(TenantContextState::Uninitialized->value);
});

it('keeps authenticated endpoint queries tenant-scoped and hides cross-tenant customers', function (): void {
    $tenantA = makeTenant();
    $tenantB = makeTenant();

    $ownerA = makeUser($tenantA, 'owner', ['email' => 'scope.a@test.test']);
    makeUser($tenantB, 'owner', ['email' => 'scope.b@test.test']);

    $customerA = withTenantContext($tenantA, fn () => Customer::factory()->create(['tenant_id' => $tenantA->id]));
    $customerB1 = withTenantContext($tenantB, fn () => Customer::factory()->create(['tenant_id' => $tenantB->id]));
    $customerB2 = withTenantContext($tenantB, fn () => Customer::factory()->create(['tenant_id' => $tenantB->id]));

    $tokenA = tenantPostLogin(tenantLoginPayload([
        'email' => 'scope.a@test.test',
        'device_uuid' => 'scope-device-a',
    ]))->json('data.token');

    $response = test()->withToken($tokenA)
        ->withHeader('X-App-Version', '1.0.0')
        ->getJson('/api/v1/customers?per_page=100')
        ->assertOk();

    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toContain($customerA->id);
    expect($ids)->not->toContain($customerB1->id)
        ->not->toContain($customerB2->id);
});

it('does not leak tenant context between sequential bearer requests', function (): void {
    $tenantA = makeTenant();
    $tenantB = makeTenant();

    makeUser($tenantA, 'owner', ['email' => 'leak.a@test.test']);
    makeUser($tenantB, 'owner', ['email' => 'leak.b@test.test']);

    $tokenA = tenantPostLogin(tenantLoginPayload([
        'email' => 'leak.a@test.test',
        'device_uuid' => 'leak-a',
    ]))->json('data.token');

    $tokenB = tenantPostLogin(tenantLoginPayload([
        'email' => 'leak.b@test.test',
        'device_uuid' => 'leak-b',
    ]))->json('data.token');

    $probeA1 = tenantStateProbe($tokenA)->assertOk();
    expect($probeA1->json('tenant_id'))->toBe($tenantA->id);
    expect($probeA1->json('state'))->toBe(TenantContextState::Tenant->value);

    $probeB = tenantStateProbe($tokenB)->assertOk();
    expect($probeB->json('tenant_id'))->toBe($tenantB->id);
    expect($probeB->json('state'))->toBe(TenantContextState::Tenant->value);

    $probeA2 = tenantStateProbe($tokenA)->assertOk();
    expect($probeA2->json('tenant_id'))->toBe($tenantA->id);
    expect($probeA2->json('state'))->toBe(TenantContextState::Tenant->value);
});

it('does not leak a revoked token back into an active session tenant', function (): void {
    $tenantA = makeTenant();
    $tenantB = makeTenant();

    makeUser($tenantA, 'owner', ['email' => 'revoke.a@test.test']);
    makeUser($tenantB, 'owner', ['email' => 'revoke.b@test.test']);

    $tokenA = tenantPostLogin(tenantLoginPayload([
        'email' => 'revoke.a@test.test',
        'device_uuid' => 'revoke-a',
    ]))->json('data.token');

    $tokenB = tenantPostLogin(tenantLoginPayload([
        'email' => 'revoke.b@test.test',
        'device_uuid' => 'revoke-b',
    ]))->json('data.token');

    $this->withToken($tokenA)->postJson('/api/v1/auth/logout')->assertOk();

    $this->withToken($tokenA)->getJson('/api/v1/me')->assertStatus(401);
    $this->withToken($tokenB)->getJson('/api/v1/me')->assertOk()
        ->assertJsonPath('data.user.email', 'revoke.b@test.test');
});
