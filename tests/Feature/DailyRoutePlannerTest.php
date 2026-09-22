<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerFollowUp;
use App\Models\Order;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RouteCustomer;
use App\Models\Salesman;
use App\Models\SalesmanAssignment;
use App\Models\SalesRoute;
use App\Models\Tenant;
use App\Models\Territory;
use App\Models\User;
use App\Services\DailyRoutePlannerService;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class DailyRoutePlannerTest extends TestCase
{
    use RefreshDatabase;

    public function test_planner_prioritizes_overdue_and_due_follow_up_customers(): void
    {
        [$tenant, $admin, $salesman, $route, $regular, $urgent] = $this->fixture();

        $plan = app(TenantContext::class)->withTenant(
            $tenant,
            fn () => app(DailyRoutePlannerService::class)->planFor(
                $salesman,
                CarbonImmutable::parse('2026-09-23', 'Asia/Kabul'),
            )
        );

        $this->assertSame($route->uuid, $plan['route']['id']);
        $this->assertSame($urgent->uuid, $plan['stops'][0]['customer_id']);
        $this->assertSame('urgent', $plan['stops'][0]['priority']);
        $this->assertSame($regular->uuid, $plan['stops'][1]['customer_id']);
        $this->assertTrue($plan['stops'][0]['recommended_order'] < $plan['stops'][0]['route_sequence']);
        $this->assertSame(1, $plan['summary']['customers_with_overdue_balance']);
        $this->assertSame(1, $plan['summary']['customers_with_due_follow_ups']);

        $this->actingAs($admin)
            ->get(route('admin.daily-planner.index', [
                'salesman' => $salesman->uuid,
                'date' => '2026-09-23',
            ]))
            ->assertOk()
            ->assertSee('Daily planner')
            ->assertSee($urgent->name)
            ->assertSee('AFN 80.00 overdue');
    }

    private function fixture(): array
    {
        $context = app(TenantContext::class);

        $tenant = $context->withPlatformScope(fn () => Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Planner Tenant',
            'slug' => 'planner-'.Str::lower(Str::random(6)),
            'timezone' => 'Asia/Kabul',
            'subscription_status' => 'active',
        ]));

        return $context->withTenant($tenant, function () use ($tenant): array {
            $adminRole = Role::create([
                'tenant_id' => $tenant->id,
                'name' => 'Planner Admin',
                'slug' => 'planner-admin',
                'is_system' => false,
            ]);

            $permissions = collect([
                'sales-team:view',
                'customers:view',
            ])->map(fn (string $slug) => Permission::firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => str($slug)->replace(':', ' ')->title(),
                    'group' => str($slug)->before(':'),
                ]
            ));

            $adminRole->permissions()->sync($permissions->pluck('id'));

            $admin = User::create([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'name' => 'Planner Admin',
                'email' => 'planner-admin@example.test',
                'password' => Hash::make('password'),
                'role' => 'company-admin',
                'is_active' => true,
            ]);
            $admin->syncPrimaryRole($adminRole);

            $salesUser = User::create([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'name' => 'Planner Salesman',
                'email' => 'planner-sales@example.test',
                'password' => Hash::make('password'),
                'role' => 'salesman',
                'is_active' => true,
            ]);

            $salesman = Salesman::create([
                'tenant_id' => $tenant->id,
                'user_id' => $salesUser->id,
                'employee_code' => 'PLAN-001',
                'first_name' => 'Planner',
                'last_name' => 'Salesman',
                'is_active' => true,
            ]);

            $branch = Branch::create([
                'tenant_id' => $tenant->id,
                'name' => 'Kabul Main',
                'code' => 'KBL',
                'is_active' => true,
            ]);

            $territory = Territory::create([
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
                'code' => 'KBL-C',
                'name' => 'Kabul Central',
                'is_active' => true,
            ]);

            $route = SalesRoute::create([
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
                'territory_id' => $territory->id,
                'code' => 'KBL-DAY',
                'name' => 'Kabul Daily Route',
                'weekdays' => ['wed'],
                'is_active' => true,
            ]);

            $regular = Customer::create([
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
                'territory_id' => $territory->id,
                'code' => 'REG-1',
                'name' => 'Regular Shop',
                'latitude' => 34.53,
                'longitude' => 69.17,
                'credit_currency' => 'AFN',
                'credit_terms_days' => 30,
                'is_active' => true,
            ]);

            $urgent = Customer::create([
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
                'territory_id' => $territory->id,
                'code' => 'URG-1',
                'name' => 'Urgent Shop',
                'latitude' => 34.54,
                'longitude' => 69.18,
                'credit_currency' => 'AFN',
                'credit_terms_days' => 10,
                'is_active' => true,
            ]);

            RouteCustomer::create([
                'tenant_id' => $tenant->id,
                'route_id' => $route->id,
                'customer_id' => $regular->id,
                'sequence_number' => 1,
                'planned_visit_minutes' => 10,
            ]);

            RouteCustomer::create([
                'tenant_id' => $tenant->id,
                'route_id' => $route->id,
                'customer_id' => $urgent->id,
                'sequence_number' => 2,
                'planned_visit_minutes' => 15,
            ]);

            SalesmanAssignment::create([
                'tenant_id' => $tenant->id,
                'salesman_id' => $salesman->id,
                'branch_id' => $branch->id,
                'territory_id' => $territory->id,
                'route_id' => $route->id,
                'effective_from' => '2026-09-01',
                'created_by' => $admin->id,
            ]);

            Order::create([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'user_id' => $salesUser->id,
                'salesman_id' => $salesman->id,
                'customer_id' => $urgent->id,
                'order_number' => 'PLAN-ORDER-1',
                'ordered_at' => '2026-08-01 04:00:00',
                'due_date' => '2026-08-11',
                'payment_type' => 'credit',
                'status' => 'approved',
                'currency' => 'AFN',
                'subtotal' => 80,
                'discount_total' => 0,
                'grand_total' => 80,
            ]);

            CustomerFollowUp::create([
                'tenant_id' => $tenant->id,
                'customer_id' => $urgent->id,
                'assigned_salesman_id' => $salesman->id,
                'type' => 'payment',
                'priority' => 'high',
                'status' => 'pending',
                'due_at' => '2026-09-22 04:30:00',
                'created_by' => $admin->id,
            ]);

            return [$tenant, $admin, $salesman, $route, $regular, $urgent];
        });
    }
}
