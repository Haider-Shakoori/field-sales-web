<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Device;
use App\Models\RouteCustomer;
use App\Models\Salesman;
use App\Models\SalesmanAssignment;
use App\Models\SalesRoute;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkSession;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class Batch8CustomerVisitsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-09-19T06:30:00Z');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private ?string $token = null;

    public function test_planned_visit_is_idempotent_geofenced_and_completes_with_outcome(): void
    {
        $actor = $this->actor();
        [$customer, $route] = $this->plannedCustomer($actor);
        $this->session($actor);

        $uuid = (string) Str::uuid();
        $checkIn = [
            'offline_uuid' => $uuid,
            'customer_id' => $customer->uuid,
            'latitude' => 34.50001,
            'longitude' => 69.20001,
            'accuracy' => 8,
            'checked_in_at' => '2026-09-19T05:00:00Z',
        ];

        $this->postJson('/api/v1/visits/check-in', $checkIn, $this->headers())
            ->assertCreated()
            ->assertJsonPath('data.id', $uuid)
            ->assertJsonPath('data.is_planned', true)
            ->assertJsonPath('data.route_id', $route->uuid)
            ->assertJsonPath('data.checkin.within_geofence', true);

        $this->postJson('/api/v1/visits/check-in', $checkIn, $this->headers())
            ->assertOk();

        $this->assertDatabaseCount('customer_visits', 1);

        $this->postJson('/api/v1/visits/'.$uuid.'/check-out', [
            'latitude' => 34.50002,
            'longitude' => 69.20002,
            'accuracy' => 7,
            'checked_out_at' => '2026-09-19T05:10:00Z',
            'outcome' => 'order_placed',
            'notes' => 'Order discussed and confirmed.',
        ], $this->headers())
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.outcome', 'order_placed')
            ->assertJsonPath('data.duration_seconds', 600);

        $this->assertDatabaseHas('customer_visits', [
            'uuid' => $uuid,
            'status' => 'completed',
            'outcome' => 'order_placed',
            'is_planned' => 1,
        ]);
    }

    public function test_outside_geofence_and_short_visit_are_flagged_for_review(): void
    {
        $actor = $this->actor();
        $customer = $this->customer($actor);
        $this->session($actor);

        $uuid = (string) Str::uuid();

        $this->postJson('/api/v1/visits/check-in', [
            'offline_uuid' => $uuid,
            'customer_id' => $customer->uuid,
            'latitude' => 34.51000,
            'longitude' => 69.21000,
            'accuracy' => 8,
            'checked_in_at' => '2026-09-19T05:00:00Z',
        ], $this->headers())
            ->assertCreated()
            ->assertJsonPath('data.is_planned', false)
            ->assertJsonPath('data.checkin.within_geofence', false)
            ->assertJsonFragment(['reason' => 'location_mismatch']);

        $this->postJson('/api/v1/visits/'.$uuid.'/check-out', [
            'latitude' => 34.51000,
            'longitude' => 69.21000,
            'accuracy' => 8,
            'checked_out_at' => '2026-09-19T05:00:30Z',
            'outcome' => 'customer_unavailable',
        ], $this->headers())
            ->assertOk()
            ->assertJsonFragment(['reason' => 'too_short_duration']);

        $this->assertDatabaseHas('visit_suspicious_flags', [
            'reason_code' => 'location_mismatch',
        ]);
        $this->assertDatabaseHas('visit_suspicious_flags', [
            'reason_code' => 'too_short_duration',
        ]);
    }

    private function actor(): array
    {
        $tenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Visit Tenant',
            'slug' => 'visit-tenant',
            'timezone' => 'Asia/Kabul',
        ]);

        [$user, $salesman, $device] = app(TenantContext::class)->withPlatformScope(function () use ($tenant): array {
            $user = User::create([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'name' => 'Field Rep',
                'email' => 'visit-rep@example.test',
                'password' => Hash::make('password'),
                'role' => 'salesman',
                'is_active' => true,
            ]);

            $salesman = Salesman::create([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'employee_code' => 'VIS-001',
                'first_name' => 'Field',
                'last_name' => 'Rep',
                'is_active' => true,
            ]);

            $device = Device::create([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'salesman_id' => $salesman->id,
                'device_uuid' => 'visit-device',
                'installation_uuid' => 'visit-install',
                'is_active' => true,
            ]);

            return [$user, $salesman, $device];
        });

        $this->token = app(TenantContext::class)->withTenant(
            $tenant,
            fn () => $user->createToken('mobile-'.$device->uuid)->plainTextToken
        );

        return ['t' => $tenant, 'u' => $user, 's' => $salesman, 'd' => $device];
    }

    private function customer(array $actor): Customer
    {
        return app(TenantContext::class)->withTenant($actor['t'], fn () => Customer::create([
            'code' => 'CUS-001',
            'name' => 'Visit Shop',
            'latitude' => 34.5,
            'longitude' => 69.2,
            'geofence_radius_meters' => 100,
            'is_active' => true,
        ]));
    }

    private function plannedCustomer(array $actor): array
    {
        return app(TenantContext::class)->withTenant($actor['t'], function () use ($actor): array {
            $customer = Customer::create([
                'code' => 'CUS-PLANNED',
                'name' => 'Planned Shop',
                'latitude' => 34.5,
                'longitude' => 69.2,
                'geofence_radius_meters' => 100,
                'is_active' => true,
            ]);

            $route = SalesRoute::create([
                'code' => 'R-001',
                'name' => 'Daily Route',
                'is_active' => true,
            ]);

            RouteCustomer::create([
                'route_id' => $route->id,
                'customer_id' => $customer->id,
                'sequence_number' => 1,
                'planned_visit_minutes' => 10,
            ]);

            SalesmanAssignment::create([
                'salesman_id' => $actor['s']->id,
                'route_id' => $route->id,
                'effective_from' => '2026-09-19',
                'created_by' => $actor['u']->id,
            ]);

            return [$customer, $route];
        });
    }

    private function session(array $actor): void
    {
        app(TenantContext::class)->withTenant($actor['t'], fn () => WorkSession::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $actor['u']->id,
            'salesman_id' => $actor['s']->id,
            'device_id' => $actor['d']->id,
            'date' => '2026-09-19',
            'start_time' => '2026-09-19 04:00:00',
            'end_time' => '2026-09-19 10:00:00',
            'start_latitude' => 34.5,
            'start_longitude' => 69.2,
            'start_accuracy' => 5,
            'status' => 'completed',
        ]));
    }

    private function headers(): array
    {
        return [
            'Authorization' => 'Bearer '.$this->token,
            'X-Device-UUID' => 'visit-device',
            'X-Installation-UUID' => 'visit-install',
            'X-App-Version' => '1.0',
            'X-Platform' => 'android',
            'X-OS-Version' => '16',
        ];
    }
}
