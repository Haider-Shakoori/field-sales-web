<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Device;
use App\Models\Salesman;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AppointmentReminderService;
use App\Services\TenantProvisioningService;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AppointmentsTest extends TestCase
{
    use RefreshDatabase;

    private ?string $token = null;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-09-25T05:30:00Z');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_manager_can_schedule_and_view_timezone_correct_appointment(): void
    {
        $fixture = $this->fixture();

        $this->actingAs($fixture['admin'])
            ->post(route('admin.appointments.store'), [
                'customer_id' => $fixture['customer']->id,
                'assigned_salesman_id' => $fixture['salesman']->id,
                'title' => 'Quarterly account review',
                'type' => 'meeting',
                'starts_at' => '2026-09-26T10:00',
                'ends_at' => '2026-09-26T11:00',
                'reminder_minutes_before' => 30,
                'location' => 'Customer office',
                'notes' => 'Review payment and next order.',
            ])
            ->assertRedirect();

        $appointment = app(TenantContext::class)->withTenant(
            $fixture['tenant'],
            fn () => Appointment::firstOrFail(),
        );

        $this->assertSame(
            '2026-09-26T05:30:00.000000Z',
            $appointment->starts_at->toISOString(),
        );

        $this->actingAs($fixture['admin'])
            ->get(route('admin.appointments.index', ['month' => '2026-09']))
            ->assertOk()
            ->assertSee('Calendar &amp; appointments', false)
            ->assertSee('Quarterly account review')
            ->assertSee($fixture['customer']->name)
            ->assertSee('10:00');

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'appointment.created',
            'subject_id' => $appointment->id,
        ]);
    }

    public function test_mobile_appointment_creation_is_idempotent_and_status_is_owned(): void
    {
        $fixture = $this->fixture();
        $uuid = (string) Str::uuid();

        $payload = [
            'offline_uuid' => $uuid,
            'customer_id' => $fixture['customer']->uuid,
            'title' => 'Collect signed order',
            'type' => 'visit',
            'starts_at' => '2026-09-26T05:30:00Z',
            'ends_at' => '2026-09-26T06:00:00Z',
            'reminder_minutes_before' => 15,
            'location' => 'Customer site',
        ];

        $this->postJson('/api/v1/appointments', $payload, $this->headers())
            ->assertCreated()
            ->assertJsonPath('data.id', $uuid)
            ->assertJsonPath('data.status', 'scheduled')
            ->assertJsonPath('data.customer_id', $fixture['customer']->uuid);

        $this->postJson('/api/v1/appointments', $payload, $this->headers())
            ->assertOk()
            ->assertJsonPath('data.id', $uuid);

        $this->getJson(
            '/api/v1/appointments?from=2026-09-25&to=2026-09-30',
            $this->headers(),
        )
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Collect signed order');

        $this->patchJson(
            '/api/v1/appointments/'.$uuid.'/status',
            ['status' => 'completed'],
            $this->headers(),
        )
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $this->assertDatabaseCount('appointments', 1);
    }

    public function test_due_reminder_is_delivered_once(): void
    {
        $fixture = $this->fixture();

        app(TenantContext::class)->withTenant(
            $fixture['tenant'],
            function () use ($fixture): void {
                Appointment::create([
                    'customer_id' => $fixture['customer']->id,
                    'assigned_salesman_id' => $fixture['salesman']->id,
                    'created_by' => $fixture['admin']->id,
                    'title' => 'Reminder test meeting',
                    'type' => 'meeting',
                    'status' => 'scheduled',
                    'starts_at' => now()->addMinutes(15),
                    'reminder_minutes_before' => 30,
                ]);

                $service = app(AppointmentReminderService::class);

                $this->assertSame(1, $service->sendDue());
                $this->assertSame(0, $service->sendDue());
            },
        );

        $this->assertDatabaseHas('operational_notifications', [
            'user_id' => $fixture['salesmanUser']->id,
            'type' => 'appointment.reminder',
            'category' => 'appointment_reminders',
            'title' => 'Upcoming appointment',
        ]);
    }

    private function fixture(): array
    {
        $context = app(TenantContext::class);

        $tenant = $context->withPlatformScope(fn () => Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Calendar Tenant',
            'slug' => 'calendar-'.Str::lower(Str::random(6)),
            'timezone' => 'Asia/Kabul',
            'subscription_status' => 'active',
        ]));

        return $context->withTenant($tenant, function () use ($tenant): array {
            $roles = app(TenantProvisioningService::class)->provisionRbac($tenant);

            $branch = Branch::create([
                'name' => 'Calendar Branch',
                'code' => 'CAL',
                'is_active' => true,
            ]);

            $admin = User::create([
                'uuid' => (string) Str::uuid(),
                'branch_id' => $branch->id,
                'name' => 'Calendar Admin',
                'email' => 'calendar-admin@example.test',
                'password' => Hash::make('password'),
                'role' => 'company_admin',
                'is_active' => true,
            ]);
            $admin->syncPrimaryRole($roles['company_admin']);

            $salesmanUser = User::create([
                'uuid' => (string) Str::uuid(),
                'branch_id' => $branch->id,
                'name' => 'Calendar Salesman',
                'email' => 'calendar-salesman@example.test',
                'password' => Hash::make('password'),
                'role' => 'salesman',
                'is_active' => true,
            ]);
            $salesmanUser->syncPrimaryRole($roles['salesman']);

            $salesman = Salesman::create([
                'user_id' => $salesmanUser->id,
                'employee_code' => 'CAL-SAL-1',
                'first_name' => 'Calendar',
                'last_name' => 'Salesman',
                'is_active' => true,
            ]);

            $customer = Customer::create([
                'branch_id' => $branch->id,
                'code' => 'CAL-CUST-1',
                'name' => 'Calendar Customer',
                'phone' => '+93700000001',
                'created_by' => $admin->id,
                'is_active' => true,
            ]);

            $device = Device::create([
                'user_id' => $salesmanUser->id,
                'salesman_id' => $salesman->id,
                'device_uuid' => 'calendar-device',
                'installation_uuid' => 'calendar-install',
                'is_active' => true,
            ]);

            $this->token = $salesmanUser
                ->createToken('mobile-'.$device->uuid)
                ->plainTextToken;

            return compact(
                'tenant',
                'admin',
                'salesmanUser',
                'salesman',
                'customer',
                'device',
            );
        });
    }

    private function headers(): array
    {
        return [
            'Authorization' => 'Bearer '.$this->token,
            'X-Device-UUID' => 'calendar-device',
            'X-Installation-UUID' => 'calendar-install',
            'X-App-Version' => '1.0',
            'X-Platform' => 'android',
            'X-OS-Version' => '16',
        ];
    }
}
