<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Backfill the stock/returns permissions for tenants that existed
     * before the salesman stock module was introduced.
     */
    public function up(): void
    {
        DB::transaction(function (): void {
            $definitions = [
                'stock:view' => ['Stock View', 'stock'],
                'stock:manage' => ['Stock Manage', 'stock'],
                'returns:view' => ['Returns View', 'returns'],
                'returns:manage' => ['Returns Manage', 'returns'],
            ];

            $now = now();

            foreach ($definitions as $slug => [$name, $group]) {
                DB::table('permissions')->insertOrIgnore([
                    'name' => $name,
                    'slug' => $slug,
                    'group' => $group,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $permissionIds = DB::table('permissions')
                ->whereIn('slug', array_keys($definitions))
                ->pluck('id', 'slug');

            $grants = [
                'owner' => [
                    'stock:view',
                    'stock:manage',
                    'returns:view',
                    'returns:manage',
                ],
                'company_admin' => [
                    'stock:view',
                    'stock:manage',
                    'returns:view',
                    'returns:manage',
                ],
                'sales_manager' => [
                    'stock:view',
                    'stock:manage',
                    'returns:view',
                    'returns:manage',
                ],
                'supervisor' => [
                    'stock:view',
                    'returns:view',
                ],
                'salesman' => [
                    'stock:view',
                    'returns:view',
                ],
                'warehouse_user' => [
                    'stock:view',
                    'stock:manage',
                    'returns:view',
                    'returns:manage',
                ],
                'auditor' => [
                    'stock:view',
                    'returns:view',
                ],
            ];

            foreach ($grants as $roleSlug => $permissionSlugs) {
                $roleIds = DB::table('roles')
                    ->where('slug', $roleSlug)
                    ->pluck('id');

                foreach ($roleIds as $roleId) {
                    foreach ($permissionSlugs as $permissionSlug) {
                        $permissionId = $permissionIds->get($permissionSlug);

                        if ($permissionId === null) {
                            continue;
                        }

                        DB::table('role_permissions')->insertOrIgnore([
                            'role_id' => $roleId,
                            'permission_id' => $permissionId,
                        ]);
                    }
                }
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            $permissionIds = DB::table('permissions')
                ->whereIn('slug', [
                    'stock:view',
                    'stock:manage',
                    'returns:view',
                    'returns:manage',
                ])
                ->pluck('id', 'slug');

            $grants = [
                'owner' => [
                    'stock:view',
                    'stock:manage',
                    'returns:view',
                    'returns:manage',
                ],
                'company_admin' => [
                    'stock:view',
                    'stock:manage',
                    'returns:view',
                    'returns:manage',
                ],
                'sales_manager' => [
                    'stock:view',
                    'stock:manage',
                    'returns:view',
                    'returns:manage',
                ],
                'supervisor' => [
                    'stock:view',
                    'returns:view',
                ],
                'salesman' => [
                    'stock:view',
                    'returns:view',
                ],
                'warehouse_user' => [
                    'stock:view',
                    'stock:manage',
                    'returns:view',
                    'returns:manage',
                ],
                'auditor' => [
                    'stock:view',
                    'returns:view',
                ],
            ];

            foreach ($grants as $roleSlug => $permissionSlugs) {
                $roleIds = DB::table('roles')
                    ->where('slug', $roleSlug)
                    ->pluck('id');

                $ids = collect($permissionSlugs)
                    ->map(fn (string $slug) => $permissionIds->get($slug))
                    ->filter()
                    ->values()
                    ->all();

                if ($roleIds->isEmpty() || $ids === []) {
                    continue;
                }

                DB::table('role_permissions')
                    ->whereIn('role_id', $roleIds)
                    ->whereIn('permission_id', $ids)
                    ->delete();
            }

            foreach ($permissionIds as $permissionId) {
                $inUse = DB::table('role_permissions')
                    ->where('permission_id', $permissionId)
                    ->exists();

                if (! $inUse) {
                    DB::table('permissions')
                        ->where('id', $permissionId)
                        ->delete();
                }
            }
        });
    }
};
