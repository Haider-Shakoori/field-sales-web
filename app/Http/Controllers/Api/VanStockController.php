<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SalesmanStockBalance;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VanStockController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('inventory:view'), 403);

        $user = $request->user()->loadMissing('salesman');
        abort_unless($user->salesman?->is_active, 403);

        $balances = SalesmanStockBalance::with('product')
            ->where('salesman_id', $user->salesman->id)
            ->whereHas('product', fn ($query) => $query->where('is_active', true))
            ->orderBy('product_id')
            ->get();

        return ApiResponse::success([
            'enabled' => (bool) $user->salesman->van_stock_enabled,
            'summary' => [
                'products' => $balances->count(),
                'sellable_quantity' => round((float) $balances->sum('sellable_quantity'), 4),
                'reserved_quantity' => round((float) $balances->sum('reserved_quantity'), 4),
                'available_quantity' => round(
                    (float) $balances->sum(
                        fn (SalesmanStockBalance $balance) => $balance->available_quantity
                    ),
                    4,
                ),
                'damaged_quantity' => round((float) $balances->sum('damaged_quantity'), 4),
            ],
            'items' => $balances->map(fn (SalesmanStockBalance $balance) => [
                'product_id' => $balance->product?->uuid,
                'sku' => $balance->product?->sku,
                'name' => $balance->product?->name,
                'unit' => $balance->product?->unit,
                'sellable_quantity' => (float) $balance->sellable_quantity,
                'reserved_quantity' => (float) $balance->reserved_quantity,
                'available_quantity' => $balance->available_quantity,
                'damaged_quantity' => (float) $balance->damaged_quantity,
                'updated_at' => $balance->updated_at?->toISOString(),
            ])->values()->all(),
        ]);
    }
}
