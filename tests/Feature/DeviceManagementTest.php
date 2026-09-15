<?php

use App\Models\AuditLog;
use App\Models\CompanySetting;
use App\Models\Device;
use App\Models\Salesman;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    seedRbac();
});

function makeSalesmanUser(Tenant $tenant, array $attributes = []): User
{
    return withTenantContext($tenant, function () use ($tenant, $attributes): User {
        $user = makeUser($tenant, 'salesman', array_merge([
            'email' => 'slm.'.Str::random(8).'@test.test',
        ], $attributes));

        Salesman::factory()->forTenant($tenant->id)->create([
            'user_id' => $user->id,
            'email' => $user->email,
            'employee_code' => $user->email,
        ]);

        return $user;
    });
}

function registerDevice(User $user, array $overrides = []): TestResponse
{
    Sanctum::actingAs($user);

    return test()->postJson('/api/v1/devices/register', array_merge([
        'device_uuid' => 'device-'.Str::uuid(),
        'device_model' => 'Samsung Galaxy S24',
        'manufacturer' => 'Samsung',
        'android_version' => '14',
        'app_version' => '1.0.0',
        'push_token' => 'fcm_'.Str::random(40),
    ], $overrides), [
        'X-Installation-UUID' => 'install-'.Str::uuid(),
        'X-App-Version' => '1.0.0',
    ]);
}

it('registers a device for a salesman, deriving salesman_id server-side', function (): void {
    $tenant = makeTenant();
    $salesmanUser = makeSalesmanUser($tenant);
    $salesman = withTenantContext($tenant, fn () => $salesmanUser->salesmanProfile);

    Sanctum::actingAs($salesmanUser);

    $this->postJson('/api/v1/devices/register', [
        'device_uuid' => 'abc-123',
        'device_model' => 'Samsung S21',
        'manufacturer' => 'Samsung',
        'android_version' => '14',
        'app_version' => '1.0.0',
        'push_token' => 'fcm_token_here',
        'salesman_id' => 999999,
        'tenant_id' => 999999,
    ], [
        'X-Installation-UUID' => 'install-abc',
        'X-App-Version' => '1.0.0',
    ])
        ->assertCreated()
        ->assertJsonPath('data.device.status', 'active')
        ->assertJsonPath('data.device.salesman.employee_code', $salesman->employee_code);

    $device = withTenantContext($tenant, fn () => Device::first());
    expect($device)->not->toBeNull();
    expect($device->tenant_id)->toBe($tenant->id);
    expect($device->salesman_id)->toBe($salesman->id);
    expect($device->user_id)->toBe($salesmanUser->id);
});

it('rejects registration without the installation UUID header', function (): void {
    $tenant = makeTenant();
    $salesmanUser = makeSalesmanUser($tenant);

    Sanctum::actingAs($salesmanUser);

    $this->postJson('/api/v1/devices/register', [
        'device_uuid' => 'no-install',
        'app_version' => '1.0.0',
    ])->AssertUnprocessable();
});

it('is idempotent for repeated registrations of the same installation', function (): void {
    $tenant = makeTenant();
    $salesmanUser = makeSalesmanUser($tenant);
    $installationId = 'install-stable';
    $deviceUuid = 'device-stable';

    Sanctum::actingAs($salesmanUser);

    $first = $this->postJson('/api/v1/devices/register', [
        'device_uuid' => $deviceUuid,
        'device_model' => 'Model A',
        'push_token' => 'token-a',
        'app_version' => '1.0.0',
    ], ['X-Installation-UUID' => $installationId])->assertCreated();

    expect(Device::count())->toBe(1);

    $this->postJson('/api/v1/devices/register', [
        'device_uuid' => $deviceUuid,
        'device_model' => 'Model B',
        'push_token' => 'token-b',
        'app_version' => '1.0.1',
    ], ['X-Installation-UUID' => $installationId])->assertCreated();

    expect(Device::count())->toBe(1);
    expect(Device::first()->device_model)->toBe('Model B');
    expect(Device::first()->push_token)->toBe('token-b');
    expect(Device::first()->app_version)->toBe('1.0.1');
});

it('enforces one device per salesman by default, failing clearly', function (): void {
    $tenant = makeTenant();
    $salesmanUser = makeSalesmanUser($tenant);

    registerDevice($salesmanUser)->assertCreated();

    registerDevice($salesmanUser)->assertStatus(422);
    expect(Device::count())->toBe(1);
});

it('allows multiple devices when the one-device policy is disabled', function (): void {
    $tenant = makeTenant();
    $salesmanUser = makeSalesmanUser($tenant);

    withTenantContext($tenant, fn () => CompanySetting::create([
        'tenant_id' => $tenant->id,
        'key' => 'mobile.one_device_per_salesman',
        'value' => false,
    ]));

    registerDevice($salesmanUser)->assertCreated();
    registerDevice($salesmanUser)->assertCreated();

    expect(Device::count())->toBe(2);
});

it('updates heartbeat state and rejects revoked devices', function (): void {
    $tenant = makeTenant();
    $salesmanUser = makeSalesmanUser($tenant);
    $device = withTenantContext($tenant, fn () => Device::factory()->forTenant($tenant->id)->withUser($salesmanUser)->withSalesman($salesmanUser->salesmanProfile)->create([
        'device_uuid' => 'hb-device',
    ]));

    Sanctum::actingAs($salesmanUser);

    $this->putJson('/api/v1/devices/'.$device->uuid.'/heartbeat', [
        'app_version' => '2.3.0',
        'push_token' => 'hb-token',
    ], [
        'X-Device-UUID' => 'hb-device',
        'X-App-Version' => '2.3.0',
    ])->assertOk()
        ->assertJsonPath('data.last_heartbeat_at', $device->fresh()->last_seen_at->toIso8601String());

    expect($device->fresh()->app_version)->toBe('2.3.0');
    expect($device->fresh()->push_token)->toBe('hb-token');
    expect($device->fresh()->last_seen_at)->not->toBeNull();

    $device->revoke();

    $this->putJson('/api/v1/devices/'.$device->uuid.'/heartbeat', [
        'app_version' => '2.3.0',
    ], [
        'X-Device-UUID' => 'hb-device',
        'X-App-Version' => '2.3.0',
    ])->assertStatus(403);
});

it('revokes a device and deletes its bound sanctum token', function (): void {
    $tenant = makeTenant();
    $salesmanUser = makeSalesmanUser($tenant);
    $owner = makeUser($tenant, 'owner', ['email' => 'owner-token@test.test']);

    Sanctum::actingAs($salesmanUser);

    $response = $this->postJson('/api/v1/devices/register', [
        'device_uuid' => 'revoke-me',
        'app_version' => '1.0.0',
    ], ['X-Installation-UUID' => 'install-revoke'])->assertCreated();

    $deviceUuid = $response->json('data.device.id');
    $device = Device::first();

    expect($salesmanUser->tokens()->where('name', 'device:revoke-me')->exists())->toBeTrue();

    Sanctum::actingAs($owner);

    $this->deleteJson('/api/v1/devices/'.$deviceUuid)->assertOk();

    expect($device->fresh()->isActive())->toBeFalse();
    expect($salesmanUser->tokens()->where('name', 'device:revoke-me')->exists())->toBeFalse();
});

it('lists devices for admins only and isolates them by tenant', function (): void {
    $tenantA = makeTenant();
    $tenantB = makeTenant();
    $salesmanUserA = makeSalesmanUser($tenantA);
    $ownerA = makeUser($tenantA, 'owner', ['email' => 'owner-a@test.test']);
    withTenantContext($tenantA, fn () => Device::factory()->forTenant($tenantA->id)->withUser($salesmanUserA)->create());
    withTenantContext($tenantB, fn () => Device::factory()->forTenant($tenantB->id)->withUser(makeSalesmanUser($tenantB))->create([
        'device_uuid' => 'unwanted-device',
    ]));

    Sanctum::actingAs($salesmanUserA);
    $this->getJson('/api/v1/devices')->assertForbidden();

    Sanctum::actingAs($ownerA);
    $this->getJson('/api/v1/devices')->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertDontSee('unwanted-device');
});

it('enforces the minimum app version with upgrade details', function (): void {
    $tenant = makeTenant();
    $salesmanUser = makeSalesmanUser($tenant);
    $device = withTenantContext($tenant, fn () => Device::factory()->forTenant($tenant->id)->withUser($salesmanUser)->create([
        'device_uuid' => 'old-app-device',
        'app_version' => '0.5.0',
    ]));

    Sanctum::actingAs($salesmanUser);

    $this->putJson('/api/v1/devices/'.$device->uuid.'/heartbeat', [
        'app_version' => '0.5.0',
    ], [
        'X-Device-UUID' => 'old-app-device',
        'X-App-Version' => '0.5.0',
    ])->assertStatus(426)
        ->assertJsonPath('error.details.required_version', '1.0.0');

    expect(Device::first()->isActive())->toBeTrue();
});

it('does not record audit events for heartbeats', function (): void {
    $tenant = makeTenant();
    $salesmanUser = makeSalesmanUser($tenant);
    $device = withTenantContext($tenant, fn () => Device::factory()->forTenant($tenant->id)->withUser($salesmanUser)->create([
        'device_uuid' => 'no-audit-device',
    ]));

    Sanctum::actingAs($salesmanUser);

    $this->putJson('/api/v1/devices/'.$device->uuid.'/heartbeat', [], [
        'X-Device-UUID' => 'no-audit-device',
        'X-App-Version' => '1.0.0',
    ])->assertOk();

    expect(AuditLog::where('event', 'like', 'device.%')->count())->toBe(0);
});
