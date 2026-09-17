<?php

use App\Models\Device;
use App\Models\Salesman;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

beforeEach(function (): void {
    seedRbac();
});

function makeApiSalesmanUser(Tenant $tenant, array $attributes = []): User
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

function loginPayload(array $overrides = []): array
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

function postLogin(array $payload, array $headers = [], string $ip = '127.0.0.1'): TestResponse
{
    test()->withServerVariables(['REMOTE_ADDR' => $ip]);

    return test()->postJson('/api/v1/auth/login', $payload, array_merge([
        'X-Installation-UUID' => 'install-'.Str::uuid(),
        'X-App-Version' => '1.0.0',
    ], $headers));
}

it('authenticates a company user and returns a device-bound Sanctum token', function (): void {
    $tenant = makeTenant();
    $user = makeApiSalesmanUser($tenant, ['email' => 'login.ok@test.test']);

    $response = postLogin(loginPayload(['email' => 'login.ok@test.test']))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.user.email', 'login.ok@test.test')
        ->assertJsonPath('data.user.role', 'salesman')
        ->assertJsonPath('data.tenant.slug', $tenant->slug)
        ->assertJsonPath('data.device.status', 'active')
        ->assertJsonMissingPath('data.token.plain_text');

    $token = $response->json('data.token');
    expect($token)->toBeString()->not->toBeEmpty();

    withTenantContext($tenant, function () use ($user): void {
        $device = Device::first();
        expect($device)->not->toBeNull();
        expect($device->user_id)->toBe($user->id);
        expect($device->salesman_id)->toBe($user->salesmanProfile->id);
        expect($user->tokens()->where('name', 'device:'.$device->device_uuid)->exists())->toBeTrue();
        expect($user->fresh()->last_login_at)->not->toBeNull();
    });
});

it('rejects invalid credentials with 401', function (): void {
    $tenant = makeTenant();
    makeApiSalesmanUser($tenant, ['email' => 'login.bad@test.test']);

    postLogin(loginPayload(['email' => 'login.bad@test.test', 'password' => 'wrong']), [], '10.0.0.11')
        ->assertStatus(401)
        ->assertJsonPath('success', false)
        ->assertJsonPath('error.message', 'Invalid credentials.');
});

it('rejects a deactivated account with 403', function (): void {
    $tenant = makeTenant();
    makeApiSalesmanUser($tenant, ['email' => 'login.inactive@test.test', 'is_active' => false]);

    postLogin(loginPayload(['email' => 'login.inactive@test.test']), [], '10.0.0.12')
        ->assertStatus(403);
});

it('requires the installation UUID header', function (): void {
    $tenant = makeTenant();
    makeApiSalesmanUser($tenant, ['email' => 'login.noinstall@test.test']);

    test()->postJson('/api/v1/auth/login', loginPayload([
        'email' => 'login.noinstall@test.test',
    ]))
        ->assertStatus(422)
        ->assertJsonPath('error.details.errors.device.0', 'The X-Installation-UUID header is required.');
});

it('validates required device fields', function (): void {
    $tenant = makeTenant();
    makeApiSalesmanUser($tenant, ['email' => 'login.nouuid@test.test']);

    postLogin(loginPayload([
        'email' => 'login.nouuid@test.test',
        'device_uuid' => '',
    ]), [], '10.0.0.13')
        ->assertStatus(422)
        ->assertJsonPath('error.details.errors.device_uuid.0', 'The device uuid field is required.');
});

it('rotates the previous device token on re-login', function (): void {
    $tenant = makeTenant();
    $user = makeApiSalesmanUser($tenant, ['email' => 'login.rotate@test.test']);
    $deviceUuid = 'device-rotation';
    $installation = 'install-rotation';

    $first = test()->postJson('/api/v1/auth/login', loginPayload([
        'email' => 'login.rotate@test.test',
        'device_uuid' => $deviceUuid,
    ]), ['X-Installation-UUID' => $installation])->assertOk();
    $oldToken = $first->json('data.token');
    $deviceId = $first->json('data.device.id');

    $second = test()->postJson('/api/v1/auth/login', loginPayload([
        'email' => 'login.rotate@test.test',
        'device_uuid' => $deviceUuid,
    ]), ['X-Installation-UUID' => $installation])->assertOk();
    $newToken = $second->json('data.token');

    expect($newToken)->not->toBe($oldToken);
    expect(withTenantContext($tenant, fn () => Device::where('uuid', $deviceId)->first()))->not->toBeNull();

    $this->getJson('/api/v1/me')->assertStatus(401);
    $this->withToken($oldToken)->getJson('/api/v1/me')->assertStatus(401);
    $this->withToken($newToken)->getJson('/api/v1/me')->assertOk()
        ->assertJsonPath('data.user.email', 'login.rotate@test.test');
});

it('blocks login on a revoked device', function (): void {
    $tenant = makeTenant();
    $user = makeApiSalesmanUser($tenant, ['email' => 'login.revoked@test.test']);
    $installation = 'install-revoked';
    $deviceUuid = 'device-revoked';

    test()->postJson('/api/v1/auth/login', loginPayload([
        'email' => 'login.revoked@test.test',
        'device_uuid' => $deviceUuid,
    ]), ['X-Installation-UUID' => $installation])->assertOk();

    withTenantContext($tenant, fn () => Device::first()->revoke());

    test()->postJson('/api/v1/auth/login', loginPayload([
        'email' => 'login.revoked@test.test',
        'device_uuid' => $deviceUuid,
    ]), ['X-Installation-UUID' => $installation])
        ->assertStatus(403)
        ->assertJsonPath('error.message', 'This device has been revoked. Reinstall the app or contact support.');
});

it('enforces the one-device-per-salesman policy on a second device', function (): void {
    $tenant = makeTenant();
    $user = makeApiSalesmanUser($tenant, ['email' => 'login.limit@test.test']);

    test()->postJson('/api/v1/auth/login', loginPayload([
        'email' => 'login.limit@test.test',
        'device_uuid' => 'device-one',
    ]), ['X-Installation-UUID' => 'install-one'])->assertOk();

    test()->postJson('/api/v1/auth/login', loginPayload([
        'email' => 'login.limit@test.test',
        'device_uuid' => 'device-two',
    ]), ['X-Installation-UUID' => 'install-two'])
        ->assertStatus(422)
        ->assertJsonPath('error.message', 'Device limit reached for this salesman. Revoke another device or contact support.');
});

it('logs out and revokes the current token', function (): void {
    $tenant = makeTenant();
    makeApiSalesmanUser($tenant, ['email' => 'login.logout@test.test']);

    $response = postLogin(loginPayload(['email' => 'login.logout@test.test']), [], '10.0.0.17');
    $token = $response->json('data.token');
    expect($token)->toBeString()->not->toBeEmpty();

    $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();

    $this->withToken($token)->getJson('/api/v1/me')->assertStatus(401);
});

it('refreshes the token and invalidates the old one', function (): void {
    $tenant = makeTenant();
    makeApiSalesmanUser($tenant, ['email' => 'login.refresh@test.test']);

    $response = postLogin(loginPayload(['email' => 'login.refresh@test.test']), [], '10.0.0.18');
    $old = $response->json('data.token');
    expect($old)->toBeString()->not->toBeEmpty();

    $refreshResponse = $this->withToken($old)->postJson('/api/v1/auth/refresh')->assertOk();
    $new = $refreshResponse->json('data.token');

    expect($new)->toBeString()->not->toBeEmpty()->not->toBe($old);

    $this->withToken($old)->getJson('/api/v1/me')->assertStatus(401);
    $this->withToken($new)->getJson('/api/v1/me')->assertOk();
});

it('enforces the minimum app version at login', function (): void {
    $tenant = makeTenant();
    makeApiSalesmanUser($tenant, ['email' => 'login.version@test.test']);

    postLogin(loginPayload(['email' => 'login.version@test.test']), [
        'X-App-Version' => '0.5.0',
    ], '10.0.0.19')
        ->assertStatus(426)
        ->assertJsonPath('error.details.required_version', '1.0.0');
});

it('throttles repeated failed login attempts', function (): void {
    $tenant = makeTenant();
    makeApiSalesmanUser($tenant, ['email' => 'login.throttle@test.test']);

    foreach (range(1, 5) as $ignored) {
        postLogin(loginPayload([
            'email' => 'login.throttle@test.test',
            'password' => 'wrong',
        ]), [], '10.0.0.20')->assertStatus(401);
    }

    postLogin(loginPayload([
        'email' => 'login.throttle@test.test',
    ]), [], '10.0.0.20')
        ->assertStatus(429)
        ->assertJsonPath('error.message', 'Too many login attempts. Please try again later.');
});
