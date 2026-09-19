<?php

namespace Tests\Feature;

use App\Models\Collection;
use App\Models\Customer;
use App\Models\CustomerVisit;
use App\Models\Device;
use App\Models\Expense;
use App\Models\Order;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Salesman;
use App\Models\SalesTarget;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class Batch11ExpensesTargetsTest extends TestCase
{
    use RefreshDatabase;

    private ?string $token = null;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-09-19T08:00:00Z');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_offline_expense_is_idempotent(): void
    {
        $actor = $this->salesmanActor();
        $uuid = (string) Str::uuid();

        $payload = [
            'offline_uuid' => $uuid,
            'spent_at' => '2026-09-19T07:00:00Z',
            'category' => 'fuel',
            'currency' => 'afn',
            'amount' => 450,
            'merchant' => 'Fuel Station',
            'reference_number' => 'FUEL-001',
            'latitude' => 34.5553,
            'longitude' => 69.2075,
            'accuracy' => 8,
            'notes' => 'Field route fuel.',
        ];

        $this->postJson('/api/v1/expenses', $payload, $this->headers())
            ->assertCreated()
            ->assertJsonPath('data.id', $uuid)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.currency', 'AFN')
            ->assertJsonPath('data.amount', 450);

        $this->postJson('/api/v1/expenses', $payload, $this->headers())
            ->assertOk()
            ->assertJsonPath('data.id', $uuid);

        $this->assertDatabaseCount('expenses', 1);
    }

    public function test_approved_expense_is_terminal_and_audited(): void
    {
        $actor = $this->salesmanActor();

        $this->postJson('/api/v1/expenses', [
            'offline_uuid' => (string) Str::uuid(),
            'spent_at' => '2026-09-19T07:15:00Z',
            'category' => 'meals',
            'currency' => 'AFN',
            'amount' => 200,
            'latitude' => 34.5553,
            'longitude' => 69.2075,
            'accuracy' => 7,
        ], $this->headers())->assertCreated();

        $expense = app(TenantContext::class)->withTenant(
            $actor['tenant'],
            fn () => Expense::firstOrFail()
        );

        $admin = $this->admin($actor['tenant']);

        $this->actingAs($admin)
            ->patch('/admin/expenses/'.$expense->id.'/status', [
                'status' => 'approved',
            ])
            ->assertRedirect(route('admin.expenses.show', $expense));

        $this->assertDatabaseHas('expenses', [
            'id' => $expense->id,
            'status' => 'approved',
            'reviewed_by' => $admin->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'expense.status_changed',
            'subject_id' => $expense->id,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.expenses.show', $expense))
            ->patch('/admin/expenses/'.$expense->id.'/status', [
                'status' => 'rejected',
                'review_note' => 'Should not rewrite approved history.',
            ])
            ->assertSessionHasErrors('status');

        $this->assertDatabaseHas('expenses', [
            'id' => $expense->id,
            'status' => 'approved',
        ]);
    }

    public function test_target_progress_uses_only_authoritative_records(): void
    {
        $actor = $this->salesmanActor();

        app(TenantContext::class)->withTenant($actor['tenant'], function () use ($actor): void {
            $customer = Customer::create([
                'code' => 'TGT-CUS-1',
                'name' => 'Target Customer',
                'created_by' => $actor['user']->id,
                'is_active' => true,
            ]);

            Order::create([
                'user_id' => $actor['user']->id,
                'salesman_id' => $actor['salesman']->id,
                'device_id' => $actor['device']->id,
                'customer_id' => $customer->id,
                'order_number' => 'ORD-TGT-APPROVED',
                'ordered_at' => '2026-09-19 06:00:00',
                'payment_type' => 'cash',
                'status' => 'approved',
                'currency' => 'AFN',
                'subtotal' => 300,
                'discount_total' => 0,
                'grand_total' => 300,
                'pricing_adjusted' => false,
            ]);

            Order::create([
                'user_id' => $actor['user']->id,
                'salesman_id' => $actor['salesman']->id,
                'device_id' => $actor['device']->id,
                'customer_id' => $customer->id,
                'order_number' => 'ORD-TGT-PENDING',
                'ordered_at' => '2026-09-19 06:10:00',
                'payment_type' => 'cash',
                'status' => 'pending',
                'currency' => 'AFN',
                'subtotal' => 500,
                'discount_total' => 0,
                'grand_total' => 500,
                'pricing_adjusted' => false,
            ]);

            Collection::create([
                'user_id' => $actor['user']->id,
                'salesman_id' => $actor['salesman']->id,
                'device_id' => $actor['device']->id,
                'customer_id' => $customer->id,
                'receipt_number' => 'REC-TGT-VERIFIED',
                'collected_at' => '2026-09-19 06:20:00',
                'currency' => 'AFN',
                'amount' => 100,
                'payment_method' => 'cash',
                'status' => 'verified',
                'latitude' => 34.5553,
                'longitude' => 69.2075,
                'accuracy' => 8,
            ]);

            Collection::create([
                'user_id' => $actor['user']->id,
                'salesman_id' => $actor['salesman']->id,
                'device_id' => $actor['device']->id,
                'customer_id' => $customer->id,
                'receipt_number' => 'REC-TGT-PENDING',
                'collected_at' => '2026-09-19 06:30:00',
                'currency' => 'AFN',
                'amount' => 200,
                'payment_method' => 'cash',
                'status' => 'pending',
                'latitude' => 34.5553,
                'longitude' => 69.2075,
                'accuracy' => 8,
            ]);

            CustomerVisit::create([
                'user_id' => $actor['user']->id,
                'salesman_id' => $actor['salesman']->id,
                'device_id' => $actor['device']->id,
                'customer_id' => $customer->id,
                'status' => 'completed',
                'checked_in_at' => '2026-09-19 06:40:00',
                'checked_out_at' => '2026-09-19 06:50:00',
                'checkin_latitude' => 34.5553,
                'checkin_longitude' => 69.2075,
                'checkin_accuracy' => 8,
            ]);

            CustomerVisit::create([
                'user_id' => $actor['user']->id,
                'salesman_id' => $actor['salesman']->id,
                'device_id' => $actor['device']->id,
                'customer_id' => $customer->id,
                'status' => 'active',
                'checked_in_at' => '2026-09-19 07:00:00',
                'checkin_latitude' => 34.5553,
                'checkin_longitude' => 69.2075,
                'checkin_accuracy' => 8,
            ]);

            foreach ([
                ['sales_amount', 'AFN', 600],
                ['collections_amount', 'AFN', 200],
                ['orders_count', null, 2],
                ['visits_count', null, 2],
            ] as [$type, $currency, $value]) {
                SalesTarget::create([
                    'salesman_id' => $actor['salesman']->id,
                    'target_type' => $type,
                    'currency' => $currency,
                    'target_value' => $value,
                    'period_start' => '2026-09-01',
                    'period_end' => '2026-09-30',
                    'created_by' => $actor['user']->id,
                ]);
            }
        });

        $response = $this->getJson('/api/v1/targets/current', $this->headers())
            ->assertOk();

        $targets = collect($response->json('data'))->keyBy('target_type');

        $this->assertSame(300.0, (float) $targets['sales_amount']['achieved_value']);
        $this->assertSame(100.0, (float) $targets['collections_amount']['achieved_value']);
        $this->assertSame(1.0, (float) $targets['orders_count']['achieved_value']);
        $this->assertSame(1.0, (float) $targets['visits_count']['achieved_value']);
    }

    public function test_started_target_is_immutable_but_future_target_can_be_updated(): void
    {
        $actor = $this->salesmanActor();
        $admin = $this->admin($actor['tenant']);

        [$active, $future] = app(TenantContext::class)->withTenant(
            $actor['tenant'],
            function () use ($actor, $admin): array {
                $active = SalesTarget::create([
                    'salesman_id' => $actor['salesman']->id,
                    'target_type' => 'orders_count',
                    'target_value' => 5,
                    'period_start' => '2026-09-01',
                    'period_end' => '2026-09-30',
                    'created_by' => $admin->id,
                ]);

                $future = SalesTarget::create([
                    'salesman_id' => $actor['salesman']->id,
                    'target_type' => 'visits_count',
                    'target_value' => 10,
                    'period_start' => '2026-10-01',
                    'period_end' => '2026-10-31',
                    'created_by' => $admin->id,
                ]);

                return [$active, $future];
            }
        );

        $this->actingAs($admin)
            ->get('/admin/targets/'.$active->id.'/edit')
            ->assertStatus(409);

        $this->actingAs($admin)
            ->put('/admin/targets/'.$future->id, [
                'salesman_id' => $actor['salesman']->uuid,
                'target_type' => 'visits_count',
                'target_value' => 12,
                'period_start' => '2026-10-01',
                'period_end' => '2026-10-31',
            ])
            ->assertRedirect(route('admin.targets.index'));

        $this->assertDatabaseHas('sales_targets', [
            'id' => $future->id,
            'target_value' => 12,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'target.updated',
            'subject_id' => $future->id,
        ]);
    }

    private function salesmanActor(): array
    {
        $tenant = app(TenantContext::class)->withPlatformScope(fn () => Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Batch 11 Tenant',
            'slug' => 'batch-11-tenant',
            'timezone' => 'Asia/Kabul',
        ]));

        [$user, $salesman, $device] = app(TenantContext::class)->withTenant(
            $tenant,
            function () use ($tenant): array {
                $user = User::create([
                    'uuid' => (string) Str::uuid(),
                    'tenant_id' => $tenant->id,
                    'name' => 'Batch 11 Salesman',
                    'email' => 'batch11-salesman@example.test',
                    'password' => Hash::make('password'),
                    'role' => 'salesman',
                    'is_active' => true,
                ]);

                $role = Role::create([
                    'tenant_id' => $tenant->id,
                    'name' => 'Batch 11 Salesman',
                    'slug' => 'batch-11-salesman',
                    'is_system' => false,
                ]);

                $permissionIds = collect(['expenses:view', 'targets:view'])
                    ->map(fn (string $slug) => Permission::firstOrCreate(
                        ['slug' => $slug],
                        [
                            'name' => str($slug)->replace(':', ' ')->title(),
                            'group' => str($slug)->before(':'),
                        ]
                    )->id)
                    ->all();

                $role->permissions()->sync($permissionIds);
                $user->syncPrimaryRole($role);

                $salesman = Salesman::create([
                    'tenant_id' => $tenant->id,
                    'user_id' => $user->id,
                    'employee_code' => 'B11-SAL-1',
                    'first_name' => 'Batch',
                    'last_name' => 'Salesman',
                    'is_active' => true,
                ]);

                $device = Device::create([
                    'tenant_id' => $tenant->id,
                    'user_id' => $user->id,
                    'salesman_id' => $salesman->id,
                    'device_uuid' => 'batch11-device',
                    'installation_uuid' => 'batch11-install',
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

    private function admin(Tenant $tenant): User
    {
        return app(TenantContext::class)->withTenant($tenant, function () use ($tenant): User {
            $admin = User::create([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'name' => 'Batch 11 Admin',
                'email' => 'batch11-admin@example.test',
                'password' => Hash::make('password'),
                'role' => 'company_admin',
                'is_active' => true,
            ]);

            $role = Role::create([
                'tenant_id' => $tenant->id,
                'name' => 'Batch 11 Admin',
                'slug' => 'batch-11-admin',
                'is_system' => false,
            ]);

            $permissionIds = collect([
                'expenses:view',
                'expenses:manage',
                'targets:view',
                'targets:manage',
            ])->map(fn (string $slug) => Permission::firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => str($slug)->replace(':', ' ')->title(),
                    'group' => str($slug)->before(':'),
                ]
            )->id)->all();

            $role->permissions()->sync($permissionIds);
            $admin->syncPrimaryRole($role);

            return $admin;
        });
    }

    private function headers(): array
    {
        return [
            'Authorization' => 'Bearer '.$this->token,
            'X-Device-UUID' => 'batch11-device',
            'X-Installation-UUID' => 'batch11-install',
            'X-App-Version' => '1.0',
            'X-Platform' => 'android',
            'X-OS-Version' => '16',
        ];
    }
}
