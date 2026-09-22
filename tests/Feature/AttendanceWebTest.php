<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Salesman;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkSession;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AttendanceWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_attendance_page_shows_each_salesman_and_total_worked_hours(): void
    {
        [$tenant, $admin] = $this->tenantUser(
            'attendance-admin@example.test',
            ['sales-team:view'],
        );

        [$salesman, $device] = $this->salesman($tenant, 'SALES-001', 'Ahmad');
        [$withoutAttendance] = $this->salesman($tenant, 'SALES-002', 'Fatima');

        app(TenantContext::class)->withTenant(
            $tenant,
            function () use ($tenant, $salesman, $device): void {
                WorkSession::create([
                    'tenant_id' => $tenant->id,
                    'uuid' => (string) Str::uuid(),
                    'user_id' => $salesman->user_id,
                    'salesman_id' => $salesman->id,
                    'device_id' => $device->id,
                    'date' => '2026-09-21',
                    'start_time' => '2026-09-21 03:30:00',
                    'end_time' => '2026-09-21 11:30:00',
                    'start_latitude' => 34.5553,
                    'start_longitude' => 69.2075,
                    'start_accuracy' => 5,
                    'end_latitude' => 34.5554,
                    'end_longitude' => 69.2076,
                    'end_accuracy' => 5,
                    'status' => 'completed',
                    'duration_minutes' => 480,
                    'is_late_start' => false,
                    'is_early_finish' => false,
                ]);

                WorkSession::create([
                    'tenant_id' => $tenant->id,
                    'uuid' => (string) Str::uuid(),
                    'user_id' => $salesman->user_id,
                    'salesman_id' => $salesman->id,
                    'device_id' => $device->id,
                    'date' => '2026-09-22',
                    'start_time' => '2026-09-22 03:45:00',
                    'end_time' => '2026-09-22 11:15:00',
                    'start_latitude' => 34.5553,
                    'start_longitude' => 69.2075,
                    'start_accuracy' => 5,
                    'end_latitude' => 34.5554,
                    'end_longitude' => 69.2076,
                    'end_accuracy' => 5,
                    'status' => 'completed',
                    'duration_minutes' => 450,
                    'is_late_start' => true,
                    'is_early_finish' => true,
                ]);
            }
        );

        $response = $this->actingAs($admin)->get(route('admin.attendance.index', [
            'date_from' => '2026-09-21',
            'date_to' => '2026-09-22',
        ]));

        $response
            ->assertOk()
            ->assertSee('Attendance &amp; work hours', false)
            ->assertSee($salesman->full_name)
            ->assertSee($withoutAttendance->full_name)
            ->assertSee('15h 30m')
            ->assertSee('No record');
    }

    public function test_attendance_page_requires_sales_team_view_permission(): void
    {
        [, $user] = $this->tenantUser(
            'attendance-denied@example.test',
            [],
            'auditor',
            'Denied Tenant',
            'attendance-denied',
        );

        $this->actingAs($user)
            ->get(route('admin.attendance.index'))
            ->assertForbidden();
    }

    private function tenantUser(
        string $email,
        array $permissions,
        string $roleSlug = 'company-admin',
        string $tenantName = 'Attendance Tenant',
        string $tenantSlug = 'attendance-tenant',
    ): array {
        $tenant = app(TenantContext::class)->withPlatformScope(
            fn (): Tenant => Tenant::create([
                'uuid' => (string) Str::uuid(),
                'name' => $tenantName,
                'slug' => $tenantSlug,
                'timezone' => 'Asia/Kabul',
            ])
        );

        $user = app(TenantContext::class)->withTenant(
            $tenant,
            function () use ($tenant, $email, $permissions, $roleSlug): User {
                $user = User::create([
                    'uuid' => (string) Str::uuid(),
                    'tenant_id' => $tenant->id,
                    'name' => str($roleSlug)->replace('-', ' ')->title(),
                    'email' => $email,
                    'password' => Hash::make('password'),
                    'role' => $roleSlug,
                    'is_active' => true,
                ]);

                $role = Role::create([
                    'tenant_id' => $tenant->id,
                    'name' => str($roleSlug)->replace('-', ' ')->title(),
                    'slug' => $roleSlug,
                    'is_system' => false,
                ]);

                $permissionIds = collect($permissions)->map(
                    fn (string $slug) => Permission::firstOrCreate(
                        ['slug' => $slug],
                        [
                            'name' => str($slug)->replace(':', ' ')->title(),
                            'group' => str($slug)->before(':'),
                        ]
                    )->id
                )->all();

                $role->permissions()->sync($permissionIds);
                $user->syncPrimaryRole($role);

                return $user;
            }
        );

        return [$tenant, $user];
    }

    private function salesman(
        Tenant $tenant,
        string $employeeCode,
        string $firstName,
    ): array {
        return app(TenantContext::class)->withTenant(
            $tenant,
            function () use ($tenant, $employeeCode, $firstName): array {
                $user = User::create([
                    'uuid' => (string) Str::uuid(),
                    'tenant_id' => $tenant->id,
                    'name' => $firstName.' Salesman',
                    'email' => strtolower($employeeCode).'@example.test',
                    'password' => Hash::make('password'),
                    'role' => 'salesman',
                    'is_active' => true,
                ]);

                $salesman = Salesman::create([
                    'tenant_id' => $tenant->id,
                    'user_id' => $user->id,
                    'employee_code' => $employeeCode,
                    'first_name' => $firstName,
                    'last_name' => 'Salesman',
                    'is_active' => true,
                ]);

                $device = Device::create([
                    'tenant_id' => $tenant->id,
                    'user_id' => $user->id,
                    'salesman_id' => $salesman->id,
                    'device_uuid' => 'device-'.strtolower($employeeCode),
                    'installation_uuid' => (string) Str::uuid(),
                    'is_active' => true,
                ]);

                return [$salesman, $device];
            }
        );
    }
}
