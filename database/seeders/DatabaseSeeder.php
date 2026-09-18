<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\CustomerCategory;
use App\Models\Permission;
use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Models\Role;
use App\Models\Salesman;
use App\Models\SalesmanAssignment;
use App\Models\SalesRoute;
use App\Models\Tenant;
use App\Models\Territory;
use App\Models\User;
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

        $admin = User::firstOrCreate(
            ['tenant_id' => $tenant->id, 'email' => 'admin@example.com'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Demo Admin',
                'password' => Hash::make('password'),
                'role' => 'company_admin',
                'is_active' => true,
            ]
        );

        $supervisor = User::firstOrCreate(
            ['tenant_id' => $tenant->id, 'email' => 'supervisor@example.com'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Demo Supervisor',
                'password' => Hash::make('password'),
                'role' => 'supervisor',
                'is_active' => true,
            ]
        );

        $user = User::firstOrCreate(
            ['tenant_id' => $tenant->id, 'email' => 'salesman@example.com'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Demo Salesman',
                'password' => Hash::make('password'),
                'role' => 'salesman',
                'is_active' => true,
            ]
        );

        $salesman = Salesman::firstOrCreate(
            ['user_id' => $user->id],
            [
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'employee_code' => 'SAL-001',
                'first_name' => 'Demo',
                'last_name' => 'Salesman',
                'is_active' => true,
            ]
        );

        $this->seedRbac($tenant, $admin, $supervisor, $user);

        foreach (config('tenancy.defaults') as $key => $value) {
            CompanySetting::firstOrCreate(
                ['tenant_id' => $tenant->id, 'key' => 'tracking.'.$key],
                ['value' => is_bool($value) ? ($value ? '1' : '0') : (string) $value]
            );
        }

        $branch = Branch::firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'KBL'],
            ['uuid' => (string) Str::uuid(), 'name' => 'Kabul', 'is_active' => true]
        );

        $territory = Territory::firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'KBL-CENTRAL'],
            [
                'uuid' => (string) Str::uuid(),
                'branch_id' => $branch->id,
                'name' => 'Kabul Central',
                'is_active' => true,
            ]
        );

        $route = SalesRoute::firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'KBL-R01'],
            [
                'uuid' => (string) Str::uuid(),
                'branch_id' => $branch->id,
                'territory_id' => $territory->id,
                'name' => 'Central Retail Route',
                'visit_days' => ['monday', 'wednesday', 'saturday'],
                'is_active' => true,
            ]
        );

        $category = CustomerCategory::firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Retailer'],
            ['uuid' => (string) Str::uuid(), 'is_active' => true]
        );

        $priceList = PriceList::firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'RETAIL'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Retail Price List',
                'currency' => 'AFN',
                'is_active' => true,
            ]
        );

        $customers = [
            ['CUST-001', 'Shahr-e-Naw Market', 34.5328, 69.1652, '0700000001', 1],
            ['CUST-002', 'Wazir Akbar Khan Shop', 34.5431, 69.1854, '0700000002', 2],
            ['CUST-003', 'Kart-e-Parwan Retail', 34.5495, 69.1488, '0700000003', 3],
        ];

        foreach ($customers as [$code, $name, $lat, $lng, $phone, $sequence]) {
            $customer = Customer::firstOrCreate(
                ['tenant_id' => $tenant->id, 'code' => $code],
                [
                    'uuid' => (string) Str::uuid(),
                    'branch_id' => $branch->id,
                    'territory_id' => $territory->id,
                    'route_id' => $route->id,
                    'category_id' => $category->id,
                    'price_list_id' => $priceList->id,
                    'assigned_salesman_id' => $salesman->id,
                    'name' => $name,
                    'shop_name' => $name,
                    'phone' => $phone,
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'geofence_radius' => 100,
                    'visit_frequency' => 'weekly',
                    'is_active' => true,
                ]
            );

            $route->customers()->syncWithoutDetaching([
                $customer->id => [
                    'tenant_id' => $tenant->id,
                    'sequence' => $sequence,
                    'visit_days' => json_encode(['monday', 'wednesday', 'saturday']),
                ],
            ]);
        }

        $products = [
            ['SKU-001', 'Product A', 120],
            ['SKU-002', 'Product B', 180],
            ['SKU-003', 'Product C', 250],
        ];

        foreach ($products as [$sku, $name, $price]) {
            $product = Product::firstOrCreate(
                ['tenant_id' => $tenant->id, 'sku' => $sku],
                [
                    'uuid' => (string) Str::uuid(),
                    'name' => $name,
                    'unit' => 'pcs',
                    'base_price' => $price,
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
                ['price' => $price]
            );
        }

        SalesmanAssignment::firstOrCreate(
            [
                'tenant_id' => $tenant->id,
                'salesman_id' => $salesman->id,
                'effective_from' => now()->toDateString(),
            ],
            [
                'uuid' => (string) Str::uuid(),
                'branch_id' => $branch->id,
                'territory_id' => $territory->id,
                'route_id' => $route->id,
                'supervisor_user_id' => $supervisor->id,
                'created_by' => $admin->id,
            ]
        );
        });
    }

    private function seedRbac(Tenant $tenant, User $admin, User $supervisor, User $salesman): void
    {
        $permissions = [
            'settings:view', 'settings:manage',
            'users:view', 'users:manage',
            'sales-team:view', 'sales-team:manage',
            'customers:view', 'customers:manage',
            'catalog:view', 'catalog:manage',
            'attendance:view', 'attendance:manage',
            'tracking:view', 'tracking:manage',
            'visits:view', 'visits:manage',
            'orders:view', 'orders:manage',
            'collections:view', 'collections:manage',
            'expenses:view', 'expenses:manage',
            'targets:view', 'targets:manage',
            'reports:view',
            'notifications:send',
            'integrations:manage',
        ];

        foreach ($permissions as $slug) {
            Permission::firstOrCreate(
                ['slug' => $slug],
                ['name' => str($slug)->replace(':', ' ')->title(), 'group' => str($slug)->before(':')]
            );
        }

        $roles = [
            'company_admin' => $permissions,
            'sales_manager' => array_values(array_diff($permissions, ['settings:manage', 'integrations:manage'])),
            'supervisor' => [
                'customers:view', 'catalog:view', 'attendance:view', 'tracking:view',
                'visits:view', 'orders:view', 'collections:view', 'expenses:view',
                'targets:view', 'reports:view', 'notifications:send',
            ],
            'salesman' => [
                'customers:view', 'catalog:view', 'attendance:view',
                'visits:view', 'orders:view', 'collections:view',
                'expenses:view', 'targets:view',
            ],
            'auditor' => [
                'attendance:view', 'tracking:view', 'visits:view', 'orders:view',
                'collections:view', 'expenses:view', 'reports:view',
            ],
        ];

        foreach ($roles as $slug => $grants) {
            $role = Role::firstOrCreate(
                ['tenant_id' => $tenant->id, 'slug' => $slug],
                [
                    'uuid' => (string) Str::uuid(),
                    'name' => str($slug)->replace('_', ' ')->title(),
                    'is_system' => true,
                ]
            );

            $role->permissions()->sync(
                Permission::whereIn('slug', $grants)->pluck('id')->all()
            );

            $target = match ($slug) {
                'company_admin' => $admin,
                'supervisor' => $supervisor,
                'salesman' => $salesman,
                default => null,
            };

            if ($target) {
                $target->roles()->syncWithoutDetaching([
                    $role->id => ['tenant_id' => $tenant->id],
                ]);
            }
        }
    }
}
