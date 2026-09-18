<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RbacSeeder extends Seeder
{
    /**
     * Role → permission catalog for the whole application.
     * super_admin is platform-scoped and breezes through the Gate::before
     * shortcut, every other role is company-scoped.
     *
     * @var array<string, list<string>>
     */
    public const ROLE_PERMISSIONS = [
        'super_admin' => [
            'dashboard:view', 'users:view', 'users:manage', 'branches:view',
            'branches:manage', 'roles:view', 'roles:manage', 'settings:view',
            'settings:manage', 'audit:view', 'tenants:manage',
            'salesmen:view', 'salesmen:create', 'salesmen:update', 'salesmen:deactivate',
            'supervisors:view', 'supervisors:create', 'supervisors:update', 'supervisors:deactivate',
            'devices:view', 'devices:revoke',
            'customers:view', 'customers:create', 'customers:update', 'customers:deactivate',
            'customer_categories:view', 'customer_categories:create', 'customer_categories:update', 'customer_categories:deactivate',
            'territories:view', 'territories:create', 'territories:update', 'territories:deactivate',
            'routes:view', 'routes:create', 'routes:update', 'routes:deactivate',
            'assignments:view', 'assignments:manage',
            'products:view', 'products:create', 'products:update', 'products:deactivate',
            'price_lists:view', 'price_lists:create', 'price_lists:update', 'price_lists:deactivate', 'price_lists:manage_prices',
            'attendance:view', 'attendance:manage', 'tracking:view',
        ],
        'owner' => [
            'dashboard:view', 'users:view', 'users:manage', 'branches:view',
            'branches:manage', 'roles:view', 'roles:manage', 'settings:view',
            'settings:manage', 'audit:view',
            'salesmen:view', 'salesmen:create', 'salesmen:update', 'salesmen:deactivate',
            'supervisors:view', 'supervisors:create', 'supervisors:update', 'supervisors:deactivate',
            'devices:view', 'devices:revoke',
            'customers:view', 'customers:create', 'customers:update', 'customers:deactivate',
            'customer_categories:view', 'customer_categories:create', 'customer_categories:update', 'customer_categories:deactivate',
            'territories:view', 'territories:create', 'territories:update', 'territories:deactivate',
            'routes:view', 'routes:create', 'routes:update', 'routes:deactivate',
            'assignments:view', 'assignments:manage',
            'products:view', 'products:create', 'products:update', 'products:deactivate',
            'price_lists:view', 'price_lists:create', 'price_lists:update', 'price_lists:deactivate', 'price_lists:manage_prices',
            'attendance:view', 'attendance:manage', 'tracking:view',
        ],
        'company_admin' => [
            'dashboard:view', 'users:view', 'users:manage', 'branches:view',
            'branches:manage', 'roles:view', 'settings:view', 'settings:manage', 'audit:view',
            'salesmen:view', 'salesmen:create', 'salesmen:update', 'salesmen:deactivate',
            'supervisors:view', 'supervisors:create', 'supervisors:update', 'supervisors:deactivate',
            'devices:view', 'devices:revoke',
            'customers:view', 'customers:create', 'customers:update', 'customers:deactivate',
            'customer_categories:view', 'customer_categories:create', 'customer_categories:update', 'customer_categories:deactivate',
            'territories:view', 'territories:create', 'territories:update', 'territories:deactivate',
            'routes:view', 'routes:create', 'routes:update', 'routes:deactivate',
            'assignments:view', 'assignments:manage',
            'products:view', 'products:create', 'products:update', 'products:deactivate',
            'price_lists:view', 'price_lists:create', 'price_lists:update', 'price_lists:deactivate', 'price_lists:manage_prices',
            'attendance:view', 'attendance:manage', 'tracking:view',
        ],
        'sales_manager' => [
            'dashboard:view', 'users:view', 'branches:view', 'roles:view',
            'salesmen:view', 'salesmen:update', 'supervisors:view', 'devices:view',
            'customers:view', 'customers:create', 'customers:update',
            'customer_categories:view',
            'territories:view', 'territories:create', 'territories:update',
            'routes:view', 'routes:create', 'routes:update',
            'assignments:view',
            'products:view', 'price_lists:view', 'price_lists:manage_prices',
            'attendance:view', 'tracking:view',
        ],
        'supervisor' => [
            'dashboard:view', 'users:view', 'branches:view',
            'salesmen:view', 'devices:view',
            'customers:view',
            'territories:view',
            'routes:view',
            'assignments:view',
            'products:view', 'price_lists:view',
            'attendance:view', 'tracking:view',
        ],
        'salesman' => [
            'dashboard:view',
            'customers:view',
            'routes:view',
            'products:view', 'price_lists:view',
        ],
        'accountant' => [
            'dashboard:view', 'settings:view', 'audit:view',
            'customers:view',
            'products:view', 'price_lists:view',
        ],
        'warehouse_user' => [
            'dashboard:view', 'branches:view',
            'products:view', 'price_lists:view',
        ],
        'auditor' => [
            'dashboard:view', 'users:view', 'branches:view', 'roles:view',
            'settings:view', 'audit:view',
            'customers:view',
            'territories:view',
            'routes:view',
            'products:view', 'price_lists:view',
            'attendance:view',
        ],
    ];

    public function run(): void
    {
        collect(static::ROLE_PERMISSIONS)
            ->flatMap(fn (array $permissions) => $permissions)
            ->unique()
            ->each(fn (string $permission) => Permission::updateOrCreate(
                ['name' => $permission],
                ['guard_name' => 'web'],
            ));

        foreach (static::ROLE_PERMISSIONS as $roleName => $permissionNames) {
            $role = Role::updateOrCreate(['name' => $roleName], ['guard_name' => 'web']);

            $role->permissions()->sync(
                Permission::whereIn('name', $permissionNames)->pluck('id'),
            );
        }
    }
}
