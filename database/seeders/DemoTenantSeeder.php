<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

class DemoTenantSeeder extends Seeder
{
    private const DEMO_PASSWORD = 'Password123!';

    public function run(): void
    {
        TenantContext::withSystemScope(function (): void {
            $this->seedSuperAdmin();

            $this->seedDemoTenant('Demo Distributors', 'demo-distributors', [
                'owner' => 'owner@demo.test',
                'company_admin' => 'admin@demo.test',
                'sales_manager' => 'manager@demo.test',
                'supervisor' => 'supervisor@demo.test',
                'salesman' => 'salesman@demo.test',
                'accountant' => 'accountant@demo.test',
                'warehouse_user' => 'warehouse@demo.test',
                'auditor' => 'auditor@demo.test',
            ]);

            $this->seedDemoTenant('KBL Fresh Foods', 'kbl-fresh-foods', [
                'owner' => 'owner@fresh.test',
                'salesman' => 'salesman@fresh.test',
            ]);
        });
    }

    private function seedSuperAdmin(): void
    {
        $superAdmin = User::updateOrCreate(
            ['email' => 'superadmin@platform.test'],
            ['name' => 'Platform Super Admin', 'password' => static::DEMO_PASSWORD, 'is_active' => true],
        );
        $superAdmin->assignRole('super_admin');
    }

    /**
     * @param  array<string, string>  $usersByRole
     */
    private function seedDemoTenant(string $name, string $slug, array $usersByRole): void
    {
        $tenant = Tenant::updateOrCreate(
            ['slug' => $slug],
            [
                'name' => $name,
                'timezone' => 'Asia/Kabul',
                'default_currency' => 'AFN',
                'locale' => 'en',
                'subscription_status' => 'trial',
            ],
        );

        Branch::updateOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'KBL-01'],
            [
                'name' => 'Kabul Main Office',
                'address' => 'Shahr-e-Naw, Kabul',
                'city' => 'Kabul',
                'province' => 'Kabul',
                'phone' => '+93 70 000 0000',
                'is_active' => true,
            ],
        );

        foreach ($usersByRole as $role => $email) {
            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => ucfirst(str_replace('_', ' ', $role)),
                    'tenant_id' => $tenant->id,
                    'password' => static::DEMO_PASSWORD,
                    'is_active' => true,
                ],
            );

            $user->assignRole($role, $tenant->id);
        }
    }
}
