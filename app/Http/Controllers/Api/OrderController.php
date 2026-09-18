<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerVisit;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Support\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user()->load('salesman');
        $query = SalesOrder::with(['customer:id,uuid,code,name,shop_name', 'items.product:id,uuid,sku,name,unit'])
            ->where('tenant_id', $user->tenant_id);

        if ($user->role === 'salesman' && $user->salesman) {
            $query->where('salesman_id', $user->salesman->id);
        }

        return ApiResponse::success($query->latest('ordered_at')->limit(200)->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'offline_uuid' => ['required', 'uuid'],
            'customer_uuid' => ['required', 'uuid'],
            'visit_uuid' => ['nullable', 'uuid'],
            'ordered_at' => ['required', 'date'],
            'currency' => ['nullable', 'string', 'size:3'],
            'cash_amount' => ['nullable', 'numeric', 'min:0'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.product_uuid' => ['required', 'uuid'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $user = $request->user()->load('salesman');
        if (! $user->salesman) {
            return ApiResponse::error('No salesman profile is linked to this user.', 422, null, 'SALESMAN_REQUIRED');
        }

        $existing = SalesOrder::where('tenant_id', $user->tenant_id)
            ->where('uuid', $validated['offline_uuid'])
            ->with('items.product')
            ->first();

        if ($existing) {
            if ((int) $existing->user_id !== (int) $user->id) {
                return ApiResponse::error('Order identifier conflict.', 409, null, 'OFFLINE_UUID_CONFLICT');
            }
            return ApiResponse::success($existing, 200);
        }

        $customer = Customer::where('tenant_id', $user->tenant_id)
            ->where('uuid', $validated['customer_uuid'])
            ->where('is_active', true)
            ->first();

        if (! $customer) {
            return ApiResponse::error('Customer not found.', 404, null, 'CUSTOMER_NOT_FOUND');
        }

        $orderedAt = CarbonImmutable::parse($validated['ordered_at'])->utc();
        if ($orderedAt->gt(CarbonImmutable::now('UTC')->addMinutes(5))) {
            return ApiResponse::error('Order time is too far in the future.', 422, null, 'INVALID_EVENT_TIME');
        }

        $visitId = null;
        if (! empty($validated['visit_uuid'])) {
            $visitId = CustomerVisit::where('tenant_id', $user->tenant_id)
                ->where('uuid', $validated['visit_uuid'])
                ->value('id');
        }

        $productUuids = collect($validated['items'])->pluck('product_uuid')->unique();
        $products = Product::where('tenant_id', $user->tenant_id)
            ->whereIn('uuid', $productUuids)
            ->where('is_active', true)
            ->get()
            ->keyBy('uuid');

        if ($products->count() !== $productUuids->count()) {
            return ApiResponse::error('One or more products are unavailable.', 422, null, 'PRODUCT_UNAVAILABLE');
        }

        $order = DB::transaction(function () use ($validated, $user, $customer, $orderedAt, $visitId, $products) {
            $subtotal = 0.0;
            $lineRows = [];

            foreach ($validated['items'] as $item) {
                $product = $products->get($item['product_uuid']);
                $quantity = (float) $item['quantity'];
                $unitPrice = $this->priceFor($customer->price_list_id, $product, $quantity);
                $discount = (float) ($item['discount_amount'] ?? 0);
                $lineTotal = max(0, ($quantity * $unitPrice) - $discount);
                $subtotal += $lineTotal;
                $lineRows[] = compact('product', 'quantity', 'unitPrice', 'discount', 'lineTotal');
            }

            $orderDiscount = min((float) ($validated['discount_amount'] ?? 0), $subtotal);
            $total = max(0, $subtotal - $orderDiscount);
            $cash = min((float) ($validated['cash_amount'] ?? 0), $total);
            $credit = $total - $cash;

            $order = SalesOrder::create([
                'uuid' => $validated['offline_uuid'],
                'tenant_id' => $user->tenant_id,
                'customer_id' => $customer->id,
                'user_id' => $user->id,
                'salesman_id' => $user->salesman->id,
                'visit_id' => $visitId,
                'order_number' => 'SO-'.now()->format('Ymd').'-'.strtoupper(substr(str_replace('-', '', $validated['offline_uuid']), 0, 8)),
                'status' => 'submitted',
                'ordered_at' => $orderedAt,
                'currency' => strtoupper($validated['currency'] ?? 'AFN'),
                'subtotal' => $subtotal,
                'discount_amount' => $orderDiscount,
                'total_amount' => $total,
                'cash_amount' => $cash,
                'credit_amount' => $credit,
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($lineRows as $row) {
                $order->items()->create([
                    'tenant_id' => $user->tenant_id,
                    'product_id' => $row['product']->id,
                    'quantity' => $row['quantity'],
                    'unit_price' => $row['unitPrice'],
                    'discount_amount' => $row['discount'],
                    'line_total' => $row['lineTotal'],
                ]);
            }

            return $order;
        });

        return ApiResponse::success($order->load('items.product'), 201);
    }

    public function show(Request $request, SalesOrder $order)
    {
        abort_unless((int) $order->tenant_id === (int) $request->user()->tenant_id, 404);

        return ApiResponse::success($order->load(['customer', 'items.product']));
    }

    private function priceFor(?int $priceListId, Product $product, float $quantity): float
    {
        if ($priceListId) {
            $price = PriceListItem::where('price_list_id', $priceListId)
                ->where('product_id', $product->id)
                ->where('min_quantity', '<=', $quantity)
                ->orderByDesc('min_quantity')
                ->value('price');

            if ($price !== null) {
                return (float) $price;
            }
        }

        return (float) $product->base_price;
    }
}
