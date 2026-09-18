<?php

namespace Database\Seeders;

use App\Models\CompanySetting;
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

            User::firstOrCreate(
                ['tenant_id' => $tenant->id, 'email' => 'admin@example.com'],
                [
                    'uuid' => (string) Str::uuid(),
                    'name' => 'Demo Admin',
                    'password' => Hash::make('password'),
                    'role' => 'admin',
                    'is_active' => true,
                ]
            );

            $user = User::firstOrCreate(
                ['tenant_id' => $tenant->id, 'email' => 'salesman@example.com'],
                [
                    'uuid' => (string) Str::uuid(),
                    'name' => 'Demo Salesman',
                    'password' => Hash::make('password'),
                    'role' => 'salesman',
                    'is_active' => true,
                ]
            );

            Salesman::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'uuid' => (string) Str::uuid(),
                    'tenant_id' => $tenant->id,
                    'employee_code' => 'SAL-001',
                    'first_name' => 'Demo',
                    'last_name' => 'Salesman',
                    'is_active' => true,
                ]
            );

            foreach (config('tenancy.defaults') as $key => $value) {
                CompanySetting::firstOrCreate(
                    ['tenant_id' => $tenant->id, 'key' => 'tracking.'.$key],
                    ['value' => is_bool($value) ? ($value ? '1' : '0') : (string) $value]
                );
            }
        });
    }
}
