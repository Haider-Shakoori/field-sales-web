<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Models\Salesman;
use App\Models\SalesmanAssignment;
use App\Models\Supervisor;
use App\Models\SupervisorAssignment;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantProvisioningService;
use App\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        app(TenantContext::class)->withPlatformScope(function (): void {
            $tenant = Tenant::firstOrCreate(
                ['slug' => 'demo-field-sales'],
                [
                    'uuid' => (string) Str::uuid(),
                    'name' => 'Demo Field Sales',
                    'timezone' => 'Asia/Kabul',
                    'subscription_status' => 'active',
                ]
            );

            $branch = Branch::firstOrCreate(
                ['tenant_id' => $tenant->id, 'code' => 'KBL'],
                [
                    'name' => 'Kabul Main',
                    'is_active' => true,
                ]
            );

            $admin = User::firstOrCreate(
                ['tenant_id' => $tenant->id, 'email' => 'admin@example.com'],
                [
                    'uuid' => (string) Str::uuid(),
                    'branch_id' => $branch->id,
                    'name' => 'Demo Admin',
                    'password' => Hash::make('password'),
                    'role' => 'company_admin',
                    'is_active' => true,
                ]
            );

            $supervisorUser = User::firstOrCreate(
                ['tenant_id' => $tenant->id, 'email' => 'supervisor@example.com'],
                [
                    'uuid' => (string) Str::uuid(),
                    'branch_id' => $branch->id,
                    'name' => 'Demo Supervisor',
                    'password' => Hash::make('password'),
                    'role' => 'supervisor',
                    'is_active' => true,
                ]
            );

            $salesmanUser = User::firstOrCreate(
                ['tenant_id' => $tenant->id, 'email' => 'salesman@example.com'],
                [
                    'uuid' => (string) Str::uuid(),
                    'branch_id' => $branch->id,
                    'name' => 'Demo Salesman',
                    'password' => Hash::make('password'),
                    'role' => 'salesman',
                    'is_active' => true,
                ]
            );

            $admin->update(['branch_id' => $branch->id, 'is_platform_admin' => true]);
            $supervisorUser->update(['branch_id' => $branch->id]);
            $salesmanUser->update(['branch_id' => $branch->id]);

            $supervisor = Supervisor::firstOrCreate(
                ['user_id' => $supervisorUser->id],
                [
                    'uuid' => (string) Str::uuid(),
                    'tenant_id' => $tenant->id,
                    'employee_code' => 'SUP-001',
                    'first_name' => 'Demo',
                    'last_name' => 'Supervisor',
                    'is_active' => true,
                ]
            );

            $salesman = Salesman::firstOrCreate(
                ['user_id' => $salesmanUser->id],
                [
                    'uuid' => (string) Str::uuid(),
                    'tenant_id' => $tenant->id,
                    'employee_code' => 'SAL-001',
                    'first_name' => 'Demo',
                    'last_name' => 'Salesman',
                    'is_active' => true,
                ]
            );

            $roles = app(TenantProvisioningService::class)->provisionRbac($tenant);

            $admin->syncPrimaryRole($roles['company_admin']);
            $supervisorUser->syncPrimaryRole($roles['supervisor']);
            $salesmanUser->syncPrimaryRole($roles['salesman']);

            $product = Product::firstOrCreate(
                ['tenant_id' => $tenant->id, 'sku' => 'DEMO-001'],
                [
                    'name' => 'Demo Product',
                    'unit' => 'pcs',
                    'base_price' => 100,
                    'currency' => 'AFN',
                    'is_active' => true,
                ]
            );

            $priceList = PriceList::firstOrCreate(
                ['tenant_id' => $tenant->id, 'code' => 'RETAIL'],
                [
                    'name' => 'Retail Price List',
                    'currency' => 'AFN',
                    'is_active' => true,
                ]
            );

            PriceListItem::firstOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'price_list_id' => $priceList->id,
                    'product_id' => $product->id,
                    'min_quantity' => 1,
                ],
                [
                    'price' => 95,
                ]
            );

            SupervisorAssignment::firstOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'supervisor_id' => $supervisor->id,
                    'branch_id' => $branch->id,
                    'effective_from' => now()->toDateString(),
                ],
                [
                    'created_by' => $admin->id,
                ]
            );

            SalesmanAssignment::firstOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'salesman_id' => $salesman->id,
                    'effective_from' => now()->toDateString(),
                ],
                [
                    'branch_id' => $branch->id,
                    'supervisor_id' => $supervisor->id,
                    'created_by' => $admin->id,
                ]
            );

            app(TenantProvisioningService::class)->seedTrackingDefaults($tenant);
        });

        if (filter_var(env('SEED_SHAHAB_DEMO', false), FILTER_VALIDATE_BOOL)) {
            $this->call(ShahabDemoSeeder::class);
        }
    }
}
