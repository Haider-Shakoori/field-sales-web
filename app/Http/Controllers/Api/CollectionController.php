<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Collection;
use App\Models\Customer;
use App\Models\CustomerVisit;
use App\Models\SalesOrder;
use App\Support\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class CollectionController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user()->load('salesman');
        $query = Collection::with('customer:id,uuid,code,name,shop_name')
            ->where('tenant_id', $user->tenant_id);

        if ($user->role === 'salesman' && $user->salesman) {
            $query->where('salesman_id', $user->salesman->id);
        }

        return ApiResponse::success($query->latest('collected_at')->limit(200)->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'offline_uuid' => ['required', 'uuid'],
            'customer_uuid' => ['required', 'uuid'],
            'order_uuid' => ['nullable', 'uuid'],
            'visit_uuid' => ['nullable', 'uuid'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'payment_method' => ['required', 'in:cash,bank_transfer,mobile_money,cheque,other'],
            'receipt_number' => ['nullable', 'string', 'max:80'],
            'manual_reference' => ['nullable', 'string', 'max:100'],
            'collected_at' => ['required', 'date'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'between:0,200'],
            'receipt_photo_path' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $user = $request->user()->load('salesman');
        $existing = Collection::where('tenant_id', $user->tenant_id)
            ->where('uuid', $validated['offline_uuid'])
            ->first();

        if ($existing) {
            return ApiResponse::success($existing, 200);
        }

        $customer = Customer::where('tenant_id', $user->tenant_id)
            ->where('uuid', $validated['customer_uuid'])
            ->first();

        if (! $customer) {
            return ApiResponse::error('Customer not found.', 404, null, 'CUSTOMER_NOT_FOUND');
        }

        $collectedAt = CarbonImmutable::parse($validated['collected_at'])->utc();
        if ($collectedAt->gt(CarbonImmutable::now('UTC')->addMinutes(5))) {
            return ApiResponse::error('Collection time is too far in the future.', 422, null, 'INVALID_EVENT_TIME');
        }

        $orderId = ! empty($validated['order_uuid'])
            ? SalesOrder::where('tenant_id', $user->tenant_id)->where('uuid', $validated['order_uuid'])->value('id')
            : null;

        $visitId = ! empty($validated['visit_uuid'])
            ? CustomerVisit::where('tenant_id', $user->tenant_id)->where('uuid', $validated['visit_uuid'])->value('id')
            : null;

        $row = Collection::create([
            'uuid' => $validated['offline_uuid'],
            'tenant_id' => $user->tenant_id,
            'customer_id' => $customer->id,
            'sales_order_id' => $orderId,
            'user_id' => $user->id,
            'salesman_id' => $user->salesman->id,
            'visit_id' => $visitId,
            'amount' => $validated['amount'],
            'currency' => strtoupper($validated['currency'] ?? 'AFN'),
            'payment_method' => $validated['payment_method'],
            'receipt_number' => $validated['receipt_number'] ?? null,
            'manual_reference' => $validated['manual_reference'] ?? null,
            'collected_at' => $collectedAt,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'accuracy' => $validated['accuracy'] ?? null,
            'receipt_photo_path' => $validated['receipt_photo_path'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return ApiResponse::success($row, 201);
    }
}
