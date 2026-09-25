<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Collection;
use App\Models\Customer;
use App\Models\Device;
use App\Models\Expense;
use App\Models\OperationalAnomaly;
use App\Models\Order;
use App\Models\Salesman;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantProvisioningService;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class OperationalAnomalyDetectionTest extends TestCase
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

    public function test_alert_scan_persists_collection_expense_and_order_anomalies(): void
    {
        $f = $this->fixture();

        app(TenantContext::class)->withTenant($f['tenant'], function () use ($f): void {
            foreach ([100, 100, 100, 100, 500] as $index => $amount) {
                Expense::create([
                    'user_id' => $f['salesmanUser']->id,
                    'salesman_id' => $f['salesman']->id,
                    'device_id' => $f['device']->id,
                    'expense_number' => 'EXP-A-'.$index,
                    'spent_at' => now()->subDays(5 - $index),
                    'category' => 'meals',
                    'currency' => 'AFN',
                    'amount' => $amount,
                    'latitude' => 34.55,
                    'longitude' => 69.20,
                    'accuracy' => 8,
                    'status' => 'approved',
                ]);
            }

            foreach ([200, 200, 200, 200, 1200] as $index => $amount) {
                Order::create([
                    'user_id' => $f['salesmanUser']->id,
                    'salesman_id' => $f['salesman']->id,
                    'device_id' => $f['device']->id,
                    'customer_id' => $f['customer']->id,
                    'order_number' => 'ORD-A-'.$index,
                    'ordered_at' => now()->subDays(5 - $index),
                    'payment_type' => 'cash',
                    'status' => 'approved',
                    'currency' => 'AFN',
                    'subtotal' => $amount,
                    'discount_total' => 0,
                    'grand_total' => $amount,
                ]);
            }

            Collection::create([
                'user_id' => $f['salesmanUser']->id,
                'salesman_id' => $f['salesman']->id,
                'device_id' => $f['device']->id,
                'customer_id' => $f['customer']->id,
                'receipt_number' => 'RC-ANOM-1',
                'collected_at' => now()->subDay(),
                'currency' => 'AFN',
                'amount' => 1000,
                'payment_method' => 'cash',
                'status' => 'verified',
                'latitude' => 34.60,
                'longitude' => 69.30,
                'accuracy' => 10,
                'distance_meters' => 420,
                'within_geofence' => false,
                'balance_before' => 500,
                'overpayment_flag' => true,
            ]);
        });

        $this->actingAs($f['admin'])
            ->get(route('admin.alerts.index', [
                'date_from' => '2026-09-01',
                'date_to' => '2026-09-25',
            ]))
            ->assertOk()
            ->assertSeeText('Operational alerts & anomalies')
            ->assertSee('Collection exceeds available balance')
            ->assertSee('Expense is unusually high for its peer history')
            ->assertSee('Order value is unusually high for this customer');

        app(TenantContext::class)->withTenant($f['tenant'], function (): void {
            $this->assertDatabaseHas('operational_anomalies', ['rule_code' => 'collection_overpayment', 'state' => 'open']);
            $this->assertDatabaseHas('operational_anomalies', ['rule_code' => 'collection_outside_geofence', 'state' => 'open']);
            $this->assertDatabaseHas('operational_anomalies', ['rule_code' => 'expense_amount_outlier', 'state' => 'open']);
            $this->assertDatabaseHas('operational_anomalies', ['rule_code' => 'order_value_outlier', 'state' => 'open']);
            $this->assertSame(4, OperationalAnomaly::count());
        });
    }

    public function test_reviewed_anomaly_stays_reviewed_after_rescan(): void
    {
        $f = $this->fixture();

        $anomaly = app(TenantContext::class)->withTenant($f['tenant'], fn () => OperationalAnomaly::create([
            'salesman_id' => $f['salesman']->id,
            'entity_type' => 'collection',
            'entity_id' => 999,
            'rule_code' => 'collection_overpayment',
            'severity' => 'high',
            'state' => 'open',
            'title' => 'Collection exceeds available balance',
            'summary' => 'Test anomaly.',
            'evidence' => ['receipt_number' => 'TEST'],
            'fingerprint' => hash('sha256', 'collection_overpayment|collection|999'),
            'occurred_at' => now()->subDay(),
            'first_detected_at' => now(),
            'last_detected_at' => now(),
        ]));

        $this->actingAs($f['admin'])
            ->patch(route('admin.alerts.anomalies.review', $anomaly), [
                'review_notes' => 'Verified with finance.',
            ])
            ->assertRedirect();

        $anomaly->refresh();
        $this->assertSame('reviewed', $anomaly->state);
        $this->assertSame('Verified with finance.', $anomaly->review_notes);
        $this->assertNotNull($anomaly->reviewed_at);

        $this->actingAs($f['admin'])
            ->get(route('admin.alerts.index', [
                'date_from' => '2026-09-01',
                'date_to' => '2026-09-25',
            ]))
            ->assertOk();

        $this->assertSame('reviewed', $anomaly->fresh()->state);
    }

    private function fixture(): array
    {
        $context = app(TenantContext::class);
        $tenant = $context->withPlatformScope(fn () => Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Anomaly Tenant',
            'slug' => 'anomaly-'.Str::lower(Str::random(6)),
            'timezone' => 'Asia/Kabul',
            'subscription_status' => 'active',
        ]));

        return $context->withTenant($tenant, function () use ($tenant): array {
            $roles = app(TenantProvisioningService::class)->provisionRbac($tenant);
            $branch = Branch::create(['name' => 'Main', 'code' => 'MAIN', 'is_active' => true]);
            $admin = User::create([
                'uuid' => (string) Str::uuid(),
                'branch_id' => $branch->id,
                'name' => 'Anomaly Admin',
                'email' => 'anomaly-admin@example.test',
                'password' => Hash::make('password'),
                'role' => 'company_admin',
                'is_active' => true,
            ]);
            $admin->syncPrimaryRole($roles['company_admin']);
            $salesmanUser = User::create([
                'uuid' => (string) Str::uuid(),
                'branch_id' => $branch->id,
                'name' => 'Anomaly Salesman',
                'email' => 'anomaly-salesman@example.test',
                'password' => Hash::make('password'),
                'role' => 'salesman',
                'is_active' => true,
            ]);
            $salesmanUser->syncPrimaryRole($roles['salesman']);
            $salesman = Salesman::create([
                'user_id' => $salesmanUser->id,
                'employee_code' => 'ANOM-1',
                'first_name' => 'Anomaly',
                'last_name' => 'Salesman',
                'is_active' => true,
            ]);
            $customer = Customer::create([
                'branch_id' => $branch->id,
                'code' => 'ANOM-C1',
                'name' => 'Anomaly Customer',
                'latitude' => 34.55,
                'longitude' => 69.20,
                'created_by' => $admin->id,
                'is_active' => true,
            ]);
            $device = Device::create([
                'user_id' => $salesmanUser->id,
                'salesman_id' => $salesman->id,
                'device_uuid' => 'anomaly-device',
                'installation_uuid' => 'anomaly-install',
                'is_active' => true,
            ]);

            return compact('tenant', 'branch', 'admin', 'salesmanUser', 'salesman', 'customer', 'device');
        });
    }
}
