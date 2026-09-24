<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\CompanySetting;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TenantProvisioningService
{
    public const PERMISSION_SLUGS = [
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
        'leads:view',
        'leads:manage',
        'appointments:view',
        'appointments:manage',
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
        'stock:view',
        'stock:manage',
        'returns:view',
        'returns:manage',
        'collections:view',
        'collections:manage',
        'expenses:view',
        'expenses:manage',
        'targets:view',
        'targets:manage',
        'reports:view',
        'commissions:view',
        'commissions:manage',
        'notifications:send',
        'integrations:manage',
    ];

    public const ROLE_GRANTS = [
        'owner' => '*',
        'company_admin' => '*',
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
            'leads:view',
            'leads:manage',
            'appointments:view',
            'appointments:manage',
            'catalog:view',
            'attendance:view',
            'attendance:manage',
            'tracking:view',
            'visits:view',
            'visits:manage',
            'orders:view',
            'orders:manage',
            'stock:view',
            'stock:manage',
            'returns:view',
            'returns:manage',
            'collections:view',
            'collections:manage',
            'expenses:view',
            'targets:view',
            'targets:manage',
            'reports:view',
            'commissions:view',
            'commissions:manage',
            'notifications:send',
        ],
        'supervisor' => [
            'branches:view',
            'sales-team:view',
            'customers:view',
            'leads:view',
            'leads:manage',
            'appointments:view',
            'appointments:manage',
            'catalog:view',
            'attendance:view',
            'tracking:view',
            'visits:view',
            'visits:manage',
            'orders:view',
            'stock:view',
            'returns:view',
            'collections:view',
            'expenses:view',
            'targets:view',
            'reports:view',
            'commissions:view',
            'notifications:send',
        ],
        'salesman' => [
            'customers:view',
            'leads:view',
            'leads:manage',
            'appointments:view',
            'appointments:manage',
            'catalog:view',
            'attendance:view',
            'visits:view',
            'orders:view',
            'stock:view',
            'returns:view',
            'collections:view',
            'expenses:view',
            'targets:view',
            'commissions:view',
        ],
        'accountant' => [
            'collections:view',
            'collections:manage',
            'expenses:view',
            'expenses:manage',
            'reports:view',
            'commissions:view',
            'commissions:manage',
        ],
        'warehouse_user' => [
            'catalog:view',
            'catalog:manage',
            'customers:view',
            'stock:view',
            'stock:manage',
            'returns:view',
            'returns:manage',
        ],
        'auditor' => [
            'settings:view',
            'users:view',
            'roles:view',
            'branches:view',
            'audit:view',
            'sales-team:view',
            'customers:view',
            'leads:view',
            'appointments:view',
            'catalog:view',
            'attendance:view',
            'tracking:view',
            'visits:view',
            'orders:view',
            'stock:view',
            'returns:view',
            'collections:view',
            'expenses:view',
            'targets:view',
            'reports:view',
            'commissions:view',
        ],
    ];

    public function provision(array $attributes, array $administrator): Tenant
    {
        return DB::transaction(function () use ($attributes, $administrator): Tenant {
            $tenant = Tenant::create([
                'uuid' => (string) Str::uuid(),
                'name' => $attributes['name'],
                'slug' => $attributes['slug'],
                'timezone' => $attributes['timezone'],
                'contact_email' => $attributes['contact_email'] ?? ($administrator['email'] ?? null),
                'subscription_status' => 'active',
            ]);

            $roles = $this->provisionRbac($tenant);
            $this->seedTrackingDefaults($tenant);

            $branch = Branch::create([
                'tenant_id' => $tenant->id,
                'code' => 'MAIN',
                'name' => $attributes['name'].' Headquarters',
                'is_active' => true,
            ]);

            $user = User::create([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
                'name' => $administrator['name'],
                'email' => $administrator['email'],
                'password' => $administrator['password'],
                'role' => 'company_admin',
                'is_active' => true,
            ]);

            $user->syncPrimaryRole($roles['company_admin']);

            return $tenant;
        });
    }

    public function provisionRbac(Tenant $tenant): array
    {
        foreach (self::PERMISSION_SLUGS as $slug) {
            Permission::firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => str($slug)->replace(':', ' ')->title(),
                    'group' => str($slug)->before(':'),
                ],
            );
        }

        $roles = [];

        foreach (self::ROLE_GRANTS as $slug => $permissions) {
            $role = Role::firstOrCreate(
                ['tenant_id' => $tenant->id, 'slug' => $slug],
                [
                    'name' => str($slug)->replace('_', ' ')->title(),
                    'is_system' => true,
                ],
            );

            $role->permissions()->sync(
                $permissions === '*'
                    ? Permission::pluck('id')->all()
                    : Permission::whereIn('slug', $permissions)->pluck('id')->all()
            );

            $roles[$slug] = $role;
        }

        return $roles;
    }

    public function seedTrackingDefaults(Tenant $tenant): void
    {
        foreach (config('tenancy.defaults') as $key => $value) {
            CompanySetting::firstOrCreate(
                ['tenant_id' => $tenant->id, 'key' => 'tracking.'.$key],
                ['value' => is_bool($value) ? ($value ? '1' : '0') : (string) $value],
            );
        }
    }
}
