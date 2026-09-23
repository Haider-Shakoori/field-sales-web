<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerVisit;
use App\Models\Order;
use App\Models\Product;
use App\Models\SalesReturn;
use App\Support\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SalesReturnController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('returns:view'), 403);

        $validated = $request->validate([
            'offline_uuid' => ['required', 'uuid'],
            'customer_id' => ['required', 'uuid'],
            'visit_id' => ['nullable', 'uuid'],
            'order_id' => ['nullable', 'uuid'],
            'returned_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['required', 'uuid'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.condition' => ['required', Rule::in(SalesReturn::CONDITIONS)],
            'items.*.reason' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user()->loadMissing(['salesman', 'tenant']);
        abort_unless($user->salesman?->is_active, 403);

        $existing = SalesReturn::where('uuid', $validated['offline_uuid'])->first();

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
                (int) $visit->salesman_id !== (int) $user->salesman->id
                || (int) $visit->customer_id !== (int) $customer->id
            ) {
                throw ValidationException::withMessages([
                    'visit_id' => 'The visit does not match this salesman and customer.',
                ]);
            }
        }

        $order = null;
        if (! empty($validated['order_id'])) {
            $order = Order::where('uuid', $validated['order_id'])
                ->where('customer_id', $customer->id)
                ->where('salesman_id', $user->salesman->id)
                ->firstOrFail();
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
                'items' => 'One or more return products are invalid or inactive.',
            ]);
        }

        $device = $request->attributes->get('device');

        $return = DB::transaction(function () use (
            $validated,
            $user,
            $customer,
            $visit,
            $order,
            $products,
            $device,
            $returnedAt,
        ): SalesReturn {
            $compactUuid = strtoupper(substr(
                str_replace('-', '', $validated['offline_uuid']),
                0,
                8,
            ));

            $return = SalesReturn::create([
                'uuid' => $validated['offline_uuid'],
                'user_id' => $user->id,
                'salesman_id' => $user->salesman->id,
                'device_id' => $device?->id,
                'customer_id' => $customer->id,
                'visit_id' => $visit?->id,
                'order_id' => $order?->id,
                'return_number' => 'RET-'.$returnedAt->format('Ymd').'-'.$compactUuid,
                'returned_at' => $returnedAt,
                'status' => 'pending',
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($validated['items'] as $item) {
                $return->items()->create([
                    'product_id' => $products->get($item['product_id'])->id,
                    'quantity' => round((float) $item['quantity'], 4),
                    'condition' => $item['condition'],
                    'reason' => $item['reason'] ?? null,
                ]);
            }

            return $return;
        });

        return ApiResponse::success(
            $this->payload($return->load($this->relations())),
            201,
        );
    }

    public function history(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('returns:view'), 403);

        $user = $request->user()->loadMissing('salesman');
        abort_unless($user->salesman?->is_active, 403);

        $rows = SalesReturn::with($this->relations())
            ->where('salesman_id', $user->salesman->id)
            ->orderByDesc('returned_at')
            ->limit(100)
            ->get()
            ->map(fn (SalesReturn $return) => $this->payload($return))
            ->values()
            ->all();

        return ApiResponse::success(['returns' => $rows]);
    }

    private function relations(): array
    {
        return ['customer', 'visit', 'order', 'items.product'];
    }

    private function payload(SalesReturn $return): array
    {
        return [
            'id' => $return->uuid,
            'return_number' => $return->return_number,
            'customer_id' => $return->customer?->uuid,
            'customer_name' => $return->customer?->name,
            'visit_id' => $return->visit?->uuid,
            'order_id' => $return->order?->uuid,
            'returned_at' => $return->returned_at?->toISOString(),
            'status' => $return->status,
            'notes' => $return->notes,
            'status_note' => $return->status_note,
            'items' => $return->items->map(fn ($item) => [
                'id' => $item->uuid,
                'product_id' => $item->product?->uuid,
                'sku' => $item->product?->sku,
                'name' => $item->product?->name,
                'unit' => $item->product?->unit,
                'quantity' => (float) $item->quantity,
                'condition' => $item->condition,
                'reason' => $item->reason,
            ])->values()->all(),
        ];
    }
}
