<?php

use Illuminate\Support\Str;

beforeEach(function (): void {
    seedRbac();
});

function batch7DeviceStartPayload(): array
{
    return [
        'latitude' => 34.5553,
        'longitude' => 69.2075,
        'accuracy' => 10.5,
        'offline_uuid' => (string) Str::uuid(),
    ];
}

it('returns DEVICE_REVOKED for a revoked device', function (): void {
    $tenant = makeTenant(['timezone' => 'UTC']);
    $mobile = makeMobileSalesman($tenant);

    withTenantContext($tenant, fn () => $mobile['device']->fresh()->revoke());

    $this->withToken($mobile['token'])->withHeaders($mobile['headers'])
        ->postJson('/api/v1/attendance/start', batch7DeviceStartPayload())
        ->assertStatus(403)
        ->assertJsonPath('success', false)
        ->assertJsonPath('error.code', 'DEVICE_REVOKED')
        ->assertJsonPath('error.message', 'This device has been revoked. Please reinstall the app.');
});

it('returns DEVICE_REQUIRED when the device header is missing', function (): void {
    $tenant = makeTenant(['timezone' => 'UTC']);
    $mobile = makeMobileSalesman($tenant);

    $this->withToken($mobile['token'])
        ->getJson('/api/v1/settings/attendance-tracking')
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'DEVICE_REQUIRED')
        ->assertJsonPath('error.message', 'Device identification is required.');
});

it('returns DEVICE_NOT_FOUND for an unknown device uuid', function (): void {
    $tenant = makeTenant(['timezone' => 'UTC']);
    $mobile = makeMobileSalesman($tenant);

    $this->withToken($mobile['token'])
        ->withHeaders(array_merge($mobile['headers'], ['X-Device-UUID' => 'unknown-device']))
        ->postJson('/api/v1/attendance/start', batch7DeviceStartPayload())
        ->assertStatus(404)
        ->assertJsonPath('error.code', 'DEVICE_NOT_FOUND')
        ->assertJsonPath('error.message', 'Device not found.');
});

it('returns DEVICE_REVOKED on login for a revoked device', function (): void {
    $tenant = makeTenant(['timezone' => 'UTC']);
    $mobile = makeMobileSalesman($tenant);

    withTenantContext($tenant, fn () => $mobile['device']->fresh()->revoke());

    $this->postJson('/api/v1/auth/login', [
        'email' => $mobile['user']->email,
        'password' => 'Password123!',
        'device_uuid' => $mobile['headers']['X-Device-UUID'],
    ], [
        'X-Installation-UUID' => $mobile['headers']['X-Installation-UUID'],
        'X-App-Version' => '1.0.0',
    ])
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'DEVICE_REVOKED')
        ->assertJsonPath('error.message', 'This device has been revoked. Reinstall the app or contact support.');
});

it('envelopes api errors even without a JSON accept header', function (): void {
    $tenant = makeTenant(['timezone' => 'UTC']);
    $mobile = makeMobileSalesman($tenant);

    $response = $this->withToken($mobile['token'])
        ->withHeaders(['Accept' => 'text/html'])
        ->post('/api/v1/attendance/start', batch7DeviceStartPayload());

    $response->assertStatus(403);

    expect($response->json('success'))->toBeFalse()
        ->and($response->json('error.code'))->toBe('DEVICE_REQUIRED')
        ->and($response->json('error.message'))->toBe('Device identification is required.');
});

it('leaves valid devices unaffected', function (): void {
    $tenant = makeTenant(['timezone' => 'UTC']);
    $mobile = makeMobileSalesman($tenant);

    $this->withToken($mobile['token'])->withHeaders($mobile['headers'])
        ->postJson('/api/v1/attendance/start', batch7DeviceStartPayload())
        ->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'active');
});
