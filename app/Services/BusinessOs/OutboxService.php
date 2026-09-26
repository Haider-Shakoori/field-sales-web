<?php

namespace App\Services\BusinessOs;

use App\Models\BusinessOsEntityLink;
use App\Models\BusinessOsOutboxEvent;
use App\Models\Collection as CustomerCollection;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Tenant;

final class OutboxService
{
    public function __construct(
        private readonly EntityLinkService $links,
    ) {}

    public function seed(Tenant $tenant, array $policy): array
    {
        $counts = [
            'customers' => 0,
            'orders' => 0,
            'collections' => 0,
        ];

        if ($policy['push_field_customers']) {
            $counts['customers'] = $this->seedCustomers($tenant);
        }

        if ($policy['push_orders']) {
            $counts['orders'] = $this->seedOrders($tenant);
        }

        if ($policy['push_collections']) {
            $counts['collections'] = $this->seedCollections($tenant);
        }

        return $counts;
    }

    public function pending(string $eventType, int $limit = 250)
    {
        return BusinessOsOutboxEvent::query()
            ->where('event_type', $eventType)
            ->whereIn('status', ['pending', 'failed'])
            ->orderBy('created_at')
            ->limit($limit)
            ->get();
    }

    public function markSent(
        BusinessOsOutboxEvent $event,
        ?string $externalId = null,
    ): void {
        $event->update([
            'status' => 'sent',
            'attempts' => $event->attempts + 1,
            'last_attempt_at' => now(),
            'sent_at' => now(),
            'external_id' => $externalId,
            'last_error' => null,
        ]);

        if ($externalId !== null && $externalId !== '') {
            $model = match ($event->entity_type) {
                'customer' => Customer::query()
                    ->where('uuid', $event->local_uuid)
                    ->first(),
                'order' => Order::query()
                    ->where('uuid', $event->local_uuid)
                    ->first(),
                'collection' => CustomerCollection::query()
                    ->where('uuid', $event->local_uuid)
                    ->first(),
                default => null,
            };

            if ($model) {
                $this->links->link(
                    $model,
                    $event->entity_type,
                    $externalId,
                    null,
                    'fieldpulse',
                    ['event_key' => $event->event_key],
                );
            }
        }
    }

    public function markFailed(
        BusinessOsOutboxEvent $event,
        string $error,
    ): void {
        $event->update([
            'status' => 'failed',
            'attempts' => $event->attempts + 1,
            'last_attempt_at' => now(),
            'last_error' => mb_substr($error, 0, 4000),
        ]);
    }

    private function seedCustomers(Tenant $tenant): int
    {
        $businessOsOrigin = BusinessOsEntityLink::query()
            ->where('entity_type', 'customer')
            ->get()
            ->filter(
                fn (BusinessOsEntityLink $link) => (
                    data_get($link->metadata, 'origin') === 'businessos'
                )
            )
            ->pluck('local_uuid')
            ->flip();

        $created = 0;

        Customer::query()
            ->orderBy('id')
            ->chunkById(250, function ($customers) use (
                $tenant,
                $businessOsOrigin,
                &$created,
            ): void {
                foreach ($customers as $customer) {
                    if ($businessOsOrigin->has($customer->uuid)) {
                        continue;
                    }

                    $version = $customer->updated_at?->getTimestamp()
                        ?? $customer->created_at?->getTimestamp()
                        ?? 0;
                    $eventKey = implode(':', [
                        'fieldpulse',
                        $tenant->uuid,
                        'customer.upserted',
                        $customer->uuid,
                        $version,
                    ]);

                    $event = BusinessOsOutboxEvent::query()->firstOrCreate(
                        ['event_key' => $eventKey],
                        [
                            'event_type' => 'customer.upserted',
                            'entity_type' => 'customer',
                            'local_uuid' => $customer->uuid,
                            'payload' => $this->customerPayload(
                                $tenant,
                                $customer,
                                $eventKey,
                            ),
                        ],
                    );

                    if ($event->wasRecentlyCreated) {
                        $created++;
                    }
                }
            });

        return $created;
    }

    private function seedOrders(Tenant $tenant): int
    {
        $created = 0;

        Order::query()
            ->where('status', 'approved')
            ->with(['customer', 'salesman', 'items.product'])
            ->orderBy('id')
            ->chunkById(200, function ($orders) use (
                $tenant,
                &$created,
            ): void {
                foreach ($orders as $order) {
                    $eventKey = implode(':', [
                        'fieldpulse',
                        $tenant->uuid,
                        'order.approved',
                        $order->uuid,
                    ]);

                    $event = BusinessOsOutboxEvent::query()->firstOrCreate(
                        ['event_key' => $eventKey],
                        [
                            'event_type' => 'order.approved',
                            'entity_type' => 'order',
                            'local_uuid' => $order->uuid,
                            'payload' => $this->orderPayload(
                                $tenant,
                                $order,
                                $eventKey,
                            ),
                        ],
                    );

                    if ($event->wasRecentlyCreated) {
                        $created++;
                    }
                }
            });

        return $created;
    }

    private function seedCollections(Tenant $tenant): int
    {
        $created = 0;

        CustomerCollection::query()
            ->where('status', 'verified')
            ->with(['customer', 'salesman'])
            ->orderBy('id')
            ->chunkById(200, function ($collections) use (
                $tenant,
                &$created,
            ): void {
                foreach ($collections as $collection) {
                    $eventKey = implode(':', [
                        'fieldpulse',
                        $tenant->uuid,
                        'collection.verified',
                        $collection->uuid,
                    ]);

                    $event = BusinessOsOutboxEvent::query()->firstOrCreate(
                        ['event_key' => $eventKey],
                        [
                            'event_type' => 'collection.verified',
                            'entity_type' => 'collection',
                            'local_uuid' => $collection->uuid,
                            'payload' => $this->collectionPayload(
                                $tenant,
                                $collection,
                                $eventKey,
                            ),
                        ],
                    );

                    if ($event->wasRecentlyCreated) {
                        $created++;
                    }
                }
            });

        return $created;
    }

    private function customerPayload(
        Tenant $tenant,
        Customer $customer,
        string $eventKey,
    ): array {
        return [
            'idempotency_key' => $eventKey,
            'type' => 'customer.upserted',
            'occurred_at' => $customer->updated_at?->toIso8601String()
                ?? now()->toIso8601String(),
            'source' => 'fieldpulse',
            'organization_key' => data_get(
                $tenant->settings,
                'businessos.organization_key',
            ),
            'data' => [
                'fieldpulse_uuid' => $customer->uuid,
                'businessos_id' => $this->links->externalId(
                    'customer',
                    $customer->uuid,
                ),
                'code' => $customer->code,
                'name' => $customer->name,
                'contact_person' => $customer->contact_person,
                'phone' => $customer->phone,
                'alternate_phone' => $customer->alternate_phone,
                'email' => $customer->email,
                'address' => $customer->address,
                'latitude' => $customer->latitude === null
                    ? null
                    : (float) $customer->latitude,
                'longitude' => $customer->longitude === null
                    ? null
                    : (float) $customer->longitude,
                'is_active' => (bool) $customer->is_active,
            ],
        ];
    }

    private function orderPayload(
        Tenant $tenant,
        Order $order,
        string $eventKey,
    ): array {
        return [
            'idempotency_key' => $eventKey,
            'type' => 'order.approved',
            'occurred_at' => $order->status_changed_at?->toIso8601String()
                ?? $order->updated_at?->toIso8601String()
                ?? now()->toIso8601String(),
            'source' => 'fieldpulse',
            'organization_key' => data_get(
                $tenant->settings,
                'businessos.organization_key',
            ),
            'data' => [
                'fieldpulse_uuid' => $order->uuid,
                'businessos_id' => $this->links->externalId(
                    'order',
                    $order->uuid,
                ),
                'order_number' => $order->order_number,
                'ordered_at' => $order->ordered_at?->toIso8601String(),
                'customer' => [
                    'fieldpulse_uuid' => $order->customer?->uuid,
                    'businessos_id' => $order->customer
                        ? $this->links->externalId(
                            'customer',
                            $order->customer->uuid,
                        )
                        : null,
                    'code' => $order->customer?->code,
                ],
                'salesman' => [
                    'employee_code' => $order->salesman?->employee_code,
                    'name' => $order->salesman?->full_name,
                ],
                'payment_type' => $order->payment_type,
                'currency' => $order->currency,
                'subtotal' => (float) $order->subtotal,
                'discount_total' => (float) $order->discount_total,
                'grand_total' => (float) $order->grand_total,
                'notes' => $order->notes,
                'items' => $order->items->map(
                    fn ($item) => [
                        'product' => [
                            'fieldpulse_uuid' => $item->product?->uuid,
                            'businessos_id' => $item->product
                                ? $this->links->externalId(
                                    'product',
                                    $item->product->uuid,
                                )
                                : null,
                            'sku' => $item->product_sku,
                            'name' => $item->product_name,
                        ],
                        'unit' => $item->unit,
                        'quantity' => (float) $item->quantity,
                        'unit_price' => (float) $item->unit_price,
                        'discount_percent' => (float) $item->discount_percent,
                        'discount_amount' => (float) $item->discount_amount,
                        'line_total' => (float) $item->line_total,
                    ]
                )->values()->all(),
            ],
        ];
    }

    private function collectionPayload(
        Tenant $tenant,
        CustomerCollection $collection,
        string $eventKey,
    ): array {
        return [
            'idempotency_key' => $eventKey,
            'type' => 'collection.verified',
            'occurred_at' => $collection->status_changed_at?->toIso8601String()
                ?? $collection->updated_at?->toIso8601String()
                ?? now()->toIso8601String(),
            'source' => 'fieldpulse',
            'organization_key' => data_get(
                $tenant->settings,
                'businessos.organization_key',
            ),
            'data' => [
                'fieldpulse_uuid' => $collection->uuid,
                'businessos_id' => $this->links->externalId(
                    'collection',
                    $collection->uuid,
                ),
                'receipt_number' => $collection->receipt_number,
                'collected_at' => $collection->collected_at?->toIso8601String(),
                'customer' => [
                    'fieldpulse_uuid' => $collection->customer?->uuid,
                    'businessos_id' => $collection->customer
                        ? $this->links->externalId(
                            'customer',
                            $collection->customer->uuid,
                        )
                        : null,
                    'code' => $collection->customer?->code,
                ],
                'salesman' => [
                    'employee_code' => $collection->salesman?->employee_code,
                    'name' => $collection->salesman?->full_name,
                ],
                'currency' => $collection->currency,
                'amount' => (float) $collection->amount,
                'payment_method' => $collection->payment_method,
                'reference_number' => $collection->reference_number,
                'latitude' => (float) $collection->latitude,
                'longitude' => (float) $collection->longitude,
                'within_geofence' => $collection->within_geofence,
                'notes' => $collection->notes,
            ],
        ];
    }
}
