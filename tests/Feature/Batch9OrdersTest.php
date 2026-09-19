<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerVisit;
use App\Models\Device;
use App\Models\Order;
use App\Models\Permission;
use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Models\Role;
use App\Models\Salesman;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class Batch9OrdersTest extends TestCase
{
    use RefreshDatabase;

    private ?string $token = null;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-09-19T07:00:00Z');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_offline_order_is_idempotent_visit_linked_and_server_priced(): void
    {
        $actor = $this->salesmanActor();
        [$customer, $product] = $this->catalog($actor);
        $visit = $this->visit($actor, $customer);
        $uuid = (string) Str::uuid();

        $payload = [
            'offline_uuid' => $uuid,
            'customer_id' => $customer->uuid,
            'visit_id' => $visit->uuid,
            'ordered_at' => '2026-09-19T06:00:00Z',
            'payment_type' => 'credit',
            'client_estimated_total' => 999,
            'notes' => 'Offline order',
            'items' => [[
                'product_id' => $product->uuid,
                'quantity' => 10,
                'discount_percent' => 5,
            ]],
        ];

        $this->postJson('/api/v1/orders', $payload, $this->headers())
            ->assertCreated()
            ->assertJsonPath('data.id', $uuid)
            ->assertJsonPath('data.visit_id', $visit->uuid)
            ->assertJsonPath('data.payment_type', 'credit')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.items.0.unit_price', 80)
            ->assertJsonPath('data.items.0.discount_percent', 5)
            ->assertJsonPath('data.subtotal', 800)
            ->assertJsonPath('data.discount_total', 40)
            ->assertJsonPath('data.grand_total', 760)
            ->assertJsonPath('data.pricing_adjusted', true);

        $this->postJson('/api/v1/orders', $payload, $this->headers())
            ->assertOk()
            ->assertJsonPath('data.id', $uuid);

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);
        $this->assertDatabaseHas('orders', [
            'uuid' => $uuid,
            'status' => 'pending',
            'grand_total' => 760,
            'pricing_adjusted' => 1,
        ]);

        $this->getJson('/api/v1/orders/history', $this->headers())
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $uuid);
    }

    public function test_authorized_admin_can_approve_pending_order_and_transition_is_audited(): void
    {
        $actor = $this->salesmanActor();
        [$customer, $product] = $this->catalog($actor);

        $this->postJson('/api/v1/orders', [
            'offline_uuid' => (string) Str::uuid(),
            'customer_id' => $customer->uuid,
            'ordered_at' => '2026-09-19T06:15:00Z',
            'payment_type' => 'cash',
            'items' => [[
                'product_id' => $product->uuid,
                'quantity' => 2,
                'discount_percent' => 0,
            ]],
        ], $this->headers())->assertCreated();

        $order = Order::firstOrFail();
        $admin = $this->admin($actor['tenant']);

        $this->actingAs($admin)
            ->patch('/admin/orders/'.$order->id.'/status', [
                'status' => 'approved',
            ])
            ->assertRedirect(route('admin.orders.show', $order));

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'approved',
            'status_changed_by' => $admin->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'order.status_changed',
            'subject_id' => $order->id,
        ]);
    }

    public function test_duplicate_product_lines_are_rejected(): void
    {
        $actor = $this->salesmanActor();
        [$customer, $product] = $this->catalog($actor);

        $this->postJson('/api/v1/orders', [
            'offline_uuid' => (string) Str::uuid(),
            'customer_id' => $customer->uuid,
            'ordered_at' => '2026-09-19T06:20:00Z',
            'payment_type' => 'cash',
            'items' => [
                ['product_id' => $product->uuid, 'quantity' => 1],
                ['product_id' => $product->uuid, 'quantity' => 2],
            ],
        ], $this->headers())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items.1.product_id');
    }

    private function salesmanActor(): array
    {
        $tenant = app(TenantContext::class)->withPlatformScope(fn () => Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Order Tenant',
            'slug' => 'order-tenant',
            'timezone' => 'Asia/Kabul',
        ]));

        [$user, $salesman, $device] = app(TenantContext::class)->withTenant(
            $tenant,
            function () use ($tenant): array {
                $user = User::create([
                    'uuid' => (string) Str::uuid(),
                    'tenant_id' => $tenant->id,
                    'name' => 'Order Salesman',
                    'email' => 'orders-salesman@example.test',
                    'password' => Hash::make('password'),
                    'role' => 'salesman',
                    'is_active' => true,
                ]);

                $role = Role::create([
                    'tenant_id' => $tenant->id,
                    'name' => 'Salesman',
                    'slug' => 'salesman',
                    'is_system' => true,
                ]);

                $permissionIds = collect(['orders:view', 'customers:view', 'catalog:view'])
                    ->map(fn (string $slug) => Permission::firstOrCreate(
                        ['slug' => $slug],
                        ['name' => str($slug)->replace(':', ' ')->title(), 'group' => str($slug)->before(':')]
                    )->id)
                    ->all();

                $role->permissions()->sync($permissionIds);
                $user->syncPrimaryRole($role);

                $salesman = Salesman::create([
                    'tenant_id' => $tenant->id,
                    'user_id' => $user->id,
                    'employee_code' => 'ORD-SAL-1',
                    'first_name' => 'Order',
                    'last_name' => 'Salesman',
                    'is_active' => true,
                ]);

                $device = Device::create([
                    'tenant_id' => $tenant->id,
                    'user_id' => $user->id,
                    'salesman_id' => $salesman->id,
                    'device_uuid' => 'order-device',
                    'installation_uuid' => 'order-install',
                    'is_active' => true,
                ]);

                return [$user, $salesman, $device];
            }
        );

        $this->token = app(TenantContext::class)->withTenant(
            $tenant,
            fn () => $user->createToken('mobile-'.$device->uuid)->plainTextToken
        );

        return compact('tenant', 'user', 'salesman', 'device');
    }

    private function catalog(array $actor): array
    {
        return app(TenantContext::class)->withTenant(
            $actor['tenant'],
            function () use ($actor): array {
                $product = Product::create([
                    'sku' => 'PROD-ORDER-1',
                    'name' => 'Order Product',
                    'unit' => 'pcs',
                    'base_price' => 100,
                    'currency' => 'AFN',
                    'is_active' => true,
                ]);

                $priceList = PriceList::create([
                    'code' => 'ORDER-RETAIL',
                    'name' => 'Order Retail',
                    'currency' => 'AFN',
                    'effective_from' => '2026-09-01',
                    'is_active' => true,
                ]);

                PriceListItem::create([
                    'price_list_id' => $priceList->id,
                    'product_id' => $product->id,
                    'min_quantity' => 1,
                    'price' => 90,
                ]);

                PriceListItem::create([
                    'price_list_id' => $priceList->id,
                    'product_id' => $product->id,
                    'min_quantity' => 10,
                    'price' => 80,
                ]);

                $customer = Customer::create([
                    'code' => 'ORD-CUS-1',
                    'name' => 'Order Customer',
                    'price_list_id' => $priceList->id,
                    'created_by' => $actor['user']->id,
                    'is_active' => true,
                ]);

                return [$customer, $product];
            }
        );
    }

    private function visit(array $actor, Customer $customer): CustomerVisit
    {
        return app(TenantContext::class)->withTenant(
            $actor['tenant'],
            fn () => CustomerVisit::create([
                'user_id' => $actor['user']->id,
                'salesman_id' => $actor['salesman']->id,
                'device_id' => $actor['device']->id,
                'customer_id' => $customer->id,
                'is_planned' => false,
                'status' => 'completed',
                'outcome' => 'order_placed',
                'checked_in_at' => '2026-09-19 05:30:00',
                'checked_out_at' => '2026-09-19 05:45:00',
                'checkin_latitude' => 34.5,
                'checkin_longitude' => 69.2,
                'checkin_accuracy' => 8,
                'checkout_latitude' => 34.5,
                'checkout_longitude' => 69.2,
                'checkout_accuracy' => 8,
                'duration_seconds' => 900,
            ])
        );
    }

    private function admin(Tenant $tenant): User
    {
        return app(TenantContext::class)->withTenant($tenant, function () use ($tenant): User {
            $admin = User::create([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'name' => 'Order Admin',
                'email' => 'orders-admin@example.test',
                'password' => Hash::make('password'),
                'role' => 'company_admin',
                'is_active' => true,
            ]);

            $role = Role::create([
                'tenant_id' => $tenant->id,
                'name' => 'Order Admin',
                'slug' => 'order-admin',
                'is_system' => false,
            ]);

            $permissionIds = collect(['orders:view', 'orders:manage'])
                ->map(fn (string $slug) => Permission::firstOrCreate(
                    ['slug' => $slug],
                    ['name' => str($slug)->replace(':', ' ')->title(), 'group' => 'orders']
                )->id)
                ->all();

            $role->permissions()->sync($permissionIds);
            $admin->syncPrimaryRole($role);

            return $admin;
        });
    }

    private function headers(): array
    {
        return [
            'Authorization' => 'Bearer '.$this->token,
            'X-Device-UUID' => 'order-device',
            'X-Installation-UUID' => 'order-install',
            'X-App-Version' => '1.0',
            'X-Platform' => 'android',
            'X-OS-Version' => '16',
        ];
    }
}
