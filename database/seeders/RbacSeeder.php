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
        ],
        'owner' => [
            'dashboard:view', 'users:view', 'users:manage', 'branches:view',
            'branches:manage', 'roles:view', 'roles:manage', 'settings:view',
            'settings:manage', 'audit:view',
        ],
        'company_admin' => [
            'dashboard:view', 'users:view', 'users:manage', 'branches:view',
            'branches:manage', 'roles:view', 'settings:view', 'settings:manage', 'audit:view',
        ],
        'sales_manager' => [
            'dashboard:view', 'users:view', 'branches:view', 'roles:view',
        ],
        'supervisor' => [
            'dashboard:view', 'users:view', 'branches:view',
        ],
        'salesman' => [
            'dashboard:view',
        ],
        'accountant' => [
            'dashboard:view', 'settings:view', 'audit:view',
        ],
        'warehouse_user' => [
            'dashboard:view', 'branches:view',
        ],
        'auditor' => [
            'dashboard:view', 'users:view', 'branches:view', 'roles:view',
            'settings:view', 'audit:view',
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
