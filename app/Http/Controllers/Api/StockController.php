<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SalesmanStockBalance;
use App\Services\StockSettingsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockController extends Controller
{
    public function mine(
        Request $request,
        StockSettingsService $settings,
    ): JsonResponse {
        abort_unless($request->user()->hasPermission('stock:view'), 403);

        $user = $request->user()->loadMissing('salesman');
        abort_unless($user->salesman?->is_active, 403);

        $rows = SalesmanStockBalance::with('product')
            ->where('salesman_id', $user->salesman->id)
            ->orderBy('product_id')
            ->get()
            ->map(fn (SalesmanStockBalance $balance) => [
                'product_id' => $balance->product?->uuid,
                'sku' => $balance->product?->sku,
                'name' => $balance->product?->name,
                'unit' => $balance->product?->unit,
                'sellable_qty' => (float) $balance->sellable_qty,
                'damaged_qty' => (float) $balance->damaged_qty,
                'updated_at' => $balance->updated_at?->toISOString(),
            ])
            ->values()
            ->all();

        return ApiResponse::success([
            'enabled' => $settings->enabled(
                $request->user()->loadMissing('tenant')->tenant,
            ),
            'stock' => $rows,
        ]);
    }
}
