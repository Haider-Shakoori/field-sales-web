<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $permissions = ['commissions:view' => 'Commissions View', 'commissions:manage' => 'Commissions Manage'];
        foreach ($permissions as $slug => $name) {
            DB::table('permissions')->updateOrInsert(['slug' => $slug], ['name' => $name, 'group' => 'commissions', 'created_at' => $now, 'updated_at' => $now]);
        }
        $ids = DB::table('permissions')->whereIn('slug', array_keys($permissions))->pluck('id', 'slug');
        $grants = [
            'owner' => ['commissions:view','commissions:manage'], 'company_admin' => ['commissions:view','commissions:manage'],
            'sales_manager' => ['commissions:view','commissions:manage'], 'supervisor' => ['commissions:view'],
            'salesman' => ['commissions:view'], 'accountant' => ['commissions:view','commissions:manage'], 'auditor' => ['commissions:view'],
        ];
        DB::table('roles')->whereIn('slug', array_keys($grants))->orderBy('id')->each(function ($role) use ($grants, $ids): void {
            foreach ($grants[$role->slug] as $slug) if (isset($ids[$slug])) DB::table('role_permissions')->updateOrInsert(['role_id'=>$role->id,'permission_id'=>$ids[$slug]]);
        });
    }

    public function down(): void
    {
        $ids=DB::table('permissions')->whereIn('slug',['commissions:view','commissions:manage'])->pluck('id');
        DB::table('role_permissions')->whereIn('permission_id',$ids)->delete();
        DB::table('permissions')->whereIn('id',$ids)->delete();
    }
};