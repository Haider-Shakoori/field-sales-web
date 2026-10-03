<?php

namespace Tests\Feature;

use App\Models\CompanySetting;
use App\Models\Device;
use App\Models\DeviceActivityLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Salesman;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DeviceSettingsService;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class OptionalDeviceManagementTest extends TestCase
{
    use RefreshDatabase;

    private TenantContext $context;

    protected function setUp(): void
    {
        parent::setUp();

        $this->context = app(TenantContext::class);
        $this->context->clear();
        config(['push.enabled' => false]);
    }

    protected function tearDown(): void
    {
        $this->context->clear();

        parent::tearDown();
    }

    public function test_optional_device_features_are_off_by_default_and_single_device_behavior_is_preserved(): void
    {
        $tenant = $this->tenant('optional-defaults');
        $settings = $this->tenantScope(
            $tenant,
            fn () => app(DeviceSettingsService::class)->get($tenant),
        );

        $this->assertTrue($settings['device_restriction_enabled']);
        $this->assertFalse($settings['approval_required']);
        $this->assertFalse($settings['secondary_device_enabled']);
        $this->assertSame(1, $settings['max_active_devices']);
        $this->assertFalse($settings['lost_device_workflow_enabled']);
        $this->assertFalse($settings['activity_history_enabled']);
        $this->assertFalse($settings['remote_diagnostics_enabled']);
        $this->assertFalse($settings['push_test_enabled']);
        $this->assertFalse($settings['gps_background_test_enabled']);
        $this->assertFalse($settings['sync_test_enabled']);

        $user = $this->mobileSalesman($tenant, 'sales@optional-defaults.test');

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ], $this->deviceHeaders('device-a', 'install-a'))
            ->assertOk();

        $this->assertDatabaseCount('device_activity_logs', 0);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ], $this->deviceHeaders('device-b', 'install-b'))
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'DEVICE_LIMIT_REACHED');
    }

    public function test_optional_device_settings_can_be_saved_independently(): void
    {
        $tenant = $this->tenant('optional-settings');
        $admin = $this->tenantScope(
            $tenant,
            fn () => $this->userWithRole(
                $tenant,
                'admin@optional-settings.test',
                ['settings:view', 'settings:manage'],
                'company_admin',
            ),
        );

        $this->actingAs($admin)
            ->get(route('tracking.edit'))
            ->assertOk()
            ->assertSee('Optional device controls')
            ->assertSee('Require device approval')
            ->assertSee('Allow secondary devices');

        $this->actingAs($admin)
            ->put(route('tracking.update'), [
                'work_session_start_mode' => 'manual',
                'workday_start_time' => '08:00',
                'workday_end_time' => '17:00',
                'auto_end_session' => '0',
                'gps_tracking_enabled' => '1',
                'gps_moving_interval_seconds' => 15,
                'gps_stationary_interval_seconds' => 60,
                'gps_stale_after_minutes' => 15,
                'idle_alerts_enabled' => '0',
                'idle_alert_after_minutes' => 30,
                'idle_alert_repeat_minutes' => 60,
                'idle_escalation_enabled' => '0',
                'idle_escalate_after_minutes' => 30,
                'privacy_policy_version' => '1',
                'device_restriction_enabled' => '1',
                'device_approval_required' => '1',
                'secondary_device_enabled' => '0',
                'max_active_devices' => 2,
                'lost_device_workflow_enabled' => '0',
                'device_activity_history_enabled' => '1',
                'remote_diagnostics_enabled' => '0',
                'push_test_enabled' => '1',
                'gps_background_test_enabled' => '0',
                'sync_test_enabled' => '1',
            ])
            ->assertRedirect();

        $settings = $this->tenantScope(
            $tenant,
            fn () => app(DeviceSettingsService::class)->get($tenant),
        );

        $this->assertTrue($settings['approval_required']);
        $this->assertTrue($settings['activity_history_enabled']);
        $this->assertTrue($settings['push_test_enabled']);
        $this->assertTrue($settings['sync_test_enabled']);
        $this->assertFalse($settings['secondary_device_enabled']);
        $this->assertFalse($settings['remote_diagnostics_enabled']);
        $this->assertFalse($settings['gps_background_test_enabled']);
    }

    public function test_device_approval_is_only_required_when_enabled(): void
    {
        $tenant = $this->tenant('approval-optional');
        $this->setDeviceSetting($tenant, 'approval_required', true);
        $user = $this->mobileSalesman($tenant, 'sales@approval-optional.test');

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
            'device_model' => 'Approval Phone',
        ], $this->deviceHeaders('approval-device', 'approval-install'))
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'DEVICE_APPROVAL_REQUIRED')
            ->assertJsonPath('error.details.device.approval_status', 'pending');

        $device = $this->tenantScope(
            $tenant,
            fn () => Device::where('uuid', $response->json('error.details.device.id'))->firstOrFail(),
        );

        $this->assertSame('pending', $device->approval_status);
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
        ]);

        $admin = $this->tenantScope(
            $tenant,
            fn () => $this->userWithRole(
                $tenant,
                'admin@approval-optional.test',
                ['sales-team:view', 'sales-team:manage'],
                'company_admin',
            ),
        );

        $this->actingAs($admin)
            ->post(route('admin.devices.approve', $device))
            ->assertRedirect();

        auth()->logout();
        app('auth')->forgetGuards();

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ], $this->deviceHeaders('approval-device', 'approval-install'))
            ->assertOk();

        $approved = $this->tenantScope($tenant, fn () => $device->fresh());
        $this->assertSame('approved', $approved->approval_status);
        $this->assertNotNull($approved->approved_at);
        $this->assertSame($admin->id, $approved->approved_by);
    }

    public function test_secondary_device_support_only_increases_limit_when_enabled(): void
    {
        $tenant = $this->tenant('secondary-optional');
        $user = $this->mobileSalesman($tenant, 'sales@secondary-optional.test');

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ], $this->deviceHeaders('primary-device', 'primary-install'))
            ->assertOk();

        $this->setDeviceSetting($tenant, 'secondary_device_enabled', true);
        $this->setDeviceSetting($tenant, 'max_active_devices', 2);

        $second = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ], $this->deviceHeaders('secondary-device', 'secondary-install'))
            ->assertOk();

        $this->assertNotNull($second->json('data.token'));

        $devices = $this->tenantScope(
            $tenant,
            fn () => Device::where('user_id', $user->id)
                ->orderByDesc('is_primary')
                ->get(),
        );

        $this->assertCount(2, $devices);
        $this->assertTrue($devices->first()->is_primary);
        $this->assertFalse($devices->last()->is_primary);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ], $this->deviceHeaders('third-device', 'third-install'))
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'DEVICE_LIMIT_REACHED')
            ->assertJsonPath('error.details.max_active_devices', 2);
    }

    public function test_lost_stolen_workflow_is_hidden_when_disabled_and_revokes_when_enabled(): void
    {
        [$tenant, $user, $device, $token, $admin] = $this->managedDeviceFixture('lost-stolen');

        $this->actingAs($admin)
            ->post(route('admin.devices.management-status', $device), [
                'status' => 'stolen',
            ])
            ->assertNotFound();

        $this->setDeviceSetting($tenant, 'lost_device_workflow_enabled', true);
        $this->setDeviceSetting($tenant, 'activity_history_enabled', true);

        $this->actingAs($admin)
            ->post(route('admin.devices.management-status', $device), [
                'status' => 'stolen',
                'reason' => 'Phone reported missing.',
            ])
            ->assertRedirect();

        $fresh = $this->tenantScope($tenant, fn () => $device->fresh());

        $this->assertSame('stolen', $fresh->management_status);
        $this->assertTrue($fresh->isRevoked());
        $this->assertNull($fresh->push_token);
        $this->assertDatabaseHas('device_activity_logs', [
            'tenant_id' => $tenant->id,
            'device_id' => $device->id,
            'event' => 'device.marked_stolen',
        ]);

        auth()->logout();
        auth('sanctum')->forgetUser();
        app('auth')->forgetGuards();

        $this->withToken($token)
            ->getJson('/api/v1/auth/me', $this->deviceHeaders(
                $device->device_uuid,
                $device->installation_uuid,
            ))
            ->assertUnauthorized();

        $this->assertSame($user->id, $fresh->user_id);
    }

    public function test_optional_diagnostics_and_device_tests_are_server_gated(): void
    {
        [$tenant, , $device, , $admin] = $this->managedDeviceFixture('device-actions');

        $this->actingAs($admin)
            ->post(route('admin.devices.request-diagnostics', $device))
            ->assertNotFound();

        foreach ([
            'remote_diagnostics_enabled',
            'push_test_enabled',
            'gps_background_test_enabled',
            'sync_test_enabled',
            'activity_history_enabled',
        ] as $key) {
            $this->setDeviceSetting($tenant, $key, true);
        }

        $this->tenantScope($tenant, function () use ($device): void {
            $device->forceFill([
                'push_token' => 'test-push-token',
                'health_status' => 'healthy',
                'health_reported_at' => now(),
                'location_services_enabled' => true,
                'location_permission' => 'always',
                'background_location_permission' => 'granted',
                'background_tracking_active' => true,
                'workday_active' => true,
                'last_gps_fix_at' => now(),
                'pending_sync_count' => 0,
                'failed_sync_count' => 0,
                'blocked_sync_count' => 0,
                'last_sync_at' => now(),
            ])->save();
        });

        $this->actingAs($admin)
            ->post(route('admin.devices.request-diagnostics', $device))
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->actingAs($admin)
            ->post(route('admin.devices.test-push', $device))
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->actingAs($admin)
            ->post(route('admin.devices.test-gps-background', $device))
            ->assertRedirect()
            ->assertSessionHas('device_test_result', fn (array $result) => $result['status'] === 'passed');

        $this->actingAs($admin)
            ->post(route('admin.devices.test-sync', $device))
            ->assertRedirect()
            ->assertSessionHas('device_test_result', fn (array $result) => $result['status'] === 'passed');

        $this->assertDatabaseHas('operational_notifications', [
            'tenant_id' => $tenant->id,
            'user_id' => $device->user_id,
            'type' => 'device.diagnostics_request',
        ]);
        $this->assertDatabaseHas('operational_notifications', [
            'tenant_id' => $tenant->id,
            'user_id' => $device->user_id,
            'type' => 'device.push_test',
        ]);

        $events = $this->tenantScope(
            $tenant,
            fn () => DeviceActivityLog::where('device_id', $device->id)
                ->pluck('event'),
        );

        $this->assertTrue($events->contains('device.diagnostics_requested'));
        $this->assertTrue($events->contains('device.push_test'));
        $this->assertTrue($events->contains('device.gps_background_test'));
        $this->assertTrue($events->contains('device.sync_test'));
    }

    private function managedDeviceFixture(string $slug): array
    {
        $tenant = $this->tenant($slug);
        $user = $this->mobileSalesman($tenant, 'sales@'.$slug.'.test');

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ], $this->deviceHeaders('device-'.$slug, 'install-'.$slug))
            ->assertOk();

        $device = $this->tenantScope(
            $tenant,
            fn () => Device::where('user_id', $user->id)->firstOrFail(),
        );

        $admin = $this->tenantScope(
            $tenant,
            fn () => $this->userWithRole(
                $tenant,
                'admin@'.$slug.'.test',
                ['sales-team:view', 'sales-team:manage'],
                'company_admin',
            ),
        );

        return [$tenant, $user, $device, $login->json('data.token'), $admin];
    }

    private function setDeviceSetting(
        Tenant $tenant,
        string $key,
        mixed $value,
    ): void {
        $this->tenantScope(
            $tenant,
            fn () => CompanySetting::updateOrCreate(
                ['tenant_id' => $tenant->id, 'key' => 'device.'.$key],
                ['value' => is_bool($value) ? ($value ? '1' : '0') : (string) $value],
            ),
        );
    }

    private function mobileSalesman(Tenant $tenant, string $email): User
    {
        return $this->tenantScope($tenant, function () use ($tenant, $email): User {
            $role = $this->role(
                $tenant,
                'salesman',
                ['attendance:view', 'attendance:manage', 'gps:upload'],
            );
            $user = $this->plainUser($tenant, $email);
            $user->syncPrimaryRole($role);

            Salesman::create([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'employee_code' => 'SAL-'.strtoupper(Str::random(5)),
                'first_name' => 'Mobile',
                'last_name' => 'Salesman',
                'is_active' => true,
            ]);

            return $user;
        });
    }

    private function deviceHeaders(
        string $device,
        string $installation,
        string $appVersion = '1.0',
    ): array {
        return [
            'X-Device-UUID' => $device,
            'X-Installation-UUID' => $installation,
            'X-App-Version' => $appVersion,
            'X-Platform' => 'android',
            'X-OS-Version' => '16',
        ];
    }

    private function tenant(string $slug): Tenant
    {
        return $this->platform(fn () => Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => str($slug)->replace('-', ' ')->title(),
            'slug' => $slug,
            'timezone' => 'Asia/Kabul',
            'subscription_status' => 'active',
        ]));
    }

    private function permission(string $slug): Permission
    {
        return Permission::firstOrCreate(
            ['slug' => $slug],
            [
                'name' => str($slug)->replace(':', ' ')->title(),
                'group' => str($slug)->before(':'),
            ],
        );
    }

    private function role(
        Tenant $tenant,
        string $slug,
        array $permissions,
    ): Role {
        return $this->tenantScope($tenant, function () use (
            $tenant,
            $slug,
            $permissions,
        ): Role {
            $role = Role::firstOrCreate(
                ['tenant_id' => $tenant->id, 'slug' => $slug],
                [
                    'name' => str($slug)->replace('_', ' ')->title(),
                    'is_system' => true,
                ],
            );

            $role->permissions()->sync(
                collect($permissions)
                    ->map(fn (string $permission) => $this->permission($permission)->id)
                    ->all(),
            );

            return $role;
        });
    }

    private function plainUser(Tenant $tenant, string $email): User
    {
        return User::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'name' => (string) str($email)->before('@')->replace('.', ' ')->title(),
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => 'salesman',
            'is_active' => true,
        ]);
    }

    private function userWithRole(
        Tenant $tenant,
        string $email,
        array $permissions,
        string $roleSlug,
    ): User {
        $role = $this->role($tenant, $roleSlug, $permissions);
        $user = $this->plainUser($tenant, $email);
        $user->syncPrimaryRole($role);

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
