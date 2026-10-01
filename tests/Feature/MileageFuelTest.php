<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Device;
use App\Models\Expense;
use App\Models\LocationHistory;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Salesman;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkSession;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
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
        Storage::fake('public');

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
            'vehicle_reference' => 'CAR-01',
            'full_tank' => true,
            'merchant' => 'Test Fuel',
            'latitude' => 34.5550,
            'longitude' => 69.2000,
            'accuracy' => 5,
        ], $this->headers())
            ->assertCreated()
            ->assertJsonPath('data.fuel_liters', 10)
            ->assertJsonPath('data.fuel_unit_price', 45)
            ->assertJsonPath('data.odometer_km', 1001.5)
            ->assertJsonPath('data.vehicle_reference', 'CAR-01')
            ->assertJsonPath('data.full_tank', true)
            ->assertJsonPath('data.receipt_uploaded', false);

        $this->post('/api/v1/expenses/'.$expenseUuid.'/receipt', [
            'receipt' => UploadedFile::fake()->create('fuel-receipt.jpg', 120, 'image/jpeg'),
        ], $this->headers() + ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.receipt_uploaded', true);

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

        auth('sanctum')->forgetUser();
        app('auth')->forgetGuards();

        $admin = $this->admin($actor['tenant']);

        $this->actingAs($admin, 'web')
            ->get(route('admin.mileage.index', [
                'date_from' => '2026-09-25',
                'date_to' => '2026-09-25',
            ]))
            ->assertOk()
            ->assertSeeText('Mileage')
            ->assertSeeText('CAR-01')
            ->assertSeeText('Test Salesman');

        $this->actingAs($admin, 'web')
            ->get(route('admin.fuel.index', [
                'date_from' => '2026-09-25',
                'date_to' => '2026-09-25',
            ]))
            ->assertOk()
            ->assertSeeText('Fuel Management')
            ->assertSeeText('CAR-01')
            ->assertSeeText('Test Fuel')
            ->assertSeeText('Receipt');
    }

    public function test_management_can_enter_and_correct_fuel_and_mileage_with_audit(): void
    {
        $actor = $this->salesmanActor();
        Storage::fake('public');
        $admin = $this->admin($actor['tenant']);

        $this->actingAs($admin, 'web')
            ->post(route('admin.fuel.store'), [
                'salesman' => $actor['salesman']->uuid,
                'spent_at' => '2026-09-25T11:30',
                'vehicle_reference' => 'CAR-MANAGED',
                'merchant' => 'Fuel Card Station',
                'fuel_liters' => 20,
                'amount' => 1000,
                'odometer_km' => 1500,
                'currency' => 'AFN',
                'full_tank' => 1,
                'reference_number' => 'CARD-001',
                'correction_reason' => 'Fuel card transaction was not entered by the driver.',
            ])
            ->assertRedirect(route('admin.fuel.index'));

        $expense = app(TenantContext::class)->withTenant(
            $actor['tenant'],
            fn () => Expense::query()
                ->where('entry_source', 'management_web')
                ->firstOrFail(),
        );

        $this->assertSame($admin->id, $expense->entered_by);
        $this->assertSame($actor['salesman']->id, $expense->salesman_id);
        $this->assertSame('pending', $expense->status);
        $this->assertNull($expense->latitude);
        $this->assertNull($expense->longitude);

        app(TenantContext::class)->withTenant(
            $actor['tenant'],
            fn () => $expense->update([
                'status' => 'approved',
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ]),
        );

        $this->actingAs($admin, 'web')
            ->patch(route('admin.fuel.update', $expense), [
                'spent_at' => '2026-09-25T11:30',
                'vehicle_reference' => 'CAR-MANAGED',
                'merchant' => 'Fuel Card Station',
                'fuel_liters' => 21,
                'amount' => 1050,
                'odometer_km' => 1501,
                'currency' => 'AFN',
                'full_tank' => 1,
                'reference_number' => 'CARD-001',
                'correction_reason' => 'Receipt shows 21 liters, not 20.',
            ])
            ->assertRedirect(route('admin.fuel.index'));

        $expense->refresh();
        $this->assertSame('pending', $expense->status);
        $this->assertSame('21.000', $expense->fuel_liters);
        $this->assertSame('Receipt shows 21 liters, not 20.', $expense->correction_reason);
        $this->assertNull($expense->reviewed_by);

        $session = app(TenantContext::class)->withTenant(
            $actor['tenant'],
            fn () => WorkSession::create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $actor['user']->id,
                'salesman_id' => $actor['salesman']->id,
                'device_id' => $actor['device']->id,
                'date' => '2026-09-25',
                'start_time' => '2026-09-25 07:00:00',
                'end_time' => '2026-09-25 15:00:00',
                'start_latitude' => 34.55,
                'start_longitude' => 69.20,
                'start_accuracy' => 5,
                'end_latitude' => 34.56,
                'end_longitude' => 69.21,
                'end_accuracy' => 5,
                'status' => 'completed',
                'duration_minutes' => 480,
                'vehicle_reference' => 'CAR-OLD',
                'odometer_start_km' => 2000,
                'odometer_end_km' => 2050,
                'gps_distance_km' => 48.5,
            ]),
        );

        $this->actingAs($admin, 'web')
            ->patch(route('admin.mileage.update', $session), [
                'vehicle_reference' => 'CAR-CORRECTED',
                'odometer_start_km' => 2001,
                'odometer_end_km' => 2052,
                'correction_reason' => 'Verified against the vehicle dashboard photo.',
            ])
            ->assertRedirect();

        $session->refresh();
        $this->assertSame('CAR-CORRECTED', $session->vehicle_reference);
        $this->assertSame('2001.00', $session->odometer_start_km);
        $this->assertSame('2052.00', $session->odometer_end_km);
        $this->assertSame('48.500', $session->gps_distance_km);
        $this->assertSame('management_mileage_correction', $session->corrections[0]['type'] ?? null);

        app(TenantContext::class)->withTenant(
            $actor['tenant'],
            function (): void {
                $this->assertSame(
                    1,
                    AuditLog::query()->where('event', 'fuel.management_created')->count(),
                );
                $this->assertSame(
                    1,
                    AuditLog::query()->where('event', 'fuel.management_corrected')->count(),
                );
                $this->assertSame(
                    1,
                    AuditLog::query()->where('event', 'mileage.corrected')->count(),
                );
            },
        );
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

                $permissionSlugs = [
                    'reports:view',
                    'expenses:manage',
                    'attendance:manage',
                    'audit:view',
                ];
                $permissions = collect($permissionSlugs)->map(
                    fn (string $slug) => Permission::firstOrCreate(
                        ['slug' => $slug],
                        [
                            'name' => str($slug)->replace(':', ' ')->title(),
                            'group' => str($slug)->before(':'),
                        ],
                    ),
                );
                $role->permissions()->sync($permissions->pluck('id')->all());
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
