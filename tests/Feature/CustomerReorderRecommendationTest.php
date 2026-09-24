<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Device;
use App\Models\Order;
use App\Models\Product;
use App\Models\Salesman;
use App\Models\SalesmanAssignment;
use App\Models\SalesmanStockBalance;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CustomerReorderRecommendationService;
use App\Services\TenantProvisioningService;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerReorderRecommendationTest extends TestCase
{
    use RefreshDatabase;

    private ?string $token = null;

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

    public function test_repeat_history_produces_explainable_stock_capped_recommendation(): void
    {
        $f = $this->fixture();
        $this->approvedOrder($f, '2026-06-25T08:00:00Z', 10);
        $this->approvedOrder($f, '2026-07-25T08:00:00Z', 12);
        $this->approvedOrder($f, '2026-08-25T08:00:00Z', 14);

        app(TenantContext::class)->withTenant($f['tenant'], function () use ($f): void {
            CompanySetting::create(['key' => 'inventory.salesman_stock_enabled', 'value' => '1']);
            SalesmanStockBalance::create([
                'salesman_id' => $f['salesman']->id,
                'product_id' => $f['product']->id,
                'sellable_qty' => 8,
                'damaged_qty' => 0,
            ]);
        });

        $rows = app(TenantContext::class)->withTenant(
            $f['tenant'],
            fn () => app(CustomerReorderRecommendationService::class)->recommend(
                $f['customer']->loadMissing('tenant'),
                $f['salesman'],
                asOf: CarbonImmutable::now(),
            ),
        );

        $this->assertCount(1, $rows);
        $this->assertSame($f['product']->uuid, $rows[0]['product_id']);
        $this->assertSame(3, $rows[0]['purchase_count']);
        $this->assertSame(12.0, $rows[0]['average_quantity']);
        $this->assertSame(8.0, $rows[0]['suggested_quantity']);
        $this->assertTrue($rows[0]['stock_limited']);
        $this->assertContains($rows[0]['typical_interval_days'], [30, 31]);
    }

    public function test_mobile_api_returns_visible_customer_recommendations(): void
    {
        $f = $this->fixture();
        $this->approvedOrder($f, '2026-07-25T08:00:00Z', 5);
        $this->approvedOrder($f, '2026-08-25T08:00:00Z', 7);

        $this->getJson('/api/v1/customers/'.$f['customer']->uuid.'/reorder-recommendations', $this->headers())
            ->assertOk()
            ->assertJsonPath('data.customer_id', $f['customer']->uuid)
            ->assertJsonPath('data.recommendations.0.product_id', $f['product']->uuid)
            ->assertJsonPath('data.recommendations.0.purchase_count', 2);
    }

    public function test_one_off_purchase_is_not_presented_as_recurring_demand(): void
    {
        $f = $this->fixture();
        $this->approvedOrder($f, '2026-08-25T08:00:00Z', 5);

        $rows = app(TenantContext::class)->withTenant(
            $f['tenant'],
            fn () => app(CustomerReorderRecommendationService::class)->recommend(
                $f['customer']->loadMissing('tenant'),
                $f['salesman'],
                asOf: CarbonImmutable::now(),
            ),
        );

        $this->assertSame([], $rows);
    }

    private function approvedOrder(array $f, string $orderedAt, float $quantity): void
    {
        app(TenantContext::class)->withTenant($f['tenant'], function () use ($f, $orderedAt, $quantity): void {
            $order = Order::create([
                'user_id' => $f['salesmanUser']->id,
                'salesman_id' => $f['salesman']->id,
                'device_id' => $f['device']->id,
                'customer_id' => $f['customer']->id,
                'order_number' => 'ORD-'.str()->upper(str()->random(10)),
                'ordered_at' => $orderedAt,
                'payment_type' => 'cash',
                'status' => 'approved',
                'currency' => 'AFN',
                'subtotal' => $quantity * 100,
                'discount_total' => 0,
                'grand_total' => $quantity * 100,
            ]);
            $order->items()->create([
                'product_id' => $f['product']->id,
                'product_sku' => $f['product']->sku,
                'product_name' => $f['product']->name,
                'unit' => $f['product']->unit,
                'quantity' => $quantity,
                'unit_price' => 100,
                'discount_percent' => 0,
                'discount_amount' => 0,
                'line_total' => $quantity * 100,
            ]);
        });
    }

    private function fixture(): array
    {
        $context = app(TenantContext::class);
        $tenant = $context->withPlatformScope(fn () => Tenant::create([
            'uuid' => (string) Str::uuid(), 'name' => 'Reorder Tenant', 'slug' => 'reorder-'.Str::lower(Str::random(6)), 'timezone' => 'Asia/Kabul', 'subscription_status' => 'active',
        ]));

        return $context->withTenant($tenant, function () use ($tenant): array {
            $roles = app(TenantProvisioningService::class)->provisionRbac($tenant);
            $branch = Branch::create(['name' => 'Main', 'code' => 'MAIN', 'is_active' => true]);
            $admin = User::create(['uuid' => (string) Str::uuid(), 'branch_id' => $branch->id, 'name' => 'Admin', 'email' => 'reorder-admin@example.test', 'password' => Hash::make('password'), 'role' => 'company_admin', 'is_active' => true]);
            $admin->syncPrimaryRole($roles['company_admin']);
            $salesmanUser = User::create(['uuid' => (string) Str::uuid(), 'branch_id' => $branch->id, 'name' => 'Salesman', 'email' => 'reorder-salesman@example.test', 'password' => Hash::make('password'), 'role' => 'salesman', 'is_active' => true]);
            $salesmanUser->syncPrimaryRole($roles['salesman']);
            $salesman = Salesman::create(['user_id' => $salesmanUser->id, 'employee_code' => 'R-1', 'first_name' => 'Reorder', 'last_name' => 'Salesman', 'is_active' => true]);
            SalesmanAssignment::create(['salesman_id' => $salesman->id, 'branch_id' => $branch->id, 'effective_from' => today()->subDay(), 'created_by' => $admin->id]);
            $customer = Customer::create(['branch_id' => $branch->id, 'code' => 'C-R-1', 'name' => 'Repeat Customer', 'created_by' => $admin->id, 'is_active' => true]);
            $product = Product::create(['sku' => 'SKU-R-1', 'name' => 'Repeat Product', 'unit' => 'pcs', 'base_price' => 100, 'currency' => 'AFN', 'is_active' => true]);
            $device = Device::create(['user_id' => $salesmanUser->id, 'salesman_id' => $salesman->id, 'device_uuid' => 'reorder-device', 'installation_uuid' => 'reorder-install', 'is_active' => true]);
            $this->token = $salesmanUser->createToken('mobile-'.$device->uuid)->plainTextToken;

            return compact('tenant', 'branch', 'admin', 'salesmanUser', 'salesman', 'customer', 'product', 'device');
        });
    }

    private function headers(): array
    {
        return ['Authorization' => 'Bearer '.$this->token, 'X-Device-UUID' => 'reorder-device', 'X-Installation-UUID' => 'reorder-install', 'X-App-Version' => '1.0', 'X-Platform' => 'android', 'X-OS-Version' => '16'];
    }
}
