<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Expense;
use App\Models\LocationHistory;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Salesman;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class MileageFuelTest extends TestCase
{
    use RefreshDatabase;

    private ?string $token = null;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-09-25T08:00:00Z');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_attendance_gps_odometer_and_fuel_flow_produces_mileage_summary(): void
    {
        $actor = $this->salesmanActor();

        $startUuid = (string) Str::uuid();

        $this->postJson('/api/v1/attendance/start', [
            'offline_uuid' => $startUuid,
            'started_at' => '2026-09-25T07:00:00Z',
            'latitude' => 34.5500,
            'longitude' => 69.2000,
            'accuracy' => 5,
            'vehicle_reference' => 'CAR-01',
            'odometer_start_km' => 1000,
        ], $this->headers())
            ->assertCreated()
            ->assertJsonPath('data.vehicle_reference', 'CAR-01')
            ->assertJsonPath('data.odometer_start_km', 1000);

        app(TenantContext::class)->withTenant(
            $actor['tenant'],
            function () use ($actor): void {
                foreach ([
                    ['2026-09-25 07:05:00', 34.5500, 69.2000],
                    ['2026-09-25 07:15:00', 34.5600, 69.2000],
                ] as [$recordedAt, $latitude, $longitude]) {
                    LocationHistory::create([
                        'user_id' => $actor['user']->id,
                        'salesman_id' => $actor['salesman']->id,
                        'device_id' => $actor['device']->id,
                        'client_uuid' => (string) Str::uuid(),
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                        'horizontal_accuracy' => 5,
                        'is_charging' => false,
                        'is_mock_location' => false,
                        'recorded_at' => $recordedAt,
                        'received_at' => $recordedAt,
                    ]);
                }
            },
        );

        $end = $this->postJson('/api/v1/attendance/end', [
            'ended_at' => '2026-09-25T07:30:00Z',
            'latitude' => 34.5600,
            'longitude' => 69.2000,
            'accuracy' => 5,
            'vehicle_reference' => 'CAR-01',
            'odometer_end_km' => 1002,
        ], $this->headers())
            ->assertOk()
            ->assertJsonPath('data.odometer_distance_km', 2);

        $this->assertGreaterThan(
            1.0,
            (float) $end->json('data.gps_distance_km'),
        );

        $expenseUuid = (string) Str::uuid();

        $this->postJson('/api/v1/expenses', [
            'offline_uuid' => $expenseUuid,
            'spent_at' => '2026-09-25T07:20:00Z',
            'category' => 'fuel',
            'currency' => 'AFN',
            'amount' => 450,
            'fuel_liters' => 10,
            'odometer_km' => 1001.5,
            'merchant' => 'Test Fuel',
            'latitude' => 34.5550,
            'longitude' => 69.2000,
            'accuracy' => 5,
        ], $this->headers())
            ->assertCreated()
            ->assertJsonPath('data.fuel_liters', 10)
            ->assertJsonPath('data.fuel_unit_price', 45)
            ->assertJsonPath('data.odometer_km', 1001.5);

        app(TenantContext::class)->withTenant(
            $actor['tenant'],
            fn () => Expense::where('uuid', $expenseUuid)
                ->firstOrFail()
                ->update(['status' => 'approved']),
        );

        $this->getJson('/api/v1/mileage/today', $this->headers())
            ->assertOk()
            ->assertJsonPath('data.vehicle_reference', 'CAR-01')
            ->assertJsonPath('data.odometer_distance_km', 2)
            ->assertJsonPath('data.effective_distance_km', 2)
            ->assertJsonPath('data.fuel_liters', 10)
            ->assertJsonPath('data.km_per_liter', 0.2)
            ->assertJsonPath('data.fuel_cost_by_currency.AFN', 450);

        $admin = $this->admin($actor['tenant']);

        $this->actingAs($admin)
            ->get(route('admin.mileage.index', [
                'date_from' => '2026-09-25',
                'date_to' => '2026-09-25',
            ]))
            ->assertOk()
            ->assertSeeText('Mileage & Fuel')
            ->assertSeeText('CAR-01')
            ->assertSeeText('Test Salesman');
    }

    public function test_non_fuel_expense_rejects_fuel_specific_fields(): void
    {
        $this->salesmanActor();

        $this->postJson('/api/v1/expenses', [
            'offline_uuid' => (string) Str::uuid(),
            'spent_at' => '2026-09-25T07:20:00Z',
            'category' => 'meals',
            'currency' => 'AFN',
            'amount' => 200,
            'fuel_liters' => 5,
            'latitude' => 34.5550,
            'longitude' => 69.2000,
            'accuracy' => 5,
        ], $this->headers())
            ->assertUnprocessable();
    }

    private function salesmanActor(): array
    {
        $tenant = app(TenantContext::class)->withPlatformScope(
            fn () => Tenant::create([
                'uuid' => (string) Str::uuid(),
                'name' => 'Mileage Tenant',
                'slug' => 'mileage-'.Str::lower(Str::random(6)),
                'timezone' => 'Asia/Kabul',
            ]),
        );

        [$user, $salesman, $device] = app(TenantContext::class)->withTenant(
            $tenant,
            function () use ($tenant): array {
                $user = User::create([
                    'uuid' => (string) Str::uuid(),
                    'tenant_id' => $tenant->id,
                    'name' => 'Test Salesman',
                    'email' => Str::lower(Str::random(6)).'@mileage.example.test',
                    'password' => Hash::make('password'),
                    'role' => 'salesman',
                    'is_active' => true,
                ]);

                $role = Role::create([
                    'tenant_id' => $tenant->id,
                    'name' => 'Mileage Salesman',
                    'slug' => 'mileage-salesman',
                    'is_system' => false,
                ]);

                $permission = Permission::firstOrCreate(
                    ['slug' => 'expenses:view'],
                    [
                        'name' => 'Expenses View',
                        'group' => 'expenses',
                    ],
                );
                $role->permissions()->sync([$permission->id]);
                $user->syncPrimaryRole($role);

                $salesman = Salesman::create([
                    'tenant_id' => $tenant->id,
                    'user_id' => $user->id,
                    'employee_code' => 'MIL-001',
                    'first_name' => 'Test',
                    'last_name' => 'Salesman',
                    'is_active' => true,
                ]);

                $device = Device::create([
                    'tenant_id' => $tenant->id,
                    'user_id' => $user->id,
                    'salesman_id' => $salesman->id,
                    'device_uuid' => 'mileage-device',
                    'installation_uuid' => 'mileage-install',
                    'is_active' => true,
                ]);

                return [$user, $salesman, $device];
            },
        );

        $this->token = app(TenantContext::class)->withTenant(
            $tenant,
            fn () => $user->createToken('mobile-'.$device->uuid)->plainTextToken,
        );

        return compact('tenant', 'user', 'salesman', 'device');
    }

    private function admin(Tenant $tenant): User
    {
        return app(TenantContext::class)->withTenant(
            $tenant,
            function () use ($tenant): User {
                $user = User::create([
                    'uuid' => (string) Str::uuid(),
                    'tenant_id' => $tenant->id,
                    'name' => 'Mileage Admin',
                    'email' => Str::lower(Str::random(6)).'@mileage-admin.example.test',
                    'password' => Hash::make('password'),
                    'role' => 'company_admin',
                    'is_active' => true,
                ]);

                $role = Role::create([
                    'tenant_id' => $tenant->id,
                    'name' => 'Mileage Admin',
                    'slug' => 'mileage-admin',
                    'is_system' => false,
                ]);

                $permission = Permission::firstOrCreate(
                    ['slug' => 'reports:view'],
                    [
                        'name' => 'Reports View',
                        'group' => 'reports',
                    ],
                );
                $role->permissions()->sync([$permission->id]);
                $user->syncPrimaryRole($role);

                return $user;
            },
        );
    }

    private function headers(): array
    {
        return [
            'Authorization' => 'Bearer '.$this->token,
            'X-Device-UUID' => 'mileage-device',
            'X-Installation-UUID' => 'mileage-install',
            'X-App-Version' => '1.0',
            'X-Platform' => 'android',
            'X-OS-Version' => '16',
        ];
    }
}
