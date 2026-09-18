<?php

use App\Models\CurrentLocation;
use App\Models\LocationHistory;
use App\Models\LocationSyncBatch;
use App\Support\Tracking\LatestLocationCache;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Support\FailingLatestLocationCache;
use Tests\Support\FakeLatestLocationCache;

beforeEach(function (): void {
    seedRbac();
});

function batch7OpenSession(array $mobile): void
{
    test()->withToken($mobile['token'])->withHeaders($mobile['headers'])
        ->postJson('/api/v1/attendance/start', [
            'latitude' => 34.5553,
            'longitude' => 69.2075,
            'accuracy' => 10.5,
            'offline_uuid' => (string) Str::uuid(),
        ])
        ->assertStatus(201);
}

function batch7GpsPoint(string $clientUuid, string $recordedAt, array $overrides = []): array
{
    return array_merge([
        'client_uuid' => $clientUuid,
        'latitude' => 34.5553,
        'longitude' => 69.2075,
        'accuracy' => 10.5,
        'altitude' => 1800,
        'speed' => 0,
        'heading' => 180,
        'battery_level' => 85,
        'is_charging' => false,
        'network_status' => 'wifi',
        'is_mock_location' => false,
        'provider' => 'gps',
        'recorded_at' => $recordedAt,
        'sequence_number' => 1,
    ], $overrides);
}

function batch7Upload(array $mobile, array $locations, ?string $batchUuid = null): TestResponse
{
    return test()->withToken($mobile['token'])->withHeaders($mobile['headers'])
        ->postJson('/api/v1/gps/locations', [
            'batch_uuid' => $batchUuid ?? (string) Str::uuid(),
            'locations' => $locations,
        ]);
}

it('accepts a valid batch and stores history, current location, batch, and cache entry', function (): void {
    $cache = new FakeLatestLocationCache;
    app()->instance(LatestLocationCache::class, $cache);

    $tenant = makeTenant(['timezone' => 'Asia/Kabul']);
    $mobile = makeMobileSalesman($tenant);
    $this->travelTo(CarbonImmutable::parse('2026-01-15 12:00:00', 'UTC'));
    batch7OpenSession($mobile);

    $response = batch7Upload($mobile, [
        batch7GpsPoint((string) Str::uuid(), '2026-01-15T09:00:00Z', ['latitude' => 34.5001]),
        batch7GpsPoint((string) Str::uuid(), '2026-01-15T09:02:00Z', ['latitude' => 34.5002]),
    ])->assertStatus(201)
        ->assertJsonPath('data.accepted', 2)
        ->assertJsonPath('data.rejected', 0)
        ->assertJsonPath('data.duplicates', 0);

    $batchId = $response->json('data.batch_id');

    withTenantContext($tenant, function () use ($mobile, $batchId): void {
        expect(LocationHistory::query()->count())->toBe(2);

        $current = CurrentLocation::query()->firstOrFail();

        expect($current->user_id)->toBe($mobile['user']->id)
            ->and($current->salesman_id)->toBe($mobile['salesman']->id)
            ->and($current->device_id)->toBe($mobile['device']->id)
            ->and($current->recorded_at->toIso8601String())->toBe('2026-01-15T09:02:00+00:00');

        expect(LocationSyncBatch::query()
            ->whereKey($batchId)
            ->whereNotNull('processed_at')
            ->exists())->toBeTrue();
    });

    expect($cache->keys)->toBe(["fs:{$tenant->id}:locations:latest"]);
    expect($cache->writes)->toHaveCount(1);
    expect($cache->writes[0]['payload']['recorded_at'])->toBe('2026-01-15T09:02:00+00:00');

    $this->travelBack();
});

it('rejects batches larger than one hundred points at the request level', function (): void {
    $tenant = makeTenant(['timezone' => 'Asia/Kabul']);
    $mobile = makeMobileSalesman($tenant);
    $this->travelTo(CarbonImmutable::parse('2026-01-15 12:00:00', 'UTC'));
    batch7OpenSession($mobile);

    $locations = collect(range(1, 101))
        ->map(fn () => batch7GpsPoint((string) Str::uuid(), '2026-01-15T09:00:00Z'))
        ->all();

    batch7Upload($mobile, $locations)
        ->assertStatus(422)
        ->assertJsonPath('success', false);

    withTenantContext($tenant, function (): void {
        expect(LocationHistory::query()->count())->toBe(0);
    });

    $this->travelBack();
});

it('rejects invalid points individually while accepting valid ones', function (): void {
    $tenant = makeTenant(['timezone' => 'Asia/Kabul']);
    $mobile = makeMobileSalesman($tenant);
    $this->travelTo(CarbonImmutable::parse('2026-01-15 12:00:00', 'UTC'));
    batch7OpenSession($mobile);

    $response = batch7Upload($mobile, [
        batch7GpsPoint((string) Str::uuid(), '2026-01-15T09:00:00Z'),
        batch7GpsPoint((string) Str::uuid(), '2026-01-15T09:01:00Z', ['latitude' => 95]),
        batch7GpsPoint((string) Str::uuid(), '2026-01-15T09:02:00Z', ['latitude' => 0, 'longitude' => 0]),
        batch7GpsPoint((string) Str::uuid(), '2026-01-15T09:03:00Z', ['accuracy' => 250]),
        batch7GpsPoint((string) Str::uuid(), '2026-01-15T12:10:00Z'),
        batch7GpsPoint((string) Str::uuid(), '2026-01-15T09:05:00Z', ['speed' => 60]),
    ])->assertStatus(201)
        ->assertJsonPath('data.accepted', 1)
        ->assertJsonPath('data.rejected', 5)
        ->assertJsonPath('data.duplicates', 0);

    $reasons = collect($response->json('data.rejected_details'))->pluck('reason')->all();

    expect($reasons)->toBe([
        'invalid_latitude',
        'empty_coordinates',
        'low_accuracy',
        'future_recorded_at',
        'impossible_speed',
    ]);

    withTenantContext($tenant, function (): void {
        expect(LocationHistory::query()->count())->toBe(1);
    });

    $this->travelBack();
});

it('counts duplicate client uuids and is safe on batch retry', function (): void {
    $tenant = makeTenant(['timezone' => 'Asia/Kabul']);
    $mobile = makeMobileSalesman($tenant);
    $this->travelTo(CarbonImmutable::parse('2026-01-15 12:00:00', 'UTC'));
    batch7OpenSession($mobile);

    $batchUuid = (string) Str::uuid();
    $points = [
        batch7GpsPoint((string) Str::uuid(), '2026-01-15T09:00:00Z'),
        batch7GpsPoint((string) Str::uuid(), '2026-01-15T09:02:00Z'),
    ];

    batch7Upload($mobile, $points, $batchUuid)
        ->assertStatus(201)
        ->assertJsonPath('data.accepted', 2);

    $retry = batch7Upload($mobile, $points, $batchUuid)
        ->assertStatus(201)
        ->assertJsonPath('data.accepted', 0)
        ->assertJsonPath('data.duplicates', 2);

    withTenantContext($tenant, function () use ($retry): void {
        expect(LocationHistory::query()->count())->toBe(2);
        expect(LocationSyncBatch::query()->count())->toBe(1);
        expect(LocationSyncBatch::query()->value('id'))->toBe($retry->json('data.batch_id'));
    });

    $this->travelBack();
});

it('preserves mock location as a signal instead of rejecting it', function (): void {
    $tenant = makeTenant(['timezone' => 'Asia/Kabul']);
    $mobile = makeMobileSalesman($tenant);
    $this->travelTo(CarbonImmutable::parse('2026-01-15 12:00:00', 'UTC'));
    batch7OpenSession($mobile);

    batch7Upload($mobile, [
        batch7GpsPoint((string) Str::uuid(), '2026-01-15T09:00:00Z', ['is_mock_location' => true]),
    ])->assertStatus(201)
        ->assertJsonPath('data.accepted', 1)
        ->assertJsonPath('data.rejected', 0);

    withTenantContext($tenant, function (): void {
        $point = LocationHistory::query()->firstOrFail();

        expect($point->is_mock_location)->toBeTrue();

        $current = CurrentLocation::query()->firstOrFail();
        expect($current->is_mock_location)->toBeTrue();
    });

    $this->travelBack();
});

it('never regresses the current location with an older offline point', function (): void {
    $cache = new FakeLatestLocationCache;
    app()->instance(LatestLocationCache::class, $cache);

    $tenant = makeTenant(['timezone' => 'Asia/Kabul']);
    $mobile = makeMobileSalesman($tenant);
    $this->travelTo(CarbonImmutable::parse('2026-01-15 12:00:00', 'UTC'));
    batch7OpenSession($mobile);

    batch7Upload($mobile, [
        batch7GpsPoint((string) Str::uuid(), '2026-01-15T09:00:00Z', ['latitude' => 34.6]),
    ])->assertStatus(201);

    batch7Upload($mobile, [
        batch7GpsPoint((string) Str::uuid(), '2026-01-15T07:00:00Z', ['latitude' => 34.7]),
    ])->assertStatus(201)
        ->assertJsonPath('data.accepted', 1);

    withTenantContext($tenant, function (): void {
        expect(LocationHistory::query()->count())->toBe(2);

        $current = CurrentLocation::query()->firstOrFail();

        expect($current->recorded_at->toIso8601String())->toBe('2026-01-15T09:00:00+00:00')
            ->and((float) $current->latitude)->toBe(34.6);
    });

    expect($cache->writes)->toHaveCount(1);
    expect($cache->writes[0]['payload']['recorded_at'])->toBe('2026-01-15T09:00:00+00:00');

    $this->travelBack();
});

it('chooses the newest point of a batch regardless of array order', function (): void {
    $tenant = makeTenant(['timezone' => 'Asia/Kabul']);
    $mobile = makeMobileSalesman($tenant);
    $this->travelTo(CarbonImmutable::parse('2026-01-15 12:00:00', 'UTC'));
    batch7OpenSession($mobile);

    batch7Upload($mobile, [
        batch7GpsPoint((string) Str::uuid(), '2026-01-15T09:00:00Z', ['latitude' => 34.6]),
        batch7GpsPoint((string) Str::uuid(), '2026-01-15T07:00:00Z', ['latitude' => 34.7]),
        batch7GpsPoint((string) Str::uuid(), '2026-01-15T08:00:00Z', ['latitude' => 34.8]),
    ])->assertStatus(201)
        ->assertJsonPath('data.accepted', 3);

    withTenantContext($tenant, function (): void {
        $current = CurrentLocation::query()->firstOrFail();

        expect($current->recorded_at->toIso8601String())->toBe('2026-01-15T09:00:00+00:00')
            ->and((float) $current->latitude)->toBe(34.6);
    });

    $this->travelBack();
});

it('keeps accepted gps data when the redis cache fails', function (): void {
    app()->instance(LatestLocationCache::class, new FailingLatestLocationCache);

    $tenant = makeTenant(['timezone' => 'Asia/Kabul']);
    $mobile = makeMobileSalesman($tenant);
    $this->travelTo(CarbonImmutable::parse('2026-01-15 12:00:00', 'UTC'));
    batch7OpenSession($mobile);

    batch7Upload($mobile, [
        batch7GpsPoint((string) Str::uuid(), '2026-01-15T09:00:00Z'),
    ])->assertStatus(201)
        ->assertJsonPath('data.accepted', 1);

    withTenantContext($tenant, function (): void {
        expect(LocationHistory::query()->count())->toBe(1);
        expect(CurrentLocation::query()->count())->toBe(1);
    });

    $this->travelBack();
});

it('derives tenant, user, salesman, and device from the authenticated device', function (): void {
    $tenant = makeTenant(['timezone' => 'Asia/Kabul']);
    $mobile = makeMobileSalesman($tenant);
    $this->travelTo(CarbonImmutable::parse('2026-01-15 12:00:00', 'UTC'));
    batch7OpenSession($mobile);

    batch7Upload($mobile, [
        batch7GpsPoint((string) Str::uuid(), '2026-01-15T09:00:00Z', [
            'user_id' => 9999,
            'salesman_id' => 9999,
            'tenant_id' => 9999,
            'device_id' => 9999,
        ]),
    ])->assertStatus(201);

    withTenantContext($tenant, function () use ($mobile): void {
        $point = LocationHistory::query()->firstOrFail();

        expect($point->user_id)->toBe($mobile['user']->id)
            ->and($point->salesman_id)->toBe($mobile['salesman']->id)
            ->and($point->device_id)->toBe($mobile['device']->id)
            ->and($point->tenant_id)->toBe($mobile['user']->tenant_id);
    });

    $this->travelBack();
});

it('rejects gps points when no work session exists for the local date', function (): void {
    $tenant = makeTenant(['timezone' => 'Asia/Kabul']);
    $mobile = makeMobileSalesman($tenant);

    $this->travelTo(CarbonImmutable::parse('2026-01-15 12:00:00', 'UTC'));

    batch7Upload($mobile, [
        batch7GpsPoint((string) Str::uuid(), '2026-01-15T09:00:00Z'),
    ])->assertStatus(201)
        ->assertJsonPath('data.accepted', 0)
        ->assertJsonPath('data.rejected', 1)
        ->assertJsonPath('data.rejected_details.0.reason', 'no_work_session');

    withTenantContext($tenant, function (): void {
        expect(LocationHistory::query()->count())->toBe(0);
    });

    $this->travelBack();
});

it('requires an active device for gps uploads', function (): void {
    $tenant = makeTenant(['timezone' => 'Asia/Kabul']);
    $mobile = makeMobileSalesman($tenant);

    // No device header and no default headers yet: rejected before any session logic.
    $this->withToken($mobile['token'])
        ->postJson('/api/v1/gps/locations', [
            'batch_uuid' => (string) Str::uuid(),
            'locations' => [batch7GpsPoint((string) Str::uuid(), now('UTC')->toIso8601String())],
        ])
        ->assertStatus(403)
        ->assertJsonPath('error.message', 'Device identification is required.');

    batch7OpenSession($mobile);

    withTenantContext($tenant, fn () => $mobile['device']->fresh()->revoke());

    batch7Upload($mobile, [batch7GpsPoint((string) Str::uuid(), now('UTC')->toIso8601String())])
        ->assertStatus(403)
        ->assertJsonPath('error.message', 'This device has been revoked. Please reinstall the app.');
});

it('returns own current location and own history with summary', function (): void {
    $tenant = makeTenant(['timezone' => 'Asia/Kabul']);
    $mobile = makeMobileSalesman($tenant);
    $this->travelTo(CarbonImmutable::parse('2026-01-15 12:00:00', 'UTC'));
    batch7OpenSession($mobile);

    $this->withToken($mobile['token'])->withHeaders($mobile['headers'])
        ->getJson('/api/v1/gps/current')
        ->assertOk()
        ->assertJsonPath('data', null);

    batch7Upload($mobile, [
        batch7GpsPoint((string) Str::uuid(), '2026-01-15T09:00:00Z', ['latitude' => 34.5, 'longitude' => 69.2]),
        batch7GpsPoint((string) Str::uuid(), '2026-01-15T09:10:00Z', ['latitude' => 34.51, 'longitude' => 69.21]),
    ])->assertStatus(201);

    $this->withToken($mobile['token'])->withHeaders($mobile['headers'])
        ->getJson('/api/v1/gps/current')
        ->assertOk()
        ->assertJsonPath('data.recorded_at', '2026-01-15T09:10:00+00:00')
        ->assertJsonPath('data.battery_level', 85);

    $history = $this->withToken($mobile['token'])->withHeaders($mobile['headers'])
        ->getJson('/api/v1/gps/history?date=2026-01-15')
        ->assertOk()
        ->assertJsonPath('data.summary.total_points', 2)
        ->assertJsonPath('meta.total', 2);

    expect($history->json('data.locations'))->toHaveCount(2);
    expect($history->json('data.summary.distance_km'))->toBeGreaterThan(0);

    $this->withToken($mobile['token'])->withHeaders($mobile['headers'])
        ->getJson('/api/v1/gps/history?date=2026-01-15&user_id='.$mobile['user']->id)
        ->assertOk();

    $this->travelBack();
});

it('keeps gps data tenant isolated', function (): void {
    $tenantA = makeTenant(['timezone' => 'Asia/Kabul']);
    $tenantB = makeTenant(['timezone' => 'Asia/Kabul']);

    $mobileA = makeMobileSalesman($tenantA);
    $mobileB = makeMobileSalesman($tenantB);

    $this->travelTo(CarbonImmutable::parse('2026-01-15 12:00:00', 'UTC'));

    batch7OpenSession($mobileA);

    batch7Upload($mobileA, [
        batch7GpsPoint((string) Str::uuid(), '2026-01-15T09:00:00Z'),
    ])->assertStatus(201);

    $this->withToken($mobileB['token'])->withHeaders($mobileB['headers'])
        ->getJson('/api/v1/gps/current')
        ->assertOk()
        ->assertJsonPath('data', null);

    $this->withToken($mobileB['token'])->withHeaders($mobileB['headers'])
        ->getJson('/api/v1/gps/history?date=2026-01-15')
        ->assertOk()
        ->assertJsonPath('data.summary.total_points', 0)
        ->assertJsonPath('meta.total', 0);

    $this->withToken($mobileB['token'])->withHeaders($mobileB['headers'])
        ->getJson('/api/v1/gps/history?date=2026-01-15&user_id='.$mobileA['user']->id)
        ->assertStatus(403);

    withTenantContext($tenantB, function (): void {
        expect(LocationHistory::query()->count())->toBe(0);
        expect(CurrentLocation::query()->count())->toBe(0);
    });

    $this->travelBack();
});

it('returns per-point uuid verdicts for a valid upload', function (): void {
    $tenant = makeTenant(['timezone' => 'Asia/Kabul']);
    $mobile = makeMobileSalesman($tenant);

    $this->travelTo(CarbonImmutable::parse('2026-01-15 12:00:00', 'UTC'));
    batch7OpenSession($mobile);

    $uuid = (string) Str::uuid();

    $response = batch7Upload($mobile, [
        batch7GpsPoint($uuid, '2026-01-15T09:00:00Z'),
    ])->assertStatus(201)
        ->assertJsonPath('data.accepted', 1)
        ->assertJsonPath('data.rejected', 0)
        ->assertJsonPath('data.duplicates', 0)
        ->assertJsonPath('data.accepted_uuids', [$uuid])
        ->assertJsonPath('data.duplicate_uuids', [])
        ->assertJsonPath('data.rejected_uuids', []);

    expect($response->json('data.accepted') + $response->json('data.duplicates') + $response->json('data.rejected'))->toBe(1);

    $this->travelBack();
});

it('returns uuid verdicts and invariant counts for a mixed batch', function (): void {
    $tenant = makeTenant(['timezone' => 'Asia/Kabul']);
    $mobile = makeMobileSalesman($tenant);

    $this->travelTo(CarbonImmutable::parse('2026-01-15 12:00:00', 'UTC'));
    batch7OpenSession($mobile);

    $duplicate = (string) Str::uuid();

    batch7Upload($mobile, [batch7GpsPoint($duplicate, '2026-01-15T08:00:00Z')])
        ->assertStatus(201)
        ->assertJsonPath('data.accepted', 1);

    $newOne = (string) Str::uuid();
    $newTwo = (string) Str::uuid();
    $invalid = (string) Str::uuid();

    $response = batch7Upload($mobile, [
        batch7GpsPoint($newOne, '2026-01-15T09:00:00Z'),
        batch7GpsPoint($newTwo, '2026-01-15T09:01:00Z'),
        batch7GpsPoint($duplicate, '2026-01-15T07:00:00Z'),
        batch7GpsPoint($invalid, '2026-01-15T09:02:00Z', ['latitude' => 95]),
    ])->assertStatus(201)
        ->assertJsonPath('data.accepted', 2)
        ->assertJsonPath('data.duplicates', 1)
        ->assertJsonPath('data.rejected', 1)
        ->assertJsonPath('data.accepted_uuids', [$newOne, $newTwo])
        ->assertJsonPath('data.duplicate_uuids', [$duplicate])
        ->assertJsonPath('data.rejected_uuids', [$invalid])
        ->assertJsonPath('data.rejected_details.0.client_uuid', $invalid)
        ->assertJsonPath('data.rejected_details.0.code', 'invalid_latitude')
        ->assertJsonPath('data.rejected_details.0.reason', 'invalid_latitude');

    expect($response->json('data.accepted') + $response->json('data.duplicates') + $response->json('data.rejected'))->toBe(4);

    withTenantContext($tenant, function (): void {
        expect(LocationHistory::query()->count())->toBe(3);
    });

    $this->travelBack();
});
