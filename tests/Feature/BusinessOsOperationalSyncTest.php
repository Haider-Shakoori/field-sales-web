<?php

namespace Tests\Feature;

use App\Contracts\BusinessOsConnector;
use App\Models\BusinessOsEntityLink;
use App\Models\BusinessOsOutboxEvent;
use App\Models\BusinessOsSyncRun;
use App\Models\BusinessOsSyncState;
use App\Models\Customer;
use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Models\Tenant;
use App\Services\BusinessOs\OutboxService;
use App\Services\BusinessOs\SyncService;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class BusinessOsOperationalSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('businessos.enabled', true);
        config()->set(
            'businessos.base_url',
            'https://businessos.example.test',
        );
        config()->set('businessos.token', 'test-token');

        $this->app->bind(
            BusinessOsConnector::class,
            fn () => new class implements BusinessOsConnector
            {
                public function health(Tenant $tenant): array
                {
                    return [
                        'connected' => true,
                        'status' => 'healthy',
                    ];
                }

                public function pullMasterData(
                    Tenant $tenant,
                    array $types,
                    ?string $cursor = null,
                ): array {
                    $type = $types[0] ?? '';

                    return match ($type) {
                        'products' => [
                            'data' => [
                                'products' => [[
                                    'external_id' => 'product:101',
                                    'version' => 'v1',
                                    'sku' => 'BOS-101',
                                    'name' => 'BusinessOS Product',
                                    'description' => 'Imported product',
                                    'unit' => 'pcs',
                                    'base_price' => 125,
                                    'currency' => 'AFN',
                                    'is_active' => true,
                                ]],
                            ],
                            'next_cursor' => 'products-cursor-1',
                            'has_more' => false,
                        ],
                        'prices' => [
                            'data' => [
                                'prices' => [[
                                    'external_id' => 'price-list:default',
                                    'version' => 'v1',
                                    'code' => 'BUSINESSOS',
                                    'name' => 'BusinessOS Default Price List',
                                    'currency' => 'AFN',
                                    'is_active' => true,
                                    'items' => [[
                                        'product_external_id' => 'product:101',
                                        'sku' => 'BOS-101',
                                        'min_quantity' => 1,
                                        'price' => 120,
                                    ]],
                                ]],
                            ],
                            'next_cursor' => 'prices-cursor-1',
                            'has_more' => false,
                        ],
                        'customers' => [
                            'data' => [
                                'customers' => [[
                                    'external_id' => 'customer:501',
                                    'version' => 'v1',
                                    'code' => 'BOS-CUST-501',
                                    'name' => 'BusinessOS Customer',
                                    'contact_person' => 'Customer Contact',
                                    'phone' => '0700000000',
                                    'email' => 'customer@example.test',
                                    'address' => 'Kabul',
                                    'price_list_external_id' => 'price-list:default',
                                    'is_active' => true,
                                ]],
                            ],
                            'next_cursor' => 'customers-cursor-1',
                            'has_more' => false,
                        ],
                        default => [
                            'data' => [$type => []],
                            'next_cursor' => null,
                            'has_more' => false,
                        ],
                    };
                }

                public function pushEvents(
                    Tenant $tenant,
                    array $events,
                ): array {
                    return [
                        'status' => 'success',
                        'accepted' => count($events),
                        'results' => collect($events)
                            ->map(fn (array $event) => [
                                'idempotency_key' => $event[
                                    'idempotency_key'
                                ],
                                'accepted' => true,
                                'status' => 'accepted',
                                'external_id' => $event['type']
                                    === 'customer.upserted'
                                        ? 'customer:9001'
                                        : null,
                            ])
                            ->all(),
                    ];
                }
            },
        );
    }

    public function test_master_data_sync_is_idempotent_and_preserves_stream_cursors(): void
    {
        $tenant = $this->tenant();

        app(TenantContext::class)->withTenant(
            $tenant,
            function () use ($tenant): void {
                $run = BusinessOsSyncRun::create([
                    'trigger' => 'manual',
                    'status' => 'queued',
                    'requested_streams' => [
                        'pull:products',
                        'pull:prices',
                        'pull:customers',
                    ],
                ]);

                app(SyncService::class)->execute($tenant, $run);

                $this->assertSame(
                    'succeeded',
                    $run->fresh()->status,
                );
                $this->assertSame(1, Product::count());
                $this->assertSame(1, PriceList::count());
                $this->assertSame(1, PriceListItem::count());
                $this->assertSame(1, Customer::count());

                $product = Product::firstOrFail();
                $priceList = PriceList::firstOrFail();
                $customer = Customer::firstOrFail();

                $this->assertSame('BOS-101', $product->sku);
                $this->assertSame(
                    $priceList->id,
                    $customer->price_list_id,
                );
                $this->assertSame(
                    '120.0000',
                    PriceListItem::firstOrFail()->price,
                );
                $this->assertSame(
                    3,
                    BusinessOsEntityLink::count(),
                );
                $this->assertSame(
                    'products-cursor-1',
                    BusinessOsSyncState::where(
                        'stream',
                        'pull:products',
                    )->value('cursor'),
                );
                $this->assertSame(
                    'prices-cursor-1',
                    BusinessOsSyncState::where(
                        'stream',
                        'pull:prices',
                    )->value('cursor'),
                );
                $this->assertSame(
                    'customers-cursor-1',
                    BusinessOsSyncState::where(
                        'stream',
                        'pull:customers',
                    )->value('cursor'),
                );

                $repeat = BusinessOsSyncRun::create([
                    'trigger' => 'manual',
                    'status' => 'queued',
                    'requested_streams' => [
                        'pull:products',
                        'pull:prices',
                        'pull:customers',
                    ],
                ]);
                app(SyncService::class)->execute(
                    $tenant,
                    $repeat,
                );

                $this->assertSame('succeeded', $repeat->fresh()->status);
                $this->assertSame(1, Product::count());
                $this->assertSame(1, PriceList::count());
                $this->assertSame(1, PriceListItem::count());
                $this->assertSame(1, Customer::count());
                $this->assertSame(
                    3,
                    BusinessOsEntityLink::count(),
                );
            },
        );
    }

    public function test_outbox_excludes_imported_customers_and_deduplicates_local_customer_events(): void
    {
        $tenant = $this->tenant();

        app(TenantContext::class)->withTenant(
            $tenant,
            function () use ($tenant): void {
                $imported = Customer::create([
                    'code' => 'IMPORTED',
                    'name' => 'Imported Customer',
                    'is_active' => true,
                ]);
                BusinessOsEntityLink::create([
                    'entity_type' => 'customer',
                    'local_uuid' => $imported->uuid,
                    'external_id' => 'customer:1',
                    'last_synced_at' => now(),
                    'metadata' => ['origin' => 'businessos'],
                ]);

                $local = Customer::create([
                    'code' => 'LOCAL',
                    'name' => 'Local Customer',
                    'is_active' => true,
                ]);

                $policy = [
                    'push_field_customers' => true,
                    'push_orders' => false,
                    'push_collections' => false,
                ];

                $outbox = app(OutboxService::class);
                $first = $outbox->seed($tenant, $policy);
                $second = $outbox->seed($tenant, $policy);

                $this->assertSame(1, $first['customers']);
                $this->assertSame(0, $second['customers']);
                $this->assertSame(
                    1,
                    BusinessOsOutboxEvent::count(),
                );
                $this->assertSame(
                    $local->uuid,
                    BusinessOsOutboxEvent::firstOrFail()
                        ->local_uuid,
                );

                $run = BusinessOsSyncRun::create([
                    'trigger' => 'manual',
                    'status' => 'queued',
                    'requested_streams' => ['push:customers'],
                ]);
                app(SyncService::class)->execute($tenant, $run);

                $event = BusinessOsOutboxEvent::firstOrFail();
                $this->assertSame('sent', $event->status);
                $this->assertSame(
                    'customer:9001',
                    $event->external_id,
                );

                $link = BusinessOsEntityLink::query()
                    ->where('local_uuid', $local->uuid)
                    ->firstOrFail();
                $this->assertSame('customer:9001', $link->external_id);
                $this->assertSame(
                    'fieldpulse',
                    data_get($link->metadata, 'origin'),
                );
            },
        );
    }

    private function tenant(): Tenant
    {
        return app(TenantContext::class)->withPlatformScope(
            fn () => Tenant::create([
                'uuid' => (string) Str::uuid(),
                'name' => 'BusinessOS Sync Tenant',
                'slug' => 'businessos-sync-'.Str::lower(
                    Str::random(6),
                ),
                'timezone' => 'Asia/Kabul',
                'subscription_status' => 'active',
                'settings' => [
                    'businessos' => [
                        'enabled' => true,
                        'organization_key' => 'businessos-test',
                        'sync' => [
                            'pull_products' => true,
                            'pull_customers' => true,
                            'pull_prices' => true,
                            'push_orders' => true,
                            'push_collections' => true,
                            'push_field_customers' => true,
                            'interval_minutes' => 15,
                        ],
                    ],
                ],
            ])
        );
    }
}
