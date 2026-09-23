<?php

namespace Tests\Feature;

use App\Models\Collection;
use App\Models\Customer;
use App\Models\Device;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Salesman;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantProvisioningService;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class InvoiceAndCustomerStatementTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_approved_order_can_render_printable_invoice(): void
    {
        $actor = $this->actor();
        $approved = $this->order(
            $actor,
            'ORD-INV-001',
            'approved',
            'credit',
            'AFN',
            750,
            '2026-09-12 08:00:00',
        );
        $pending = $this->order(
            $actor,
            'ORD-INV-002',
            'pending',
            'cash',
            'AFN',
            100,
            '2026-09-12 09:00:00',
        );

        $this->actingAs($actor['admin'])
            ->get('/admin/orders/'.$approved->id.'/invoice')
            ->assertOk()
            ->assertSee('INVOICE')
            ->assertSee('ORD-INV-001')
            ->assertSee('Statement Customer')
            ->assertSee('750.00 AFN');

        $this->actingAs($actor['admin'])
            ->get('/admin/orders/'.$pending->id.'/invoice')
            ->assertNotFound();
    }

    public function test_statement_uses_only_approved_credit_orders_and_verified_collections_in_selected_currency(): void
    {
        $actor = $this->actor();

        $this->order(
            $actor,
            'ORD-OPENING',
            'approved',
            'credit',
            'AFN',
            1000,
            '2026-08-20 08:00:00',
        );
        $this->collection(
            $actor,
            'REC-OPENING',
            'verified',
            'AFN',
            200,
            '2026-08-25 08:00:00',
        );

        $this->order(
            $actor,
            'ORD-CURRENT',
            'approved',
            'credit',
            'AFN',
            500,
            '2026-09-10 08:00:00',
        );
        $this->collection(
            $actor,
            'REC-CURRENT',
            'verified',
            'AFN',
            300,
            '2026-09-15 08:00:00',
        );

        $this->order(
            $actor,
            'ORD-CASH-EXCLUDED',
            'approved',
            'cash',
            'AFN',
            50,
            '2026-09-11 08:00:00',
        );
        $this->order(
            $actor,
            'ORD-REJECTED-EXCLUDED',
            'rejected',
            'credit',
            'AFN',
            90,
            '2026-09-12 08:00:00',
        );
        $this->order(
            $actor,
            'ORD-USD-EXCLUDED',
            'approved',
            'credit',
            'USD',
            100,
            '2026-09-13 08:00:00',
        );
        $this->collection(
            $actor,
            'REC-PENDING-EXCLUDED',
            'pending',
            'AFN',
            100,
            '2026-09-16 08:00:00',
        );

        $response = $this->actingAs($actor['admin'])->get(
            '/admin/customers/'.$actor['customer']->id
            .'/statement?from=2026-09-01&to=2026-09-30&currency=AFN',
        );

        $response
            ->assertOk()
            ->assertSee('ORD-CURRENT')
            ->assertSee('REC-CURRENT')
            ->assertSee('800.00 AFN')
            ->assertSee('500.00 AFN')
            ->assertSee('300.00 AFN')
            ->assertSee('1,000.00 AFN')
            ->assertDontSee('ORD-CASH-EXCLUDED')
            ->assertDontSee('ORD-REJECTED-EXCLUDED')
            ->assertDontSee('ORD-USD-EXCLUDED')
            ->assertDontSee('REC-PENDING-EXCLUDED');
    }

    private function actor(): array
    {
        $tenant = app(TenantContext::class)->withPlatformScope(
            fn () => Tenant::create([
                'uuid' => (string) Str::uuid(),
                'name' => 'Statement Tenant',
                'slug' => 'statement-tenant',
                'timezone' => 'Asia/Kabul',
                'subscription_status' => 'active',
            ]),
        );

        return app(TenantContext::class)->withTenant(
            $tenant,
            function () use ($tenant): array {
                $roles = app(TenantProvisioningService::class)
                    ->provisionRbac($tenant);

                $admin = User::create([
                    'uuid' => (string) Str::uuid(),
                    'tenant_id' => $tenant->id,
                    'name' => 'Statement Admin',
                    'email' => 'statement-admin@example.test',
                    'password' => Hash::make('password'),
                    'role' => 'company_admin',
                    'is_active' => true,
                ]);
                $admin->syncPrimaryRole($roles['company_admin']);

                $salesUser = User::create([
                    'uuid' => (string) Str::uuid(),
                    'tenant_id' => $tenant->id,
                    'name' => 'Statement Salesman',
                    'email' => 'statement-salesman@example.test',
                    'password' => Hash::make('password'),
                    'role' => 'salesman',
                    'is_active' => true,
                ]);
                $salesUser->syncPrimaryRole($roles['salesman']);

                $salesman = Salesman::create([
                    'tenant_id' => $tenant->id,
                    'user_id' => $salesUser->id,
                    'employee_code' => 'STM-001',
                    'first_name' => 'Statement',
                    'last_name' => 'Salesman',
                    'is_active' => true,
                ]);

                $device = Device::create([
                    'tenant_id' => $tenant->id,
                    'user_id' => $salesUser->id,
                    'salesman_id' => $salesman->id,
                    'device_uuid' => 'statement-device',
                    'installation_uuid' => 'statement-install',
                    'is_active' => true,
                ]);

                $customer = Customer::create([
                    'tenant_id' => $tenant->id,
                    'code' => 'STM-CUS-001',
                    'name' => 'Statement Customer',
                    'credit_currency' => 'AFN',
                    'credit_terms_days' => 30,
                    'created_by' => $admin->id,
                    'is_active' => true,
                ]);

                $product = Product::create([
                    'tenant_id' => $tenant->id,
                    'sku' => 'STM-PROD-001',
                    'name' => 'Statement Product',
                    'unit' => 'pcs',
                    'base_price' => 100,
                    'currency' => 'AFN',
                    'is_active' => true,
                ]);

                return compact(
                    'tenant',
                    'admin',
                    'salesUser',
                    'salesman',
                    'device',
                    'customer',
                    'product',
                );
            },
        );
    }

    private function order(
        array $actor,
        string $number,
        string $status,
        string $paymentType,
        string $currency,
        float $amount,
        string $orderedAt,
    ): Order {
        return app(TenantContext::class)->withTenant(
            $actor['tenant'],
            function () use (
                $actor,
                $number,
                $status,
                $paymentType,
                $currency,
                $amount,
                $orderedAt,
            ): Order {
                $order = Order::create([
                    'tenant_id' => $actor['tenant']->id,
                    'user_id' => $actor['salesUser']->id,
                    'salesman_id' => $actor['salesman']->id,
                    'device_id' => $actor['device']->id,
                    'customer_id' => $actor['customer']->id,
                    'order_number' => $number,
                    'ordered_at' => $orderedAt,
                    'payment_type' => $paymentType,
                    'status' => $status,
                    'currency' => $currency,
                    'subtotal' => $amount,
                    'discount_total' => 0,
                    'grand_total' => $amount,
                    'pricing_adjusted' => false,
                    'due_date' => $paymentType === 'credit'
                        ? '2026-10-10'
                        : null,
                ]);

                OrderItem::create([
                    'tenant_id' => $actor['tenant']->id,
                    'order_id' => $order->id,
                    'product_id' => $actor['product']->id,
                    'product_sku' => $actor['product']->sku,
                    'product_name' => $actor['product']->name,
                    'unit' => $actor['product']->unit,
                    'quantity' => 1,
                    'unit_price' => $amount,
                    'discount_percent' => 0,
                    'discount_amount' => 0,
                    'line_total' => $amount,
                ]);

                return $order;
            },
        );
    }

    private function collection(
        array $actor,
        string $number,
        string $status,
        string $currency,
        float $amount,
        string $collectedAt,
    ): Collection {
        return app(TenantContext::class)->withTenant(
            $actor['tenant'],
            fn () => Collection::create([
                'tenant_id' => $actor['tenant']->id,
                'user_id' => $actor['salesUser']->id,
                'salesman_id' => $actor['salesman']->id,
                'device_id' => $actor['device']->id,
                'customer_id' => $actor['customer']->id,
                'receipt_number' => $number,
                'collected_at' => $collectedAt,
                'currency' => $currency,
                'amount' => $amount,
                'payment_method' => 'cash',
                'status' => $status,
                'latitude' => 34.5,
                'longitude' => 69.2,
                'accuracy' => 8,
                'balance_before' => 1000,
                'overpayment_flag' => false,
            ]),
        );
    }
}
