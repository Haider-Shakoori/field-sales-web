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
use App\Services\CatalogPriceResolver;
use App\Tenancy\TenantContext;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class Batch5ProductsPriceListsMobileApiTest extends TestCase
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

    public function test_product_and_price_list_admin_crud_are_tenant_scoped_and_audited(): void
    {
        $tenant = $this->tenant('catalog-admin');
        $admin = $this->platform(fn () => $this->userWithRole(
            $tenant,
            'admin@catalog.local',
            ['catalog:view', 'catalog:manage'],
            'company_admin',
        ));

        $this->actingAs($admin)
            ->post(route('admin.products.store'), [
                'sku' => 'p-100',
                'barcode' => '123456789',
                'name' => 'Demo Product',
                'description' => 'Product description',
                'unit' => 'box',
                'base_price' => '125.5000',
                'currency' => 'afn',
                'is_active' => '1',
            ])
            ->assertRedirect();

        $product = $this->tenantScope(
            $tenant,
            fn () => Product::where('sku', 'P-100')->firstOrFail()
        );

        $this->actingAs($admin)
            ->post(route('admin.price-lists.store'), [
                'code' => 'retail',
                'name' => 'Retail',
                'currency' => 'afn',
                'effective_from' => '2026-01-01',
                'effective_to' => '',
                'is_active' => '1',
            ])
            ->assertRedirect();

        $priceList = $this->tenantScope(
            $tenant,
            fn () => PriceList::where('code', 'RETAIL')->firstOrFail()
        );

        $this->actingAs($admin)
            ->post(route('admin.price-lists.items.store', $priceList), [
                'product_id' => $product->id,
                'min_quantity' => '10',
                'price' => '110.2500',
            ])
            ->assertRedirect();

        $item = $this->tenantScope(
            $tenant,
            fn () => PriceListItem::where('price_list_id', $priceList->id)->firstOrFail()
        );

        $this->assertSame('110.2500', $item->price);
        $this->actingAs($admin)->get(route('admin.products.show', $product))->assertOk();
        $this->actingAs($admin)->get(route('admin.price-lists.show', $priceList))->assertOk();

        foreach (['product.created', 'price_list.created', 'price_list_item.created'] as $event) {
            $this->assertDatabaseHas('audit_logs', [
                'tenant_id' => $tenant->id,
                'event' => $event,
            ]);
        }
    }

    public function test_cross_tenant_catalog_route_binding_is_hidden(): void
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
                    'sku' => 'B-P',
                    'name' => 'Foreign Product',
                    'unit' => 'pcs',
                    'base_price' => 10,
                    'currency' => 'AFN',
                    'is_active' => true,
                ]),
                PriceList::create([
                    'tenant_id' => $tenantB->id,
                    'code' => 'B-PL',
                    'name' => 'Foreign List',
                    'currency' => 'AFN',
                    'is_active' => true,
                ]),
            ];
        });

        $this->actingAs($admin)->get(route('admin.products.show', $product))->assertNotFound();
        $this->actingAs($admin)->get(route('admin.price-lists.show', $priceList))->assertNotFound();
    }

    public function test_price_resolver_uses_best_quantity_tier_and_safe_base_fallback(): void
    {
        $tenant = $this->tenant('price-resolver');

        [$product, $customer, $priceList] = $this->platform(function () use ($tenant): array {
            $product = Product::create([
                'tenant_id' => $tenant->id,
                'sku' => 'SKU-1',
                'name' => 'Product',
                'unit' => 'pcs',
                'base_price' => 100,
                'currency' => 'AFN',
                'is_active' => true,
            ]);

            $priceList = PriceList::create([
                'tenant_id' => $tenant->id,
                'code' => 'VIP',
                'name' => 'VIP',
                'currency' => 'AFN',
                'effective_from' => '2026-01-01',
                'effective_to' => '2026-12-31',
                'is_active' => true,
            ]);

            foreach ([[1, 95], [10, 90], [50, 80]] as [$minimum, $price]) {
                PriceListItem::create([
                    'tenant_id' => $tenant->id,
                    'price_list_id' => $priceList->id,
                    'product_id' => $product->id,
                    'min_quantity' => $minimum,
                    'price' => $price,
                ]);
            }

            $customer = Customer::create([
                'tenant_id' => $tenant->id,
                'price_list_id' => $priceList->id,
                'code' => 'C-1',
                'name' => 'Customer',
                'geofence_radius_meters' => 100,
                'is_active' => true,
            ]);

            return [$product, $customer, $priceList];
        });

        $this->tenantScope($tenant, function () use ($product, $customer, $priceList): void {
            $resolver = app(CatalogPriceResolver::class);
            $customer->load('priceList');

            $tier = $resolver->resolve(
                $product,
                $customer,
                25,
                now()->setDate(2026, 9, 19)
            );

            $this->assertSame(90.0, $tier['unit_price']);
            $this->assertSame('price_list', $tier['source']);
            $this->assertSame($priceList->uuid, $tier['price_list_id']);
            $this->assertSame(10.0, $tier['min_quantity']);

            $priceList->update(['is_active' => false]);
            $customer->unsetRelation('priceList')->load('priceList');

            $fallback = $resolver->resolve(
                $product,
                $customer,
                25,
                now()->setDate(2026, 9, 19)
            );

            $this->assertSame(100.0, $fallback['unit_price']);
            $this->assertSame('base_price', $fallback['source']);
        });
    }

    public function test_mobile_master_data_returns_public_uuids_pagination_and_route_memberships(): void
    {
        $tenant = $this->tenant('mobile-master');

        $fixture = $this->mobileFixture($tenant);
        $headers = $fixture['headers'];

        $this->getJson('/api/v1/products?per_page=1', $headers)
            ->assertOk()
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $fixture['product']->uuid)
            ->assertJsonPath('data.0.sku', 'SKU-1');

        $this->getJson('/api/v1/price-lists', $headers)
            ->assertOk()
            ->assertJsonPath('data.0.id', $fixture['priceList']->uuid);

        $this->getJson('/api/v1/price-lists/'.$fixture['priceList']->uuid.'/items', $headers)
            ->assertOk()
            ->assertJsonPath('data.0.product_id', $fixture['product']->uuid)
            ->assertJsonPath('data.0.price_list_id', $fixture['priceList']->uuid);

        $this->getJson('/api/v1/routes/'.$fixture['route']->uuid.'/customers', $headers)
            ->assertOk()
            ->assertJsonPath('data.0.id', $fixture['membership']->uuid)
            ->assertJsonPath('data.0.route_id', $fixture['route']->uuid)
            ->assertJsonPath('data.0.customer_id', $fixture['customer']->uuid)
            ->assertJsonPath('data.0.sequence_number', 1);

        $this->getJson('/api/v1/customers', $headers)
            ->assertOk()
            ->assertJsonPath('data.0.id', $fixture['customer']->uuid)
            ->assertJsonPath('data.0.price_list_id', $fixture['priceList']->uuid)
            ->assertJsonPath('data.0.route_ids.0', $fixture['route']->uuid);
    }

    public function test_mobile_customer_creation_is_offline_idempotent_and_assignment_derived(): void
    {
        $tenant = $this->tenant('offline-customer');
        $fixture = $this->mobileFixture($tenant);
        $offlineUuid = (string) Str::uuid();

        $payload = [
            'offline_uuid' => $offlineUuid,
            'name' => 'Offline Shop',
            'contact_person' => 'Owner',
            'phone' => '0700123456',
            'latitude' => 34.52,
            'longitude' => 69.18,
            'geofence_radius_meters' => 125,
            'price_list_id' => $fixture['priceList']->uuid,
        ];

        $this->postJson('/api/v1/customers', $payload, $fixture['headers'])
            ->assertCreated()
            ->assertJsonPath('data.id', $offlineUuid)
            ->assertJsonPath('data.offline_uuid', $offlineUuid)
            ->assertJsonPath('data.territory_id', $fixture['territory']->uuid)
            ->assertJsonPath('data.price_list_id', $fixture['priceList']->uuid);

        $this->postJson('/api/v1/customers', $payload, $fixture['headers'])
            ->assertOk()
            ->assertJsonPath('data.id', $offlineUuid);

        $this->assertDatabaseCount('customers', 2);

        $created = $this->tenantScope(
            $tenant,
            fn () => Customer::where('offline_uuid', $offlineUuid)->firstOrFail()
        );

        $this->assertSame($fixture['branch']->id, $created->branch_id);
        $this->assertSame($fixture['territory']->id, $created->territory_id);
        $this->assertSame($fixture['priceList']->id, $created->price_list_id);
        $this->assertSame($offlineUuid, $created->uuid);
    }

    public function test_mobile_master_data_respects_updated_since_and_includes_inactive_changes(): void
    {
        $tenant = $this->tenant('updated-since');
        $fixture = $this->mobileFixture($tenant);

        $this->tenantScope($tenant, function () use ($fixture): void {
            $fixture['product']->forceFill([
                'is_active' => false,
                'updated_at' => '2026-09-19 01:00:00',
            ])->save();
        });

        $this->getJson(
            '/api/v1/products?updated_since=2026-09-19T00:30:00Z',
            $fixture['headers']
        )
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $fixture['product']->uuid)
            ->assertJsonPath('data.0.is_active', false);

        $this->getJson(
            '/api/v1/products?updated_since=2026-09-19T01:30:00Z',
            $fixture['headers']
        )
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_mobile_catalog_requires_registered_device_headers(): void
    {
        $tenant = $this->tenant('mobile-device-required');
        $fixture = $this->mobileFixture($tenant);

        $this->withToken($fixture['token'])
            ->getJson('/api/v1/products')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'DEVICE_HEADERS_REQUIRED');
    }

    public function test_database_seeder_builds_catalog_route_and_price_list_demo(): void
    {
        app(DatabaseSeeder::class)->run();

        $this->assertDatabaseHas('products', [
            'sku' => 'DEMO-001',
            'currency' => 'AFN',
        ]);
        $this->assertDatabaseHas('price_lists', [
            'code' => 'RETAIL',
            'currency' => 'AFN',
        ]);
        $this->assertDatabaseHas('customers', [
            'code' => 'CUS-001',
        ]);
        $this->assertDatabaseHas('routes', [
            'code' => 'KBL-R01',
        ]);
        $this->assertDatabaseCount('route_customers', 1);
    }

    private function mobileFixture(Tenant $tenant): array
    {
        return $this->platform(function () use ($tenant): array {
            $branch = Branch::create([
                'tenant_id' => $tenant->id,
                'name' => 'Main',
                'code' => 'MAIN',
                'is_active' => true,
            ]);

            $territory = Territory::create([
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
                'code' => 'MAIN-T',
                'name' => 'Main Territory',
                'is_active' => true,
            ]);

            $route = SalesRoute::create([
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
                'territory_id' => $territory->id,
                'code' => 'MAIN-R',
                'name' => 'Main Route',
                'weekdays' => ['sat', 'sun'],
                'is_active' => true,
            ]);

            $priceList = PriceList::create([
                'tenant_id' => $tenant->id,
                'code' => 'RETAIL',
                'name' => 'Retail',
                'currency' => 'AFN',
                'is_active' => true,
            ]);

            $product = Product::create([
                'tenant_id' => $tenant->id,
                'sku' => 'SKU-1',
                'name' => 'Product',
                'unit' => 'pcs',
                'base_price' => 100,
                'currency' => 'AFN',
                'is_active' => true,
            ]);

            PriceListItem::create([
                'tenant_id' => $tenant->id,
                'price_list_id' => $priceList->id,
                'product_id' => $product->id,
                'min_quantity' => 1,
                'price' => 95,
            ]);

            $role = $this->role(
                $tenant,
                'salesman',
                ['customers:view', 'catalog:view'],
            );

            $user = User::create([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
                'name' => 'Salesman',
                'email' => 'sales@'.$tenant->slug.'.local',
                'password' => Hash::make('password'),
                'role' => 'salesman',
                'is_active' => true,
            ]);
            $user->syncPrimaryRole($role);

            $salesman = Salesman::create([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'employee_code' => 'SAL-001',
                'first_name' => 'Sales',
                'is_active' => true,
            ]);

            SalesmanAssignment::create([
                'tenant_id' => $tenant->id,
                'salesman_id' => $salesman->id,
                'branch_id' => $branch->id,
                'territory_id' => $territory->id,
                'route_id' => $route->id,
                'effective_from' => '2026-01-01',
                'created_by' => $user->id,
            ]);

            $customer = Customer::create([
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
                'territory_id' => $territory->id,
                'price_list_id' => $priceList->id,
                'code' => 'CUS-1',
                'name' => 'Customer',
                'geofence_radius_meters' => 100,
                'is_active' => true,
            ]);

            $membership = RouteCustomer::create([
                'tenant_id' => $tenant->id,
                'route_id' => $route->id,
                'customer_id' => $customer->id,
                'sequence_number' => 1,
                'planned_visit_minutes' => 10,
            ]);

            $device = Device::create([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'salesman_id' => $salesman->id,
                'device_uuid' => 'device-'.$tenant->slug,
                'installation_uuid' => 'install-'.$tenant->slug,
                'platform' => 'android',
                'app_version' => '1.0',
                'is_active' => true,
                'registered_at' => now(),
                'last_seen_at' => now(),
            ]);

            $token = $user->createToken(
                'mobile-'.$device->uuid,
                ['customers:view', 'catalog:view']
            )->plainTextToken;

            return [
                'branch' => $branch,
                'territory' => $territory,
                'route' => $route,
                'priceList' => $priceList,
                'product' => $product,
                'user' => $user,
                'salesman' => $salesman,
                'customer' => $customer,
                'membership' => $membership,
                'device' => $device,
                'token' => $token,
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

    private function userWithRole(
        Tenant $tenant,
        string $email,
        array $permissions,
        string $roleSlug,
    ): User {
        $role = $this->role($tenant, $roleSlug, $permissions);
        $user = User::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'name' => (string) str($email)->before('@')->replace('.', ' ')->title(),
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => $roleSlug,
            'is_active' => true,
        ]);
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
