<?php

use App\Models\PrivacyAcknowledgement;
use Illuminate\Support\Str;

beforeEach(function (): void {
    seedRbac();
});

function batch7PrivacyPayload(array $overrides = []): array
{
    return array_merge([
        'policy_version' => '1',
        'acknowledged_at' => '2026-09-18T08:00:00Z',
        'app_version' => '1.0.0',
    ], $overrides);
}

it('records a privacy acknowledgement with server-derived identity', function (): void {
    $tenant = makeTenant();
    $mobile = makeMobileSalesman($tenant);

    $response = $this->withToken($mobile['token'])->withHeaders($mobile['headers'])
        ->postJson('/api/v1/gps/privacy-acknowledgement', batch7PrivacyPayload([
            'tenant_id' => 9999,
            'user_id' => 9999,
            'device_id' => 9999,
        ]))
        ->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.policy_version', '1')
        ->assertJsonPath('data.acknowledged_at', '2026-09-18T08:00:00+00:00');

    expect($response->json('data.id'))->toBeInt();
    expect($response->json('data.recorded_at'))->not->toBeNull();

    withTenantContext($tenant, function () use ($tenant, $mobile): void {
        $acknowledgement = PrivacyAcknowledgement::query()->firstOrFail();

        expect($acknowledgement->tenant_id)->toBe($tenant->id)
            ->and($acknowledgement->user_id)->toBe($mobile['user']->id)
            ->and($acknowledgement->device_id)->toBe($mobile['device']->id)
            ->and($acknowledgement->acknowledged_at->toIso8601String())->toBe('2026-09-18T08:00:00+00:00');
    });
});

it('is idempotent when the same acknowledgement is retried', function (): void {
    $tenant = makeTenant();
    $mobile = makeMobileSalesman($tenant);

    $first = $this->withToken($mobile['token'])->withHeaders($mobile['headers'])
        ->postJson('/api/v1/gps/privacy-acknowledgement', batch7PrivacyPayload())
        ->assertStatus(201);

    $second = $this->withToken($mobile['token'])->withHeaders($mobile['headers'])
        ->postJson('/api/v1/gps/privacy-acknowledgement', batch7PrivacyPayload())
        ->assertStatus(200);

    expect($second->json('data.id'))->toBe($first->json('data.id'));

    withTenantContext($tenant, function (): void {
        expect(PrivacyAcknowledgement::query()->count())->toBe(1);
    });
});

it('creates a separate record for a new policy version', function (): void {
    $tenant = makeTenant();
    $mobile = makeMobileSalesman($tenant);

    $first = $this->withToken($mobile['token'])->withHeaders($mobile['headers'])
        ->postJson('/api/v1/gps/privacy-acknowledgement', batch7PrivacyPayload(['policy_version' => '1']))
        ->assertStatus(201);

    $second = $this->withToken($mobile['token'])->withHeaders($mobile['headers'])
        ->postJson('/api/v1/gps/privacy-acknowledgement', batch7PrivacyPayload(['policy_version' => '2']))
        ->assertStatus(201);

    expect($second->json('data.id'))->not->toBe($first->json('data.id'));

    withTenantContext($tenant, function (): void {
        expect(PrivacyAcknowledgement::query()->count())->toBe(2);
        expect(PrivacyAcknowledgement::query()->pluck('policy_version')->sort()->values()->all())->toBe(['1', '2']);
    });
});

it('validates privacy acknowledgement input', function (): void {
    $tenant = makeTenant();
    $mobile = makeMobileSalesman($tenant);

    $this->withToken($mobile['token'])->withHeaders($mobile['headers'])
        ->postJson('/api/v1/gps/privacy-acknowledgement', batch7PrivacyPayload(['acknowledged_at' => 'not-a-date']))
        ->assertStatus(422)
        ->assertJsonPath('error.details.errors.acknowledged_at.0', 'The acknowledged at field must be a valid date.');

    $this->withToken($mobile['token'])->withHeaders($mobile['headers'])
        ->postJson('/api/v1/gps/privacy-acknowledgement', ['acknowledged_at' => '2026-09-18T08:00:00Z'])
        ->assertStatus(422);

    $this->withToken($mobile['token'])->withHeaders($mobile['headers'])
        ->postJson('/api/v1/gps/privacy-acknowledgement', batch7PrivacyPayload(['policy_version' => 'bad version!']))
        ->assertStatus(422);

    withTenantContext($tenant, function (): void {
        expect(PrivacyAcknowledgement::query()->count())->toBe(0);
    });
});

it('requires a registered active device for privacy acknowledgement', function (): void {
    $tenant = makeTenant();
    $mobile = makeMobileSalesman($tenant);

    $this->withToken($mobile['token'])
        ->postJson('/api/v1/gps/privacy-acknowledgement', batch7PrivacyPayload())
        ->assertStatus(403)
        ->assertJsonPath('error.message', 'Device identification is required.');

    withTenantContext($tenant, fn () => $mobile['device']->fresh()->revoke());

    $this->withToken($mobile['token'])->withHeaders($mobile['headers'])
        ->postJson('/api/v1/gps/privacy-acknowledgement', batch7PrivacyPayload())
        ->assertStatus(403)
        ->assertJsonPath('error.message', 'This device has been revoked. Please reinstall the app.');
});

it('keeps privacy acknowledgements tenant isolated', function (): void {
    $tenantA = makeTenant();
    $tenantB = makeTenant();

    $mobileA = makeMobileSalesman($tenantA);
    $mobileB = makeMobileSalesman($tenantB);

    $this->withToken($mobileA['token'])->withHeaders($mobileA['headers'])
        ->postJson('/api/v1/gps/privacy-acknowledgement', batch7PrivacyPayload())
        ->assertStatus(201);

    withTenantContext($tenantB, function (): void {
        expect(PrivacyAcknowledgement::query()->count())->toBe(0);
    });

    $this->withToken($mobileB['token'])->withHeaders($mobileB['headers'])
        ->postJson('/api/v1/gps/privacy-acknowledgement', batch7PrivacyPayload())
        ->assertStatus(201);

    withTenantContext($tenantA, function (): void {
        expect(PrivacyAcknowledgement::query()->count())->toBe(1);
    });
});

it('never fabricates a privacy acknowledgement', function (): void {
    $tenant = makeTenant();
    $mobile = makeMobileSalesman($tenant);

    withTenantContext($tenant, function (): void {
        expect(PrivacyAcknowledgement::query()->count())->toBe(0);
    });

    $this->withToken($mobile['token'])->withHeaders($mobile['headers'])
        ->postJson('/api/v1/attendance/start', [
            'latitude' => 34.5553,
            'longitude' => 69.2075,
            'accuracy' => 10.5,
            'offline_uuid' => (string) Str::uuid(),
        ])
        ->assertStatus(201);

    withTenantContext($tenant, function (): void {
        expect(PrivacyAcknowledgement::query()->count())->toBe(0);
    });
});
