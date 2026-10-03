<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeviceHealthTest extends TestCase
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

    public function test_device_health_heartbeat_updates_bound_device_and_classifies_critical_state(): void
    {
        [$tenant, $user, $device, $token] = $this->mobileFixture('critical-health');

        $response = $this->withToken($token)
            ->postJson('/api/v1/device/health', [
                'battery_level' => 4,
                'is_charging' => false,
                'power_save_mode' => true,
                'battery_optimization_exempt' => false,
                'background_restricted' => true,
                'location_services_enabled' => false,
                'location_permission' => 'denied',
                'background_location_permission' => 'denied',
                'notification_permission' => 'denied',
                'background_tracking_active' => false,
                'workday_active' => true,
                'network_type' => 'cellular',
                'storage_free_mb' => 40,
                'storage_total_mb' => 64000,
                'pending_sync_count' => 140,
                'failed_sync_count' => 3,
                'blocked_sync_count' => 1,
                'last_sync_at' => now()->subHour()->toISOString(),
                'is_physical_device' => true,
                'root_signal_detected' => false,
                'mock_location_detected' => true,
                'last_gps_fix_at' => now()->subMinutes(20)->toISOString(),
            ], $this->deviceHeaders($device))
            ->assertOk()
            ->assertJsonPath('data.health_status', 'critical')
            ->assertJsonPath('data.battery.level', 4)
            ->assertJsonPath('data.location.services_enabled', false)
            ->assertJsonPath('data.sync.blocked', 1);

        $codes = collect($response->json('data.health_issues'))->pluck('code');

        $this->assertTrue($codes->contains('battery_critical'));
        $this->assertTrue($codes->contains('location_services_disabled'));
        $this->assertTrue($codes->contains('background_tracking_inactive'));
        $this->assertTrue($codes->contains('sync_blocked'));
        $this->assertTrue($codes->contains('mock_location_signal'));

        $fresh = $this->tenantScope($tenant, fn () => $device->fresh());

        $this->assertSame('critical', $fresh->health_status);
        $this->assertSame(4, $fresh->battery_level);
        $this->assertTrue($fresh->workday_active);
        $this->assertNotNull($fresh->health_reported_at);
        $this->assertSame($user->id, $fresh->user_id);
    }

    public function test_healthy_device_can_read_back_health_and_admin_console_renders_it(): void
    {
        [$tenant, , $device, $token] = $this->mobileFixture('healthy-device');

        $this->withToken($token)
            ->postJson('/api/v1/device/health', [
                'battery_level' => 83,
                'is_charging' => false,
                'power_save_mode' => false,
                'battery_optimization_exempt' => true,
                'background_restricted' => false,
                'location_services_enabled' => true,
                'location_permission' => 'always',
                'background_location_permission' => 'granted',
                'notification_permission' => 'granted',
                'background_tracking_active' => false,
                'workday_active' => false,
                'network_type' => 'wifi',
                'storage_free_mb' => 12000,
                'storage_total_mb' => 64000,
                'pending_sync_count' => 2,
                'failed_sync_count' => 0,
                'blocked_sync_count' => 0,
                'last_sync_at' => now()->subMinute()->toISOString(),
                'is_physical_device' => true,
                'root_signal_detected' => false,
                'mock_location_detected' => false,
                'last_gps_fix_at' => now()->subMinute()->toISOString(),
            ], $this->deviceHeaders($device))
            ->assertOk()
            ->assertJsonPath('data.health_status', 'healthy');

        $this->withToken($token)
            ->getJson('/api/v1/device/health', $this->deviceHeaders($device))
            ->assertOk()
            ->assertJsonPath('data.health_status', 'healthy')
            ->assertJsonPath('data.network_type', 'wifi')
            ->assertJsonPath('data.sync.pending', 2);

        auth('sanctum')->forgetUser();
        app('auth')->forgetGuards();

        $admin = $this->tenantScope(
            $tenant,
            fn () => $this->userWithRole(
                $tenant,
                'admin@healthy-device.test',
                ['sales-team:view'],
                'company_admin',
            )
        );

        $this->actingAs($admin, 'web')
            ->get(route('admin.devices.show', $device))
            ->assertOk()
            ->assertSee('Health summary')
            ->assertSee('Healthy')
            ->assertSeeText('Battery & Android background')
            ->assertSeeText('Location & tracking')
            ->assertSeeText('Sync & storage');
    }

    public function test_health_status_becomes_stale_when_heartbeat_is_old(): void
    {
        [$tenant, , $device] = $this->mobileFixture('stale-health');

        $this->tenantScope($tenant, function () use ($device): void {
            $device->forceFill([
                'health_status' => 'healthy',
                'health_reported_at' => now()->subMinutes(20),
            ])->save();

            $this->assertSame('stale', $device->fresh()->effectiveHealthStatus());
        });
    }

    private function mobileFixture(string $slug): array
    {
        $tenant = $this->platform(fn () => Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => str($slug)->replace('-', ' ')->title(),
            'slug' => $slug,
            'timezone' => 'Asia/Kabul',
            'subscription_status' => 'active',
        ]));

        return $this->tenantScope($tenant, function () use ($tenant): array {
            $user = User::create([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'name' => 'Mobile User',
                'email' => 'mobile-'.$tenant->slug.'@example.test',
                'password' => Hash::make('password'),
                'role' => 'salesman',
                'is_active' => true,
            ]);

            $device = Device::create([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'salesman_id' => null,
                'device_uuid' => 'device-'.$tenant->slug,
                'installation_uuid' => 'install-'.$tenant->slug,
                'device_model' => 'Pixel Test',
                'platform' => 'android',
                'os_version' => '16',
                'app_version' => '1.0.2',
                'is_active' => true,
                'registered_at' => now(),
                'last_seen_at' => now(),
            ]);

            $token = $user->createToken('mobile-'.$device->uuid)->plainTextToken;

            return [$tenant, $user, $device, $token];
        });
    }

    private function deviceHeaders(Device $device): array
    {
        return [
            'X-Device-UUID' => $device->device_uuid,
            'X-Installation-UUID' => $device->installation_uuid,
            'X-App-Version' => '1.0.2',
            'X-Platform' => 'android',
            'X-OS-Version' => '16',
        ];
    }

    private function permission(string $slug): Permission
    {
        return Permission::firstOrCreate(
            ['slug' => $slug],
            [
                'name' => str($slug)->replace(':', ' ')->title(),
                'group' => str($slug)->before(':'),
            ]
        );
    }

    private function role(
        Tenant $tenant,
        string $slug,
        array $permissions,
    ): Role {
        $role = Role::firstOrCreate(
            ['tenant_id' => $tenant->id, 'slug' => $slug],
            [
                'name' => str($slug)->replace('_', ' ')->title(),
                'is_system' => true,
            ]
        );

        $role->permissions()->sync(
            collect($permissions)
                ->map(fn (string $permission) => $this->permission($permission)->id)
                ->all()
        );

        return $role;
    }

    private function userWithRole(
        Tenant $tenant,
        string $email,
        array $permissions,
        string $roleSlug,
    ): User {
        $user = User::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'name' => 'Device Admin',
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => $roleSlug,
            'is_active' => true,
        ]);

        $user->syncPrimaryRole($this->role($tenant, $roleSlug, $permissions));

        return $user;
    }

    private function platform(callable $callback): mixed
    {
        return $this->context->withPlatformScope(fn () => $callback());
    }

    private function tenantScope(Tenant $tenant, callable $callback): mixed
    {
        return $this->context->withTenant($tenant, fn () => $callback());
    }
}
