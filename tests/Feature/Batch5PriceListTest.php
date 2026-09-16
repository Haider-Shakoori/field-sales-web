<?php

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Models\Territory;

beforeEach(function (): void {
    seedRbac();
});

/**
 * PRICE LISTS - WEB
 */
test('creates a price list through the web form and records an audit event', function (): void {
    $tenant = makeTenant();
    $owner = makeUser($tenant, 'owner');

    withTenantContext($tenant, function () use ($owner, $tenant): void {
        $this->actingAs($owner)
            ->post(route('price-lists.store'), [
                'name' => 'Wholesale',
                'is_default' => 1,
                'is_active' => true,
            ])->assertRedirect();

        $priceList = PriceList::where('tenant_id', $tenant->id)->firstOrFail();
        expect($priceList->name)->toBe('Wholesale');
        expect($priceList->is_default)->toBeTrue();
        expect($priceList->is_active)->toBeTrue();
        expect($priceList->uuid)->not->toBeNull();

        expect(AuditLog::where('event', 'price_list.created')->exists())->toBeTrue();
    });
});

test('creating a new default price list swaps the previous default', function (): void {
    $tenant = makeTenant();
    $owner = makeUser($tenant, 'owner');

    withTenantContext($tenant, function () use ($owner, $tenant): void {
        $this->actingAs($owner)->post(route('price-lists.store'), [
            'name' => 'Original',
            'is_default' => 1,
        ]);

        $this->actingAs($owner)->post(route('price-lists.store'), [
            'name' => 'Promoted',
            'is_default' => 1,
        ]);

        expect(PriceList::where('tenant_id', $tenant->id)->where('is_default', true)->count())->toBe(1);
        expect(PriceList::where('tenant_id', $tenant->id)->where('name', 'Original')->first()->is_default)->toBeFalse();
        expect(PriceList::where('tenant_id', $tenant->id)->where('name', 'Promoted')->first()->is_default)->toBeTrue();
    });
});

test('promoting a non-default list to default via update swaps the default', function (): void {
    $tenant = makeTenant();
    $owner = makeUser($tenant, 'owner');

    withTenantContext($tenant, function () use ($owner, $tenant): void {
        $this->actingAs($owner)->post(route('price-lists.store'), [
            'name' => 'Original',
            'is_default' => 1,
        ]);
        $original = PriceList::where('tenant_id', $tenant->id)->where('name', 'Original')->firstOrFail();

        $this->actingAs($owner)->post(route('price-lists.store'), [
            'name' => 'Secondary',
        ]);
        $secondary = PriceList::where('tenant_id', $tenant->id)->where('name', 'Secondary')->firstOrFail();

        $this->actingAs($owner)->put(route('price-lists.update', $secondary), [
            'name' => 'Secondary',
            'is_default' => 1,
            'is_active' => 1,
        ])->assertRedirect();

        $original->refresh();
        $secondary->refresh();
        expect($original->is_default)->toBeFalse();
        expect($secondary->is_default)->toBeTrue();
        expect(PriceList::where('tenant_id', $tenant->id)->where('is_default', true)->count())->toBe(1);
    });

    expect(AuditLog::where('event', 'price_list.default_changed')->exists())->toBeTrue();
});

test('removing the default status from the default list is blocked', function (): void {
    $tenant = makeTenant();
    $owner = makeUser($tenant, 'owner');
    $priceList = withTenantContext($tenant, fn () => PriceList::factory()->forTenant($tenant->id)->default()->create());

    withTenantContext($tenant, function () use ($owner, $priceList): void {
        $this->actingAs($owner)
            ->put(route('price-lists.update', $priceList), [
                'name' => $priceList->name,
                'is_default' => 0,
                'is_active' => 1,
            ])->assertRedirect()->assertSessionHasErrors('is_default');

        $priceList->refresh();
        expect($priceList->is_default)->toBeTrue();
    });
});

test('deactivating the default price list is blocked', function (): void {
    $tenant = makeTenant();
    $owner = makeUser($tenant, 'owner');
    $priceList = withTenantContext($tenant, fn () => PriceList::factory()->forTenant($tenant->id)->default()->create());

    withTenantContext($tenant, function () use ($owner, $priceList): void {
        $this->actingAs($owner)
            ->delete(route('price-lists.deactivate', $priceList))
            ->assertRedirect()
            ->assertSessionHasErrors('is_active');

        $priceList->refresh();
        expect($priceList->is_active)->toBeTrue();
    });
});

test('deactivating a non-default price list works and records an audit event', function (): void {
    $tenant = makeTenant();
    $owner = makeUser($tenant, 'owner');
    withTenantContext($tenant, fn () => PriceList::factory()->forTenant($tenant->id)->default()->create());
    $target = withTenantContext($tenant, fn () => PriceList::factory()->forTenant($tenant->id)->create());

    withTenantContext($tenant, function () use ($owner, $target): void {
        $this->actingAs($owner)
            ->delete(route('price-lists.deactivate', $target))
            ->assertRedirect();

        $target->refresh();
        expect($target->is_active)->toBeFalse();
    });

    expect(AuditLog::where('event', 'price_list.deactivated')->exists())->toBeTrue();
});

test('renders price list web pages', function (): void {
    $tenant = makeTenant();
    $owner = makeUser($tenant, 'owner');
    $priceList = withTenantContext($tenant, fn () => PriceList::factory()->forTenant($tenant->id)->create());

    withTenantContext($tenant, function () use ($owner, $priceList): void {
        $this->actingAs($owner)->get(route('price-lists.index'))->assertOk()->assertSee($priceList->name);
        $this->actingAs($owner)->get(route('price-lists.create'))->assertOk();
        $this->actingAs($owner)->get(route('price-lists.show', $priceList))->assertOk()->assertSee($priceList->name);
        $this->actingAs($owner)->get(route('price-lists.edit', $priceList))->assertOk()->assertSee($priceList->name);
    });
});

/**
 * PRICE LISTS - RBAC
 */
test('sales manager may manage prices but cannot create or deactivate price lists', function (): void {
    $tenant = makeTenant();
    $manager = makeUser($tenant, 'sales_manager');
    $priceList = withTenantContext($tenant, fn () => PriceList::factory()->forTenant($tenant->id)->create());
    $product = withTenantContext($tenant, fn () => Product::factory()->forTenant($tenant->id)->create());

    withTenantContext($tenant, function () use ($manager, $priceList, $product): void {
        $this->actingAs($manager)->get(route('price-lists.index'))->assertOk();
        $this->actingAs($manager)->get(route('price-lists.create'))->assertForbidden();
        $this->actingAs($manager)->post(route('price-lists.store'), [
            'name' => 'Blocked',
        ])->assertForbidden();
        $this->actingAs($manager)
            ->post(route('price-lists.items.store', $priceList), [
                'product_id' => $product->id,
                'price' => '500.00',
            ])->assertRedirect();
        expect($priceList->items()->count())->toBe(1);
    });
});

/**
 * PRICE LISTS - API
 */
test('API price list index is tenant-isolated and exposes item counts', function (): void {
    $tenantA = makeTenant();
    $tenantB = makeTenant();
    $ownerA = makeUser($tenantA, 'owner');

    $listA = withTenantContext($tenantA, fn () => PriceList::factory()->forTenant($tenantA->id)->default()->create());
    $listB = withTenantContext($tenantA, fn () => PriceList::factory()->forTenant($tenantA->id)->create());

    withTenantContext($tenantA, function () use ($listA, $tenantA): void {
        foreach (range(1, 2) as $i) {
            $product = Product::factory()->forTenant($tenantA->id)->create();
            PriceListItem::create([
                'tenant_id' => $tenantA->id,
                'price_list_id' => $listA->id,
                'product_id' => $product->id,
                'price' => 100 * $i,
            ]);
        }
    });

    withTenantContext($tenantB, fn () => PriceList::factory()->forTenant($tenantB->id)->create());

    withTenantContext($tenantA, function () use ($ownerA, $listA, $listB): void {
        $response = $this->actingAs($ownerA)->getJson('/api/v1/price-lists');
        $response->assertOk();
        expect($response->json('meta.total'))->toBe(2);

        $byName = collect($response->json('data'))->keyBy('name');
        expect($byName[$listA->name]['items_count'])->toBe(2);
        expect($byName[$listB->name]['items_count'])->toBe(0);

        $search = $this->actingAs($ownerA)->getJson('/api/v1/price-lists?filter[search]='.urlencode($listA->name));
        expect($search->json('meta.total'))->toBe(1);
    });
});

/**
 * PRICE LISTS - CUSTOMER ASSIGNMENT VALIDATION
 */
test('customer store rejects a price list from another tenant', function (): void {
    $tenantA = makeTenant();
    $tenantB = makeTenant();
    $ownerB = makeUser($tenantB, 'owner');
    $foreignList = withTenantContext($tenantA, fn () => PriceList::factory()->forTenant($tenantA->id)->create());
    $branch = withTenantContext($tenantB, fn () => Branch::factory()->forTenant($tenantB->id)->create());
    $territory = withTenantContext($tenantB, fn () => Territory::factory()->forTenant($tenantB->id)->forBranch($branch->id)->create());

    withTenantContext($tenantB, function () use ($ownerB, $foreignList, $branch, $territory): void {
        $response = $this->actingAs($ownerB)
            ->postJson('/api/v1/customers', [
                'branch_id' => $branch->id,
                'territory_id' => $territory->id,
                'name' => 'Cross Tenant Customer',
                'price_list_id' => $foreignList->id,
            ]);

        expect($response->status())->toBe(422);
        expect($response->json('error.details.errors.price_list_id'))->toBeArray();
    });
});

test('customer store rejects a deactivated price list', function (): void {
    $tenant = makeTenant();
    $owner = makeUser($tenant, 'owner');
    $inactiveList = withTenantContext($tenant, fn () => PriceList::factory()->forTenant($tenant->id)->inactive()->create());
    $branch = withTenantContext($tenant, fn () => Branch::factory()->forTenant($tenant->id)->create());
    $territory = withTenantContext($tenant, fn () => Territory::factory()->forTenant($tenant->id)->forBranch($branch->id)->create());

    withTenantContext($tenant, function () use ($owner, $inactiveList, $branch, $territory): void {
        $response = $this->actingAs($owner)
            ->postJson('/api/v1/customers', [
                'branch_id' => $branch->id,
                'territory_id' => $territory->id,
                'name' => 'Inactive List Customer',
                'price_list_id' => $inactiveList->id,
            ]);

        expect($response->status())->toBe(422);
        expect($response->json('error.details.errors.price_list_id'))->toBeArray();
    });
});

test('customer store accepts an active in-tenant price list', function (): void {
    $tenant = makeTenant();
    $owner = makeUser($tenant, 'owner');
    $priceList = withTenantContext($tenant, fn () => PriceList::factory()->forTenant($tenant->id)->create());
    $branch = withTenantContext($tenant, fn () => Branch::factory()->forTenant($tenant->id)->create());
    $territory = withTenantContext($tenant, fn () => Territory::factory()->forTenant($tenant->id)->forBranch($branch->id)->create());

    withTenantContext($tenant, function () use ($owner, $priceList, $branch, $territory, $tenant): void {
        $this->actingAs($owner)
            ->postJson('/api/v1/customers', [
                'branch_id' => $branch->id,
                'territory_id' => $territory->id,
                'name' => 'Priced Customer',
                'price_list_id' => $priceList->id,
            ])->assertCreated();

        $customer = Customer::where('tenant_id', $tenant->id)->firstOrFail();
        expect($customer->price_list_id)->toBe($priceList->id);
    });
});

/**
 * CUSTOMER UPDATE PATH - PRICE LIST SECURITY
 */
test('customer update rejects a price list from another tenant', function (): void {
    $tenantA = makeTenant();
    $tenantB = makeTenant();
    $ownerB = makeUser($tenantB, 'owner');
    $listA = withTenantContext($tenantA, fn () => PriceList::factory()->forTenant($tenantA->id)->create());
    $branch = withTenantContext($tenantB, fn () => Branch::factory()->forTenant($tenantB->id)->create());
    $territory = withTenantContext($tenantB, fn () => Territory::factory()->forTenant($tenantB->id)->forBranch($branch->id)->create());
    $customer = withTenantContext($tenantB, fn () => Customer::factory()->forTenant($tenantB->id)->create([
        'branch_id' => $branch->id,
        'territory_id' => $territory->id,
    ]));

    withTenantContext($tenantB, function () use ($ownerB, $customer, $branch, $territory, $listA): void {
        $response = $this->actingAs($ownerB)
            ->putJson("/api/v1/customers/{$customer->id}", [
                'branch_id' => $branch->id,
                'territory_id' => $territory->id,
                'name' => 'Customer Name',
                'price_list_id' => $listA->id,
            ]);

        expect($response->status())->toBe(422);
        expect($response->json('error.details.errors.price_list_id'))->toBeArray();
    });
});

test('customer update rejects a deactivated price list', function (): void {
    $tenant = makeTenant();
    $owner = makeUser($tenant, 'owner');
    $inactiveList = withTenantContext($tenant, fn () => PriceList::factory()->forTenant($tenant->id)->inactive()->create());
    $branch = withTenantContext($tenant, fn () => Branch::factory()->forTenant($tenant->id)->create());
    $territory = withTenantContext($tenant, fn () => Territory::factory()->forTenant($tenant->id)->forBranch($branch->id)->create());
    $customer = withTenantContext($tenant, fn () => Customer::factory()->forTenant($tenant->id)->create([
        'branch_id' => $branch->id,
        'territory_id' => $territory->id,
    ]));

    withTenantContext($tenant, function () use ($owner, $customer, $branch, $territory, $inactiveList): void {
        $response = $this->actingAs($owner)
            ->putJson("/api/v1/customers/{$customer->id}", [
                'branch_id' => $branch->id,
                'territory_id' => $territory->id,
                'name' => 'Customer Name',
                'price_list_id' => $inactiveList->id,
            ]);

        expect($response->status())->toBe(422);
        expect($response->json('error.details.errors.price_list_id'))->toBeArray();
    });
});

test('customer update accepts active in-tenant price list and fires audit on change', function (): void {
    $tenant = makeTenant();
    $owner = makeUser($tenant, 'owner');
    $listA = withTenantContext($tenant, fn () => PriceList::factory()->forTenant($tenant->id)->create());
    $listB = withTenantContext($tenant, fn () => PriceList::factory()->forTenant($tenant->id)->create());
    $branch = withTenantContext($tenant, fn () => Branch::factory()->forTenant($tenant->id)->create());
    $territory = withTenantContext($tenant, fn () => Territory::factory()->forTenant($tenant->id)->forBranch($branch->id)->create());
    $customer = withTenantContext($tenant, fn () => Customer::factory()->forTenant($tenant->id)->create([
        'branch_id' => $branch->id,
        'territory_id' => $territory->id,
        'price_list_id' => $listA->id,
    ]));

    withTenantContext($tenant, function () use ($owner, $customer, $branch, $territory, $listB): void {
        // Switch price list — should fire customer.price_list_changed
        $this->actingAs($owner)
            ->putJson("/api/v1/customers/{$customer->id}", [
                'branch_id' => $branch->id,
                'territory_id' => $territory->id,
                'name' => 'Updated Name',
                'price_list_id' => $listB->id,
            ])->assertOk();

        expect($customer->fresh()->price_list_id)->toBe($listB->id);
        expect(
            AuditLog::where('event', 'customer.price_list_changed')
                ->where('auditable_id', $customer->id)
                ->exists()
        )->toBeTrue();

        // Unrelated update preserving the same price list — no duplicate audit
        $beforeCount = AuditLog::where('event', 'customer.price_list_changed')
            ->where('auditable_id', $customer->id)
            ->count();

        $this->actingAs($owner)
            ->putJson("/api/v1/customers/{$customer->id}", [
                'branch_id' => $branch->id,
                'territory_id' => $territory->id,
                'name' => 'Updated Name',
                'price_list_id' => $listB->id,
            ])->assertOk();

        expect(
            AuditLog::where('event', 'customer.price_list_changed')
                ->where('auditable_id', $customer->id)
                ->count()
        )->toBe($beforeCount);
    });
});
