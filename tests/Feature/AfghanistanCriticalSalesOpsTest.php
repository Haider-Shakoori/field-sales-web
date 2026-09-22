<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Device;
use App\Models\Order;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Salesman;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AfghanistanCriticalSalesOpsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dari_locale_switch_renders_rtl_customer_ui(): void
    {
        [$tenant, $admin] = $this->tenantUser('locale@example.test', ['customers:view']);

        app(TenantContext::class)->withTenant($tenant, fn () => Customer::create([
            'code' => 'C-001',
            'name' => 'Kabul Shop',
            'credit_currency' => 'AFN',
            'credit_terms_days' => 30,
            'created_by' => $admin->id,
            'is_active' => true,
        ]));

        $this->actingAs($admin)
            ->post(route('locale.update'), ['locale' => 'fa'])
            ->assertSessionHas('locale', 'fa');

        $this->actingAs($admin)
            ->withSession(['locale' => 'fa'])
            ->get(route('admin.customers.index'))
            ->assertOk()
            ->assertSee('lang="fa"', false)
            ->assertSee('dir="rtl"', false)
            ->assertSee('مشتریان');
    }

    public function test_credit_limit_requires_manager_override_and_sets_due_date(): void
    {
        [$tenant, $admin] = $this->tenantUser('credit@example.test', ['orders:view', 'orders:manage']);
        [$salesman, $device] = $this->salesman($tenant, 'S-001', 'Ahmad');

        [$customer, $pending] = app(TenantContext::class)->withTenant($tenant, function () use ($admin, $salesman, $device): array {
            $customer = Customer::create([
                'code' => 'CREDIT-1',
                'name' => 'Credit Customer',
                'credit_limit' => 100,
                'credit_currency' => 'AFN',
                'credit_terms_days' => 30,
                'created_by' => $admin->id,
                'is_active' => true,
            ]);

            Order::create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $salesman->user_id,
                'salesman_id' => $salesman->id,
                'device_id' => $device->id,
                'customer_id' => $customer->id,
                'order_number' => 'ORD-OLD',
                'ordered_at' => '2026-09-01 04:00:00',
                'payment_type' => 'credit',
                'status' => 'approved',
                'currency' => 'AFN',
                'subtotal' => 80,
                'discount_total' => 0,
                'grand_total' => 80,
            ]);

            $pending = Order::create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $salesman->user_id,
                'salesman_id' => $salesman->id,
                'device_id' => $device->id,
                'customer_id' => $customer->id,
                'order_number' => 'ORD-NEW',
                'ordered_at' => '2026-09-20 04:00:00',
                'payment_type' => 'credit',
                'status' => 'pending',
                'currency' => 'AFN',
                'subtotal' => 30,
                'discount_total' => 0,
                'grand_total' => 30,
            ]);

            return [$customer, $pending];
        });

        $this->actingAs($admin)
            ->patch(route('admin.orders.status', $pending), ['status' => 'approved'])
            ->assertSessionHasErrors('status_note');

        $this->actingAs($admin)
            ->patch(route('admin.orders.status', $pending), [
                'status' => 'approved',
                'status_note' => 'Manager approved temporary over-limit sale.',
            ])
            ->assertRedirect();

        $pending->refresh();
        $this->assertSame('approved', $pending->status);
        $this->assertSame('2026-10-20', $pending->due_date?->toDateString());
    }

    public function test_manager_can_schedule_and_complete_customer_follow_up(): void
    {
        [$tenant, $admin] = $this->tenantUser('followup@example.test', ['customers:view', 'customers:manage']);
        [$salesman] = $this->salesman($tenant, 'S-002', 'Fatima');

        $customer = app(TenantContext::class)->withTenant($tenant, fn () => Customer::create([
            'code' => 'FOLLOW-1',
            'name' => 'Follow Up Customer',
            'credit_currency' => 'AFN',
            'credit_terms_days' => 30,
            'created_by' => $admin->id,
            'is_active' => true,
        ]));

        $this->actingAs($admin)
            ->post(route('admin.customers.follow-ups.store', $customer), [
                'assigned_salesman_id' => $salesman->id,
                'type' => 'payment',
                'priority' => 'high',
                'due_at' => '2026-09-24T09:00',
                'notes' => 'Collect overdue balance.',
            ])
            ->assertRedirect();

        $followUp = app(TenantContext::class)->withTenant(
            $tenant,
            fn () => $customer->followUps()->firstOrFail()
        );
        $this->assertSame('pending', $followUp->status);
        $this->assertSame($salesman->id, $followUp->assigned_salesman_id);

        $this->actingAs($admin)
            ->patch(route('admin.follow-ups.status', $followUp), ['status' => 'completed'])
            ->assertRedirect();

        $completed = app(TenantContext::class)->withTenant(
            $tenant,
            fn () => $followUp->fresh()
        );
        $this->assertSame('completed', $completed->status);
    }

    private function tenantUser(string $email, array $permissions): array
    {
        $tenant = app(TenantContext::class)->withPlatformScope(fn () => Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Afghanistan Sales Tenant',
            'slug' => 'afghanistan-sales-'.Str::lower(Str::random(8)),
            'timezone' => 'Asia/Kabul',
        ]));

        $user = app(TenantContext::class)->withTenant($tenant, function () use ($tenant, $email, $permissions): User {
            $user = User::create([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'name' => 'Company Admin',
                'email' => $email,
                'password' => Hash::make('password'),
                'role' => 'company-admin',
                'is_active' => true,
            ]);

            $role = Role::create([
                'tenant_id' => $tenant->id,
                'name' => 'Company Admin',
                'slug' => 'company-admin-'.Str::lower(Str::random(6)),
                'is_system' => false,
            ]);

            $ids = collect($permissions)->map(fn (string $slug) => Permission::firstOrCreate(
                ['slug' => $slug],
                ['name' => str($slug)->replace(':', ' ')->title(), 'group' => str($slug)->before(':')]
            )->id)->all();

            $role->permissions()->sync($ids);
            $user->syncPrimaryRole($role);

            return $user;
        });

        return [$tenant, $user];
    }

    private function salesman(Tenant $tenant, string $code, string $name): array
    {
        return app(TenantContext::class)->withTenant($tenant, function () use ($tenant, $code, $name): array {
            $user = User::create([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'name' => $name.' Salesman',
                'email' => strtolower($code).'@example.test',
                'password' => Hash::make('password'),
                'role' => 'salesman',
                'is_active' => true,
            ]);

            $salesman = Salesman::create([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'employee_code' => $code,
                'first_name' => $name,
                'last_name' => 'Salesman',
                'is_active' => true,
            ]);

            $device = Device::create([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'salesman_id' => $salesman->id,
                'device_uuid' => 'device-'.strtolower($code),
                'installation_uuid' => (string) Str::uuid(),
                'is_active' => true,
            ]);

            return [$salesman, $device];
        });
    }
}
