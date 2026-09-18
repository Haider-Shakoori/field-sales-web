<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Salesman;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkSession;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class FieldSalesApiTest extends TestCase
{
    use RefreshDatabase;

    private ?string $deviceToken = null;

    private function actor(): array
    {
        $tenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'T',
            'slug' => 't',
            'timezone' => 'Asia/Kabul',
        ]);

        [$user, $salesman, $device] = app(TenantContext::class)->withPlatformScope(function () use ($tenant): array {
            $user = User::create([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'name' => 'S',
                'email' => 's@x.test',
                'password' => Hash::make('password'),
                'role' => 'salesman',
                'is_active' => true,
            ]);

            $salesman = Salesman::create([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'employee_code' => '1',
                'first_name' => 'S',
                'is_active' => true,
            ]);

            $device = Device::create([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'salesman_id' => $salesman->id,
                'device_uuid' => 'device-1',
                'installation_uuid' => 'install-1',
                'is_active' => true,
            ]);

            return [$user, $salesman, $device];
        });

        $this->deviceToken = app(TenantContext::class)->withTenant(
            $tenant,
            fn () => $user->createToken('mobile-'.$device->uuid)->plainTextToken
        );

        return [
            't' => $tenant,
            'u' => $user,
            's' => $salesman,
            'd' => $device,
        ];
    }

    private function headers(): array
    {
        return [
            'Authorization' => 'Bearer '.$this->deviceToken,
            'X-Device-UUID' => 'device-1',
            'X-Installation-UUID' => 'install-1',
            'X-App-Version' => '1.0',
            'X-Platform' => 'android',
            'X-OS-Version' => '16',
        ];
    }

    private function inTenant(array $actor, callable $callback): mixed
    {
        return app(TenantContext::class)->withTenant(
            $actor['t'],
            fn () => $callback()
        );
    }

    public function test_start_is_idempotent_and_preserves_offline_start_time(): void
    {
        $this->actor();
        $uuid = (string) Str::uuid();
        $body = [
            'latitude' => 34.55,
            'longitude' => 69.2,
            'accuracy' => 10,
            'offline_uuid' => $uuid,
            'started_at' => '2026-09-18T04:30:00Z',
        ];

        $this->postJson('/api/v1/attendance/start', $body, $this->headers())
            ->assertCreated()
            ->assertJsonPath('data.offline_uuid', $uuid)
            ->assertJsonPath('data.started_at', '2026-09-18T04:30:00.000000Z');

        $this->postJson('/api/v1/attendance/start', $body, $this->headers())
            ->assertOk();

        $this->assertDatabaseCount('work_sessions', 1);
    }

    public function test_second_session_same_local_day_conflicts(): void
    {
        $this->actor();

        $this->postJson('/api/v1/attendance/start', [
            'latitude' => 34.5,
            'longitude' => 69.1,
            'accuracy' => 5,
            'offline_uuid' => (string) Str::uuid(),
            'started_at' => '2026-09-18T05:00:00Z',
        ], $this->headers())->assertCreated();

        $this->postJson('/api/v1/attendance/start', [
            'latitude' => 34.5,
            'longitude' => 69.1,
            'accuracy' => 5,
            'offline_uuid' => (string) Str::uuid(),
            'started_at' => '2026-09-18T06:00:00Z',
        ], $this->headers())
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'SESSION_ALREADY_EXISTS');
    }

    public function test_revoked_device_has_machine_code(): void
    {
        $actor = $this->actor();

        $this->inTenant($actor, fn () => $actor['d']->update([
            'revoked_at' => now(),
            'is_active' => false,
        ]));

        $this->postJson('/api/v1/attendance/start', [
            'latitude' => 34.5,
            'longitude' => 69.1,
            'accuracy' => 5,
            'offline_uuid' => (string) Str::uuid(),
        ], $this->headers())
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'DEVICE_REVOKED');
    }

    public function test_mixed_gps_batch_maps_each_verdict(): void
    {
        $actor = $this->actor();

        $this->createActiveSession($actor);

        $acceptedUuid = (string) Str::uuid();
        $rejectedUuid = (string) Str::uuid();

        $payload = [
            'batch_uuid' => (string) Str::uuid(),
            'locations' => [
                [
                    'client_uuid' => $acceptedUuid,
                    'latitude' => 34.55,
                    'longitude' => 69.2,
                    'accuracy' => 8,
                    'recorded_at' => '2026-09-18T05:00:00Z',
                    'sequence_number' => 1,
                ],
                [
                    'client_uuid' => $rejectedUuid,
                    'latitude' => 34.55,
                    'longitude' => 69.2,
                    'accuracy' => 999,
                    'recorded_at' => '2026-09-18T05:01:00Z',
                    'sequence_number' => 2,
                ],
            ],
        ];

        $this->postJson('/api/v1/gps/locations', $payload, $this->headers())
            ->assertCreated()
            ->assertJsonPath('data.accepted', 1)
            ->assertJsonPath('data.rejected', 1)
            ->assertJsonPath('data.duplicates', 0);

        $payload['batch_uuid'] = (string) Str::uuid();
        $payload['locations'] = [$payload['locations'][0]];

        $this->postJson('/api/v1/gps/locations', $payload, $this->headers())
            ->assertJsonPath('data.duplicates', 1);
    }

    public function test_out_of_order_point_does_not_regress_current_location(): void
    {
        $actor = $this->actor();

        $this->createActiveSession($actor);

        foreach ([
            ['2026-09-18T05:10:00Z', 34.60],
            ['2026-09-18T05:00:00Z', 34.50],
        ] as [$time, $latitude]) {
            $this->postJson('/api/v1/gps/locations', [
                'batch_uuid' => (string) Str::uuid(),
                'locations' => [[
                    'client_uuid' => (string) Str::uuid(),
                    'latitude' => $latitude,
                    'longitude' => 69.2,
                    'accuracy' => 8,
                    'recorded_at' => $time,
                    'sequence_number' => 1,
                ]],
            ], $this->headers())->assertCreated();
        }

        $this->assertDatabaseHas('current_locations', [
            'latitude' => 34.6000000,
        ]);
    }

    public function test_active_overnight_session_accepts_post_midnight_point(): void
    {
        CarbonImmutable::setTestNow('2026-09-19T06:00:00Z');

        try {
            $actor = $this->actor();
            $actor['t']->update(['timezone' => 'Asia/Kabul']);

            $this->createActiveSession($actor, '2026-09-18 16:00:00');

            $this->postJson('/api/v1/gps/locations', [
                'batch_uuid' => (string) Str::uuid(),
                'locations' => [[
                    'client_uuid' => (string) Str::uuid(),
                    'latitude' => 34.5,
                    'longitude' => 69.1,
                    'accuracy' => 8,
                    'recorded_at' => '2026-09-18T20:00:00Z',
                    'sequence_number' => 1,
                ]],
            ], $this->headers())
                ->assertJsonPath('data.accepted', 1);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_privacy_ack_is_idempotent(): void
    {
        $this->actor();

        $body = [
            'policy_version' => '1',
            'acknowledged_at' => '2026-09-18T05:00:00Z',
            'app_version' => '1.0.0',
        ];

        $this->postJson('/api/v1/gps/privacy-acknowledgement', $body, $this->headers())
            ->assertCreated();

        $this->postJson('/api/v1/gps/privacy-acknowledgement', $body, $this->headers())
            ->assertOk();

        $this->assertDatabaseCount('privacy_acknowledgements', 1);
    }

    public function test_settings_defaults_are_safe(): void
    {
        $this->actor();

        $this->getJson('/api/v1/settings/attendance-tracking')
            ->assertOk()
            ->assertJsonPath('data.work_session_start_mode', 'manual')
            ->assertJsonPath('data.gps_tracking_enabled', true)
            ->assertJsonPath('data.timezone', 'Asia/Kabul');
    }

    private function createActiveSession(array $actor, string $start = '2026-09-18 00:00:00'): void
    {
        $this->inTenant($actor, function () use ($actor, $start): void {
            WorkSession::create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $actor['u']->id,
                'salesman_id' => $actor['s']->id,
                'device_id' => $actor['d']->id,
                'date' => '2026-09-18',
                'start_time' => $start,
                'start_latitude' => 34.5,
                'start_longitude' => 69.1,
                'start_accuracy' => 5,
                'status' => 'active',
            ]);
        });
    }
}
