<?php

namespace Tests\Feature;

use App\Models\Collection;
use App\Models\Customer;
use App\Models\Device;
use App\Models\Order;
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

class Batch10CollectionsTest extends TestCase
{
    use RefreshDatabase;

    private ?string $token = null;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-09-19T08:00:00Z');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_offline_collection_is_idempotent_and_pending_does_not_reduce_balance(): void
    {
        $actor = $this->salesmanActor();
        $customer = $this->customerWithReceivable($actor, 1000);
        $uuid = (string) Str::uuid();

        $payload = [
            'offline_uuid' => $uuid,
            'customer_id' => $customer->uuid,
            'collected_at' => '2026-09-19T07:00:00Z',
            'currency' => 'AFN',
            'amount' => 300,
            'payment_method' => 'cash',
            'latitude' => 34.5553,
            'longitude' => 69.2075,
            'accuracy' => 8,
            'notes' => 'Collected at customer shop.',
        ];

        $this->postJson('/api/v1/collections', $payload, $this->headers())
            ->assertCreated()
            ->assertJsonPath('data.id', $uuid)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.amount', 300)
            ->assertJsonPath('data.balance_before', 1000)
            ->assertJsonPath('data.overpayment_flag', false);

        $this->postJson('/api/v1/collections', $payload, $this->headers())
            ->assertOk()
            ->assertJsonPath('data.id', $uuid);

        $this->assertDatabaseCount('collections', 1);

        $this->getJson(
            '/api/v1/collections/balances?customer_id='.$customer->uuid,
            $this->headers()
        )
            ->assertOk()
            ->assertJsonPath('data.0.balances.0.receivable_total', 1000)
            ->assertJsonPath('data.0.balances.0.pending_collections', 300)
            ->assertJsonPath('data.0.balances.0.outstanding_balance', 1000);
    }

    public function test_verified_collection_reduces_authoritative_balance_and_is_audited(): void
    {
        $actor = $this->salesmanActor();
        $customer = $this->customerWithReceivable($actor, 1000);

        $this->postJson('/api/v1/collections', [
            'offline_uuid' => (string) Str::uuid(),
            'customer_id' => $customer->uuid,
            'collected_at' => '2026-09-19T07:10:00Z',
            'currency' => 'AFN',
            'amount' => 300,
            'payment_method' => 'bank_transfer',
            'reference_number' => 'BANK-REF-001',
            'latitude' => 34.5553,
            'longitude' => 69.2075,
            'accuracy' => 7,
        ], $this->headers())->assertCreated();

        $collection = app(TenantContext::class)->withTenant(
            $actor['tenant'],
            fn () => Collection::firstOrFail()
        );
        $admin = $this->admin($actor['tenant']);

        $this->actingAs($admin)
            ->patch('/admin/collections/'.$collection->id.'/status', [
                'status' => 'verified',
            ])
            ->assertRedirect(route('admin.collections.show', $collection));

        $this->assertDatabaseHas('collections', [
            'id' => $collection->id,
            'status' => 'verified',
            'status_changed_by' => $admin->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'collection.status_changed',
            'subject_id' => $collection->id,
        ]);

        $this->getJson(
            '/api/v1/collections/balances?customer_id='.$customer->uuid,
            $this->headers()
        )
            ->assertOk()
            ->assertJsonPath('data.0.balances.0.verified_collections', 300)
            ->assertJsonPath('data.0.balances.0.outstanding_balance', 700);
    }

    public function test_overpayment_can_sync_for_review_but_cannot_be_verified(): void
    {
        $actor = $this->salesmanActor();
        $customer = $this->customerWithReceivable($actor, 200);

        $this->postJson('/api/v1/collections', [
            'offline_uuid' => (string) Str::uuid(),
            'customer_id' => $customer->uuid,
            'collected_at' => '2026-09-19T07:20:00Z',
            'currency' => 'AFN',
            'amount' => 250,
            'payment_method' => 'cash',
            'latitude' => 34.5553,
            'longitude' => 69.2075,
            'accuracy' => 6,
        ], $this->headers())
            ->assertCreated()
            ->assertJsonPath('data.overpayment_flag', true);

        $collection = app(TenantContext::class)->withTenant(
            $actor['tenant'],
            fn () => Collection::firstOrFail()
        );

        $this->actingAs($this->admin($actor['tenant']))
            ->patch('/admin/collections/'.$collection->id.'/status', [
                'status' => 'verified',
            ])
            ->assertSessionHasErrors('status');

        $this->assertDatabaseHas('collections', [
            'id' => $collection->id,
            'status' => 'pending',
        ]);
    }

    public function test_non_cash_collection_requires_reference_number(): void
    {
        $actor = $this->salesmanActor();
        $customer = $this->customerWithReceivable($actor, 500);

        $response = $this->postJson('/api/v1/collections', [
            'offline_uuid' => (string) Str::uuid(),
            'customer_id' => $customer->uuid,
            'collected_at' => '2026-09-19T07:30:00Z',
            'currency' => 'AFN',
            'amount' => 100,
            'payment_method' => 'bank_transfer',
            'latitude' => 34.5553,
            'longitude' => 69.2075,
            'accuracy' => 6,
        ], $this->headers());

        $response
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');

        $this->assertArrayHasKey(
            'reference_number',
            $response->json('error.details'),
        );
    }

    private function salesmanActor(): array
    {
        $tenant = app(TenantContext::class)->withPlatformScope(fn () => Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Collection Tenant',
            'slug' => 'collection-tenant',
            'timezone' => 'Asia/Kabul',
        ]));

        [$user, $salesman, $device] = app(TenantContext::class)->withTenant(
            $tenant,
            function () use ($tenant): array {
                $user = User::create([
                    'uuid' => (string) Str::uuid(),
                    'tenant_id' => $tenant->id,
                    'name' => 'Collection Salesman',
                    'email' => 'collection-salesman@example.test',
                    'password' => Hash::make('password'),
                    'role' => 'salesman',
                    'is_active' => true,
                ]);

                $role = Role::create([
                    'tenant_id' => $tenant->id,
                    'name' => 'Collection Salesman',
                    'slug' => 'collection-salesman',
                    'is_system' => false,
                ]);

                $permission = Permission::firstOrCreate(
                    ['slug' => 'collections:view'],
                    ['name' => 'Collections View', 'group' => 'collections']
                );
                $role->permissions()->sync([$permission->id]);
                $user->syncPrimaryRole($role);

                $salesman = Salesman::create([
                    'tenant_id' => $tenant->id,
                    'user_id' => $user->id,
                    'employee_code' => 'COL-SAL-1',
                    'first_name' => 'Collection',
                    'last_name' => 'Salesman',
                    'is_active' => true,
                ]);

                $device = Device::create([
                    'tenant_id' => $tenant->id,
                    'user_id' => $user->id,
                    'salesman_id' => $salesman->id,
                    'device_uuid' => 'collection-device',
                    'installation_uuid' => 'collection-install',
                    'is_active' => true,
                ]);

                return [$user, $salesman, $device];
            }
        );

        $this->token = app(TenantContext::class)->withTenant(
            $tenant,
            fn () => $user->createToken('mobile-'.$device->uuid)->plainTextToken
        );

        return compact('tenant', 'user', 'salesman', 'device');
    }

    private function customerWithReceivable(array $actor, float $amount): Customer
    {
        return app(TenantContext::class)->withTenant(
            $actor['tenant'],
            function () use ($actor, $amount): Customer {
                $customer = Customer::create([
                    'code' => 'COL-CUS-'.Str::random(5),
                    'name' => 'Collection Customer',
                    'created_by' => $actor['user']->id,
                    'is_active' => true,
                ]);

                Order::create([
                    'user_id' => $actor['user']->id,
                    'salesman_id' => $actor['salesman']->id,
                    'device_id' => $actor['device']->id,
                    'customer_id' => $customer->id,
                    'order_number' => 'ORD-COL-'.Str::random(8),
                    'ordered_at' => '2026-09-19 06:00:00',
                    'payment_type' => 'credit',
                    'status' => 'approved',
                    'currency' => 'AFN',
                    'subtotal' => $amount,
                    'discount_total' => 0,
                    'grand_total' => $amount,
                    'pricing_adjusted' => false,
                ]);

                return $customer;
            }
        );
    }

    private function admin(Tenant $tenant): User
    {
        return app(TenantContext::class)->withTenant($tenant, function () use ($tenant): User {
            $admin = User::create([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'name' => 'Collection Admin',
                'email' => 'collection-admin@example.test',
                'password' => Hash::make('password'),
                'role' => 'company_admin',
                'is_active' => true,
            ]);

            $role = Role::create([
                'tenant_id' => $tenant->id,
                'name' => 'Collection Admin',
                'slug' => 'collection-admin',
                'is_system' => false,
            ]);

            $permissionIds = collect(['collections:view', 'collections:manage'])
                ->map(fn (string $slug) => Permission::firstOrCreate(
                    ['slug' => $slug],
                    [
                        'name' => str($slug)->replace(':', ' ')->title(),
                        'group' => 'collections',
                    ]
                )->id)
                ->all();

            $role->permissions()->sync($permissionIds);
            $admin->syncPrimaryRole($role);

            return $admin;
        });
    }

    private function headers(): array
    {
        return [
            'Authorization' => 'Bearer '.$this->token,
            'X-Device-UUID' => 'collection-device',
            'X-Installation-UUID' => 'collection-install',
            'X-App-Version' => '1.0',
            'X-Platform' => 'android',
            'X-OS-Version' => '16',
        ];
    }
}
