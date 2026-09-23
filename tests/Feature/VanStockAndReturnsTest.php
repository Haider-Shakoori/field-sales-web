<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerReturn;
use App\Models\Device;
use App\Models\Order;
use App\Models\Permission;
use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Models\Role;
use App\Models\Salesman;
use App\Models\SalesmanStockBalance;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class VanStockAndReturnsTest extends TestCase
{
    use RefreshDatabase;

    private ?string $token = null;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow('2026-09-23T10:00:00Z');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_enabled_van_stock_reserves_consumes_and_restocks_order_quantity(): void
    {
        $actor = $this->fixture(10, true);

        $order = $this->createOrder($actor, 4);

        $this->assertBalance($actor, 10, 4, 0);

        $this->actingAs($actor['admin'])
            ->patch('/admin/orders/'.$order->id.'/status', [
                'status' => 'approved',
            ])
            ->assertRedirect(route('admin.orders.show', $order));

        $this->assertBalance($actor, 6, 0, 0);

        $this->actingAs($actor['admin'])
            ->patch('/admin/orders/'.$order->id.'/status', [
                'status' => 'cancelled',
                'status_note' => 'Customer cancelled before delivery.',
            ])
            ->assertRedirect(route('admin.orders.show', $order));

        $this->assertBalance($actor, 10, 0, 0);

        $this->assertDatabaseHas('salesman_stock_movements', [
            'order_id' => $order->id,
            'product_id' => $actor['product']->id,
            'movement_type' => 'order_reserved',
        ]);
        $this->assertDatabaseHas('salesman_stock_movements', [
            'order_id' => $order->id,
            'movement_type' => 'order_sold',
        ]);
        $this->assertDatabaseHas('salesman_stock_movements', [
            'order_id' => $order->id,
            'movement_type' => 'order_cancelled_restock',
        ]);
    }

    public function test_rejected_pending_order_releases_reservation_without_reducing_sellable_stock(): void
    {
        $actor = $this->fixture(10, true);
        $order = $this->createOrder($actor, 3);

        $this->assertBalance($actor, 10, 3, 0);

        $this->actingAs($actor['admin'])
            ->patch('/admin/orders/'.$order->id.'/status', [
                'status' => 'rejected',
                'status_note' => 'Order rejected by manager.',
            ])
            ->assertRedirect(route('admin.orders.show', $order));

        $this->assertBalance($actor, 10, 0, 0);
        $this->assertDatabaseHas('salesman_stock_movements', [
            'order_id' => $order->id,
            'movement_type' => 'order_released',
            'reserved_delta' => -3,
        ]);
    }

    public function test_order_is_rolled_back_when_enabled_van_stock_is_insufficient(): void
    {
        $actor = $this->fixture(2, true);

        $response = $this->postJson('/api/v1/orders', [
            'offline_uuid' => (string) Str::uuid(),
            'customer_id' => $actor['customer']->uuid,
            'ordered_at' => '2026-09-23T09:00:00Z',
            'payment_type' => 'cash',
            'items' => [[
                'product_id' => $actor['product']->uuid,
                'quantity' => 3,
            ]],
        ], $this->headers());

        $response
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');

        $this->assertArrayHasKey('items', $response->json('error.details'));
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertDatabaseCount('salesman_stock_movements', 0);
        $this->assertBalance($actor, 2, 0, 0);
    }

    public function test_mixed_condition_return_is_idempotent_and_approved_into_correct_stock_buckets(): void
    {
        $actor = $this->fixture(10, true);
        $order = $this->createOrder($actor, 4);

        $this->actingAs($actor['admin'])
            ->patch('/admin/orders/'.$order->id.'/status', [
                'status' => 'approved',
            ])
            ->assertRedirect();

        $this->assertBalance($actor, 6, 0, 0);

        auth()->guard('web')->logout();

        $uuid = (string) Str::uuid();
        $payload = [
            'offline_uuid' => $uuid,
            'customer_id' => $actor['customer']->uuid,
            'order_id' => $order->uuid,
            'returned_at' => '2026-09-23T09:30:00Z',
            'reason' => 'Customer return',
            'items' => [
                [
                    'product_id' => $actor['product']->uuid,
                    'quantity' => 1,
                    'condition' => 'sellable',
                    'reason' => 'Unopened',
                ],
                [
                    'product_id' => $actor['product']->uuid,
                    'quantity' => 1,
                    'condition' => 'damaged',
                    'reason' => 'Broken packaging',
                ],
            ],
        ];

        $this->postJson('/api/v1/returns', $payload, $this->headers())
            ->assertCreated()
            ->assertJsonPath('data.id', $uuid)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonCount(2, 'data.items');

        $this->postJson('/api/v1/returns', $payload, $this->headers())
            ->assertOk()
            ->assertJsonPath('data.id', $uuid);

        $this->assertDatabaseCount('customer_returns', 1);
        $this->assertDatabaseCount('customer_return_items', 2);
        $this->assertBalance($actor, 6, 0, 0);

        $customerReturn = app(TenantContext::class)->withTenant(
            $actor['tenant'],
            fn () => CustomerReturn::where('uuid', $uuid)->firstOrFail(),
        );

        $this->actingAs($actor['admin'])
            ->patch('/admin/returns/'.$customerReturn->id.'/status', [
                'status' => 'approved',
                'status_note' => 'Verified physically.',
            ])
            ->assertRedirect(route('admin.returns.show', $customerReturn));

        $this->assertBalance($actor, 7, 0, 1);

        $this->assertDatabaseHas('salesman_stock_movements', [
            'customer_return_id' => $customerReturn->id,
            'movement_type' => 'customer_return_sellable',
            'sellable_delta' => 1,
        ]);
        $this->assertDatabaseHas('salesman_stock_movements', [
            'customer_return_id' => $customerReturn->id,
            'movement_type' => 'customer_return_damaged',
            'damaged_delta' => 1,
        ]);

        auth()->guard('web')->logout();

        $response = $this->postJson('/api/v1/returns', [
            'offline_uuid' => (string) Str::uuid(),
            'customer_id' => $actor['customer']->uuid,
            'order_id' => $order->uuid,
            'returned_at' => '2026-09-23T09:45:00Z',
            'reason' => 'Excess return attempt',
            'items' => [[
                'product_id' => $actor['product']->uuid,
                'quantity' => 3,
                'condition' => 'sellable',
            ]],
        ], $this->headers());

        $response
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_disabled_van_stock_keeps_legacy_order_flow_unrestricted(): void
    {
        $actor = $this->fixture(0, false);

        $order = $this->createOrder($actor, 100);

        $this->assertSame('pending', $order->status);
        $this->assertDatabaseCount('salesman_stock_movements', 0);
        $this->assertBalance($actor, 0, 0, 0);
    }

    private function fixture(float $stockQuantity, bool $stockEnabled): array
    {
        $context = app(TenantContext::class);

        $tenant = $context->withPlatformScope(fn () => Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Van Stock Tenant',
            'slug' => 'van-stock-'.Str::lower(Str::random(6)),
            'timezone' => 'Asia/Kabul',
            'subscription_status' => 'active',
        ]));

        [$user, $admin, $salesman, $device, $product, $customer] = $context
            ->withTenant($tenant, function () use ($tenant, $stockEnabled): array {
                $salesRole = Role::create([
                    'tenant_id' => $tenant->id,
                    'name' => 'Salesman',
                    'slug' => 'test-salesman-'.Str::lower(Str::random(5)),
                    'is_system' => false,
                ]);

                $adminRole = Role::create([
                    'tenant_id' => $tenant->id,
                    'name' => 'Inventory Admin',
                    'slug' => 'test-inventory-admin-'.Str::lower(Str::random(5)),
                    'is_system' => false,
                ]);

                $permissionIds = collect([
                    'orders:view',
                    'customers:view',
                    'catalog:view',
                    'inventory:view',
                    'orders:manage',
                    'inventory:manage',
                ])->map(fn (string $slug) => Permission::firstOrCreate(
                    ['slug' => $slug],
                    [
                        'name' => str($slug)->replace(':', ' ')->title(),
                        'group' => str($slug)->before(':'),
                    ],
                ))->keyBy('slug');

                $salesRole->permissions()->sync(
                    $permissionIds
                        ->only(['orders:view', 'customers:view', 'catalog:view', 'inventory:view'])
                        ->pluck('id')
                        ->all(),
                );

                $adminRole->permissions()->sync(
                    $permissionIds
                        ->only(['orders:view', 'orders:manage', 'inventory:view', 'inventory:manage'])
                        ->pluck('id')
                        ->all(),
                );

                $user = User::create([
                    'uuid' => (string) Str::uuid(),
                    'tenant_id' => $tenant->id,
                    'name' => 'Van Stock Salesman',
                    'email' => 'van-sales-'.Str::lower(Str::random(5)).'@example.test',
                    'password' => Hash::make('password'),
                    'role' => 'salesman',
                    'is_active' => true,
                ]);
                $user->syncPrimaryRole($salesRole);

                $admin = User::create([
                    'uuid' => (string) Str::uuid(),
                    'tenant_id' => $tenant->id,
                    'name' => 'Van Stock Admin',
                    'email' => 'van-admin-'.Str::lower(Str::random(5)).'@example.test',
                    'password' => Hash::make('password'),
                    'role' => 'company_admin',
                    'is_active' => true,
                ]);
                $admin->syncPrimaryRole($adminRole);

                $salesman = Salesman::create([
                    'tenant_id' => $tenant->id,
                    'user_id' => $user->id,
                    'employee_code' => 'VAN-001',
                    'first_name' => 'Van',
                    'last_name' => 'Salesman',
                    'is_active' => true,
                    'van_stock_enabled' => $stockEnabled,
                ]);

                $device = Device::create([
                    'tenant_id' => $tenant->id,
                    'user_id' => $user->id,
                    'salesman_id' => $salesman->id,
                    'device_uuid' => 'van-device',
                    'installation_uuid' => 'van-install',
                    'is_active' => true,
                ]);

                $product = Product::create([
                    'sku' => 'VAN-PROD-1',
                    'name' => 'Van Product',
                    'unit' => 'pcs',
                    'base_price' => 100,
                    'currency' => 'AFN',
                    'is_active' => true,
                ]);

                $priceList = PriceList::create([
                    'code' => 'VAN-RETAIL',
                    'name' => 'Van Retail',
                    'currency' => 'AFN',
                    'effective_from' => '2026-09-01',
                    'is_active' => true,
                ]);

                PriceListItem::create([
                    'price_list_id' => $priceList->id,
                    'product_id' => $product->id,
                    'min_quantity' => 1,
                    'price' => 100,
                ]);

                $customer = Customer::create([
                    'code' => 'VAN-CUS-1',
                    'name' => 'Van Customer',
                    'price_list_id' => $priceList->id,
                    'created_by' => $user->id,
                    'is_active' => true,
                ]);

                return [$user, $admin, $salesman, $device, $product, $customer];
            });

        $context->withTenant($tenant, function () use (
            $salesman,
            $product,
            $stockQuantity,
        ): void {
            SalesmanStockBalance::create([
                'salesman_id' => $salesman->id,
                'product_id' => $product->id,
                'sellable_quantity' => $stockQuantity,
                'reserved_quantity' => 0,
                'damaged_quantity' => 0,
            ]);
        });

        $this->token = $context->withTenant(
            $tenant,
            fn () => $user->createToken('mobile-'.$device->uuid)->plainTextToken,
        );

        return compact(
            'tenant',
            'user',
            'admin',
            'salesman',
            'device',
            'product',
            'customer',
        );
    }

    private function createOrder(array $actor, float $quantity): Order
    {
        $uuid = (string) Str::uuid();

        $this->postJson('/api/v1/orders', [
            'offline_uuid' => $uuid,
            'customer_id' => $actor['customer']->uuid,
            'ordered_at' => '2026-09-23T09:00:00Z',
            'payment_type' => 'cash',
            'items' => [[
                'product_id' => $actor['product']->uuid,
                'quantity' => $quantity,
                'discount_percent' => 0,
            ]],
        ], $this->headers())->assertCreated();

        return app(TenantContext::class)->withTenant(
            $actor['tenant'],
            fn () => Order::where('uuid', $uuid)->firstOrFail(),
        );
    }

    private function assertBalance(
        array $actor,
        float $sellable,
        float $reserved,
        float $damaged,
    ): void {
        $balance = app(TenantContext::class)->withTenant(
            $actor['tenant'],
            fn () => SalesmanStockBalance::where('salesman_id', $actor['salesman']->id)
                ->where('product_id', $actor['product']->id)
                ->firstOrFail(),
        );

        $this->assertEqualsWithDelta($sellable, (float) $balance->sellable_quantity, 0.0001);
        $this->assertEqualsWithDelta($reserved, (float) $balance->reserved_quantity, 0.0001);
        $this->assertEqualsWithDelta($damaged, (float) $balance->damaged_quantity, 0.0001);
    }

    private function headers(): array
    {
        return [
            'Authorization' => 'Bearer '.$this->token,
            'X-Device-UUID' => 'van-device',
            'X-Installation-UUID' => 'van-install',
            'X-App-Version' => '1.0',
            'X-Platform' => 'android',
            'X-OS-Version' => '16',
        ];
    }
}
