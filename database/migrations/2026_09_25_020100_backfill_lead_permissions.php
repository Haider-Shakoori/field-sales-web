<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $permissions = [
            'leads:view' => 'Leads View',
            'leads:manage' => 'Leads Manage',
        ];

        foreach ($permissions as $slug => $name) {
            DB::table('permissions')->updateOrInsert(
                ['slug' => $slug],
                ['name' => $name, 'group' => 'leads', 'updated_at' => $now, 'created_at' => $now],
            );
        }

        $ids = DB::table('permissions')->whereIn('slug', array_keys($permissions))->pluck('id', 'slug');
        $grants = [
            'owner' => ['leads:view', 'leads:manage'],
            'company_admin' => ['leads:view', 'leads:manage'],
            'sales_manager' => ['leads:view', 'leads:manage'],
            'supervisor' => ['leads:view', 'leads:manage'],
            'salesman' => ['leads:view', 'leads:manage'],
            'auditor' => ['leads:view'],
        ];

        DB::table('roles')->whereIn('slug', array_keys($grants))->orderBy('id')->each(
            function ($role) use ($grants, $ids): void {
                foreach ($grants[$role->slug] as $slug) {
                    if (isset($ids[$slug])) {
                        DB::table('role_permissions')->updateOrInsert([
                            'role_id' => $role->id,
                            'permission_id' => $ids[$slug],
                        ]);
                    }
                }
            }
        );
    }

    public function down(): void
    {
        $ids = DB::table('permissions')->whereIn('slug', ['leads:view', 'leads:manage'])->pluck('id');
        DB::table('role_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }
};
