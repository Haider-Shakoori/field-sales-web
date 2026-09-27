<?php

namespace Tests\Feature;

use App\Models\OperationalNotification;
use App\Models\Salesman;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantProvisioningService;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class WebSalesmanNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_send_operational_notification_to_salesman_from_web(): void
    {
        $tenant = app(TenantContext::class)->withPlatformScope(fn () => Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Notification Tenant',
            'slug' => 'notify-'.Str::lower(Str::random(6)),
            'timezone' => 'Asia/Kabul',
            'subscription_status' => 'active',
        ]));

        app(TenantContext::class)->withTenant($tenant, function () use ($tenant): void {
            $roles = app(TenantProvisioningService::class)->provisionRbac($tenant);

            $owner = User::create([
                'uuid' => (string) Str::uuid(),
                'name' => 'Owner',
                'email' => 'owner-notify@example.test',
                'password' => Hash::make('password'),
                'role' => 'owner',
                'is_active' => true,
            ]);
            $owner->syncPrimaryRole($roles['owner']);

            $salesUser = User::create([
                'uuid' => (string) Str::uuid(),
                'name' => 'Field Salesman',
                'email' => 'salesman-notify@example.test',
                'password' => Hash::make('password'),
                'role' => 'salesman',
                'is_active' => true,
            ]);
            $salesUser->syncPrimaryRole($roles['salesman']);

            $salesman = Salesman::create([
                'user_id' => $salesUser->id,
                'employee_code' => 'NOTIFY-001',
                'first_name' => 'Field',
                'last_name' => 'Salesman',
                'is_active' => true,
            ]);

            $this->from(route('admin.salesmen.show', $salesman))
                ->actingAs($owner)
                ->post(route('admin.salesmen.notify', $salesman), [
                    'message' => 'Please continue your assigned route.',
                ])
                ->assertRedirect(route('admin.salesmen.show', $salesman))
                ->assertSessionHas('status', 'Notification sent to Field Salesman.');

            $notification = app(TenantContext::class)->withTenant(
                $tenant,
                fn () => OperationalNotification::query()
                    ->where('user_id', $salesUser->id)
                    ->where('type', 'team.supervisor_nudge')
                    ->firstOrFail(),
            );

            $this->assertSame('team_messages', $notification->category);
            $this->assertSame('high', $notification->priority);
            $this->assertSame('Please continue your assigned route.', $notification->message);
            $this->assertSame('Owner', $notification->data['sender_name']);
        });
    }
}
