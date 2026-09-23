<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Salesman;
use App\Models\SalesmanStockBalance;
use App\Models\StockIssue;
use App\Services\AuditLogger;
use App\Services\SalesmanStockService;
use App\Services\StockSettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StockController extends Controller
{
    public function index(
        Request $request,
        StockSettingsService $settings,
    ): View {
        $salesmanUuid = trim((string) $request->string('salesman'));
        $salesman = $salesmanUuid !== ''
            ? Salesman::with('user')->where('uuid', $salesmanUuid)->firstOrFail()
            : null;

        $balances = collect();

        if ($salesman) {
            $balances = SalesmanStockBalance::with('product')
                ->where('salesman_id', $salesman->id)
                ->orderBy('product_id')
                ->get();
        }

        return view('admin.stock.index', [
            'salesmen' => Salesman::active()->with('user')->orderBy('employee_code')->get(),
            'selectedSalesman' => $salesman,
            'balances' => $balances,
            'recentIssues' => StockIssue::with(['salesman.user', 'items.product'])
                ->orderByDesc('issued_at')
                ->limit(20)
                ->get(),
            'products' => Product::active()->orderBy('name')->get(),
            'stockControlEnabled' => $settings->enabled(
                $request->user()->loadMissing('tenant')->tenant,
            ),
        ]);
    }

    public function updateSettings(
        Request $request,
        StockSettingsService $settings,
        AuditLogger $audit,
    ): RedirectResponse {
        $validated = $request->validate([
            'salesman_stock_enabled' => ['nullable', 'boolean'],
        ]);

        $enabled = $request->boolean('salesman_stock_enabled');
        $tenant = $request->user()->loadMissing('tenant')->tenant;
        $before = ['salesman_stock_enabled' => $settings->enabled($tenant)];

        $settings->setEnabled($tenant, $enabled);

        $audit->record('stock.settings_updated', $tenant, $before, [
            'salesman_stock_enabled' => $enabled,
        ]);

        return back()->with('status', __('Stock settings updated.'));
    }

    public function issue(
        Request $request,
        SalesmanStockService $stock,
        AuditLogger $audit,
    ): RedirectResponse {
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'salesman_id' => [
                'required',
                'uuid',
                Rule::exists('salesmen', 'uuid')->where('tenant_id', $tenantId),
            ],
            'issued_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['required', 'uuid', 'distinct'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
        ]);

        $salesman = Salesman::active()
            ->where('uuid', $validated['salesman_id'])
            ->firstOrFail();

        $user = $request->user()->loadMissing('tenant');
        $timezone = $user->tenant?->timezone ?: config('app.timezone', 'UTC');

        $issue = $stock->issue(
            $salesman,
            $validated['items'],
            $user,
            CarbonImmutable::parse($validated['issued_at'], $timezone)->utc(),
            $validated['notes'] ?? null,
        );

        $audit->record('stock.issued', $issue, [], [
            'salesman_id' => $salesman->id,
            'issue_number' => $issue->issue_number,
            'item_count' => $issue->items->count(),
        ]);

        return redirect()
            ->route('admin.stock.index', ['salesman' => $salesman->uuid])
            ->with('status', __('Stock issued.'));
    }
}
