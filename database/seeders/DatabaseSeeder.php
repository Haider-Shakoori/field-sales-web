<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\CompanySetting;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Salesman;
use App\Models\Tenant;
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

            $admin->update(['branch_id' => $branch->id]);
            $salesmanUser->update(['branch_id' => $branch->id]);

            Salesman::firstOrCreate(
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

            $roles = $this->seedRbac($tenant);

            $admin->syncPrimaryRole($roles['company_admin']);
            $salesmanUser->syncPrimaryRole($roles['salesman']);

            foreach (config('tenancy.defaults') as $key => $value) {
                CompanySetting::firstOrCreate(
                    ['tenant_id' => $tenant->id, 'key' => 'tracking.'.$key],
                    ['value' => is_bool($value) ? ($value ? '1' : '0') : (string) $value]
                );
            }
        });
    }

    private function seedRbac(Tenant $tenant): array
    {
        $permissionSlugs = [
            'settings:view',
            'settings:manage',
            'users:view',
            'users:manage',
            'roles:view',
            'roles:manage',
            'branches:view',
            'branches:manage',
            'audit:view',
            'sales-team:view',
            'sales-team:manage',
            'customers:view',
            'customers:manage',
            'catalog:view',
            'catalog:manage',
            'attendance:view',
            'attendance:manage',
            'tracking:view',
            'tracking:manage',
            'visits:view',
            'visits:manage',
            'orders:view',
            'orders:manage',
            'collections:view',
            'collections:manage',
            'expenses:view',
            'expenses:manage',
            'targets:view',
            'targets:manage',
            'reports:view',
            'notifications:send',
            'integrations:manage',
        ];

        foreach ($permissionSlugs as $slug) {
            Permission::firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => str($slug)->replace(':', ' ')->title(),
                    'group' => str($slug)->before(':'),
                ]
            );
        }

        $all = Permission::pluck('slug')->all();

        $grants = [
            'owner' => $all,
            'company_admin' => $all,
            'sales_manager' => [
                'settings:view',
                'users:view',
                'roles:view',
                'branches:view',
                'branches:manage',
                'audit:view',
                'sales-team:view',
                'sales-team:manage',
                'customers:view',
                'customers:manage',
                'catalog:view',
                'attendance:view',
                'attendance:manage',
                'tracking:view',
                'visits:view',
                'visits:manage',
                'orders:view',
                'orders:manage',
                'collections:view',
                'collections:manage',
                'expenses:view',
                'targets:view',
                'targets:manage',
                'reports:view',
                'notifications:send',
            ],
            'supervisor' => [
                'branches:view',
                'sales-team:view',
                'customers:view',
                'catalog:view',
                'attendance:view',
                'tracking:view',
                'visits:view',
                'visits:manage',
                'orders:view',
                'collections:view',
                'expenses:view',
                'targets:view',
                'reports:view',
                'notifications:send',
            ],
            'salesman' => [
                'customers:view',
                'catalog:view',
                'attendance:view',
                'visits:view',
                'orders:view',
                'collections:view',
                'expenses:view',
                'targets:view',
            ],
            'accountant' => [
                'collections:view',
                'collections:manage',
                'expenses:view',
                'expenses:manage',
                'reports:view',
            ],
            'warehouse_user' => [
                'catalog:view',
                'catalog:manage',
                'customers:view',
            ],
            'auditor' => [
                'settings:view',
                'users:view',
                'roles:view',
                'branches:view',
                'audit:view',
                'sales-team:view',
                'customers:view',
                'catalog:view',
                'attendance:view',
                'tracking:view',
                'visits:view',
                'orders:view',
                'collections:view',
                'expenses:view',
                'targets:view',
                'reports:view',
            ],
        ];

        $roles = [];

        foreach ($grants as $slug => $permissions) {
            $role = Role::firstOrCreate(
                ['tenant_id' => $tenant->id, 'slug' => $slug],
                [
                    'name' => str($slug)->replace('_', ' ')->title(),
                    'is_system' => true,
                ]
            );

            $role->permissions()->sync(
                Permission::whereIn('slug', $permissions)->pluck('id')->all()
            );

            $roles[$slug] = $role;
        }

        return $roles;
    }
}
