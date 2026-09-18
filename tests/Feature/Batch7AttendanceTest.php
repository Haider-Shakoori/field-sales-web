<?php

use App\Models\Device;
use App\Models\WorkSession;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

beforeEach(function (): void {
    seedRbac();
});

function batch7StartPayload(array $overrides = []): array
{
    return array_merge([
        'latitude' => 34.5553,
        'longitude' => 69.2075,
        'accuracy' => 10.5,
        'offline_uuid' => (string) Str::uuid(),
    ], $overrides);
}

it('starts a work day and derives identity and the tenant-local date server-side', function (): void {
    $tenant = makeTenant(['timezone' => 'Asia/Kabul']);
    $mobile = makeMobileSalesman($tenant);

    $this->travelTo(CarbonImmutable::parse('2026-01-15 20:30:00', 'UTC'));

    $offlineUuid = (string) Str::uuid();

    $this->withToken($mobile['token'])
        ->withHeaders($mobile['headers'])
        ->postJson('/api/v1/attendance/start', batch7StartPayload(['offline_uuid' => $offlineUuid]))
        ->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.offline_uuid', $offlineUuid)
        ->assertJsonPath('data.date', '2026-01-16')
        ->assertJsonPath('data.ended_at', null)
        ->assertJsonPath('data.start_location.latitude', 34.5553);

    withTenantContext($tenant, function () use ($tenant, $mobile): void {
        $session = WorkSession::query()->firstOrFail();

        expect($session->tenant_id)->toBe($tenant->id)
            ->and($session->user_id)->toBe($mobile['user']->id)
            ->and($session->salesman_id)->toBe($mobile['salesman']->id)
            ->and($session->device_id)->toBe($mobile['device']->id)
            ->and($session->date->toDateString())->toBe('2026-01-16')
            ->and($session->isActive())->toBeTrue();
    });

    $this->travelBack();
});

it('is idempotent when the same offline uuid is retried', function (): void {
    $tenant = makeTenant(['timezone' => 'UTC']);
    $mobile = makeMobileSalesman($tenant);

    $payload = batch7StartPayload();

    $first = $this->withToken($mobile['token'])->withHeaders($mobile['headers'])
        ->postJson('/api/v1/attendance/start', $payload)
        ->assertStatus(201);

    $second = $this->withToken($mobile['token'])->withHeaders($mobile['headers'])
        ->postJson('/api/v1/attendance/start', $payload)
        ->assertStatus(200);

    expect($second->json('data.id'))->toBe($first->json('data.id'));

    withTenantContext($tenant, function (): void {
        expect(WorkSession::query()->count())->toBe(1);
    });
});

it('rejects a second work session for the same tenant-local date', function (): void {
    $tenant = makeTenant(['timezone' => 'UTC']);
    $mobile = makeMobileSalesman($tenant);

    $this->withToken($mobile['token'])->withHeaders($mobile['headers'])
        ->postJson('/api/v1/attendance/start', batch7StartPayload())
        ->assertStatus(201);

    $this->withToken($mobile['token'])->withHeaders($mobile['headers'])
        ->postJson('/api/v1/attendance/start', batch7StartPayload())
        ->assertStatus(409)
        ->assertJsonPath('success', false)
        ->assertJsonPath('error.message', 'A work session already exists for today.');

    withTenantContext($tenant, function (): void {
        expect(WorkSession::query()->count())->toBe(1);
    });
});

it('validates start coordinates and rejects the 0,0 point', function (): void {
    $tenant = makeTenant(['timezone' => 'UTC']);
    $mobile = makeMobileSalesman($tenant);

    $this->withToken($mobile['token'])->withHeaders($mobile['headers'])
        ->postJson('/api/v1/attendance/start', batch7StartPayload(['latitude' => 91]))
        ->assertStatus(422)
        ->assertJsonPath('success', false);

    $this->withToken($mobile['token'])->withHeaders($mobile['headers'])
        ->postJson('/api/v1/attendance/start', batch7StartPayload(['latitude' => 0, 'longitude' => 0]))
        ->assertStatus(422)
        ->assertJsonPath('error.details.errors.latitude.0', 'GPS coordinates cannot be 0,0.');

    withTenantContext($tenant, function (): void {
        expect(WorkSession::query()->count())->toBe(0);
    });
});

it('requires a registered active device to start a day', function (): void {
    $tenant = makeTenant(['timezone' => 'UTC']);
    $mobile = makeMobileSalesman($tenant);

    $this->withToken($mobile['token'])
        ->postJson('/api/v1/attendance/start', batch7StartPayload())
        ->assertStatus(403)
        ->assertJsonPath('error.message', 'Device identification is required.');

    $this->withToken($mobile['token'])
        ->withHeaders(array_merge($mobile['headers'], ['X-Device-UUID' => 'unknown-device']))
        ->postJson('/api/v1/attendance/start', batch7StartPayload())
        ->assertStatus(404);

    withTenantContext($tenant, fn () => Device::query()->firstOrFail()->revoke());

    $this->withToken($mobile['token'])->withHeaders($mobile['headers'])
        ->postJson('/api/v1/attendance/start', batch7StartPayload())
        ->assertStatus(403)
        ->assertJsonPath('error.message', 'This device has been revoked. Please reinstall the app.');
});

it('returns the current day and own-only history', function (): void {
    $tenant = makeTenant(['timezone' => 'UTC']);
    $mobile = makeMobileSalesman($tenant);
    $other = makeMobileSalesman($tenant);

    $first = $this->withToken($mobile['token'])->withHeaders($mobile['headers'])
        ->postJson('/api/v1/attendance/start', batch7StartPayload())
        ->assertStatus(201);

    $this->withToken($other['token'])->withHeaders($other['headers'])
        ->postJson('/api/v1/attendance/start', batch7StartPayload())
        ->assertStatus(201);

    $today = $this->withToken($mobile['token'])->withHeaders($mobile['headers'])
        ->getJson('/api/v1/attendance/today')
        ->assertOk()
        ->assertJsonPath('data.id', $first->json('data.id'))
        ->assertJsonPath('data.status', 'active');

    $history = $this->withToken($mobile['token'])->withHeaders($mobile['headers'])
        ->getJson('/api/v1/attendance/history')
        ->assertOk()
        ->assertJsonPath('meta.total', 1);

    expect($history->json('data'))->toHaveCount(1);
    expect($history->json('data.0.id'))->toBe($first->json('data.id'));
});

it('returns a null today payload when no session exists', function (): void {
    $tenant = makeTenant(['timezone' => 'UTC']);
    $mobile = makeMobileSalesman($tenant);

    $this->withToken($mobile['token'])->withHeaders($mobile['headers'])
        ->getJson('/api/v1/attendance/today')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data', null);
});

it('ends the day, calculates duration server-side, and is retry safe', function (): void {
    $tenant = makeTenant(['timezone' => 'UTC']);
    $mobile = makeMobileSalesman($tenant);

    $this->travelTo(CarbonImmutable::parse('2026-01-15 08:00:00', 'UTC'));

    $this->withToken($mobile['token'])->withHeaders($mobile['headers'])
        ->postJson('/api/v1/attendance/start', batch7StartPayload())
        ->assertStatus(201);

    $this->travelTo(CarbonImmutable::parse('2026-01-15 10:05:00', 'UTC'));

    $ended = $this->withToken($mobile['token'])->withHeaders($mobile['headers'])
        ->postJson('/api/v1/attendance/end', [
            'latitude' => 34.56,
            'longitude' => 69.21,
            'accuracy' => 8.2,
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.duration_minutes', 125)
        ->assertJsonPath('data.duration_hours', 2.08)
        ->assertJsonPath('data.end_location.latitude', 34.56);

    $retry = $this->withToken($mobile['token'])->withHeaders($mobile['headers'])
        ->postJson('/api/v1/attendance/end', [
            'latitude' => 34.57,
            'longitude' => 69.22,
            'accuracy' => 9.0,
        ])
        ->assertOk()
        ->assertJsonPath('data.duration_minutes', 125);

    expect($retry->json('data.id'))->toBe($ended->json('data.id'));

    withTenantContext($tenant, function (): void {
        $session = WorkSession::query()->firstOrFail();

        expect($session->duration_minutes)->toBe(125)
            ->and($session->status)->toBe(WorkSession::STATUS_COMPLETED)
            ->and($session->end_latitude)->not->toBeNull();
    });

    $this->travelBack();
});

it('returns a domain conflict when ending without an active session', function (): void {
    $tenant = makeTenant(['timezone' => 'UTC']);
    $mobile = makeMobileSalesman($tenant);

    $this->withToken($mobile['token'])->withHeaders($mobile['headers'])
        ->postJson('/api/v1/attendance/end', [
            'latitude' => 34.56,
            'longitude' => 69.21,
            'accuracy' => 8.2,
        ])
        ->assertStatus(409)
        ->assertJsonPath('error.message', 'No active work session found.');
});

it('keeps attendance tenant isolated', function (): void {
    $tenantA = makeTenant(['timezone' => 'UTC']);
    $tenantB = makeTenant(['timezone' => 'UTC']);

    $mobileA = makeMobileSalesman($tenantA);
    $mobileB = makeMobileSalesman($tenantB);

    $this->withToken($mobileA['token'])->withHeaders($mobileA['headers'])
        ->postJson('/api/v1/attendance/start', batch7StartPayload())
        ->assertStatus(201);

    $this->withToken($mobileB['token'])->withHeaders($mobileB['headers'])
        ->getJson('/api/v1/attendance/history')
        ->assertOk()
        ->assertJsonPath('meta.total', 0);

    $this->withToken($mobileB['token'])->withHeaders($mobileB['headers'])
        ->getJson('/api/v1/attendance/today')
        ->assertOk()
        ->assertJsonPath('data', null);

    withTenantContext($tenantB, function (): void {
        expect(WorkSession::query()->count())->toBe(0);
    });
});
