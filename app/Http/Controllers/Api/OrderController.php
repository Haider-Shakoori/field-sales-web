<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerVisit;
use App\Models\Order;
use App\Models\Product;
use App\Services\OrderPricingService;
use App\Support\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function store(Request $request, OrderPricingService $pricing): JsonResponse
    {
        abort_unless($request->user()->hasPermission('orders:view'), 403);

        $validated = $request->validate([
            'offline_uuid' => ['required', 'uuid'],
            'customer_id' => ['required', 'uuid'],
            'visit_id' => ['nullable', 'uuid'],
            'ordered_at' => ['required', 'date'],
            'payment_type' => ['required', Rule::in(Order::PAYMENT_TYPES)],
            'client_estimated_total' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['required', 'uuid', 'distinct'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.discount_percent' => ['nullable', 'numeric', 'between:0,100'],
        ]);

        $user = $request->user()->load(['salesman', 'tenant']);
        abort_unless($user->salesman?->is_active, 403);

        $existing = Order::where('uuid', $validated['offline_uuid'])->first();

        if ($existing) {
            abort_unless((int) $existing->user_id === (int) $user->id, 409);

            return ApiResponse::success(
                $this->payload($existing->load($this->relations()))
            );
        }

        $orderedAt = CarbonImmutable::parse($validated['ordered_at'])->utc();

        if ($orderedAt->gt(now()->addMinutes(5))) {
            return ApiResponse::error(
                'Order time is too far in the future.',
                422,
                null,
                'VALIDATION_ERROR',
            );
        }

        $customer = Customer::with('priceList')
            ->where('uuid', $validated['customer_id'])
            ->active()
            ->firstOrFail();

        $visit = null;

        if (! empty($validated['visit_id'])) {
            $visit = CustomerVisit::where('uuid', $validated['visit_id'])->first();

            if (! $visit) {
                return ApiResponse::error(
                    'The linked visit has not synchronized yet.',
                    409,
                    null,
                    'VISIT_NOT_SYNCED',
                );
            }

            if (
                (int) $visit->user_id !== (int) $user->id
                || (int) $visit->customer_id !== (int) $customer->id
            ) {
                return ApiResponse::error(
                    'The linked visit does not belong to this customer and salesman.',
                    422,
                    null,
                    'INVALID_VISIT_LINK',
                );
            }
        }

        $productUuids = collect($validated['items'])
            ->pluck('product_id')
            ->unique()
            ->values();

        $products = Product::active()
            ->whereIn('uuid', $productUuids)
            ->get()
            ->keyBy('uuid');

        if ($products->count() !== $productUuids->count()) {
            throw ValidationException::withMessages([
                'items' => 'One or more selected products are invalid or inactive.',
            ]);
        }

        $device = $request->attributes->get('device');
        $pricingAt = $orderedAt->setTimezone($user->tenant->timezone);

        $order = DB::transaction(function () use (
            $validated,
            $user,
            $customer,
            $visit,
            $orderedAt,
            $products,
            $device,
            $pricing,
            $pricingAt,
        ): Order {
            $subtotal = 0.0;
            $discountTotal = 0.0;
            $currency = null;
            $priceListId = null;
            $preparedItems = [];

            foreach ($validated['items'] as $line) {
                $product = $products->get($line['product_id']);
                $quantity = round((float) $line['quantity'], 4);
                $discountPercent = round((float) ($line['discount_percent'] ?? 0), 4);
                $resolved = $pricing->price($customer, $product, $quantity, $pricingAt);

                if ($currency !== null && $currency !== $resolved['currency']) {
                    throw ValidationException::withMessages([
                        'items' => 'All order items must use the same currency.',
                    ]);
                }

                $currency ??= $resolved['currency'];
                $priceListId ??= $resolved['price_list_id'];

                $gross = round($quantity * $resolved['unit_price'], 4);
                $discount = round($gross * ($discountPercent / 100), 4);
                $lineTotal = round($gross - $discount, 4);

                $subtotal = round($subtotal + $gross, 4);
                $discountTotal = round($discountTotal + $discount, 4);

                $preparedItems[] = [
                    'product_id' => $product->id,
                    'product_sku' => $product->sku,
                    'product_name' => $product->name,
                    'unit' => $product->unit,
                    'quantity' => $quantity,
                    'unit_price' => $resolved['unit_price'],
                    'discount_percent' => $discountPercent,
                    'discount_amount' => $discount,
                    'line_total' => $lineTotal,
                ];
            }

            $grandTotal = round($subtotal - $discountTotal, 4);
            $clientEstimated = array_key_exists('client_estimated_total', $validated)
                ? round((float) $validated['client_estimated_total'], 4)
                : null;
            $pricingAdjusted = $clientEstimated !== null
                && abs($clientEstimated - $grandTotal) >= 0.01;

            $compactUuid = strtoupper(substr(
                str_replace('-', '', $validated['offline_uuid']),
                0,
                8
            ));

            $order = Order::create([
                'uuid' => $validated['offline_uuid'],
                'user_id' => $user->id,
                'salesman_id' => $user->salesman->id,
                'device_id' => $device->id,
                'customer_id' => $customer->id,
                'visit_id' => $visit?->id,
                'price_list_id' => $priceListId,
                'order_number' => 'ORD-'.$orderedAt->format('Ymd').'-'.$compactUuid,
                'ordered_at' => $orderedAt,
                'payment_type' => $validated['payment_type'],
                'status' => 'pending',
                'currency' => $currency ?? 'AFN',
                'subtotal' => $subtotal,
                'discount_total' => $discountTotal,
                'grand_total' => $grandTotal,
                'client_estimated_total' => $clientEstimated,
                'pricing_adjusted' => $pricingAdjusted,
                'notes' => $validated['notes'] ?? null,
            ]);

            $order->items()->createMany($preparedItems);

            return $order;
        });

        return ApiResponse::success(
            $this->payload($order->load($this->relations())),
            201,
        );
    }

    public function history(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('orders:view'), 403);

        $validated = $request->validate([
            'status' => ['nullable', Rule::in(Order::STATUSES)],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $page = Order::with($this->relations())
            ->where('user_id', $request->user()->id)
            ->when(
                $validated['status'] ?? null,
                fn ($query, $status) => $query->where('status', $status)
            )
            ->orderByDesc('ordered_at')
            ->paginate((int) ($validated['per_page'] ?? 30));

        return ApiResponse::success(
            collect($page->items())
                ->map(fn (Order $order) => $this->payload($order))
                ->values()
                ->all(),
            200,
            [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        );
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        abort_unless($request->user()->hasPermission('orders:view'), 403);
        abort_unless((int) $order->user_id === (int) $request->user()->id, 404);

        return ApiResponse::success(
            $this->payload($order->load($this->relations()))
        );
    }

    private function relations(): array
    {
        return ['customer', 'visit', 'priceList', 'items.product'];
    }

    private function payload(Order $order): array
    {
        return [
            'id' => $order->uuid,
            'order_number' => $order->order_number,
            'customer_id' => $order->customer?->uuid,
            'customer_name' => $order->customer?->name,
            'visit_id' => $order->visit?->uuid,
            'price_list_id' => $order->priceList?->uuid,
            'ordered_at' => $order->ordered_at?->toISOString(),
            'payment_type' => $order->payment_type,
            'status' => $order->status,
            'currency' => $order->currency,
            'subtotal' => (float) $order->subtotal,
            'discount_total' => (float) $order->discount_total,
            'grand_total' => (float) $order->grand_total,
            'client_estimated_total' => $order->client_estimated_total === null
                ? null
                : (float) $order->client_estimated_total,
            'pricing_adjusted' => $order->pricing_adjusted,
            'notes' => $order->notes,
            'status_note' => $order->status_note,
            'status_changed_at' => $order->status_changed_at?->toISOString(),
            'items' => $order->relationLoaded('items')
                ? $order->items->map(fn ($item) => [
                    'id' => $item->uuid,
                    'product_id' => $item->product?->uuid,
                    'sku' => $item->product_sku,
                    'name' => $item->product_name,
                    'unit' => $item->unit,
                    'quantity' => (float) $item->quantity,
                    'unit_price' => (float) $item->unit_price,
                    'discount_percent' => (float) $item->discount_percent,
                    'discount_amount' => (float) $item->discount_amount,
                    'line_total' => (float) $item->line_total,
                ])->values()->all()
                : [],
        ];
    }
}
