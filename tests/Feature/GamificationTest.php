<?php

namespace Tests\Feature;

use App\Models\Collection;
use App\Models\Customer;
use App\Models\CustomerVisit;
use App\Models\Device;
use App\Models\GamificationPoint;
use App\Models\Order;
use App\Models\Salesman;
use App\Models\Tenant;
use App\Models\User;
use App\Services\GamificationService;
use App\Services\TenantProvisioningService;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class GamificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_events_award_once_and_toggle_disables_engine(): void
    {
        $tenant = app(TenantContext::class)->withPlatformScope(fn () => Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Gamification Tenant',
            'slug' => 'gamification-'.Str::lower(Str::random(6)),
            'timezone' => 'Asia/Kabul',
            'subscription_status' => 'active',
            'settings' => ['engagement' => ['gamification' => ['enabled' => true]]],
        ]));

        app(TenantContext::class)->withTenant($tenant, function () use ($tenant): void {
            $roles = app(TenantProvisioningService::class)->provisionRbac($tenant);
            $user = User::create([
                'uuid' => (string) Str::uuid(),
                'name' => 'Gamified Salesman',
                'email' => 'gamified@example.test',
                'password' => Hash::make('password'),
                'role' => 'salesman',
                'is_active' => true,
            ]);
            $user->syncPrimaryRole($roles['salesman']);
            $salesman = Salesman::create([
                'user_id' => $user->id,
                'employee_code' => 'GAME-1',
                'first_name' => 'Gamified',
                'last_name' => 'Salesman',
                'is_active' => true,
            ]);
            $device = Device::create([
                'user_id' => $user->id,
                'salesman_id' => $salesman->id,
                'device_uuid' => 'game-device',
                'installation_uuid' => 'game-install',
                'platform' => 'android',
                'is_active' => true,
            ]);
            $customer = Customer::create([
                'code' => 'GAME-C1',
                'name' => 'Gamification Customer',
                'created_by' => $user->id,
                'is_active' => true,
            ]);
            $visit = CustomerVisit::create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $user->id,
                'salesman_id' => $salesman->id,
                'device_id' => $device->id,
                'customer_id' => $customer->id,
                'is_planned' => true,
                'status' => 'completed',
                'checked_in_at' => now()->subMinutes(20),
                'checked_out_at' => now()->subMinutes(10),
                'checkin_latitude' => 34.5,
                'checkin_longitude' => 69.2,
                'checkin_accuracy' => 8,
            ]);
            Order::create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $user->id,
                'salesman_id' => $salesman->id,
                'device_id' => $device->id,
                'customer_id' => $customer->id,
                'visit_id' => $visit->id,
                'order_number' => 'GAME-ORD-1',
                'ordered_at' => now(),
                'payment_type' => 'cash',
                'status' => 'approved',
                'currency' => 'AFN',
                'subtotal' => 100,
                'grand_total' => 100,
            ]);
            Collection::create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $user->id,
                'salesman_id' => $salesman->id,
                'device_id' => $device->id,
                'customer_id' => $customer->id,
                'receipt_number' => 'GAME-REC-1',
                'collected_at' => now(),
                'currency' => 'AFN',
                'amount' => 50,
                'payment_method' => 'cash',
                'status' => 'verified',
                'latitude' => 34.5,
                'longitude' => 69.2,
                'accuracy' => 8,
            ]);

            $service = app(GamificationService::class);
            $this->assertSame(4, $service->sync($tenant));
            $this->assertSame(0, $service->sync($tenant));
            $this->assertSame(55, GamificationPoint::sum('points'));

            $tenant->update(['settings' => ['engagement' => ['gamification' => ['enabled' => false]]]]);
            $tenant->refresh();
            $this->assertSame(0, $service->sync($tenant));
            $this->assertTrue($service->leaderboard($tenant)->isEmpty());
        });
    }
}
