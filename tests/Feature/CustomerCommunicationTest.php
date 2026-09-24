<?php

namespace Tests\Feature;

use App\Jobs\SendCustomerCommunication;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerCommunicationDelivery;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\Territory;
use App\Models\User;
use App\Services\CustomerCommunicationService;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerCommunicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_queue_audited_customer_message(): void
    {
        [$tenant, $admin, $customer] = $this->fixture();

        config()->set('communications.whatsapp.enabled', false);

        $this->actingAs($admin)
            ->post(route('admin.customers.communications.store', $customer), [
                'channel' => 'whatsapp',
                'kind' => 'payment_reminder',
                'message' => 'Your payment is due. Please contact us if you need help.',
            ])
            ->assertRedirect();

        $delivery = app(TenantContext::class)->withTenant(
            $tenant,
            fn () => CustomerCommunicationDelivery::firstOrFail(),
        );

        $this->assertSame('whatsapp', $delivery->channel);
        $this->assertSame('payment_reminder', $delivery->kind);
        $this->assertSame('+93700000001', $delivery->recipient_phone);
        $this->assertSame('skipped', $delivery->status);

        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $tenant->id,
            'event' => 'customer.communication_queued',
            'subject_type' => CustomerCommunicationDelivery::class,
            'subject_id' => $delivery->id,
        ]);
    }

    public function test_enabled_channel_queues_and_delivers_through_generic_provider(): void
    {
        [$tenant, $admin, $customer] = $this->fixture();

        Queue::fake();
        config()->set('communications.sms.enabled', true);
        config()->set('communications.sms.endpoint', 'https://sms.example.test/messages');
        config()->set('communications.sms.bearer_token', 'test-token');
        config()->set('communications.sms.provider', 'generic_http');

        $delivery = app(TenantContext::class)->withTenant(
            $tenant,
            fn () => app(CustomerCommunicationService::class)->queue(
                $customer,
                $admin,
                'sms',
                'Order ORD-100 has been approved.',
                'order_update',
            ),
        );

        $this->assertSame('pending', $delivery->status);
        Queue::assertPushed(
            SendCustomerCommunication::class,
            fn (SendCustomerCommunication $job) => $job->tenantId === $tenant->id
                && $job->deliveryId === $delivery->id,
        );

        Http::fake([
            'https://sms.example.test/messages' => Http::response([
                'message_id' => 'sms-provider-123',
            ], 200),
        ]);

        $sent = app(TenantContext::class)->withTenant(
            $tenant,
            fn () => app(CustomerCommunicationService::class)->deliver(
                $delivery->fresh(),
            ),
        );

        $this->assertSame('sent', $sent->status);
        $this->assertSame('sms-provider-123', $sent->provider_message_id);
        $this->assertSame(1, $sent->attempts);
        $this->assertNotNull($sent->sent_at);

        Http::assertSent(fn ($request) => $request->url() === 'https://sms.example.test/messages'
            && $request['to'] === '+93700000001'
            && $request['channel'] === 'sms'
            && $request['customer_id'] === $customer->uuid);
    }

    private function fixture(): array
    {
        $context = app(TenantContext::class);

        $tenant = $context->withPlatformScope(fn () => Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Communication Tenant',
            'slug' => 'communications-'.Str::lower(Str::random(6)),
            'timezone' => 'Asia/Kabul',
            'subscription_status' => 'active',
        ]));

        return $context->withTenant($tenant, function () use ($tenant): array {
            $permission = Permission::firstOrCreate(
                ['slug' => 'customers:manage'],
                [
                    'name' => 'Customers Manage',
                    'group' => 'customers',
                ],
            );

            $role = Role::create([
                'tenant_id' => $tenant->id,
                'name' => 'Communication Manager',
                'slug' => 'communication-manager',
                'is_system' => false,
            ]);
            $role->permissions()->sync([$permission->id]);

            $admin = User::create([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'name' => 'Communication Manager',
                'email' => 'communications@example.test',
                'password' => Hash::make('password'),
                'role' => 'communication-manager',
                'is_active' => true,
            ]);
            $admin->syncPrimaryRole($role);

            $branch = Branch::create([
                'name' => 'Kabul Main',
                'code' => 'KBL-COMM',
                'is_active' => true,
            ]);

            $territory = Territory::create([
                'branch_id' => $branch->id,
                'code' => 'KBL-COMM-T',
                'name' => 'Kabul Communication Territory',
                'is_active' => true,
            ]);

            $customer = Customer::create([
                'branch_id' => $branch->id,
                'territory_id' => $territory->id,
                'code' => 'COMM-001',
                'name' => 'Communication Customer',
                'phone' => '+93 700 000 001',
                'credit_currency' => 'AFN',
                'credit_terms_days' => 30,
                'created_by' => $admin->id,
                'is_active' => true,
            ]);

            return [$tenant, $admin, $customer];
        });
    }
}
