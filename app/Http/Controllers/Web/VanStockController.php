<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Salesman;
use App\Models\SalesmanStockBalance;
use App\Models\SalesmanStockMovement;
use App\Services\AuditLogger;
use App\Services\VanStockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class VanStockController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'salesman' => ['nullable', 'uuid'],
        ]);

        $salesmen = Salesman::active()
            ->with('user')
            ->orderBy('employee_code')
            ->get();

        $selectedSalesman = null;

        if (! empty($validated['salesman'])) {
            $selectedSalesman = Salesman::with('user')
                ->where('uuid', $validated['salesman'])
                ->firstOrFail();
        }

        $balances = collect();
        $movements = collect();

        if ($selectedSalesman) {
            $balances = SalesmanStockBalance::with('product')
                ->where('salesman_id', $selectedSalesman->id)
                ->get()
                ->sortBy(fn (SalesmanStockBalance $balance) => $balance->product?->name)
                ->values();

            $movements = SalesmanStockMovement::with([
                'product',
                'order',
                'customerReturn',
                'creator',
            ])
                ->where('salesman_id', $selectedSalesman->id)
                ->latest('occurred_at')
                ->limit(100)
                ->get();
        }

        return view('admin.van-stock.index', [
            'salesmen' => $salesmen,
            'selectedSalesman' => $selectedSalesman,
            'products' => Product::active()->orderBy('name')->get(),
            'balances' => $balances,
            'movements' => $movements,
        ]);
    }

    public function updateControl(
        Request $request,
        Salesman $salesman,
        AuditLogger $audit,
    ): RedirectResponse {
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        $enabled = (bool) $validated['enabled'];

        if (! $enabled) {
            $hasReservations = SalesmanStockBalance::query()
                ->where('salesman_id', $salesman->id)
                ->where('reserved_quantity', '>', 0)
                ->exists();

            if ($hasReservations) {
                throw ValidationException::withMessages([
                    'enabled' => 'Van stock cannot be disabled while pending orders still reserve stock.',
                ]);
            }
        }

        $before = ['van_stock_enabled' => (bool) $salesman->van_stock_enabled];

        $salesman->update(['van_stock_enabled' => $enabled]);

        $audit->record('van_stock.control_updated', $salesman, $before, [
            'van_stock_enabled' => $enabled,
        ]);

        return back()->with(
            'status',
            $enabled
                ? __('Van stock control enabled.')
                : __('Van stock control disabled.'),
        );
    }

    public function adjust(
        Request $request,
        VanStockService $stock,
        AuditLogger $audit,
    ): RedirectResponse {
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'salesman_id' => [
                'required',
                'integer',
                Rule::exists('salesmen', 'id')->where('tenant_id', $tenantId),
            ],
            'product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')->where('tenant_id', $tenantId),
            ],
            'bucket' => ['required', Rule::in(['sellable', 'damaged'])],
            'quantity' => ['required', 'numeric', 'not_in:0'],
            'note' => ['required', 'string', 'max:1000'],
        ]);

        $salesman = Salesman::findOrFail($validated['salesman_id']);
        $product = Product::findOrFail($validated['product_id']);

        $balance = $stock->adjust(
            $salesman,
            $product,
            $validated['bucket'],
            round((float) $validated['quantity'], 4),
            $request->user(),
            trim($validated['note']),
        );

        $audit->record('van_stock.adjusted', $balance, [], [
            'salesman_id' => $salesman->id,
            'product_id' => $product->id,
            'bucket' => $validated['bucket'],
            'quantity' => round((float) $validated['quantity'], 4),
            'note' => trim($validated['note']),
        ]);

        return redirect()
            ->route('admin.van-stock.index', ['salesman' => $salesman->uuid])
            ->with('status', __('Van stock adjusted.'));
    }
}
