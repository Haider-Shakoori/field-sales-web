<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\MobileDiagnostic;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class MobileDiagnosticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_registered_mobile_can_report_sanitized_diagnostic_and_admin_can_view_it(): void
    {
        $tenant = app(TenantContext::class)->withPlatformScope(
            fn (): Tenant => Tenant::create([
                'uuid' => (string) Str::uuid(),
                'name' => 'Diagnostics Tenant',
                'slug' => 'diagnostics-tenant',
                'timezone' => 'Asia/Kabul',
            ]),
        );

        [$mobileUser, $device, $token, $admin] = app(TenantContext::class)->withTenant(
            $tenant,
            function () use ($tenant): array {
                $mobileUser = User::create([
                    'uuid' => (string) Str::uuid(),
                    'tenant_id' => $tenant->id,
                    'name' => 'Mobile User',
                    'email' => 'mobile-diagnostic@example.test',
                    'password' => Hash::make('password'),
                    'role' => 'salesman',
                    'is_active' => true,
                ]);

                $device = Device::create([
                    'tenant_id' => $tenant->id,
                    'user_id' => $mobileUser->id,
                    'salesman_id' => null,
                    'device_uuid' => 'diagnostic-device',
                    'installation_uuid' => 'diagnostic-install',
                    'device_model' => 'Test Phone',
                    'app_version' => '1.2.3',
                    'is_active' => true,
                ]);

                $admin = User::create([
                    'uuid' => (string) Str::uuid(),
                    'tenant_id' => $tenant->id,
                    'name' => 'Diagnostics Admin',
                    'email' => 'diagnostics-admin@example.test',
                    'password' => Hash::make('password'),
                    'role' => 'company_admin',
                    'is_active' => true,
                ]);

                $permission = Permission::firstOrCreate(
                    ['slug' => 'sales-team:view'],
                    [
                        'name' => 'Sales Team View',
                        'group' => 'sales-team',
                    ],
                );

                $role = Role::create([
                    'tenant_id' => $tenant->id,
                    'name' => 'Diagnostics Admin',
                    'slug' => 'diagnostics-admin',
                    'is_system' => false,
                ]);
                $role->permissions()->sync([$permission->id]);
                $admin->syncPrimaryRole($role);

                $token = $mobileUser->createToken('mobile-'.$device->uuid)->plainTextToken;

                return [$mobileUser, $device, $token, $admin];
            },
        );

        $headers = [
            'Authorization' => 'Bearer '.$token,
            'X-Device-UUID' => 'diagnostic-device',
            'X-Installation-UUID' => 'diagnostic-install',
            'X-App-Version' => '1.2.3',
            'X-Platform' => 'android',
            'X-OS-Version' => '16',
        ];

        $this->withHeaders($headers)
            ->postJson('/api/v1/mobile/diagnostics', [
                'severity' => 'error',
                'area' => 'attendance.end_day',
                'code' => 'LOCAL_STATE_RECOVERY',
                'message' => 'End Day recovered from local state.',
                'context' => [
                    'screen' => 'home',
                    'operation' => 'end_day',
                    'app_version' => '1.2.3',
                    'network' => 'online',
                    'ignored_private_value' => 'must-not-be-stored',
                ],
                'occurred_at' => '2026-09-26T07:30:00Z',
            ])
            ->assertCreated()
            ->assertJsonPath('data.stored', true);

        $diagnostic = app(TenantContext::class)->withTenant(
            $tenant,
            fn (): MobileDiagnostic => MobileDiagnostic::firstOrFail(),
        );

        $this->assertSame($mobileUser->id, $diagnostic->user_id);
        $this->assertSame($device->id, $diagnostic->device_id);
        $this->assertSame('attendance.end_day', $diagnostic->area);
        $this->assertSame('home', $diagnostic->context['screen']);
        $this->assertArrayNotHasKey('ignored_private_value', $diagnostic->context);

        auth('sanctum')->forgetUser();
        app('auth')->forgetGuards();

        $this->actingAs($admin, 'web')
            ->get(route('admin.mobile-diagnostics.index'))
            ->assertOk()
            ->assertSee('Mobile diagnostics')
            ->assertSee('attendance.end_day')
            ->assertSee('End Day recovered from local state.');
    }

    public function test_mobile_diagnostics_console_requires_sales_team_view_permission(): void
    {
        $tenant = app(TenantContext::class)->withPlatformScope(
            fn (): Tenant => Tenant::create([
                'uuid' => (string) Str::uuid(),
                'name' => 'Denied Diagnostics Tenant',
                'slug' => 'denied-diagnostics',
                'timezone' => 'Asia/Kabul',
            ]),
        );

        $user = app(TenantContext::class)->withTenant(
            $tenant,
            fn (): User => User::create([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'name' => 'Denied User',
                'email' => 'denied-diagnostics@example.test',
                'password' => Hash::make('password'),
                'role' => 'auditor',
                'is_active' => true,
            ]),
        );

        $this->actingAs($user)
            ->get(route('admin.mobile-diagnostics.index'))
            ->assertForbidden();
    }
}
