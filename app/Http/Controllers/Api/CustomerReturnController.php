<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerReturn;
use App\Models\CustomerVisit;
use App\Models\Order;
use App\Models\Product;
use App\Services\VanStockService;
use App\Support\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CustomerReturnController extends Controller
{
    public function store(
        Request $request,
        VanStockService $stock,
    ): JsonResponse {
        abort_unless($request->user()->hasPermission('inventory:view'), 403);

        $validated = $request->validate([
            'offline_uuid' => ['required', 'uuid'],
            'customer_id' => ['required', 'uuid'],
            'visit_id' => ['nullable', 'uuid'],
            'order_id' => ['nullable', 'uuid'],
            'returned_at' => ['required', 'date'],
            'reason' => ['required', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['required', 'uuid'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.condition' => ['required', Rule::in(CustomerReturn::CONDITIONS)],
            'items.*.reason' => ['nullable', 'string', 'max:120'],
        ]);

        $user = $request->user()->loadMissing(['salesman', 'tenant']);
        abort_unless($user->salesman?->is_active, 403);

        $existing = CustomerReturn::where('uuid', $validated['offline_uuid'])->first();

        if ($existing) {
            abort_unless((int) $existing->user_id === (int) $user->id, 409);

            return ApiResponse::success(
                $this->payload($existing->load($this->relations()))
            );
        }

        $returnedAt = CarbonImmutable::parse($validated['returned_at'])->utc();

        if ($returnedAt->gt(now()->addMinutes(5))) {
            throw ValidationException::withMessages([
                'returned_at' => 'Return time is too far in the future.',
            ]);
        }

        $customer = Customer::where('uuid', $validated['customer_id'])
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
                throw ValidationException::withMessages([
                    'visit_id' => 'The linked visit does not belong to this customer and salesman.',
                ]);
            }
        }

        $order = null;

        if (! empty($validated['order_id'])) {
            $order = Order::with('items')
                ->where('uuid', $validated['order_id'])
                ->firstOrFail();

            if (
                (int) $order->salesman_id !== (int) $user->salesman->id
                || (int) $order->customer_id !== (int) $customer->id
                || $order->status !== 'approved'
            ) {
                throw ValidationException::withMessages([
                    'order_id' => 'Returns can only reference an approved order for this customer and salesman.',
                ]);
            }
        }

        $productUuids = collect($validated['items'])
            ->pluck('product_id')
            ->unique()
            ->values();

        $products = Product::whereIn('uuid', $productUuids)
            ->get()
            ->keyBy('uuid');

        if ($products->count() !== $productUuids->count()) {
            throw ValidationException::withMessages([
                'items' => 'One or more returned products are invalid.',
            ]);
        }

        $groupedItems = collect($validated['items'])
            ->groupBy(fn (array $line) => $line['product_id'].'|'.$line['condition'])
            ->map(function ($lines): array {
                $first = $lines->first();

                return [
                    'product_id' => $first['product_id'],
                    'condition' => $first['condition'],
                    'quantity' => round((float) $lines->sum('quantity'), 4),
                    'reason' => $lines->pluck('reason')
                        ->filter()
                        ->unique()
                        ->implode('; ') ?: null,
                ];
            })
            ->values();

        if ($order) {
            foreach ($groupedItems->groupBy('product_id') as $productUuid => $lines) {
                $product = $products->get($productUuid);
                $remaining = $stock->remainingReturnable($order, $product);
                $quantity = round((float) $lines->sum('quantity'), 4);

                if ($remaining + 0.0001 < $quantity) {
                    throw ValidationException::withMessages([
                        'items' => sprintf(
                            'Only %.4f %s of %s remains returnable for this order.',
                            $remaining,
                            $product->unit,
                            $product->name,
                        ),
                    ]);
                }
            }
        }

        $device = $request->attributes->get('device');

        $customerReturn = DB::transaction(function () use (
            $validated,
            $user,
            $device,
            $customer,
            $visit,
            $order,
            $returnedAt,
            $products,
            $groupedItems,
        ): CustomerReturn {
            $compactUuid = strtoupper(substr(
                str_replace('-', '', $validated['offline_uuid']),
                0,
                8,
            ));

            $customerReturn = CustomerReturn::create([
                'uuid' => $validated['offline_uuid'],
                'user_id' => $user->id,
                'salesman_id' => $user->salesman->id,
                'device_id' => $device->id,
                'customer_id' => $customer->id,
                'visit_id' => $visit?->id,
                'order_id' => $order?->id,
                'return_number' => 'RET-'.$returnedAt->format('Ymd').'-'.$compactUuid,
                'returned_at' => $returnedAt,
                'status' => 'pending',
                'reason' => trim($validated['reason']),
                'notes' => $validated['notes'] ?? null,
            ]);

            $customerReturn->items()->createMany(
                $groupedItems
                    ->map(function (array $line) use ($products): array {
                        $product = $products->get($line['product_id']);

                        return [
                            'product_id' => $product->id,
                            'product_sku' => $product->sku,
                            'product_name' => $product->name,
                            'unit' => $product->unit,
                            'quantity' => round((float) $line['quantity'], 4),
                            'condition' => $line['condition'],
                            'reason' => $line['reason'] ?? null,
                        ];
                    })
                    ->all()
            );

            return $customerReturn;
        });

        return ApiResponse::success(
            $this->payload($customerReturn->load($this->relations())),
            201,
        );
    }

    public function history(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('inventory:view'), 403);

        $validated = $request->validate([
            'status' => ['nullable', Rule::in(CustomerReturn::STATUSES)],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $page = CustomerReturn::with($this->relations())
            ->where('user_id', $request->user()->id)
            ->when(
                $validated['status'] ?? null,
                fn ($query, $status) => $query->where('status', $status)
            )
            ->orderByDesc('returned_at')
            ->paginate((int) ($validated['per_page'] ?? 30));

        return ApiResponse::success(
            collect($page->items())
                ->map(fn (CustomerReturn $return) => $this->payload($return))
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

    public function show(Request $request, CustomerReturn $customerReturn): JsonResponse
    {
        abort_unless($request->user()->hasPermission('inventory:view'), 403);
        abort_unless(
            (int) $customerReturn->user_id === (int) $request->user()->id,
            404,
        );

        return ApiResponse::success(
            $this->payload($customerReturn->load($this->relations()))
        );
    }

    private function relations(): array
    {
        return ['customer', 'visit', 'order', 'items.product'];
    }

    private function payload(CustomerReturn $return): array
    {
        return [
            'id' => $return->uuid,
            'return_number' => $return->return_number,
            'customer_id' => $return->customer?->uuid,
            'customer_name' => $return->customer?->name,
            'visit_id' => $return->visit?->uuid,
            'order_id' => $return->order?->uuid,
            'order_number' => $return->order?->order_number,
            'returned_at' => $return->returned_at?->toISOString(),
            'status' => $return->status,
            'reason' => $return->reason,
            'notes' => $return->notes,
            'status_note' => $return->status_note,
            'items' => $return->items->map(fn ($item) => [
                'id' => $item->uuid,
                'product_id' => $item->product?->uuid,
                'sku' => $item->product_sku,
                'name' => $item->product_name,
                'unit' => $item->unit,
                'quantity' => (float) $item->quantity,
                'condition' => $item->condition,
                'reason' => $item->reason,
            ])->values()->all(),
        ];
    }
}
