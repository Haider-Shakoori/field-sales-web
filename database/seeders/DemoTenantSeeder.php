<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Device;
use App\Models\Salesman;
use App\Models\Supervisor;
use App\Models\Tenant;
use App\Models\User;
use App\Support\FieldProfiles\EmployeeCodeGenerator;
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

            if ($role === 'supervisor') {
                $this->upsertSupervisor($tenant, $user);
            }

            if ($role === 'salesman') {
                $this->upsertSalesman($tenant, $slug, $user);
            }
        }
    }

    private function upsertSupervisor(Tenant $tenant, User $user): void
    {
        $code = EmployeeCodeGenerator::nextSupervisorCode($tenant->id);

        Supervisor::updateOrCreate(
            ['tenant_id' => $tenant->id, 'employee_code' => $code],
            [
                'user_id' => $user->id,
                'first_name' => $user->name,
                'phone' => '+93 70 111 2222',
                'is_active' => true,
            ],
        );
    }

    private function upsertSalesman(Tenant $tenant, string $slug, User $user): void
    {
        $code = EmployeeCodeGenerator::nextSalesmanCode($tenant->id);

        $salesman = Salesman::updateOrCreate(
            ['tenant_id' => $tenant->id, 'employee_code' => $code],
            [
                'user_id' => $user->id,
                'first_name' => $user->name,
                'phone' => '+93 70 333 4444',
                'email' => $user->email,
                'hire_date' => now()->subMonths(6),
                'designation' => 'Field Sales Rep',
                'is_active' => true,
            ],
        );

        $deviceUuid = $this->deterministicUuid('device:'.$slug);
        $installationId = $this->deterministicUuid('installation:'.$slug);

        Device::updateOrCreate(
            ['installation_uuid' => $installationId],
            [
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'salesman_id' => $salesman->id,
                'device_uuid' => $deviceUuid,
                'device_model' => 'Samsung Galaxy S24',
                'manufacturer' => 'Samsung',
                'android_version' => '14',
                'app_version' => config('tenancy.mobile.min_app_version'),
                'push_token' => 'demo_push_token_'.$slug,
                'is_active' => true,
                'registered_at' => now()->subDays(7),
                'last_seen_at' => now()->subMinutes(30),
            ],
        );
    }

    private function deterministicUuid(string $seed): string
    {
        $hash = md5($seed);

        return substr($hash, 0, 8).'-'.substr($hash, 8, 4).'-'.substr($hash, 12, 4).'-'.substr($hash, 16, 4).'-'.substr($hash, 20, 12);
    }
}
