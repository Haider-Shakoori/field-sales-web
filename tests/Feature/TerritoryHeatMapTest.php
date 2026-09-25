<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Collection;
use App\Models\Customer;
use App\Models\CustomerVisit;
use App\Models\Device;
use App\Models\Order;
use App\Models\Salesman;
use App\Models\SalesmanAssignment;
use App\Models\Supervisor;
use App\Models\Tenant;
use App\Models\Territory;
use App\Models\User;
use App\Services\TenantProvisioningService;
use App\Services\TerritoryHeatMapService;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class TerritoryHeatMapTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-09-25T08:00:00Z');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_manager_heat_map_keeps_currency_separate_and_calculates_coverage(): void
    {
        $f = $this->fixture();
        $this->activity($f, $f['customerA'], 1000, 'AFN', true);
        $this->activity($f, $f['customerB'], 50, 'USD', false);

        $payload = app(TenantContext::class)->withTenant(
            $f['tenant'],
            fn () => app(TerritoryHeatMapService::class)->build($f['admin'], [
                'date_from' => '2026-09-01',
                'date_to' => '2026-09-25',
                'metric' => 'sales',
                'currency' => 'AFN',
            ]),
        );

        $a = collect($payload['territories'])->firstWhere('id', $f['territoryA']->uuid);
        $b = collect($payload['territories'])->firstWhere('id', $f['territoryB']->uuid);
        $this->assertSame(1000.0, $a['sales']);
        $this->assertSame(0.0, $b['sales']);
        $this->assertSame(100.0, $a['coverage_percent']);
        $this->assertSame(0.0, $b['coverage_percent']);
        $this->assertContains('USD', $payload['currencies']);
        $this->assertContains('AFN', $payload['currencies']);

        $this->actingAs($f['admin'])
            ->get(route('admin.territory-heat-map', ['date_from' => '2026-09-01', 'date_to' => '2026-09-25']))
            ->assertOk()
            ->assertSee('Territory heat maps')
            ->assertSee($f['territoryA']->name)
            ->assertSee($f['territoryB']->name);
    }

    public function test_supervisor_only_sees_currently_supervised_territory(): void
    {
        $f = $this->fixture();
        $payload = app(TenantContext::class)->withTenant(
            $f['tenant'],
            fn () => app(TerritoryHeatMapService::class)->build($f['supervisorUser'], [
                'date_from' => '2026-09-01',
                'date_to' => '2026-09-25',
                'metric' => 'coverage',
            ]),
        );

        $this->assertSame([$f['territoryA']->uuid], collect($payload['territories'])->pluck('id')->all());
    }

    private function activity(array $f, Customer $customer, float $amount, string $currency, bool $visited): void
    {
        app(TenantContext::class)->withTenant($f['tenant'], function () use ($f, $customer, $amount, $currency, $visited): void {
            $order = Order::create([
                'user_id' => $f['salesmanUser']->id,
                'salesman_id' => $f['salesman']->id,
                'device_id' => $f['device']->id,
                'customer_id' => $customer->id,
                'order_number' => 'H-'.str()->upper(str()->random(8)),
                'ordered_at' => now()->subDay(),
                'payment_type' => 'cash',
                'status' => 'approved',
                'currency' => $currency,
                'subtotal' => $amount,
                'discount_total' => 0,
                'grand_total' => $amount,
            ]);
            Collection::create([
                'user_id' => $f['salesmanUser']->id,
                'salesman_id' => $f['salesman']->id,
                'device_id' => $f['device']->id,
                'customer_id' => $customer->id,
                'receipt_number' => 'RC-'.str()->upper(str()->random(8)),
                'collected_at' => now()->subDay(),
                'payment_method' => 'cash',
                'status' => 'verified',
                'currency' => $currency,
                'amount' => $amount / 2,
                'latitude' => 34.55,
                'longitude' => 69.15,
                'accuracy' => 8,
            ]);
            if ($visited) {
                CustomerVisit::create([
                    'user_id' => $f['salesmanUser']->id,
                    'salesman_id' => $f['salesman']->id,
                    'device_id' => $f['device']->id,
                    'customer_id' => $customer->id,
                    'status' => 'completed',
                    'outcome' => 'order_placed',
                    'is_planned' => true,
                    'checked_in_at' => now()->subDay(),
                    'checked_out_at' => now()->subDay()->addMinutes(15),
                    'checkin_latitude' => 34.55,
                    'checkin_longitude' => 69.15,
                    'checkin_accuracy' => 8,
                    'checkout_latitude' => 34.55,
                    'checkout_longitude' => 69.15,
                    'checkout_accuracy' => 8,
                ]);
            }
        });
    }

    private function fixture(): array
    {
        $context = app(TenantContext::class);
        $tenant = $context->withPlatformScope(fn () => Tenant::create([
            'uuid' => (string) Str::uuid(), 'name' => 'Heat Tenant', 'slug' => 'heat-'.Str::lower(Str::random(6)), 'timezone' => 'Asia/Kabul', 'subscription_status' => 'active',
        ]));

        return $context->withTenant($tenant, function () use ($tenant): array {
            $roles = app(TenantProvisioningService::class)->provisionRbac($tenant);
            $branch = Branch::create(['name' => 'Main', 'code' => 'MAIN', 'is_active' => true]);
            $territoryA = Territory::create(['branch_id' => $branch->id, 'code' => 'TA', 'name' => 'Territory A', 'polygon' => [[34.5, 69.1], [34.6, 69.1], [34.6, 69.2]], 'is_active' => true]);
            $territoryB = Territory::create(['branch_id' => $branch->id, 'code' => 'TB', 'name' => 'Territory B', 'polygon' => [[34.6, 69.2], [34.7, 69.2], [34.7, 69.3]], 'is_active' => true]);
            $admin = User::create(['uuid' => (string) Str::uuid(), 'branch_id' => $branch->id, 'name' => 'Admin', 'email' => 'heat-admin@example.test', 'password' => Hash::make('password'), 'role' => 'company_admin', 'is_active' => true]);
            $admin->syncPrimaryRole($roles['company_admin']);
            $supervisorUser = User::create(['uuid' => (string) Str::uuid(), 'branch_id' => $branch->id, 'name' => 'Supervisor', 'email' => 'heat-supervisor@example.test', 'password' => Hash::make('password'), 'role' => 'supervisor', 'is_active' => true]);
            $supervisorUser->syncPrimaryRole($roles['supervisor']);
            $supervisor = Supervisor::create(['user_id' => $supervisorUser->id, 'employee_code' => 'SUP-H', 'first_name' => 'Heat', 'last_name' => 'Supervisor', 'is_active' => true]);
            $salesmanUser = User::create(['uuid' => (string) Str::uuid(), 'branch_id' => $branch->id, 'name' => 'Salesman', 'email' => 'heat-salesman@example.test', 'password' => Hash::make('password'), 'role' => 'salesman', 'is_active' => true]);
            $salesmanUser->syncPrimaryRole($roles['salesman']);
            $salesman = Salesman::create(['user_id' => $salesmanUser->id, 'employee_code' => 'H-1', 'first_name' => 'Heat', 'last_name' => 'Salesman', 'is_active' => true]);
            SalesmanAssignment::create(['salesman_id' => $salesman->id, 'supervisor_id' => $supervisor->id, 'branch_id' => $branch->id, 'territory_id' => $territoryA->id, 'effective_from' => today()->subDay(), 'created_by' => $admin->id]);
            $customerA = Customer::create(['branch_id' => $branch->id, 'territory_id' => $territoryA->id, 'code' => 'CA', 'name' => 'Customer A', 'latitude' => 34.55, 'longitude' => 69.15, 'created_by' => $admin->id, 'is_active' => true]);
            $customerB = Customer::create(['branch_id' => $branch->id, 'territory_id' => $territoryB->id, 'code' => 'CB', 'name' => 'Customer B', 'latitude' => 34.65, 'longitude' => 69.25, 'created_by' => $admin->id, 'is_active' => true]);
            $device = Device::create(['user_id' => $salesmanUser->id, 'salesman_id' => $salesman->id, 'device_uuid' => 'heat-device', 'installation_uuid' => 'heat-install', 'is_active' => true]);

            return compact('tenant', 'branch', 'territoryA', 'territoryB', 'admin', 'supervisorUser', 'supervisor', 'salesmanUser', 'salesman', 'customerA', 'customerB', 'device');
        });
    }
}
