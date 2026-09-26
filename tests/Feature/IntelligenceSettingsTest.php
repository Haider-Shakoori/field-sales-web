<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Salesman;
use App\Models\SalesmanAssignment;
use App\Models\Tenant;
use App\Models\Territory;
use App\Models\User;
use App\Services\BusinessOsIntegrationPolicyService;
use App\Services\DailyRoutePlannerService;
use App\Services\FieldIntelligenceSettingsService;
use App\Services\TenantProvisioningService;
use App\Services\TerritoryHeatMapService;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IntelligenceSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_admin_can_configure_intelligence_and_businessos_policies(): void
    {
        $fixture = $this->fixture();

        $this->actingAs($fixture['admin'])
            ->get(route('organization.edit'))
            ->assertOk()
            ->assertSee('Field intelligence')
            ->assertSee('BusinessOS integration');

        $this->actingAs($fixture['admin'])
            ->put(route('organization.update'), [
                'name' => $fixture['tenant']->name,
                'timezone' => 'Asia/Kabul',
                'contact_email' => 'ops@example.test',
                'ai_enabled' => '1',
                'ai_allow_customer_data' => '0',
                'ai_history_retention_days' => '60',
                'smart_routes_enabled' => '1',
                'route_nearby_radius_km' => '7.5',
                'route_max_opportunities' => '4',
                'territory_auto_assign_enabled' => '0',
                'territory_heat_map_enabled' => '1',
                'territory_under_covered_threshold_percent' => '70',
                'businessos_enabled' => '1',
                'businessos_organization_key' => 'acme-distribution',
                'businessos_pull_products' => '1',
                'businessos_pull_customers' => '1',
                'businessos_pull_prices' => '0',
                'businessos_push_orders' => '1',
                'businessos_push_collections' => '1',
                'businessos_push_field_customers' => '0',
                'businessos_sync_interval_minutes' => '30',
            ])
            ->assertRedirect();

        $tenant = $fixture['tenant']->fresh();
        $intelligence = app(FieldIntelligenceSettingsService::class)->settingsFor($tenant);

        $this->assertTrue($intelligence['smart_routes_enabled']);
        $this->assertSame(7.5, $intelligence['route_nearby_radius_km']);
        $this->assertSame(4, $intelligence['route_max_opportunities']);
        $this->assertFalse($intelligence['territory_auto_assign_enabled']);
        $this->assertTrue($intelligence['territory_heat_map_enabled']);
        $this->assertSame(70, $intelligence['territory_under_covered_threshold_percent']);

        $policy = app(BusinessOsIntegrationPolicyService::class)->settingsFor($tenant);
        $this->assertTrue($policy['requested_enabled']);
        $this->assertFalse($policy['enabled']);
        $this->assertSame('acme-distribution', $policy['organization_key']);
        $this->assertFalse($policy['pull_prices']);
        $this->assertFalse($policy['push_field_customers']);
        $this->assertSame(30, $policy['sync_interval_minutes']);

        config()->set('businessos.enabled', true);
        config()->set('businessos.base_url', 'https://businessos.example.test');
        config()->set('businessos.token', 'test-token');

        $enabledPolicy = app(BusinessOsIntegrationPolicyService::class)->settingsFor($tenant);
        $this->assertTrue($enabledPolicy['platform_available']);
        $this->assertTrue($enabledPolicy['enabled']);
    }

    public function test_disabling_smart_routes_changes_web_and_service_behavior(): void
    {
        $fixture = $this->fixture([
            'intelligence' => [
                'smart_routes' => [
                    'enabled' => false,
                    'nearby_radius_km' => 8,
                    'max_opportunities' => 3,
                ],
            ],
        ]);

        $plan = app(TenantContext::class)->withTenant(
            $fixture['tenant'],
            fn () => app(DailyRoutePlannerService::class)->planFor(
                $fixture['salesman'],
                CarbonImmutable::parse('2026-09-26', 'Asia/Kabul'),
            ),
        );

        $this->assertFalse($plan['enabled']);
        $this->assertSame([], $plan['stops']);
        $this->assertContains('smart_route_planning_disabled', $plan['warnings']);
        $this->assertSame(8.0, $plan['dynamic_route']['nearby_radius_km']);

        $this->actingAs($fixture['admin'])
            ->get(route('admin.daily-planner.index', [
                'salesman' => $fixture['salesman']->uuid,
                'date' => '2026-09-26',
            ]))
            ->assertOk()
            ->assertSee('Smart route planning is disabled');
    }

    public function test_customer_polygon_auto_assignment_can_be_disabled_per_tenant(): void
    {
        $fixture = $this->fixture([
            'intelligence' => [
                'territories' => [
                    'auto_assign_customers' => false,
                ],
            ],
        ]);

        $this->actingAs($fixture['admin'])
            ->post(route('admin.customers.store'), $this->customerPayload(
                $fixture,
                'AUTO-OFF',
            ))
            ->assertRedirect();

        $withoutDetection = app(TenantContext::class)->withTenant(
            $fixture['tenant'],
            fn () => Customer::where('code', 'AUTO-OFF')->firstOrFail(),
        );

        $this->assertNull($withoutDetection->territory_id);

        $settings = $fixture['tenant']->fresh()->settings ?? [];
        data_set($settings, 'intelligence.territories.auto_assign_customers', true);
        $fixture['tenant']->update(['settings' => $settings]);

        $this->actingAs($fixture['admin'])
            ->post(route('admin.customers.store'), $this->customerPayload(
                $fixture,
                'AUTO-ON',
            ))
            ->assertRedirect();

        $withDetection = app(TenantContext::class)->withTenant(
            $fixture['tenant']->fresh(),
            fn () => Customer::where('code', 'AUTO-ON')->firstOrFail(),
        );

        $this->assertSame($fixture['territory']->id, $withDetection->territory_id);
    }

    public function test_heat_map_setting_and_attention_threshold_are_applied(): void
    {
        $fixture = $this->fixture([
            'intelligence' => [
                'territories' => [
                    'heat_map_enabled' => false,
                    'under_covered_threshold_percent' => 75,
                ],
            ],
        ]);

        $disabled = app(TenantContext::class)->withTenant(
            $fixture['tenant'],
            fn () => app(TerritoryHeatMapService::class)->build(
                $fixture['admin'],
                [
                    'date_from' => '2026-09-01',
                    'date_to' => '2026-09-26',
                ],
            ),
        );

        $this->assertFalse($disabled['enabled']);
        $this->assertSame(75, $disabled['under_covered_threshold_percent']);

        $settings = $fixture['tenant']->fresh()->settings ?? [];
        data_set($settings, 'intelligence.territories.heat_map_enabled', true);
        $fixture['tenant']->update(['settings' => $settings]);

        $enabled = app(TenantContext::class)->withTenant(
            $fixture['tenant']->fresh(),
            fn () => app(TerritoryHeatMapService::class)->build(
                $fixture['admin']->fresh(),
                [
                    'date_from' => '2026-09-01',
                    'date_to' => '2026-09-26',
                ],
            ),
        );

        $this->assertTrue($enabled['enabled']);
        $this->assertSame(75, $enabled['under_covered_threshold_percent']);
        $this->assertSame(
            [$fixture['territory']->uuid],
            collect($enabled['under_covered'])->pluck('id')->all(),
        );
    }

    public function test_mobile_can_read_safe_intelligence_feature_flags(): void
    {
        $fixture = $this->fixture([
            'intelligence' => [
                'smart_routes' => [
                    'enabled' => false,
                    'nearby_radius_km' => 6,
                    'max_opportunities' => 2,
                ],
                'territories' => [
                    'auto_assign_customers' => false,
                    'heat_map_enabled' => true,
                ],
            ],
        ]);

        Sanctum::actingAs($fixture['salesmanUser']);

        $this->getJson('/api/v1/settings/features')
            ->assertOk()
            ->assertJsonPath('data.smart_routes_enabled', false)
            ->assertJsonPath('data.route_nearby_radius_km', 6)
            ->assertJsonPath('data.route_max_opportunities', 2)
            ->assertJsonPath('data.territory_auto_assign_enabled', false)
            ->assertJsonPath('data.territory_heat_map_enabled', true);
    }

    private function fixture(array $settings = []): array
    {
        $context = app(TenantContext::class);
        $tenant = $context->withPlatformScope(fn () => Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Intelligence Tenant',
            'slug' => 'intelligence-'.Str::lower(Str::random(6)),
            'timezone' => 'Asia/Kabul',
            'settings' => $settings,
            'subscription_status' => 'active',
        ]));

        return $context->withTenant($tenant, function () use ($tenant): array {
            $roles = app(TenantProvisioningService::class)->provisionRbac($tenant);
            $branch = Branch::create([
                'name' => 'Main Branch',
                'code' => 'MAIN',
                'is_active' => true,
            ]);

            $admin = User::create([
                'uuid' => (string) Str::uuid(),
                'branch_id' => $branch->id,
                'name' => 'Company Admin',
                'email' => 'admin-'.Str::lower(Str::random(6)).'@example.test',
                'password' => Hash::make('password'),
                'role' => 'company_admin',
                'is_active' => true,
            ]);
            $admin->syncPrimaryRole($roles['company_admin']);

            $salesmanUser = User::create([
                'uuid' => (string) Str::uuid(),
                'branch_id' => $branch->id,
                'name' => 'Field Salesman',
                'email' => 'salesman-'.Str::lower(Str::random(6)).'@example.test',
                'password' => Hash::make('password'),
                'role' => 'salesman',
                'is_active' => true,
            ]);
            $salesmanUser->syncPrimaryRole($roles['salesman']);

            $salesman = Salesman::create([
                'user_id' => $salesmanUser->id,
                'employee_code' => 'INT-1',
                'first_name' => 'Field',
                'last_name' => 'Salesman',
                'is_active' => true,
            ]);

            $territory = Territory::create([
                'branch_id' => $branch->id,
                'code' => 'INT-T',
                'name' => 'Intelligence Territory',
                'polygon' => [
                    [34.50, 69.10],
                    [34.60, 69.10],
                    [34.60, 69.20],
                    [34.50, 69.20],
                ],
                'is_active' => true,
            ]);

            SalesmanAssignment::create([
                'salesman_id' => $salesman->id,
                'branch_id' => $branch->id,
                'territory_id' => $territory->id,
                'effective_from' => '2026-09-01',
                'created_by' => $admin->id,
            ]);

            Customer::create([
                'branch_id' => $branch->id,
                'territory_id' => $territory->id,
                'code' => 'BASE-1',
                'name' => 'Baseline Customer',
                'latitude' => 34.55,
                'longitude' => 69.15,
                'created_by' => $admin->id,
                'is_active' => true,
            ]);

            return compact(
                'tenant',
                'branch',
                'admin',
                'salesmanUser',
                'salesman',
                'territory',
            );
        });
    }

    private function customerPayload(array $fixture, string $code): array
    {
        return [
            'branch_id' => $fixture['branch']->id,
            'territory_id' => null,
            'price_list_id' => null,
            'code' => $code,
            'name' => 'Auto Assignment '.$code,
            'contact_person' => null,
            'phone' => null,
            'alternate_phone' => null,
            'email' => null,
            'address' => 'Kabul',
            'latitude' => 34.55,
            'longitude' => 69.15,
            'credit_limit' => 0,
            'credit_currency' => 'AFN',
            'credit_terms_days' => 30,
            'geofence_radius_meters' => 100,
            'offline_uuid' => null,
            'is_active' => 1,
        ];
    }
}
