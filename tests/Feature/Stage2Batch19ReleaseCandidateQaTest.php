<?php

namespace Tests\Feature;

use App\Models\Collection;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Order;
use App\Models\Permission;
use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\Product;
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

class Stage2Batch19ReleaseCandidateQaTest extends TestCase
{
    use RefreshDatabase;

    private const DEVICE_UUID = 'rc-device-1';

    private const INSTALLATION_UUID = 'rc-installation-1';

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

    public function test_release_candidate_mobile_to_admin_golden_path_is_retry_safe(): void
    {
        $actor = $this->actor();
        [$customer, $product] = $this->catalog($actor);
        $admin = $this->admin($actor['tenant']);

        $login = $this->withHeaders($this->deviceHeaders())
            ->postJson('/api/v1/auth/login', [
                'email' => $actor['user']->email,
                'password' => 'password',
                'device_uuid' => self::DEVICE_UUID,
                'app_version' => '1.0.0',
            ])
            ->assertOk()
            ->assertJsonPath('data.tenant.id', $actor['tenant']->uuid)
            ->assertJsonPath('data.device.device_uuid', self::DEVICE_UUID);

        $this->token = $login->json('data.token');

        $this->getJson('/api/v1/auth/me', $this->headers())
            ->assertOk()
            ->assertJsonPath('data.salesman.id', $actor['salesman']->uuid);

        $attendanceUuid = (string) Str::uuid();
        $attendancePayload = [
            'offline_uuid' => $attendanceUuid,
            'latitude' => 34.50000,
            'longitude' => 69.20000,
            'accuracy' => 8,
            'started_at' => '2026-09-19T04:30:00Z',
        ];

        $this->postJson('/api/v1/attendance/start', $attendancePayload, $this->headers())
            ->assertCreated()
            ->assertJsonPath('data.offline_uuid', $attendanceUuid);

        $this->postJson('/api/v1/attendance/start', $attendancePayload, $this->headers())
            ->assertOk()
            ->assertJsonPath('data.offline_uuid', $attendanceUuid);

        $gpsUuid = (string) Str::uuid();
        $this->postJson('/api/v1/gps/locations', [
            'batch_uuid' => (string) Str::uuid(),
            'locations' => [[
                'client_uuid' => $gpsUuid,
                'latitude' => 34.50001,
                'longitude' => 69.20001,
                'accuracy' => 7,
                'recorded_at' => '2026-09-19T04:35:00Z',
                'sequence_number' => 1,
            ]],
        ], $this->headers())
            ->assertCreated()
            ->assertJsonPath('data.accepted', 1);

        $visitUuid = (string) Str::uuid();
        $visitCheckIn = [
            'offline_uuid' => $visitUuid,
            'customer_id' => $customer->uuid,
            'latitude' => 34.50001,
            'longitude' => 69.20001,
            'accuracy' => 7,
            'checked_in_at' => '2026-09-19T05:00:00Z',
        ];

        $this->postJson('/api/v1/visits/check-in', $visitCheckIn, $this->headers())
            ->assertCreated()
            ->assertJsonPath('data.id', $visitUuid)
            ->assertJsonPath('data.checkin.within_geofence', true);

        $this->postJson('/api/v1/visits/check-in', $visitCheckIn, $this->headers())
            ->assertOk()
            ->assertJsonPath('data.id', $visitUuid);

        $this->postJson('/api/v1/visits/'.$visitUuid.'/check-out', [
            'latitude' => 34.50002,
            'longitude' => 69.20002,
            'accuracy' => 7,
            'checked_out_at' => '2026-09-19T05:10:00Z',
            'outcome' => 'order_placed',
            'notes' => 'RC golden path visit.',
        ], $this->headers())
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $orderUuid = (string) Str::uuid();
        $orderPayload = [
            'offline_uuid' => $orderUuid,
            'customer_id' => $customer->uuid,
            'visit_id' => $visitUuid,
            'ordered_at' => '2026-09-19T05:12:00Z',
            'payment_type' => 'credit',
            'client_estimated_total' => 999,
            'items' => [[
                'product_id' => $product->uuid,
                'quantity' => 10,
                'discount_percent' => 5,
            ]],
        ];

        $this->postJson('/api/v1/orders', $orderPayload, $this->headers())
            ->assertCreated()
            ->assertJsonPath('data.id', $orderUuid)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.grand_total', 760)
            ->assertJsonPath('data.pricing_adjusted', true);

        $this->postJson('/api/v1/orders', $orderPayload, $this->headers())
            ->assertOk()
            ->assertJsonPath('data.id', $orderUuid);

        $order = $this->inTenant(
            $actor,
            fn () => Order::where('uuid', $orderUuid)->firstOrFail(),
        );

        $this->actingAs($admin)
            ->patch('/admin/orders/'.$order->id.'/status', ['status' => 'approved'])
            ->assertRedirect(route('admin.orders.show', $order));

        $this->clearWebGuard();

        $collectionUuid = (string) Str::uuid();
        $collectionPayload = [
            'offline_uuid' => $collectionUuid,
            'customer_id' => $customer->uuid,
            'collected_at' => '2026-09-19T05:20:00Z',
            'currency' => 'AFN',
            'amount' => 200,
            'payment_method' => 'cash',
            'latitude' => 34.50002,
            'longitude' => 69.20002,
            'accuracy' => 7,
            'notes' => 'RC golden path collection.',
        ];

        $this->postJson('/api/v1/collections', $collectionPayload, $this->headers())
            ->assertCreated()
            ->assertJsonPath('data.id', $collectionUuid)
            ->assertJsonPath('data.balance_before', 760)
            ->assertJsonPath('data.status', 'pending');

        $this->postJson('/api/v1/collections', $collectionPayload, $this->headers())
            ->assertOk()
            ->assertJsonPath('data.id', $collectionUuid);

        $collection = $this->inTenant(
            $actor,
            fn () => Collection::where('uuid', $collectionUuid)->firstOrFail(),
        );

        $this->actingAs($admin)
            ->patch('/admin/collections/'.$collection->id.'/status', ['status' => 'verified'])
            ->assertRedirect(route('admin.collections.show', $collection));

        $this->clearWebGuard();

        $this->getJson(
            '/api/v1/collections/balances?customer_id='.$customer->uuid,
            $this->headers(),
        )
            ->assertOk()
            ->assertJsonPath('data.0.balances.0.verified_collections', 200)
            ->assertJsonPath('data.0.balances.0.outstanding_balance', 560);

        $expenseUuid = (string) Str::uuid();
        $expensePayload = [
            'offline_uuid' => $expenseUuid,
            'spent_at' => '2026-09-19T05:30:00Z',
            'category' => 'fuel',
            'currency' => 'AFN',
            'amount' => 100,
            'merchant' => 'RC Fuel Station',
            'reference_number' => 'RC-FUEL-001',
            'latitude' => 34.50002,
            'longitude' => 69.20002,
            'accuracy' => 7,
        ];

        $this->postJson('/api/v1/expenses', $expensePayload, $this->headers())
            ->assertCreated()
            ->assertJsonPath('data.id', $expenseUuid)
            ->assertJsonPath('data.status', 'pending');

        $this->postJson('/api/v1/expenses', $expensePayload, $this->headers())
            ->assertOk()
            ->assertJsonPath('data.id', $expenseUuid);

        $expense = $this->inTenant(
            $actor,
            fn () => Expense::where('uuid', $expenseUuid)->firstOrFail(),
        );

        $this->actingAs($admin)
            ->patch('/admin/expenses/'.$expense->id.'/status', ['status' => 'approved'])
            ->assertRedirect(route('admin.expenses.show', $expense));

        $this->clearWebGuard();

        $endPayload = [
            'latitude' => 34.50003,
            'longitude' => 69.20003,
            'accuracy' => 7,
            'ended_at' => '2026-09-19T06:00:00Z',
        ];

        $this->postJson('/api/v1/attendance/end', $endPayload, $this->headers())
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $this->postJson('/api/v1/attendance/end', $endPayload, $this->headers())
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $this->assertDatabaseCount('work_sessions', 1);
        $this->assertDatabaseCount('customer_visits', 1);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('collections', 1);
        $this->assertDatabaseCount('expenses', 1);

        $this->assertDatabaseHas('orders', [
            'uuid' => $orderUuid,
            'status' => 'approved',
            'grand_total' => 760,
        ]);
        $this->assertDatabaseHas('collections', [
            'uuid' => $collectionUuid,
            'status' => 'verified',
            'amount' => 200,
        ]);
        $this->assertDatabaseHas('expenses', [
            'uuid' => $expenseUuid,
            'status' => 'approved',
            'amount' => 100,
        ]);
    }

    public function test_release_ci_uses_parent_commit_when_push_base_is_unavailable(): void
    {
        $workflow = file_get_contents(base_path('.github/workflows/ci.yml'));

        $this->assertNotFalse($workflow);
        $this->assertStringContainsString(
            'git rev-parse "$GITHUB_SHA^"',
            $workflow,
        );
        $this->assertStringContainsString(
            'git diff --name-only --diff-filter=ACMR "$BASE_SHA" "$GITHUB_SHA"',
            $workflow,
        );
    }

    public function test_release_candidate_document_keeps_manual_uat_explicit(): void
    {
        $document = file_get_contents(base_path('docs/RELEASE_CANDIDATE_QA.md'));

        $this->assertNotFalse($document);
        $this->assertStringContainsString('Not Executed', $document);
        $this->assertStringContainsString(
            'In Progress — Automated QA / Manual UAT Pending',
            $document,
        );
        $this->assertStringContainsString(
            'Approved mobile branding is missing',
            $document,
        );
    }

    private function actor(): array
    {
        $tenant = app(TenantContext::class)->withPlatformScope(fn () => Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'RC Tenant',
            'slug' => 'rc-tenant',
            'timezone' => 'Asia/Kabul',
            'subscription_status' => 'active',
        ]));

        [$user, $salesman] = app(TenantContext::class)->withTenant(
            $tenant,
            function () use ($tenant): array {
                $user = User::create([
                    'uuid' => (string) Str::uuid(),
                    'tenant_id' => $tenant->id,
                    'name' => 'RC Salesman',
                    'email' => 'rc-salesman@example.test',
                    'password' => Hash::make('password'),
                    'role' => 'salesman',
                    'is_active' => true,
                ]);

                $role = Role::create([
                    'tenant_id' => $tenant->id,
                    'name' => 'RC Salesman',
                    'slug' => 'rc-salesman',
                    'is_system' => false,
                ]);

                $permissions = collect([
                    'customers:view',
                    'catalog:view',
                    'orders:view',
                    'collections:view',
                    'expenses:view',
                    'targets:view',
                ])->map(fn (string $slug) => Permission::firstOrCreate(
                    ['slug' => $slug],
                    [
                        'name' => str($slug)->replace(':', ' ')->title(),
                        'group' => str($slug)->before(':'),
                    ],
                )->id)->all();

                $role->permissions()->sync($permissions);
                $user->syncPrimaryRole($role);

                $salesman = Salesman::create([
                    'tenant_id' => $tenant->id,
                    'user_id' => $user->id,
                    'employee_code' => 'RC-SAL-001',
                    'first_name' => 'Release',
                    'last_name' => 'Candidate',
                    'is_active' => true,
                ]);

                return [$user, $salesman];
            },
        );

        return compact('tenant', 'user', 'salesman');
    }

    private function admin(Tenant $tenant): User
    {
        return app(TenantContext::class)->withTenant($tenant, function () use ($tenant): User {
            $admin = User::create([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'name' => 'RC Admin',
                'email' => 'rc-admin@example.test',
                'password' => Hash::make('password'),
                'role' => 'company_admin',
                'is_active' => true,
            ]);

            $role = Role::create([
                'tenant_id' => $tenant->id,
                'name' => 'RC Admin',
                'slug' => 'rc-admin',
                'is_system' => false,
            ]);

            $permissions = collect([
                'orders:view',
                'orders:manage',
                'collections:view',
                'collections:manage',
                'expenses:view',
                'expenses:manage',
            ])->map(fn (string $slug) => Permission::firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => str($slug)->replace(':', ' ')->title(),
                    'group' => str($slug)->before(':'),
                ],
            )->id)->all();

            $role->permissions()->sync($permissions);
            $admin->syncPrimaryRole($role);

            return $admin;
        });
    }

    private function catalog(array $actor): array
    {
        return $this->inTenant($actor, function () use ($actor): array {
            $product = Product::create([
                'sku' => 'RC-PROD-001',
                'name' => 'RC Product',
                'unit' => 'pcs',
                'base_price' => 100,
                'currency' => 'AFN',
                'is_active' => true,
            ]);

            $priceList = PriceList::create([
                'code' => 'RC-RETAIL',
                'name' => 'RC Retail',
                'currency' => 'AFN',
                'effective_from' => '2026-09-01',
                'is_active' => true,
            ]);

            PriceListItem::create([
                'price_list_id' => $priceList->id,
                'product_id' => $product->id,
                'min_quantity' => 1,
                'price' => 90,
            ]);

            PriceListItem::create([
                'price_list_id' => $priceList->id,
                'product_id' => $product->id,
                'min_quantity' => 10,
                'price' => 80,
            ]);

            $customer = Customer::create([
                'code' => 'RC-CUS-001',
                'name' => 'RC Customer',
                'price_list_id' => $priceList->id,
                'latitude' => 34.5,
                'longitude' => 69.2,
                'geofence_radius_meters' => 100,
                'created_by' => $actor['user']->id,
                'is_active' => true,
            ]);

            return [$customer, $product];
        });
    }

    private function deviceHeaders(): array
    {
        return [
            'X-Device-UUID' => self::DEVICE_UUID,
            'X-Installation-UUID' => self::INSTALLATION_UUID,
            'X-App-Version' => '1.0.0',
            'X-Platform' => 'android',
            'X-OS-Version' => '16',
        ];
    }

    private function headers(): array
    {
        return [
            ...$this->deviceHeaders(),
            'Authorization' => 'Bearer '.$this->token,
        ];
    }

    private function clearWebGuard(): void
    {
        auth('web')->logout();
        app('auth')->forgetGuards();
    }

    private function inTenant(array $actor, callable $callback): mixed
    {
        return app(TenantContext::class)->withTenant(
            $actor['tenant'],
            fn () => $callback(),
        );
    }
}
