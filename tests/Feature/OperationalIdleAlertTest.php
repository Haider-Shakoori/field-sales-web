<?php

namespace Tests\Feature;

use App\Models\CompanySetting;
use App\Models\CurrentLocation;
use App\Models\Device;
use App\Models\OperationalNotification;
use App\Models\Salesman;
use App\Models\SalesmanAssignment;
use App\Models\Supervisor;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkSession;
use App\Services\OperationalAlertService;
use App\Services\TenantProvisioningService;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class OperationalIdleAlertTest extends TestCase
{
    use RefreshDatabase;

    public function test_idle_salesman_is_alerted_and_then_escalated_to_supervisor(): void
    {
        $tenant = app(TenantContext::class)->withPlatformScope(fn () => Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Idle Alert Tenant',
            'slug' => 'idle-alert-'.Str::lower(Str::random(6)),
            'timezone' => 'Asia/Kabul',
            'subscription_status' => 'active',
        ]));

        app(TenantContext::class)->withTenant($tenant, function () use ($tenant): void {
            app(TenantProvisioningService::class)->provisionRbac($tenant);

            foreach ([
                'idle_alerts_enabled' => '1',
                'idle_alert_after_minutes' => '30',
                'idle_alert_repeat_minutes' => '60',
                'idle_escalation_enabled' => '1',
                'idle_escalate_after_minutes' => '30',
                'workday_start_time' => '00:00',
                'workday_end_time' => '23:59',
            ] as $key => $value) {
                CompanySetting::updateOrCreate(
                    ['key' => 'tracking.'.$key],
                    ['value' => $value],
                );
            }

            $salesUser = User::create([
                'uuid' => (string) Str::uuid(),
                'name' => 'Idle Salesman',
                'email' => 'idle@example.test',
                'password' => Hash::make('password'),
                'role' => 'salesman',
                'is_active' => true,
            ]);
            $salesman = Salesman::create([
                'user_id' => $salesUser->id,
                'employee_code' => 'IDLE-1',
                'first_name' => 'Idle',
                'last_name' => 'Salesman',
                'is_active' => true,
            ]);

            $supervisorUser = User::create([
                'uuid' => (string) Str::uuid(),
                'name' => 'Supervisor',
                'email' => 'supervisor-idle@example.test',
                'password' => Hash::make('password'),
                'role' => 'supervisor',
                'is_active' => true,
            ]);
            $supervisor = Supervisor::create([
                'user_id' => $supervisorUser->id,
                'employee_code' => 'SUP-IDLE',
                'first_name' => 'Team',
                'last_name' => 'Supervisor',
                'is_active' => true,
            ]);
            SalesmanAssignment::create([
                'salesman_id' => $salesman->id,
                'supervisor_id' => $supervisor->id,
                'effective_from' => CarbonImmutable::parse($localDate)->subDay()->toDateString(),
            ]);

            $localDate = CarbonImmutable::now('Asia/Kabul')->toDateString();

            $device = Device::create([
                'user_id' => $salesUser->id,
                'salesman_id' => $salesman->id,
                'device_uuid' => 'idle-device',
                'installation_uuid' => 'idle-installation',
                'platform' => 'android',
                'is_active' => true,
                'registered_at' => now(),
                'last_seen_at' => now()->subMinutes(70),
            ]);

            WorkSession::create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $salesUser->id,
                'salesman_id' => $salesman->id,
                'device_id' => $device->id,
                'date' => $localDate,
                'start_time' => now()->subHours(2),
                'start_latitude' => 34.5,
                'start_longitude' => 69.2,
                'start_accuracy' => 8,
                'status' => 'active',
            ]);
            CurrentLocation::create([
                'user_id' => $salesUser->id,
                'device_id' => $device->id,
                'salesman_id' => $salesman->id,
                'latitude' => 34.5,
                'longitude' => 69.2,
                'horizontal_accuracy' => 8,
                'recorded_at' => now()->subMinutes(70),
                'received_at' => now()->subMinutes(70),
            ]);

            app(OperationalAlertService::class)->sendIdleAlerts($tenant);

            $this->assertDatabaseHas('operational_notifications', [
                'user_id' => $salesUser->id,
                'type' => 'team.idle_alert',
            ]);
            $this->assertDatabaseHas('operational_notifications', [
                'user_id' => $supervisorUser->id,
                'type' => 'team.idle_escalation',
            ]);

            $count = OperationalNotification::count();
            app(OperationalAlertService::class)->sendIdleAlerts($tenant);
            $this->assertSame($count, OperationalNotification::count());
        });
    }
}
