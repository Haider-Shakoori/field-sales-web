<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $permissions = [
            'appointments:view' => 'Appointments View',
            'appointments:manage' => 'Appointments Manage',
        ];

        foreach ($permissions as $slug => $name) {
            DB::table('permissions')->updateOrInsert(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'group' => 'appointments',
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
        }

        $permissionIds = DB::table('permissions')
            ->whereIn('slug', array_keys($permissions))
            ->pluck('id', 'slug');

        $grants = [
            'owner' => ['appointments:view', 'appointments:manage'],
            'company_admin' => ['appointments:view', 'appointments:manage'],
            'sales_manager' => ['appointments:view', 'appointments:manage'],
            'supervisor' => ['appointments:view', 'appointments:manage'],
            'salesman' => ['appointments:view', 'appointments:manage'],
            'auditor' => ['appointments:view'],
        ];

        DB::table('roles')
            ->whereIn('slug', array_keys($grants))
            ->orderBy('id')
            ->each(function ($role) use ($grants, $permissionIds): void {
                foreach ($grants[$role->slug] as $slug) {
                    $permissionId = $permissionIds[$slug] ?? null;

                    if ($permissionId === null) {
                        continue;
                    }

                    DB::table('role_permissions')->updateOrInsert([
                        'role_id' => $role->id,
                        'permission_id' => $permissionId,
                    ]);
                }
            });
    }

    public function down(): void
    {
        $ids = DB::table('permissions')
            ->whereIn('slug', ['appointments:view', 'appointments:manage'])
            ->pluck('id');

        DB::table('role_permissions')
            ->whereIn('permission_id', $ids)
            ->delete();

        DB::table('permissions')
            ->whereIn('id', $ids)
            ->delete();
    }
};
