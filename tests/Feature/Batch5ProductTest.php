<?php

use App\Models\AuditLog;
use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\Product;
use Illuminate\Database\QueryException;

beforeEach(function (): void {
    seedRbac();
});

/**
 * PRODUCTS - WEB
 */
test('creates a product through the web form and records an audit event', function (): void {
    $tenant = makeTenant();
    $owner = makeUser($tenant, 'owner');

    withTenantContext($tenant, function () use ($owner, $tenant): void {
        $this->actingAs($owner)
            ->post(route('products.store'), [
                'name' => 'Cola 500ml',
                'sku' => 'SKU-COLA-500',
                'unit' => 'piece',
                'price' => '1200.50',
                'is_active' => true,
            ])->assertRedirect();

        $product = Product::where('tenant_id', $tenant->id)->firstOrFail();
        expect($product->name)->toBe('Cola 500ml');
        expect($product->sku)->toBe('SKU-COLA-500');
        expect($product->unit)->toBe('piece');
        expect($product->price)->toBe('1200.50');
        expect($product->category)->toBeNull();
        expect($product->is_active)->toBeTrue();
        expect($product->uuid)->not->toBeNull();

        expect(AuditLog::where('event', 'product.created')->exists())->toBeTrue();
    });
});

test('rejects invalid unit values', function (): void {
    $tenant = makeTenant();
    $owner = makeUser($tenant, 'owner');

    withTenantContext($tenant, function () use ($owner): void {
        $this->actingAs($owner)
            ->post(route('products.store'), [
                'name' => 'Wrong Unit',
                'sku' => 'SKU-WRONG',
                'unit' => 'bottle',
                'price' => '10.00',
            ])->assertSessionHasErrors('unit');
    });
});

test('SKU must be unique per tenant but may repeat across tenants', function (): void {
    $tenantA = makeTenant();
    $tenantB = makeTenant();

    withTenantContext($tenantA, fn () => Product::factory()->forTenant($tenantA->id)->create([
        'sku' => 'SHARED-SKU',
    ]));

    withTenantContext($tenantB, fn () => expect(
        Product::factory()->forTenant($tenantB->id)->create(['sku' => 'SHARED-SKU'])
    )->toBeInstanceOf(Product::class));

    withTenantContext($tenantA, fn () => expect(
        fn () => Product::create([
            'tenant_id' => $tenantA->id,
            'name' => 'Duplicate',
            'sku' => 'SHARED-SKU',
            'unit' => 'piece',
            'price' => 10,
        ])
    )->toThrow(QueryException::class));
});

test('updates a product and toggles activation with audit events', function (): void {
    $tenant = makeTenant();
    $owner = makeUser($tenant, 'owner');
    $product = withTenantContext($tenant, fn () => Product::factory()->forTenant($tenant->id)->create());

    withTenantContext($tenant, function () use ($owner, $product): void {
        $this->actingAs($owner)
            ->put(route('products.update', $product), [
                'name' => 'Renamed',
                'sku' => $product->sku,
                'unit' => 'case',
                'price' => '99.00',
                'is_active' => true,
            ])->assertRedirect();

        $product->refresh();
        expect($product->name)->toBe('Renamed');
        expect($product->price)->toBe('99.00');

        $this->actingAs($owner)
            ->delete(route('products.deactivate', $product))
            ->assertRedirect();

        $product->refresh();
        expect($product->is_active)->toBeFalse();

        $this->actingAs($owner)
            ->delete(route('products.deactivate', $product))
            ->assertRedirect();

        $product->refresh();
        expect($product->is_active)->toBeTrue();
    });

    expect(AuditLog::where('event', 'product.updated')->exists())->toBeTrue();
    expect(AuditLog::where('event', 'product.deactivated')->exists())->toBeTrue();
    expect(AuditLog::where('event', 'product.activated')->exists())->toBeTrue();
});

test('renders product web pages', function (): void {
    $tenant = makeTenant();
    $owner = makeUser($tenant, 'owner');
    $product = withTenantContext($tenant, fn () => Product::factory()->forTenant($tenant->id)->create());

    withTenantContext($tenant, function () use ($owner, $product): void {
        $this->actingAs($owner)->get(route('products.index'))->assertOk()->assertSee($product->name);
        $this->actingAs($owner)->get(route('products.create'))->assertOk();
        $this->actingAs($owner)->get(route('products.show', $product))->assertOk()->assertSee($product->name);
        $this->actingAs($owner)->get(route('products.edit', $product))->assertOk()->assertSee($product->name);
    });
});

/**
 * PRODUCTS - RBAC
 */
test('sales manager can view products but cannot access the create page', function (): void {
    $tenant = makeTenant();
    $manager = makeUser($tenant, 'sales_manager');

    withTenantContext($tenant, function () use ($manager): void {
        $this->actingAs($manager)->get(route('products.index'))->assertOk();
        $this->actingAs($manager)->get(route('products.create'))->assertForbidden();
    });
});

test('supervisor and salesman can view products but cannot create them', function (string $role): void {
    $tenant = makeTenant();
    $user = makeUser($tenant, $role);

    withTenantContext($tenant, function () use ($user): void {
        $this->actingAs($user)->get(route('products.index'))->assertOk();
        $this->actingAs($user)->get(route('products.create'))->assertForbidden();
    });
})->with(['supervisor', 'salesman']);

/**
 * PRODUCTS - API
 */
test('API product index is tenant-isolated and searchable', function (): void {
    $tenantA = makeTenant();
    $tenantB = makeTenant();
    $ownerA = makeUser($tenantA, 'owner');

    withTenantContext($tenantA, fn () => Product::factory()->forTenant($tenantA->id)->create([
        'name' => 'Cola Zero',
        'sku' => 'SKU-A-001',
        'category' => 'Beverage',
    ]));
    withTenantContext($tenantA, fn () => Product::factory()->forTenant($tenantA->id)->inactive()->create([
        'name' => 'Chips',
        'sku' => 'SKU-A-002',
        'category' => 'Snack',
    ]));
    withTenantContext($tenantB, fn () => Product::factory()->forTenant($tenantB->id)->create([
        'name' => 'Cola Zero Imported',
        'sku' => 'SKU-B-001',
    ]));

    withTenantContext($tenantA, function () use ($ownerA): void {
        $response = $this->actingAs($ownerA)->getJson('/api/v1/products');
        $response->assertOk();
        expect($response->json('data'))->toHaveCount(2);
        expect($response->json('meta.total'))->toBe(2);

        $search = $this->actingAs($ownerA)->getJson('/api/v1/products?filter[search]=Cola');
        expect($search->json('meta.total'))->toBe(1);
        expect($search->json('data.0.name'))->toBe('Cola Zero');

        $category = $this->actingAs($ownerA)->getJson('/api/v1/products?filter[category]=Beverage');
        expect($category->json('meta.total'))->toBe(1);

        $active = $this->actingAs($ownerA)->getJson('/api/v1/products?filter[is_active]=true');
        expect($active->json('meta.total'))->toBe(1);

        $inactive = $this->actingAs($ownerA)->getJson('/api/v1/products?filter[is_active]=false');
        expect($inactive->json('meta.total'))->toBe(1);
        expect($inactive->json('data.0.sku'))->toBe('SKU-A-002');
    });
});

test('salesman and supervisor see only active products via the API', function (string $role): void {
    $tenant = makeTenant();
    $user = makeUser($tenant, $role);

    withTenantContext($tenant, fn () => Product::factory()->forTenant($tenant->id)->create());
    withTenantContext($tenant, fn () => Product::factory()->forTenant($tenant->id)->inactive()->create());

    withTenantContext($tenant, function () use ($user): void {
        $this->actingAs($user)->getJson('/api/v1/products?filter[is_active]=false')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    });
})->with(['salesman', 'supervisor']);

test('product API show returns price list overrides', function (): void {
    $tenant = makeTenant();
    $owner = makeUser($tenant, 'owner');
    $product = withTenantContext($tenant, fn () => Product::factory()->forTenant($tenant->id)->create());
    $priceList = withTenantContext($tenant, fn () => $priceList = PriceList::factory()->forTenant($tenant->id)->create());
    withTenantContext($tenant, fn () => PriceListItem::create([
        'tenant_id' => $tenant->id,
        'price_list_id' => $priceList->id,
        'product_id' => $product->id,
        'price' => 850.00,
    ]));

    withTenantContext($tenant, function () use ($owner, $product): void {
        $response = $this->actingAs($owner)->getJson("/api/v1/products/{$product->id}");
        $response->assertOk();
        expect($response->json('data.sku'))->toBe($product->sku);
        expect($response->json('data.price_lists'))->toHaveCount(1);
        expect($response->json('data.price_lists.0.price'))->toEqual(850);
    });
});

test('product API show is scoped to the current tenant', function (): void {
    $tenantA = makeTenant();
    $tenantB = makeTenant();
    $ownerB = makeUser($tenantB, 'owner');
    $productA = withTenantContext($tenantA, fn () => Product::factory()->forTenant($tenantA->id)->create());

    withTenantContext($tenantB, function () use ($ownerB, $productA): void {
        $this->actingAs($ownerB)->getJson("/api/v1/products/{$productA->id}")->assertNotFound();
    });
});
