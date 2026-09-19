<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Device;
use App\Models\Permission;
use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Models\Role;
use App\Models\RouteCustomer;
use App\Models\Salesman;
use App\Models\SalesmanAssignment;
use App\Models\SalesRoute;
use App\Models\Tenant;
use App\Models\Territory;
use App\Models\User;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class Batch5CatalogMobileApiTest extends TestCase
{
    use RefreshDatabase;

    private TenantContext $context;

    protected function setUp(): void
    {
        parent::setUp();

        $this->context = app(TenantContext::class);
        $this->context->clear();
    }

    protected function tearDown(): void
    {
        $this->context->clear();

        parent::tearDown();
    }

    public function test_product_crud_is_tenant_scoped_and_audited(): void
    {
        $tenant = $this->tenant('catalog-crud');

        $admin = $this->platform(fn () => $this->userWithRole(
            $tenant,
            'admin@catalog.local',
            ['catalog:view', 'catalog:manage'],
            'company_admin',
        ));

        $this->actingAs($admin)
            ->post(route('admin.products.store'), [
                'sku' => 'p-001',
                'barcode' => '123456',
                'name' => 'Water Bottle',
                'description' => '500ml',
                'unit' => 'pcs',
                'base_price' => '25.5000',
                'currency' => 'afn',
                'is_active' => '1',
            ])
            ->assertRedirect();

        $product = $this->tenantScope(
            $tenant,
            fn () => Product::where('sku', 'P-001')->firstOrFail()
        );

        $this->assertSame('AFN', $product->currency);

        $this->actingAs($admin)
            ->get(route('admin.products.show', $product))
            ->assertOk()
            ->assertSee('Water Bottle');

        $this->actingAs($admin)
            ->put(route('admin.products.update', $product), [
                'sku' => 'P-001',
                'barcode' => '123456',
                'name' => 'Water Bottle Updated',
                'description' => '500ml',
                'unit' => 'pcs',
                'base_price' => '30.0000',
                'currency' => 'AFN',
                'is_active' => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $tenant->id,
            'event' => 'product.created',
            'subject_id' => $product->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $tenant->id,
            'event' => 'product.updated',
            'subject_id' => $product->id,
        ]);
    }

    public function test_cross_tenant_product_and_price_list_bindings_are_hidden(): void
    {
        $tenantA = $this->tenant('catalog-a');
        $tenantB = $this->tenant('catalog-b');

        $admin = $this->platform(fn () => $this->userWithRole(
            $tenantA,
            'admin@catalog-a.local',
            ['catalog:view', 'catalog:manage'],
            'company_admin',
        ));

        [$product, $priceList] = $this->platform(function () use ($tenantB): array {
            return [
                Product::create([
                    'tenant_id' => $tenantB->id,
                    'sku' => 'FOREIGN-P',
                    'name' => 'Foreign Product',
                    'unit' => 'pcs',
                    'base_price' => 1,
                    'currency' => 'AFN',
                    'is_active' => true,
                ]),
                PriceList::create([
                    'tenant_id' => $tenantB->id,
                    'code' => 'FOREIGN-PL',
                    'name' => 'Foreign Price List',
                    'currency' => 'AFN',
                    'is_active' => true,
                ]),
            ];
        });

        $this->actingAs($admin)
            ->get(route('admin.products.show', $product))
            ->assertNotFound();

        $this->actingAs($admin)
            ->get(route('admin.price-lists.show', $priceList))
            ->assertNotFound();
    }

    public function test_price_list_quantity_tiers_and_customer_assignment_work(): void
    {
        $tenant = $this->tenant('pricing');

        [$admin, $branch, $territory] = $this->platform(function () use ($tenant): array {
            $admin = $this->userWithRole(
                $tenant,
                'admin@pricing.local',
                ['catalog:view', 'catalog:manage', 'customers:view', 'customers:manage'],
                'company_admin',
            );

            $branch = Branch::create([
                'tenant_id' => $tenant->id,
                'code' => 'KBL',
                'name' => 'Kabul',
                'is_active' => true,
            ]);

            $territory = Territory::create([
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
                'code' => 'KBL-C',
                'name' => 'Central',
                'is_active' => true,
            ]);

            return [$admin, $branch, $territory];
        });

        $product = $this->tenantScope($tenant, fn () => Product::create([
            'sku' => 'SKU-1',
            'name' => 'Product One',
            'unit' => 'box',
            'base_price' => 100,
            'currency' => 'AFN',
            'is_active' => true,
        ]));

        $this->actingAs($admin)
            ->post(route('admin.price-lists.store'), [
                'code' => 'retail',
                'name' => 'Retail',
                'currency' => 'afn',
                'effective_from' => '2026-01-01',
                'effective_to' => '2026-12-31',
                'is_active' => '1',
            ])
            ->assertRedirect();

        $priceList = $this->tenantScope(
            $tenant,
            fn () => PriceList::where('code', 'RETAIL')->firstOrFail()
        );

        foreach ([
            ['min_quantity' => '1', 'price' => '95'],
            ['min_quantity' => '10', 'price' => '90'],
        ] as $tier) {
            $this->actingAs($admin)
                ->post(route('admin.price-lists.items.store', $priceList), [
                    'product_id' => $product->id,
                    ...$tier,
                ])
                ->assertRedirect();
        }

        $this->assertSame(
            ['1.0000', '10.0000'],
            $this->tenantScope(
                $tenant,
                fn () => PriceListItem::where('price_list_id', $priceList->id)
                    ->orderBy('min_quantity')
                    ->pluck('min_quantity')
                    ->all()
            )
        );

        $this->actingAs($admin)
            ->post(route('admin.customers.store'), [
                'branch_id' => $branch->id,
                'territory_id' => $territory->id,
                'price_list_id' => $priceList->id,
                'code' => 'CUS-1',
                'name' => 'Retail Shop',
                'geofence_radius_meters' => 100,
                'is_active' => '1',
            ])
            ->assertRedirect();

        $customer = $this->tenantScope(
            $tenant,
            fn () => Customer::where('code', 'CUS-1')->firstOrFail()
        );

        $this->assertSame($priceList->id, $customer->price_list_id);
        $this->assertDatabaseHas('audit_logs', ['event' => 'price_list.created']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'price_list_item.created']);
    }

    public function test_price_list_effective_scope_respects_date_window(): void
    {
        CarbonImmutable::setTestNow('2026-09-19T00:00:00Z');

        try {
            $tenant = $this->tenant('effective');

            $this->platform(function () use ($tenant): void {
                PriceList::create([
                    'tenant_id' => $tenant->id,
                    'code' => 'CURRENT',
                    'name' => 'Current',
                    'currency' => 'AFN',
                    'effective_from' => '2026-01-01',
                    'effective_to' => '2026-12-31',
                    'is_active' => true,
                ]);

                PriceList::create([
                    'tenant_id' => $tenant->id,
                    'code' => 'OLD',
                    'name' => 'Old',
                    'currency' => 'AFN',
                    'effective_to' => '2025-12-31',
                    'is_active' => true,
                ]);
            });

            $codes = $this->tenantScope(
                $tenant,
                fn () => PriceList::active()->effectiveOn('2026-09-19')->pluck('code')->all()
            );

            $this->assertSame(['CURRENT'], $codes);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_mobile_master_data_uses_uuid_ids_and_pagination_metadata(): void
    {
        $tenant = $this->tenant('mobile-master');
        $actor = $this->mobileActor($tenant);

        [$product, $priceList, $customer, $route] = $this->tenantScope($tenant, function () use ($actor): array {
            $product = Product::create([
                'sku' => 'MOB-1',
                'name' => 'Mobile Product',
                'unit' => 'pcs',
                'base_price' => 12.5,
                'currency' => 'AFN',
                'is_active' => true,
            ]);

            $priceList = PriceList::create([
                'code' => 'MOBILE',
                'name' => 'Mobile Price List',
                'currency' => 'AFN',
                'is_active' => true,
            ]);

            PriceListItem::create([
                'price_list_id' => $priceList->id,
                'product_id' => $product->id,
                'min_quantity' => 1,
                'price' => 10,
            ]);

            $customer = Customer::create([
                'branch_id' => $actor['branch']->id,
                'territory_id' => $actor['territory']->id,
                'price_list_id' => $priceList->id,
                'code' => 'MOB-C',
                'name' => 'Mobile Customer',
                'geofence_radius_meters' => 100,
                'is_active' => true,
            ]);

            RouteCustomer::create([
                'route_id' => $actor['route']->id,
                'customer_id' => $customer->id,
                'sequence_number' => 1,
                'planned_visit_minutes' => 10,
            ]);

            return [$product, $priceList, $customer, $actor['route']];
        });

        $this->getJson('/api/v1/products?per_page=1', $actor['headers'])
            ->assertOk()
            ->assertJsonPath('data.0.id', $product->uuid)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.total', 1);

        $this->getJson('/api/v1/price-lists', $actor['headers'])
            ->assertOk()
            ->assertJsonPath('data.0.id', $priceList->uuid);

        $this->getJson('/api/v1/price-lists/'.$priceList->uuid.'/items', $actor['headers'])
            ->assertOk()
            ->assertJsonPath('data.0.product_id', $product->uuid);

        $this->getJson('/api/v1/customers', $actor['headers'])
            ->assertOk()
            ->assertJsonPath('data.0.id', $customer->uuid)
            ->assertJsonPath('data.0.price_list_id', $priceList->uuid);

        $membership = $this->tenantScope(
            $tenant,
            fn () => RouteCustomer::where('route_id', $route->id)
                ->where('customer_id', $customer->id)
                ->firstOrFail()
        );

        $this->getJson('/api/v1/routes/'.$route->uuid.'/customers', $actor['headers'])
            ->assertOk()
            ->assertJsonPath('data.0.id', $membership->uuid)
            ->assertJsonPath('data.0.route_id', $route->uuid)
            ->assertJsonPath('data.0.customer_id', $customer->uuid);
    }

    public function test_mobile_customer_creation_is_idempotent_and_maps_offline_uuid_to_uuid(): void
    {
        $tenant = $this->tenant('mobile-create');
        $actor = $this->mobileActor($tenant);
        $offlineUuid = (string) Str::uuid();

        $payload = [
            'offline_uuid' => $offlineUuid,
            'name' => 'Offline Created Shop',
            'phone' => '0700123456',
            'latitude' => 34.5,
            'longitude' => 69.1,
            'geofence_radius_meters' => 80,
        ];

        $this->postJson('/api/v1/customers', $payload, $actor['headers'])
            ->assertCreated()
            ->assertJsonPath('data.id', $offlineUuid)
            ->assertJsonPath('data.offline_uuid', $offlineUuid)
            ->assertJsonPath('data.territory_id', $actor['territory']->uuid);

        $this->postJson('/api/v1/customers', $payload, $actor['headers'])
            ->assertOk()
            ->assertJsonPath('data.id', $offlineUuid);

        $this->assertDatabaseCount('customers', 1);
        $this->assertDatabaseHas('customers', [
            'tenant_id' => $tenant->id,
            'uuid' => $offlineUuid,
            'offline_uuid' => $offlineUuid,
            'created_by' => $actor['user']->id,
            'territory_id' => $actor['territory']->id,
        ]);
    }

    public function test_salesman_master_data_is_scoped_to_current_route_but_keeps_offline_created_customer_visible(): void
    {
        $tenant = $this->tenant('mobile-scope');
        $actor = $this->mobileActor($tenant);

        [$planned, $outside] = $this->tenantScope($tenant, function () use ($actor): array {
            $planned = Customer::create([
                'branch_id' => $actor['branch']->id,
                'territory_id' => $actor['territory']->id,
                'code' => 'PLANNED',
                'name' => 'Planned',
                'geofence_radius_meters' => 100,
                'is_active' => true,
            ]);

            RouteCustomer::create([
                'route_id' => $actor['route']->id,
                'customer_id' => $planned->id,
                'sequence_number' => 1,
                'planned_visit_minutes' => 10,
            ]);

            $outsideTerritory = Territory::create([
                'branch_id' => $actor['branch']->id,
                'code' => 'OUT',
                'name' => 'Outside',
                'is_active' => true,
            ]);

            $outside = Customer::create([
                'branch_id' => $actor['branch']->id,
                'territory_id' => $outsideTerritory->id,
                'code' => 'OUTSIDE',
                'name' => 'Outside',
                'geofence_radius_meters' => 100,
                'is_active' => true,
            ]);

            return [$planned, $outside];
        });

        $offlineUuid = (string) Str::uuid();

        $this->postJson('/api/v1/customers', [
            'offline_uuid' => $offlineUuid,
            'name' => 'New Offline',
        ], $actor['headers'])->assertCreated();

        $response = $this->getJson('/api/v1/customers?per_page=100', $actor['headers'])
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($planned->uuid, $ids);
        $this->assertContains($offlineUuid, $ids);
        $this->assertNotContains($outside->uuid, $ids);
    }

    public function test_master_data_requires_registered_device_headers(): void
    {
        $tenant = $this->tenant('device-required');
        $actor = $this->mobileActor($tenant);

        $this->getJson('/api/v1/products', [
            'Authorization' => 'Bearer '.$actor['token'],
        ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'DEVICE_HEADERS_REQUIRED');
    }

    public function test_updated_since_filters_master_data_changes(): void
    {
        CarbonImmutable::setTestNow('2026-09-19T00:00:00Z');

        try {
            $tenant = $this->tenant('delta');
            $actor = $this->mobileActor($tenant);

            $old = $this->tenantScope($tenant, fn () => Product::create([
                'sku' => 'OLD',
                'name' => 'Old',
                'unit' => 'pcs',
                'base_price' => 1,
                'currency' => 'AFN',
                'is_active' => true,
                'created_at' => '2026-09-18 00:00:00',
                'updated_at' => '2026-09-18 00:00:00',
            ]));

            $new = $this->tenantScope($tenant, fn () => Product::create([
                'sku' => 'NEW',
                'name' => 'New',
                'unit' => 'pcs',
                'base_price' => 2,
                'currency' => 'AFN',
                'is_active' => true,
            ]));

            $response = $this->getJson(
                '/api/v1/products?updated_since=2026-09-18T12:00:00Z',
                $actor['headers']
            )->assertOk();

            $ids = collect($response->json('data'))->pluck('id')->all();

            $this->assertContains($new->uuid, $ids);
            $this->assertNotContains($old->uuid, $ids);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    private function mobileActor(Tenant $tenant): array
    {
        return $this->platform(function () use ($tenant): array {
            $branch = Branch::create([
                'tenant_id' => $tenant->id,
                'code' => 'MOB',
                'name' => 'Mobile Branch',
                'is_active' => true,
            ]);

            $territory = Territory::create([
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
                'code' => 'MOB-T',
                'name' => 'Mobile Territory',
                'is_active' => true,
            ]);

            $route = SalesRoute::create([
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
                'territory_id' => $territory->id,
                'code' => 'MOB-R',
                'name' => 'Mobile Route',
                'weekdays' => ['sat'],
                'is_active' => true,
            ]);

            $role = $this->role(
                $tenant,
                'salesman',
                ['customers:view', 'catalog:view'],
            );

            $user = $this->plainUser($tenant, 'sales@'.$tenant->slug.'.local', $branch);
            $user->syncPrimaryRole($role);

            $salesman = Salesman::create([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'employee_code' => 'SAL-MOB',
                'first_name' => 'Mobile',
                'is_active' => true,
            ]);

            SalesmanAssignment::create([
                'tenant_id' => $tenant->id,
                'salesman_id' => $salesman->id,
                'branch_id' => $branch->id,
                'territory_id' => $territory->id,
                'route_id' => $route->id,
                'effective_from' => '2026-01-01',
            ]);

            $device = Device::create([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'salesman_id' => $salesman->id,
                'device_uuid' => 'device-'.$tenant->id,
                'installation_uuid' => 'install-'.$tenant->id,
                'platform' => 'android',
                'app_version' => '1.0',
                'is_active' => true,
                'registered_at' => now(),
            ]);

            $token = app(TenantContext::class)->withTenant(
                $tenant,
                fn () => $user->createToken('mobile-'.$device->uuid)->plainTextToken
            );

            return [
                'user' => $user,
                'salesman' => $salesman,
                'device' => $device,
                'token' => $token,
                'branch' => $branch,
                'territory' => $territory,
                'route' => $route,
                'headers' => [
                    'Authorization' => 'Bearer '.$token,
                    'X-Device-UUID' => $device->device_uuid,
                    'X-Installation-UUID' => $device->installation_uuid,
                    'X-App-Version' => '1.0',
                    'X-Platform' => 'android',
                    'X-OS-Version' => '16',
                ],
            ];
        });
    }

    private function tenant(string $slug): Tenant
    {
        return Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => str($slug)->replace('-', ' ')->title(),
            'slug' => $slug,
            'timezone' => 'Asia/Kabul',
            'subscription_status' => 'active',
        ]);
    }

    private function permission(string $slug): Permission
    {
        return Permission::firstOrCreate(
            ['slug' => $slug],
            [
                'name' => str($slug)->replace(':', ' ')->title(),
                'group' => str($slug)->before(':'),
            ]
        );
    }

    private function role(Tenant $tenant, string $slug, array $permissions): Role
    {
        $role = Role::create([
            'tenant_id' => $tenant->id,
            'name' => str($slug)->replace('_', ' ')->title(),
            'slug' => $slug,
            'is_system' => true,
        ]);

        $role->permissions()->sync(
            collect($permissions)
                ->map(fn (string $permission) => $this->permission($permission)->id)
                ->all()
        );

        return $role;
    }

    private function plainUser(
        Tenant $tenant,
        string $email,
        ?Branch $branch = null,
    ): User {
        return User::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'branch_id' => $branch?->id,
            'name' => (string) str($email)->before('@')->replace('.', ' ')->title(),
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => 'salesman',
            'is_active' => true,
        ]);
    }

    private function userWithRole(
        Tenant $tenant,
        string $email,
        array $permissions,
        string $roleSlug,
    ): User {
        $role = $this->role($tenant, $roleSlug, $permissions);
        $user = $this->plainUser($tenant, $email);
        $user->syncPrimaryRole($role);

        return $user;
    }

    private function platform(callable $callback): mixed
    {
        return $this->context->withPlatformScope(fn () => $callback());
    }

    private function tenantScope(Tenant $tenant, callable $callback): mixed
    {
        return $this->context->withTenant($tenant, fn () => $callback());
    }
}
