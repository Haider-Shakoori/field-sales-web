<?php

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Support\Pricing\ProductPriceResolver;
use Illuminate\Database\QueryException;
use InvalidArgumentException;

beforeEach(function (): void {
    seedRbac();
});

/**
 * PRICE LIST ITEMS - WEB
 */
test('adds an item to a price list and records an audit event', function (): void {
    $tenant = makeTenant();
    $owner = makeUser($tenant, 'owner');
    $priceList = withTenantContext($tenant, fn () => PriceList::factory()->forTenant($tenant->id)->create());
    $product = withTenantContext($tenant, fn () => Product::factory()->forTenant($tenant->id)->create());

    withTenantContext($tenant, function () use ($owner, $priceList, $product): void {
        $this->actingAs($owner)
            ->post(route('price-lists.items.store', $priceList), [
                'product_id' => $product->id,
                'price' => '450.00',
            ])->assertRedirect();

        $item = PriceListItem::where('price_list_id', $priceList->id)->firstOrFail();
        expect($item->product_id)->toBe($product->id);
        expect($item->price)->toBe('450.00');

        expect(AuditLog::where('event', 'price_list_item.created')->exists())->toBeTrue();
    });
});

test('rejects a duplicate product in the same price list', function (): void {
    $tenant = makeTenant();
    $owner = makeUser($tenant, 'owner');
    $priceList = withTenantContext($tenant, fn () => PriceList::factory()->forTenant($tenant->id)->create());
    $product = withTenantContext($tenant, fn () => Product::factory()->forTenant($tenant->id)->create());

    withTenantContext($tenant, function () use ($owner, $priceList, $product): void {
        $this->actingAs($owner)->post(route('price-lists.items.store', $priceList), [
            'product_id' => $product->id,
            'price' => '450.00',
        ])->assertRedirect();

        $this->actingAs($owner)->post(route('price-lists.items.store', $priceList), [
            'product_id' => $product->id,
            'price' => '999.00',
        ])->assertRedirect()->assertSessionHasErrors('product_id');

        expect(PriceListItem::where('price_list_id', $priceList->id)->count())->toBe(1);
    });
});

test('allows the same product in different price lists within a tenant', function (): void {
    $tenant = makeTenant();
    $owner = makeUser($tenant, 'owner');
    $listA = withTenantContext($tenant, fn () => PriceList::factory()->forTenant($tenant->id)->create());
    $listB = withTenantContext($tenant, fn () => PriceList::factory()->forTenant($tenant->id)->create());
    $product = withTenantContext($tenant, fn () => Product::factory()->forTenant($tenant->id)->create());

    withTenantContext($tenant, function () use ($owner, $listA, $listB, $product): void {
        $this->actingAs($owner)->post(route('price-lists.items.store', $listA), [
            'product_id' => $product->id,
            'price' => '450.00',
        ])->assertRedirect();

        $this->actingAs($owner)->post(route('price-lists.items.store', $listB), [
            'product_id' => $product->id,
            'price' => '500.00',
        ])->assertRedirect();

        expect(PriceListItem::where('product_id', $product->id)->count())->toBe(2);
    });
});

test('enforces the unique product index per tenant, price list and product', function (): void {
    $tenant = makeTenant();
    $priceList = withTenantContext($tenant, fn () => PriceList::factory()->forTenant($tenant->id)->create());
    $product = withTenantContext($tenant, fn () => Product::factory()->forTenant($tenant->id)->create());

    withTenantContext($tenant, fn () => PriceListItem::create([
        'tenant_id' => $tenant->id,
        'price_list_id' => $priceList->id,
        'product_id' => $product->id,
        'price' => 10,
    ]));

    withTenantContext($tenant, fn () => expect(
        fn () => PriceListItem::create([
            'tenant_id' => $tenant->id,
            'price_list_id' => $priceList->id,
            'product_id' => $product->id,
            'price' => 20,
        ])
    )->toThrow(QueryException::class));
});

test('updates an item price and records an audit event', function (): void {
    $tenant = makeTenant();
    $owner = makeUser($tenant, 'owner');
    $item = withTenantContext($tenant, fn () => PriceListItem::factory()->forTenant($tenant->id)->create());

    withTenantContext($tenant, function () use ($owner, $item): void {
        $this->actingAs($owner)
            ->put(route('price-lists.items.update', $item), [
                'price' => '789.00',
            ])->assertRedirect();

        $item->refresh();
        expect($item->price)->toBe('789.00');
    });

    expect(AuditLog::where('event', 'price_list_item.updated')->exists())->toBeTrue();
});

test('removes an item from a price list and records an audit event', function (): void {
    $tenant = makeTenant();
    $owner = makeUser($tenant, 'owner');
    $item = withTenantContext($tenant, fn () => PriceListItem::factory()->forTenant($tenant->id)->create());
    $itemId = $item->id;

    withTenantContext($tenant, function () use ($owner, $item): void {
        $this->actingAs($owner)
            ->delete(route('price-lists.items.destroy', $item))
            ->assertRedirect();
    });

    withTenantContext($tenant, function () use ($itemId): void {
        expect(PriceListItem::find($itemId))->toBeNull();
        expect(AuditLog::where('event', 'price_list_item.deleted')->exists())->toBeTrue();
    });
});

/**
 * PRICING RESOLVER
 */
test('resolves the base price when no customer or no price list is set', function (): void {
    $tenant = makeTenant();
    $product = withTenantContext($tenant, fn () => Product::factory()->forTenant($tenant->id)->create([
        'price' => 100.00,
    ]));

    $resolver = app(ProductPriceResolver::class);

    expect($resolver->resolve($product))->toBe('100.00');

    $customer = withTenantContext($tenant, fn () => Customer::factory()->forTenant($tenant->id)->create([
        'price_list_id' => null,
    ]));
    expect($resolver->resolve($product, $customer))->toBe('100.00');
});

test('uses the price list override for a customer on an active price list', function (): void {
    $tenant = makeTenant();
    $product = withTenantContext($tenant, fn () => Product::factory()->forTenant($tenant->id)->create([
        'price' => 100.00,
    ]));
    $priceList = withTenantContext($tenant, fn () => PriceList::factory()->forTenant($tenant->id)->create());
    withTenantContext($tenant, fn () => PriceListItem::create([
        'tenant_id' => $tenant->id,
        'price_list_id' => $priceList->id,
        'product_id' => $product->id,
        'price' => 650.00,
    ]));
    $customer = withTenantContext($tenant, fn () => Customer::factory()->forTenant($tenant->id)->create([
        'price_list_id' => $priceList->id,
    ]));

    withTenantContext($tenant, fn () => expect(app(ProductPriceResolver::class)->resolve($product, $customer))->toBe('650.00'));
});

test('ignores the price list override when the assigned list is inactive', function (): void {
    $tenant = makeTenant();
    $product = withTenantContext($tenant, fn () => Product::factory()->forTenant($tenant->id)->create([
        'price' => 100.00,
    ]));
    $priceList = withTenantContext($tenant, fn () => PriceList::factory()->forTenant($tenant->id)->inactive()->create());
    withTenantContext($tenant, fn () => PriceListItem::create([
        'tenant_id' => $tenant->id,
        'price_list_id' => $priceList->id,
        'product_id' => $product->id,
        'price' => 650.00,
    ]));
    $customer = withTenantContext($tenant, fn () => Customer::factory()->forTenant($tenant->id)->create([
        'price_list_id' => $priceList->id,
    ]));

    withTenantContext($tenant, fn () => expect(app(ProductPriceResolver::class)->resolve($product, $customer))->toBe('100.00'));
});

test('falls back to the base price when the product is not in the active list', function (): void {
    $tenant = makeTenant();
    $product = withTenantContext($tenant, fn () => Product::factory()->forTenant($tenant->id)->create([
        'price' => 100.00,
    ]));
    $priceList = withTenantContext($tenant, fn () => PriceList::factory()->forTenant($tenant->id)->create());
    $customer = withTenantContext($tenant, fn () => Customer::factory()->forTenant($tenant->id)->create([
        'price_list_id' => $priceList->id,
    ]));

    withTenantContext($tenant, fn () => expect(app(ProductPriceResolver::class)->resolve($product, $customer))->toBe('100.00'));
});

test('rejects cross-tenant price resolution', function (): void {
    $tenantA = makeTenant();
    $tenantB = makeTenant();
    $productA = withTenantContext($tenantA, fn () => Product::factory()->forTenant($tenantA->id)->create());
    $priceListB = withTenantContext($tenantB, fn () => PriceList::factory()->forTenant($tenantB->id)->create());
    $customerB = withTenantContext($tenantB, fn () => Customer::factory()->forTenant($tenantB->id)->create([
        'price_list_id' => $priceListB->id,
    ]));

    withTenantContext($tenantB, fn () => expect(fn () => app(ProductPriceResolver::class)->resolve($productA, $customerB))
        ->toThrow(InvalidArgumentException::class));
});

/**
 * RELATIONSHIP INTEGRITY
 */
test('deleting a price list nulls customer references and removes its items', function (): void {
    $tenant = makeTenant();
    $priceList = withTenantContext($tenant, fn () => PriceList::factory()->forTenant($tenant->id)->create());
    $customer = withTenantContext($tenant, fn () => Customer::factory()->forTenant($tenant->id)->create([
        'price_list_id' => $priceList->id,
    ]));
    withTenantContext($tenant, fn () => PriceListItem::factory()->forTenant($tenant->id)->create([
        'price_list_id' => $priceList->id,
    ]));

    withTenantContext($tenant, fn () => $priceList->delete());

    $customer->refresh();
    expect($customer->price_list_id)->toBeNull();
    withTenantContext($tenant, function () use ($priceList): void {
        expect(PriceListItem::where('price_list_id', $priceList->id)->count())->toBe(0);
    });
});

test('assigning a price list to a customer records a price list changed audit event', function (): void {
    $tenant = makeTenant();
    $priceList = withTenantContext($tenant, fn () => PriceList::factory()->forTenant($tenant->id)->create());
    $customer = withTenantContext($tenant, fn () => Customer::factory()->forTenant($tenant->id)->create());

    withTenantContext($tenant, function () use ($customer, $priceList): void {
        $customer->update(['price_list_id' => $priceList->id]);
    });

    expect(AuditLog::where('event', 'customer.price_list_changed')->exists())->toBeTrue();
});
