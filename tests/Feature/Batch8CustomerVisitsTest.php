<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Device;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RouteCustomer;
use App\Models\Salesman;
use App\Models\SalesmanAssignment;
use App\Models\SalesRoute;
use App\Models\Tenant;
use App\Models\Territory;
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
        $this->createWorkSession($actor);

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
        $this->createWorkSession($actor);

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

    public function test_customer_call_activity_is_optional_offline_idempotent_and_visible_in_history(): void
    {
        $actor = $this->actor();
        $customer = $this->customer($actor);
        $uuid = (string) Str::uuid();

        $payload = [
            'offline_uuid' => $uuid,
            'customer_id' => $customer->uuid,
            'phone_number' => '+93700000000',
            'called_at' => '2026-09-19T05:30:00Z',
            'outcome' => 'payment_follow_up',
            'notes' => 'Customer asked for a reminder tomorrow.',
        ];

        $this->postJson('/api/v1/call-activities', $payload, $this->headers())
            ->assertCreated()
            ->assertJsonPath('data.id', $uuid)
            ->assertJsonPath('data.customer_id', $customer->uuid)
            ->assertJsonPath('data.outcome', 'payment_follow_up');

        $this->postJson('/api/v1/call-activities', $payload, $this->headers())
            ->assertOk();

        $this->assertDatabaseCount('customer_call_activities', 1);

        $this->getJson(
            '/api/v1/call-activities/history?customer_id='.$customer->uuid,
            $this->headers(),
        )
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $uuid);
    }

    public function test_territory_assignment_customer_is_planned_without_route(): void
    {
        $actor = $this->actor();

        $customer = app(TenantContext::class)->withTenant(
            $actor['t'],
            function () use ($actor): Customer {
                $branch = Branch::create([
                    'code' => 'KBL',
                    'name' => 'Kabul Main',
                    'is_active' => true,
                ]);

                $territory = Territory::create([
                    'branch_id' => $branch->id,
                    'code' => 'KBL-N',
                    'name' => 'Kabul North',
                    'is_active' => true,
                ]);

                $customer = Customer::create([
                    'branch_id' => $branch->id,
                    'territory_id' => $territory->id,
                    'code' => 'CUS-TERRITORY',
                    'name' => 'Territory Planned Shop',
                    'latitude' => 34.5,
                    'longitude' => 69.2,
                    'geofence_radius_meters' => 100,
                    'is_active' => true,
                ]);

                SalesmanAssignment::create([
                    'salesman_id' => $actor['s']->id,
                    'branch_id' => $branch->id,
                    'territory_id' => $territory->id,
                    'effective_from' => '2026-09-19',
                    'created_by' => $actor['u']->id,
                ]);

                return $customer;
            }
        );

        $this->createWorkSession($actor);
        $uuid = (string) Str::uuid();

        $this->postJson('/api/v1/visits/check-in', [
            'offline_uuid' => $uuid,
            'customer_id' => $customer->uuid,
            'latitude' => 34.50001,
            'longitude' => 69.20001,
            'accuracy' => 8,
            'checked_in_at' => '2026-09-19T05:00:00Z',
        ], $this->headers())
            ->assertCreated()
            ->assertJsonPath('data.is_planned', true)
            ->assertJsonPath('data.route_id', null);
    }

    public function test_visit_voice_note_upload_is_idempotent_and_respects_ai_customer_data_policy(): void
    {
        Storage::fake('local');
        config()->set('ai.enabled', true);
        config()->set('ai.allow_customer_data', false);
        config()->set('ai.transcription_enabled', true);
        config()->set('ai.api_key', 'test-key');
        config()->set('ai.transcription_model', 'whisper-large-v3-turbo');

        $actor = $this->actor();
        $customer = $this->customer($actor);
        $this->createWorkSession($actor);
        $visitUuid = (string) Str::uuid();
        $voiceUuid = (string) Str::uuid();

        $this->postJson('/api/v1/visits/check-in', [
            'offline_uuid' => $visitUuid,
            'customer_id' => $customer->uuid,
            'latitude' => 34.50001,
            'longitude' => 69.20001,
            'accuracy' => 8,
            'checked_in_at' => '2026-09-19T05:00:00Z',
        ], $this->headers())->assertCreated();

        $this->post('/api/v1/visits/'.$visitUuid.'/voice-notes', [
            'client_uuid' => $voiceUuid,
            'voice_note' => UploadedFile::fake()->create(
                'visit-note.m4a',
                64,
                'audio/mp4',
            ),
            'duration_seconds' => 42,
            'recorded_at' => '2026-09-19T05:08:00Z',
            'language' => 'fa',
        ], $this->headers())
            ->assertCreated()
            ->assertJsonPath('data.id', $voiceUuid)
            ->assertJsonPath('data.duration_seconds', 42)
            ->assertJsonPath('data.transcription_status', 'blocked_policy');

        $this->post('/api/v1/visits/'.$visitUuid.'/voice-notes', [
            'client_uuid' => $voiceUuid,
        ], $this->headers())
            ->assertOk()
            ->assertJsonPath('data.id', $voiceUuid);

        $this->assertDatabaseCount('visit_voice_notes', 1);
        $this->assertDatabaseHas('visit_voice_notes', [
            'uuid' => $voiceUuid,
            'transcription_status' => 'blocked_policy',
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

            $permission = Permission::firstOrCreate(
                ['slug' => 'customers:view'],
                [
                    'name' => 'Customers View',
                    'group' => 'customers',
                ]
            );

            $role = Role::create([
                'tenant_id' => $tenant->id,
                'name' => 'Salesman',
                'slug' => 'salesman',
                'is_system' => true,
            ]);
            $role->permissions()->sync([$permission->id]);
            $user->syncPrimaryRole($role);

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

    private function createWorkSession(array $actor): void
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
