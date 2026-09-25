<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\CommissionRule;
use App\Models\Customer;
use App\Models\Device;
use App\Models\Order;
use App\Models\Product;
use App\Models\Salesman;
use App\Models\SalesTarget;
use App\Models\Tenant;
use App\Models\Territory;
use App\Models\User;
use App\Services\CommissionEngine;
use App\Services\TenantProvisioningService;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CommissionEngineTest extends TestCase
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

    public function test_sales_and_product_rules_stack_without_mixing_currencies(): void
    {
        $f = $this->fixture();

        app(TenantContext::class)->withTenant($f['tenant'], function () use ($f): void {
            CommissionRule::create([
                'code' => 'SALES10',
                'name' => 'All sales 10 percent',
                'basis_type' => 'sales_amount',
                'reward_type' => 'percentage',
                'rate' => 10,
                'effective_from' => '2026-09-01',
                'is_active' => true,
                'created_by' => $f['admin']->id,
            ]);
            CommissionRule::create([
                'code' => 'PRODUCT5',
                'name' => 'Product A territory bonus',
                'basis_type' => 'product_sales_amount',
                'reward_type' => 'percentage',
                'rate' => 5,
                'territory_id' => $f['territory']->id,
                'product_id' => $f['productA']->id,
                'effective_from' => '2026-09-01',
                'is_active' => true,
                'created_by' => $f['admin']->id,
            ]);

            $afn = $this->order($f, 'AFN', 1000, '2026-09-10T08:00:00Z');
            $afn->items()->create([
                'product_id' => $f['productA']->id,
                'product_sku' => $f['productA']->sku,
                'product_name' => $f['productA']->name,
                'unit' => 'pcs',
                'quantity' => 6,
                'unit_price' => 100,
                'discount_percent' => 0,
                'discount_amount' => 0,
                'line_total' => 600,
            ]);
            $afn->items()->create([
                'product_id' => $f['productB']->id,
                'product_sku' => $f['productB']->sku,
                'product_name' => $f['productB']->name,
                'unit' => 'pcs',
                'quantity' => 4,
                'unit_price' => 100,
                'discount_percent' => 0,
                'discount_amount' => 0,
                'line_total' => 400,
            ]);

            $usd = $this->order($f, 'USD', 200, '2026-09-12T08:00:00Z');
            $usd->items()->create([
                'product_id' => $f['productA']->id,
                'product_sku' => $f['productA']->sku,
                'product_name' => $f['productA']->name,
                'unit' => 'pcs',
                'quantity' => 2,
                'unit_price' => 100,
                'discount_percent' => 0,
                'discount_amount' => 0,
                'line_total' => 200,
            ]);
        });

        $run = app(TenantContext::class)->withTenant(
            $f['tenant'],
            fn () => app(CommissionEngine::class)->generate(
                $f['admin'],
                '2026-09-01',
                '2026-09-30',
            ),
        );

        $byRuleCurrency = $run->lines
            ->keyBy(fn ($line) => $line->rule->code.'|'.$line->currency);

        $this->assertSame(100.0, (float) $byRuleCurrency['SALES10|AFN']->commission_amount);
        $this->assertSame(20.0, (float) $byRuleCurrency['SALES10|USD']->commission_amount);
        $this->assertSame(30.0, (float) $byRuleCurrency['PRODUCT5|AFN']->commission_amount);
        $this->assertSame(10.0, (float) $byRuleCurrency['PRODUCT5|USD']->commission_amount);

        $totals = collect(app(CommissionEngine::class)->totals($run))->keyBy('currency');
        $this->assertSame(130.0, $totals['AFN']['commission']);
        $this->assertSame(30.0, $totals['USD']['commission']);
    }

    public function test_target_achievement_pays_fixed_reward_per_achieved_target(): void
    {
        $f = $this->fixture();

        app(TenantContext::class)->withTenant($f['tenant'], function () use ($f): void {
            CommissionRule::create([
                'code' => 'TARGET100',
                'name' => 'Target achieved bonus',
                'basis_type' => 'target_achievement',
                'reward_type' => 'fixed',
                'rate' => 100,
                'currency' => 'AFN',
                'target_type' => 'sales_amount',
                'effective_from' => '2026-09-01',
                'is_active' => true,
                'created_by' => $f['admin']->id,
            ]);
            SalesTarget::create([
                'salesman_id' => $f['salesman']->id,
                'target_type' => 'sales_amount',
                'currency' => 'AFN',
                'target_value' => 500,
                'period_start' => '2026-09-01',
                'period_end' => '2026-09-30',
                'created_by' => $f['admin']->id,
            ]);
            $this->order($f, 'AFN', 600, '2026-09-15T08:00:00Z');
        });

        $run = app(TenantContext::class)->withTenant(
            $f['tenant'],
            fn () => app(CommissionEngine::class)->generate(
                $f['admin'],
                '2026-09-01',
                '2026-09-30',
            ),
        );

        $line = $run->lines->sole();
        $this->assertSame('target_achievement', $line->basis_type);
        $this->assertSame('AFN', $line->currency);
        $this->assertSame(1.0, (float) $line->basis_value);
        $this->assertSame(100.0, (float) $line->commission_amount);
    }

    public function test_approved_run_is_locked_and_overlapping_approved_period_is_blocked(): void
    {
        $f = $this->fixture();

        app(TenantContext::class)->withTenant($f['tenant'], function () use ($f): void {
            CommissionRule::create([
                'code' => 'LOCK5',
                'name' => 'Lock test',
                'basis_type' => 'sales_amount',
                'reward_type' => 'percentage',
                'rate' => 5,
                'effective_from' => '2026-09-01',
                'is_active' => true,
                'created_by' => $f['admin']->id,
            ]);
            $this->order($f, 'AFN', 1000, '2026-09-05T08:00:00Z');
        });

        $run = app(TenantContext::class)->withTenant(
            $f['tenant'],
            fn () => app(CommissionEngine::class)->generate(
                $f['admin'],
                '2026-09-01',
                '2026-09-15',
            ),
        );

        $this->actingAs($f['admin'])
            ->post(route('admin.commissions.runs.approve', $run))
            ->assertRedirect();

        $run->refresh();
        $this->assertSame('approved', $run->state);
        $this->assertNotNull($run->approved_at);

        app(TenantContext::class)->withTenant($f['tenant'], function () use ($f): void {
            try {
                app(CommissionEngine::class)->generate(
                    $f['admin'],
                    '2026-09-10',
                    '2026-09-20',
                );
                $this->fail('Expected overlap validation exception.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('period_start', $exception->errors());
            }
        });

        $this->actingAs($f['admin'])
            ->get(route('admin.commissions.runs.show', $run))
            ->assertOk()
            ->assertSeeText('This run is approved and immutable.');
    }

    public function test_company_admin_can_manage_rules_and_commission_dashboard(): void
    {
        $f = $this->fixture();

        $this->actingAs($f['admin'])
            ->get(route('admin.commissions.index'))
            ->assertOk()
            ->assertSeeText('Commissions');

        $this->actingAs($f['admin'])
            ->post(route('admin.commissions.rules.store'), [
                'code' => 'WEB2',
                'name' => 'Web sales rule',
                'basis_type' => 'sales_amount',
                'reward_type' => 'percentage',
                'rate' => 2,
                'minimum_basis' => 100,
                'effective_from' => '2026-09-01',
                'is_active' => 1,
            ])
            ->assertRedirect(route('admin.commissions.index'));

        $this->assertDatabaseHas('commission_rules', [
            'code' => 'WEB2',
            'reward_type' => 'percentage',
        ]);
    }

    private function order(
        array $f,
        string $currency,
        float $total,
        string $orderedAt,
    ): Order {
        return Order::create([
            'user_id' => $f['salesmanUser']->id,
            'salesman_id' => $f['salesman']->id,
            'device_id' => $f['device']->id,
            'customer_id' => $f['customer']->id,
            'order_number' => 'COM-'.str()->upper(str()->random(10)),
            'ordered_at' => $orderedAt,
            'payment_type' => 'cash',
            'status' => 'approved',
            'currency' => $currency,
            'subtotal' => $total,
            'discount_total' => 0,
            'grand_total' => $total,
        ]);
    }

    private function fixture(): array
    {
        $context = app(TenantContext::class);
        $tenant = $context->withPlatformScope(fn () => Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Commission Tenant',
            'slug' => 'commission-'.Str::lower(Str::random(6)),
            'timezone' => 'Asia/Kabul',
            'subscription_status' => 'active',
        ]));

        return $context->withTenant($tenant, function () use ($tenant): array {
            $roles = app(TenantProvisioningService::class)->provisionRbac($tenant);
            $branch = Branch::create(['name' => 'Main', 'code' => 'MAIN', 'is_active' => true]);
            $territory = Territory::create([
                'branch_id' => $branch->id,
                'code' => 'COMM-T',
                'name' => 'Commission Territory',
                'is_active' => true,
            ]);
            $admin = User::create([
                'uuid' => (string) Str::uuid(),
                'branch_id' => $branch->id,
                'name' => 'Commission Admin',
                'email' => 'commission-admin@example.test',
                'password' => Hash::make('password'),
                'role' => 'company_admin',
                'is_active' => true,
            ]);
            $admin->syncPrimaryRole($roles['company_admin']);
            $salesmanUser = User::create([
                'uuid' => (string) Str::uuid(),
                'branch_id' => $branch->id,
                'name' => 'Commission Salesman',
                'email' => 'commission-salesman@example.test',
                'password' => Hash::make('password'),
                'role' => 'salesman',
                'is_active' => true,
            ]);
            $salesmanUser->syncPrimaryRole($roles['salesman']);
            $salesman = Salesman::create([
                'user_id' => $salesmanUser->id,
                'employee_code' => 'COM-1',
                'first_name' => 'Commission',
                'last_name' => 'Salesman',
                'is_active' => true,
            ]);
            $customer = Customer::create([
                'branch_id' => $branch->id,
                'territory_id' => $territory->id,
                'code' => 'COM-C1',
                'name' => 'Commission Customer',
                'created_by' => $admin->id,
                'is_active' => true,
            ]);
            $productA = Product::create([
                'sku' => 'COM-A',
                'name' => 'Commission Product A',
                'unit' => 'pcs',
                'base_price' => 100,
                'currency' => 'AFN',
                'is_active' => true,
            ]);
            $productB = Product::create([
                'sku' => 'COM-B',
                'name' => 'Commission Product B',
                'unit' => 'pcs',
                'base_price' => 100,
                'currency' => 'AFN',
                'is_active' => true,
            ]);
            $device = Device::create([
                'user_id' => $salesmanUser->id,
                'salesman_id' => $salesman->id,
                'device_uuid' => 'commission-device',
                'installation_uuid' => 'commission-install',
                'is_active' => true,
            ]);

            return compact(
                'tenant',
                'branch',
                'territory',
                'admin',
                'salesmanUser',
                'salesman',
                'customer',
                'productA',
                'productB',
                'device',
            );
        });
    }
}
