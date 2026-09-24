<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Device;
use App\Models\LocationHistory;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Salesman;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkSession;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrganizationsAndLiveMapTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_can_provision_an_organization_with_default_roles(): void
    {
        [, $platformAdmin] = $this->tenantUser(
            'platform@example.test',
            ['settings:view'],
            'company_admin',
            'Platform Tenant',
            'platform-tenant',
            platformAdmin: true,
        );

        $this->actingAs($platformAdmin)
            ->get(route('admin.organizations.index'))
            ->assertOk()
            ->assertSee('Organizations');

        $this->actingAs($platformAdmin)
            ->post(route('admin.organizations.store'), [
                'name' => 'Acme Field Sales',
                'slug' => 'acme-field-sales',
                'timezone' => 'Asia/Kabul',
                'contact_email' => 'billing@acme.test',
                'admin_name' => 'Acme Admin',
                'admin_email' => 'admin@acme.test',
                'admin_password' => 'secret-password',
            ])
            ->assertRedirect(route('admin.organizations.index'));

        $organization = Tenant::where('slug', 'acme-field-sales')->firstOrFail();

        $this->assertSame('Asia/Kabul', $organization->timezone);
        $this->assertSame('active', $organization->subscription_status);

        $context = app(TenantContext::class);

        $roleSlugs = $context->withTenant(
            $organization,
            fn () => Role::pluck('slug')->all()
        );

        $this->assertContains('company_admin', $roleSlugs);
        $this->assertContains('salesman', $roleSlugs);

        $administrator = $context->withTenant(
            $organization,
            fn () => User::where('email', 'admin@acme.test')->firstOrFail()
        );

        $this->assertTrue(Hash::check('secret-password', $administrator->password));
        $this->assertSame('company_admin', $administrator->role);
        $this->assertNotNull($administrator->branch_id);

        $this->assertDatabaseHas('company_settings', [
            'tenant_id' => $organization->id,
            'key' => 'tracking.gps_tracking_enabled',
        ]);
    }

    public function test_non_platform_administrators_cannot_open_the_organization_console(): void
    {
        [, $companyAdmin] = $this->tenantUser(
            'company-admin@example.test',
            ['settings:view', 'settings:manage'],
            'company_admin',
            'Normal Tenant',
            'normal-tenant',
        );

        $this->actingAs($companyAdmin)
            ->get(route('admin.organizations.index'))
            ->assertForbidden();

        $this->actingAs($companyAdmin)
            ->get(route('admin.organizations.create'))
            ->assertForbidden();
    }

    public function test_suspended_organizations_cannot_sign_in(): void
    {
        [$tenant] = $this->tenantUser(
            'suspended-admin@example.test',
            ['settings:view'],
            'company_admin',
            'Suspended Tenant',
            'suspended-tenant',
        );

        $tenant->update(['subscription_status' => 'suspended']);

        $this->post('/login', [
            'email' => 'suspended-admin@example.test',
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_tenant_administrators_can_view_and_update_organization_settings(): void
    {
        [$tenant, $admin] = $this->tenantUser(
            'org-admin@example.test',
            ['settings:view', 'settings:manage'],
            'company_admin',
            'Settings Tenant',
            'settings-tenant',
        );

        $this->actingAs($admin)
            ->get(route('organization.edit'))
            ->assertOk()
            ->assertSee('Settings Tenant')
            ->assertSee('Company profile')
            ->assertSee('Ask FieldPulse AI policy');

        $this->actingAs($admin)
            ->put(route('organization.update'), [
                'name' => 'Settings Tenant Renamed',
                'timezone' => 'Asia/Dubai',
                'contact_email' => 'ops@settings.test',
                'ai_enabled' => '1',
                'ai_allow_customer_data' => '0',
                'ai_history_retention_days' => '60',
            ])
            ->assertRedirect();

        $tenant->refresh();

        $this->assertSame('Settings Tenant Renamed', $tenant->name);
        $this->assertSame('Asia/Dubai', $tenant->timezone);
        $this->assertSame('ops@settings.test', $tenant->contact_email);
        $this->assertTrue((bool) data_get($tenant->settings, 'ai.enabled'));
        $this->assertFalse((bool) data_get(
            $tenant->settings,
            'ai.allow_customer_data',
        ));
        $this->assertSame(
            60,
            (int) data_get($tenant->settings, 'ai.history_retention_days'),
        );
    }

    public function test_live_map_page_requires_tracking_permission(): void
    {
        [$tenant, $trackingAdmin] = $this->tenantUser(
            'map-admin@example.test',
            ['reports:view', 'tracking:view'],
            'company_admin',
            'Map Tenant',
            'map-tenant',
        );

        $this->salesman($tenant, 'MAP-1', 'Mapped');

        $this->actingAs($trackingAdmin)
            ->get(route('admin.live-map'))
            ->assertOk()
            ->assertSee('Live map')
            ->assertSee('Mapped Salesman');

        [, $reportsOnly] = $this->tenantUser(
            'map-auditor@example.test',
            ['reports:view'],
            'auditor',
            'Map Tenant',
            'map-tenant',
        );

        $this->actingAs($reportsOnly)
            ->get(route('admin.live-map'))
            ->assertForbidden();
    }

    public function test_live_map_embeds_route_tracks_for_initial_render(): void
    {
        [$tenant, $admin] = $this->tenantUser(
            'map-tracks@example.test',
            ['reports:view', 'tracking:view'],
            'company_admin',
            'Tracks Tenant',
            'tracks-tenant',
        );

        [$salesman, $device] = $this->salesman($tenant, 'TRK-9', 'Tracked');

        app(TenantContext::class)->withTenant($tenant, function () use ($tenant, $salesman, $device): void {
            WorkSession::create([
                'tenant_id' => $tenant->id,
                'uuid' => (string) Str::uuid(),
                'user_id' => $salesman->user_id,
                'salesman_id' => $salesman->id,
                'device_id' => $device->id,
                'date' => today()->toDateString(),
                'start_time' => now()->subMinutes(5),
                'start_latitude' => 34.5000,
                'start_longitude' => 69.1000,
                'start_accuracy' => 5,
                'status' => 'active',
            ]);

            LocationHistory::create([
                'tenant_id' => $tenant->id,
                'user_id' => $salesman->user_id,
                'salesman_id' => $salesman->id,
                'device_id' => $device->id,
                'client_uuid' => (string) Str::uuid(),
                'latitude' => 34.5100,
                'longitude' => 69.1100,
                'horizontal_accuracy' => 8,
                'recorded_at' => now(),
                'received_at' => now(),
            ]);
        });

        $this->actingAs($admin)
            ->get(route('admin.live-map'))
            ->assertOk()
            ->assertSee('Tracked Salesman')
            ->assertSee('distance_km');
    }

    private function tenantUser(
        string $email,
        array $permissions,
        string $roleSlug,
        string $tenantName,
        string $tenantSlug,
        bool $platformAdmin = false,
    ): array {
        $tenant = app(TenantContext::class)->withPlatformScope(function () use (
            $tenantName,
            $tenantSlug,
        ): Tenant {
            return Tenant::firstOrCreate(
                ['slug' => $tenantSlug],
                [
                    'uuid' => (string) Str::uuid(),
                    'name' => $tenantName,
                    'timezone' => 'Asia/Kabul',
                ]
            );
        });

        $user = app(TenantContext::class)->withTenant(
            $tenant,
            function () use ($tenant, $email, $permissions, $roleSlug, $platformAdmin): User {
                $branch = Branch::firstOrCreate(
                    ['tenant_id' => $tenant->id, 'code' => 'MAIN'],
                    ['name' => 'Main', 'is_active' => true]
                );

                $user = User::create([
                    'uuid' => (string) Str::uuid(),
                    'tenant_id' => $tenant->id,
                    'branch_id' => $branch->id,
                    'name' => str($roleSlug)->replace('-', ' ')->title(),
                    'email' => $email,
                    'password' => Hash::make('password'),
                    'role' => $roleSlug,
                    'is_active' => true,
                    'is_platform_admin' => $platformAdmin,
                ]);

                $role = Role::create([
                    'tenant_id' => $tenant->id,
                    'name' => str($roleSlug)->replace('-', ' ')->title(),
                    'slug' => $roleSlug,
                    'is_system' => false,
                ]);

                $permissionIds = collect($permissions)->map(
                    fn (string $slug) => Permission::firstOrCreate(
                        ['slug' => $slug],
                        [
                            'name' => str($slug)->replace(':', ' ')->title(),
                            'group' => str($slug)->before(':'),
                        ]
                    )->id
                )->all();

                $role->permissions()->sync($permissionIds);
                $user->syncPrimaryRole($role);

                return $user;
            }
        );

        return [$tenant, $user];
    }

    private function salesman(Tenant $tenant, string $employeeCode, string $name): array
    {
        return app(TenantContext::class)->withTenant(
            $tenant,
            function () use ($tenant, $employeeCode, $name): array {
                $user = User::create([
                    'uuid' => (string) Str::uuid(),
                    'tenant_id' => $tenant->id,
                    'name' => $name.' Salesman',
                    'email' => strtolower($employeeCode).'@example.test',
                    'password' => Hash::make('password'),
                    'role' => 'salesman',
                    'is_active' => true,
                ]);

                $salesman = Salesman::create([
                    'tenant_id' => $tenant->id,
                    'user_id' => $user->id,
                    'employee_code' => $employeeCode,
                    'first_name' => $name,
                    'last_name' => 'Salesman',
                    'is_active' => true,
                ]);

                $device = Device::create([
                    'tenant_id' => $tenant->id,
                    'user_id' => $user->id,
                    'salesman_id' => $salesman->id,
                    'device_uuid' => 'device-'.strtolower($employeeCode),
                    'installation_uuid' => (string) Str::uuid(),
                    'is_active' => true,
                ]);

                return [$salesman, $device];
            }
        );
    }
}
