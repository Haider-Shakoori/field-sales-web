<?php

namespace Tests\Feature;

use App\Models\CompanySetting;
use App\Models\Device;
use App\Models\Salesman;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class MobileConfigurationSyncTest extends TestCase
{
    use RefreshDatabase;

    private ?string $token = null;

    public function test_mobile_configuration_snapshot_contains_web_policy_and_changes_version(): void
    {
        $actor = $this->actor();

        $first = $this->getJson('/api/v1/settings/sync', $this->headers())
            ->assertOk()
            ->assertJsonPath('data.tracking.gps_tracking_enabled', true)
            ->assertJsonPath('data.features.smart_routes_enabled', true)
            ->assertJsonPath('data.device.secondary_device_enabled', false)
            ->assertJsonStructure([
                'data' => [
                    'configuration_version',
                    'server_time',
                    'tracking',
                    'features',
                    'device',
                ],
            ])
            ->json('data.configuration_version');

        app(TenantContext::class)->withTenant(
            $actor['tenant'],
            fn () => CompanySetting::updateOrCreate(
                [
                    'tenant_id' => $actor['tenant']->id,
                    'key' => 'tracking.gps_moving_interval_seconds',
                ],
                ['value' => '25'],
            ),
        );

        $secondResponse = $this->getJson('/api/v1/settings/sync', $this->headers())
            ->assertOk()
            ->assertJsonPath('data.tracking.gps_moving_interval_seconds', 25);

        $second = $secondResponse->json('data.configuration_version');

        $this->assertNotSame($first, $second);
        $this->assertSame(64, strlen($second));
    }

    private function actor(): array
    {
        $tenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Sync Tenant',
            'slug' => 'sync-tenant',
            'timezone' => 'Asia/Kabul',
        ]);

        [$user, $salesman, $device] = app(TenantContext::class)
            ->withPlatformScope(function () use ($tenant): array {
                $user = User::create([
                    'uuid' => (string) Str::uuid(),
                    'tenant_id' => $tenant->id,
                    'name' => 'Sync Salesman',
                    'email' => 'sync-salesman@example.test',
                    'password' => Hash::make('password'),
                    'role' => 'salesman',
                    'is_active' => true,
                ]);

                $salesman = Salesman::create([
                    'uuid' => (string) Str::uuid(),
                    'tenant_id' => $tenant->id,
                    'user_id' => $user->id,
                    'employee_code' => 'SYNC-1',
                    'first_name' => 'Sync',
                    'last_name' => 'Salesman',
                    'is_active' => true,
                ]);

                $device = Device::create([
                    'uuid' => (string) Str::uuid(),
                    'tenant_id' => $tenant->id,
                    'user_id' => $user->id,
                    'salesman_id' => $salesman->id,
                    'device_uuid' => 'sync-device',
                    'installation_uuid' => 'sync-install',
                    'is_active' => true,
                ]);

                return [$user, $salesman, $device];
            });

        $this->token = app(TenantContext::class)->withTenant(
            $tenant,
            fn () => $user->createToken('mobile-'.$device->uuid)->plainTextToken,
        );

        return compact('tenant', 'user', 'salesman', 'device');
    }

    private function headers(): array
    {
        return [
            'Authorization' => 'Bearer '.$this->token,
            'X-Device-UUID' => 'sync-device',
            'X-Installation-UUID' => 'sync-install',
            'X-App-Version' => '1.0',
            'X-Platform' => 'ios',
            'X-OS-Version' => '26',
            'Accept' => 'application/json',
        ];
    }
}
