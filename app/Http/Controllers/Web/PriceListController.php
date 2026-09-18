<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePriceListRequest;
use App\Http\Requests\UpdatePriceListRequest;
use App\Models\PriceList;
use App\Models\Product;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PriceListController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', PriceList::class);

        return view('admin.price-lists.index', [
            'priceLists' => PriceList::withCount(['items', 'customers'])
                ->orderBy('name')
                ->paginate(30),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', PriceList::class);

        return view('admin.price-lists.create');
    }

    public function store(StorePriceListRequest $request, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validated();

        $priceList = PriceList::create([
            ...$validated,
            'code' => strtoupper($validated['code']),
            'currency' => strtoupper($validated['currency']),
        ]);

        $audit->record('price_list.created', $priceList, [], $this->auditValues($priceList));

        return redirect()
            ->route('admin.price-lists.show', $priceList)
            ->with('status', 'Price list created.');
    }

    public function show(PriceList $priceList): View
    {
        Gate::authorize('view', $priceList);

        return view('admin.price-lists.show', [
            'priceList' => $priceList->load(['items.product']),
            'products' => Product::active()->orderBy('name')->get(),
        ]);
    }

    public function edit(PriceList $priceList): View
    {
        Gate::authorize('update', $priceList);

        return view('admin.price-lists.edit', compact('priceList'));
    }

    public function update(
        UpdatePriceListRequest $request,
        PriceList $priceList,
        AuditLogger $audit,
    ): RedirectResponse {
        $before = $this->auditValues($priceList);
        $validated = $request->validated();

        $priceList->update([
            ...$validated,
            'code' => strtoupper($validated['code']),
            'currency' => strtoupper($validated['currency']),
        ]);

        $audit->record('price_list.updated', $priceList, $before, $this->auditValues($priceList));

        return redirect()
            ->route('admin.price-lists.show', $priceList)
            ->with('status', 'Price list updated.');
    }

    public function destroy(PriceList $priceList, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('delete', $priceList);

        if ($priceList->customers()->exists() || $priceList->items()->exists()) {
            throw ValidationException::withMessages([
                'price_list' => 'This price list has customers or price tiers. Remove references or deactivate it.',
            ]);
        }

        $before = $this->auditValues($priceList);
        $audit->record('price_list.deleted', $priceList, $before);
        $priceList->delete();

        return redirect()
            ->route('admin.price-lists.index')
            ->with('status', 'Price list deleted.');
    }

    private function auditValues(PriceList $priceList): array
    {
        return [
            'code' => $priceList->code,
            'name' => $priceList->name,
            'currency' => $priceList->currency,
            'effective_from' => $priceList->effective_from?->toDateString(),
            'effective_to' => $priceList->effective_to?->toDateString(),
            'is_active' => $priceList->is_active,
        ];
    }
}
