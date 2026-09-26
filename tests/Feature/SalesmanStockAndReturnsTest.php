<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Device;
use App\Models\Order;
use App\Models\Product;
use App\Models\Salesman;
use App\Models\SalesmanStockBalance;
use App\Models\SalesReturn;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SalesmanStockService;
use App\Services\StockSettingsService;
use App\Services\TenantProvisioningService;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class SalesmanStockAndReturnsTest extends TestCase
{
    use RefreshDatabase;

    private ?string $token = null;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-09-23T12:00:00Z');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_existing_tenant_order_approval_stays_compatible_until_stock_control_is_enabled(): void
    {
        $actor = $this->actor();
        $order = $this->createOrder($actor, 2);

        $this->actingAs($actor['admin'])
            ->patch('/admin/orders/'.$order->id.'/status', ['status' => 'approved'])
            ->assertRedirect(route('admin.orders.show', $order));

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'approved',
        ]);
        $this->assertDatabaseCount('salesman_stock_movements', 0);
    }

    public function test_enabled_stock_control_deducts_approved_order_and_restores_cancelled_order(): void
    {
        $actor = $this->actor();

        app(TenantContext::class)->withTenant(
            $actor['tenant'],
            function () use ($actor): void {
                app(StockSettingsService::class)->setEnabled($actor['tenant'], true);
                app(SalesmanStockService::class)->issue(
                    $actor['salesman'],
                    [[
                        'product_id' => $actor['product']->uuid,
                        'quantity' => 10,
                    ]],
                    $actor['admin'],
                    now()->toImmutable(),
                    'Opening van stock',
                );
            },
        );

        $order = $this->createOrder($actor, 3);

        $this->actingAs($actor['admin'])
            ->patch('/admin/orders/'.$order->id.'/status', ['status' => 'approved'])
            ->assertRedirect(route('admin.orders.show', $order));

        $balance = $this->balance($actor);
        $this->assertSame(7.0, (float) $balance->sellable_qty);
        $this->assertDatabaseHas('salesman_stock_movements', [
            'movement_type' => 'sale',
            'reference_type' => 'order',
            'reference_id' => $order->id,
            'product_id' => $actor['product']->id,
            'quantity_change' => -3,
        ]);

        $this->actingAs($actor['admin'])
            ->patch('/admin/orders/'.$order->id.'/status', [
                'status' => 'cancelled',
                'status_note' => 'Customer cancelled',
            ])
            ->assertRedirect(route('admin.orders.show', $order));

        $this->assertSame(10.0, (float) $this->balance($actor)->sellable_qty);
        $this->assertDatabaseHas('salesman_stock_movements', [
            'movement_type' => 'order_cancel_restore',
            'reference_type' => 'order',
            'reference_id' => $order->id,
            'product_id' => $actor['product']->id,
            'quantity_change' => 3,
        ]);
    }

    public function test_insufficient_stock_blocks_approval_and_keeps_order_pending(): void
    {
        $actor = $this->actor();

        app(TenantContext::class)->withTenant(
            $actor['tenant'],
            function () use ($actor): void {
                app(StockSettingsService::class)->setEnabled($actor['tenant'], true);
                app(SalesmanStockService::class)->issue(
                    $actor['salesman'],
                    [[
                        'product_id' => $actor['product']->uuid,
                        'quantity' => 1,
                    ]],
                    $actor['admin'],
                    now()->toImmutable(),
                );
            },
        );

        $order = $this->createOrder($actor, 2);

        $this->from(route('admin.orders.show', $order))
            ->actingAs($actor['admin'])
            ->patch('/admin/orders/'.$order->id.'/status', ['status' => 'approved'])
            ->assertRedirect(route('admin.orders.show', $order))
            ->assertSessionHasErrors('stock');

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'pending',
        ]);
        $this->assertSame(1.0, (float) $this->balance($actor)->sellable_qty);
        $this->assertDatabaseMissing('salesman_stock_movements', [
            'movement_type' => 'sale',
            'reference_id' => $order->id,
        ]);
    }

    public function test_customer_return_is_idempotent_and_approval_routes_quantities_to_correct_buckets(): void
    {
        $actor = $this->actor();
        $returnUuid = (string) Str::uuid();

        $payload = [
            'offline_uuid' => $returnUuid,
            'customer_id' => $actor['customer']->uuid,
            'returned_at' => '2026-09-23T10:00:00Z',
            'notes' => 'Customer returned mixed stock',
            'items' => [
                [
                    'product_id' => $actor['product']->uuid,
                    'quantity' => 2,
                    'condition' => 'resalable',
                    'reason' => 'Wrong quantity ordered',
                ],
                [
                    'product_id' => $actor['product']->uuid,
                    'quantity' => 1,
                    'condition' => 'damaged',
                    'reason' => 'Crushed pack',
                ],
            ],
        ];

        $this->postJson('/api/v1/returns', $payload, $this->headers($actor))
            ->assertCreated()
            ->assertJsonPath('data.id', $returnUuid)
            ->assertJsonPath('data.status', 'pending');

        $this->postJson('/api/v1/returns', $payload, $this->headers($actor))
            ->assertOk()
            ->assertJsonPath('data.id', $returnUuid);

        $return = app(TenantContext::class)->withTenant(
            $actor['tenant'],
            fn () => SalesReturn::where('uuid', $returnUuid)->firstOrFail(),
        );

        $this->actingAs($actor['admin'], 'web')
            ->get(route('admin.returns.index'))
            ->assertOk()
            ->assertSee('All statuses');

        $this->actingAs($actor['admin'])
            ->patch('/admin/returns/'.$return->id.'/status', [
                'status' => 'approved',
            ])
            ->assertRedirect();

        $balance = $this->balance($actor);
        $this->assertSame(2.0, (float) $balance->sellable_qty);
        $this->assertSame(1.0, (float) $balance->damaged_qty);

        $this->assertDatabaseHas('salesman_stock_movements', [
            'movement_type' => 'customer_return',
            'reference_type' => 'sales_return',
            'reference_id' => $return->id,
            'bucket' => 'sellable',
            'quantity_change' => 2,
        ]);
        $this->assertDatabaseHas('salesman_stock_movements', [
            'movement_type' => 'customer_return',
            'reference_type' => 'sales_return',
            'reference_id' => $return->id,
            'bucket' => 'damaged',
            'quantity_change' => 1,
        ]);
    }

    public function test_mobile_stock_endpoint_exposes_enablement_and_current_balances(): void
    {
        $actor = $this->actor();

        app(TenantContext::class)->withTenant(
            $actor['tenant'],
            function () use ($actor): void {
                app(StockSettingsService::class)->setEnabled($actor['tenant'], true);
                app(SalesmanStockService::class)->issue(
                    $actor['salesman'],
                    [[
                        'product_id' => $actor['product']->uuid,
                        'quantity' => 8.5,
                    ]],
                    $actor['admin'],
                    now()->toImmutable(),
                );
            },
        );

        $this->getJson('/api/v1/stock/me', $this->headers($actor))
            ->assertOk()
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.stock.0.product_id', $actor['product']->uuid)
            ->assertJsonPath('data.stock.0.sellable_qty', 8.5)
            ->assertJsonPath('data.stock.0.damaged_qty', 0);
    }

    private function actor(): array
    {
        $tenant = app(TenantContext::class)->withPlatformScope(fn () => Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Stock Tenant',
            'slug' => 'stock-'.Str::lower(Str::random(6)),
            'timezone' => 'Asia/Kabul',
            'subscription_status' => 'active',
        ]));

        return app(TenantContext::class)->withTenant(
            $tenant,
            function () use ($tenant): array {
                $roles = app(TenantProvisioningService::class)->provisionRbac($tenant);

                $admin = User::create([
                    'uuid' => (string) Str::uuid(),
                    'name' => 'Stock Admin',
                    'email' => 'stock-admin@example.test',
                    'password' => Hash::make('password'),
                    'role' => 'company_admin',
                    'is_active' => true,
                ]);
                $admin->syncPrimaryRole($roles['company_admin']);

                $salesUser = User::create([
                    'uuid' => (string) Str::uuid(),
                    'name' => 'Stock Salesman',
                    'email' => 'stock-salesman@example.test',
                    'password' => Hash::make('password'),
                    'role' => 'salesman',
                    'is_active' => true,
                ]);
                $salesUser->syncPrimaryRole($roles['salesman']);

                $salesman = Salesman::create([
                    'user_id' => $salesUser->id,
                    'employee_code' => 'STK-001',
                    'first_name' => 'Stock',
                    'last_name' => 'Salesman',
                    'is_active' => true,
                ]);

                $device = Device::create([
                    'user_id' => $salesUser->id,
                    'salesman_id' => $salesman->id,
                    'device_uuid' => 'stock-device',
                    'installation_uuid' => 'stock-install',
                    'is_active' => true,
                ]);

                $product = Product::create([
                    'sku' => 'STK-PROD-001',
                    'name' => 'Stock Product',
                    'unit' => 'pcs',
                    'base_price' => 100,
                    'currency' => 'AFN',
                    'is_active' => true,
                ]);

                $customer = Customer::create([
                    'code' => 'STK-CUS-001',
                    'name' => 'Stock Customer',
                    'created_by' => $admin->id,
                    'is_active' => true,
                ]);

                $token = $salesUser
                    ->createToken('mobile-'.$device->uuid)
                    ->plainTextToken;

                return compact(
                    'tenant',
                    'admin',
                    'salesUser',
                    'salesman',
                    'device',
                    'product',
                    'customer',
                    'token',
                );
            },
        );
    }

    private function createOrder(array $actor, float $quantity): Order
    {
        $uuid = (string) Str::uuid();

        $this->postJson('/api/v1/orders', [
            'offline_uuid' => $uuid,
            'customer_id' => $actor['customer']->uuid,
            'ordered_at' => '2026-09-23T10:30:00Z',
            'payment_type' => 'cash',
            'items' => [[
                'product_id' => $actor['product']->uuid,
                'quantity' => $quantity,
                'discount_percent' => 0,
            ]],
        ], $this->headers($actor))->assertCreated();

        return app(TenantContext::class)->withTenant(
            $actor['tenant'],
            fn () => Order::where('uuid', $uuid)->firstOrFail(),
        );
    }

    private function balance(array $actor): SalesmanStockBalance
    {
        return app(TenantContext::class)->withTenant(
            $actor['tenant'],
            fn () => SalesmanStockBalance::where('salesman_id', $actor['salesman']->id)
                ->where('product_id', $actor['product']->id)
                ->firstOrFail(),
        );
    }

    private function headers(array $actor): array
    {
        return [
            'Authorization' => 'Bearer '.$actor['token'],
            'X-Device-UUID' => $actor['device']->device_uuid,
            'X-Installation-UUID' => $actor['device']->installation_uuid,
            'X-App-Version' => '1.0',
            'X-Platform' => 'android',
            'X-OS-Version' => '16',
        ];
    }
}
