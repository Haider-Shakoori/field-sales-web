<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePriceListRequest;
use App\Http\Requests\UpdatePriceListRequest;
use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PriceListController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', PriceList::class);

        $query = PriceList::query()->withCount('items');

        if (TenantContext::hasContext()) {
            $query->where('tenant_id', TenantContext::currentId());
        }

        if ($search = $request->string('search')->trim()->toString()) {
            $query->where('name', 'like', "%{$search}%");
        }

        if ($status = $request->string('status')->toString()) {
            $query->where('is_active', $status === 'active');
        }

        $priceLists = $query->orderByDesc('is_default')->orderBy('name')->paginate(20)->withQueryString();

        return view('pages.price-lists.index', compact('priceLists'));
    }

    public function create(): View
    {
        $this->authorize('create', PriceList::class);

        return view('pages.price-lists.create');
    }

    public function store(StorePriceListRequest $request): RedirectResponse
    {
        $tenantId = TenantContext::currentId();
        abort_if($tenantId === null, 403, 'A tenant context is required to create price lists.');

        $data = $request->validated();
        $isDefault = (bool) ($data['is_default'] ?? false);

        $priceList = DB::transaction(function () use ($tenantId, $data, $isDefault) {
            if ($isDefault) {
                $this->clearDefaults();
            }

            return PriceList::create([
                'tenant_id' => $tenantId,
                'name' => $data['name'],
                'is_default' => $isDefault,
                'is_active' => $data['is_active'] ?? true,
            ]);
        });

        return redirect()->route('price-lists.show', $priceList)->with('status', 'Price list created.');
    }

    public function show(PriceList $priceList): View
    {
        $this->authorize('view', $priceList);

        $priceList->load(['items.product']);

        $products = Product::query()
            ->where('tenant_id', $priceList->tenant_id)
            ->active()
            ->whereNotIn('id', $priceList->items->pluck('product_id'))
            ->orderBy('name')
            ->get();

        return view('pages.price-lists.show', compact('priceList', 'products'));
    }

    public function edit(PriceList $priceList): View
    {
        $this->authorize('update', $priceList);

        return view('pages.price-lists.edit', compact('priceList'));
    }

    public function update(UpdatePriceListRequest $request, PriceList $priceList): RedirectResponse
    {
        abort_if(TenantContext::currentId() === null || $priceList->tenant_id !== TenantContext::currentId(), 403);

        $data = $request->validated();
        $isDefault = $request->boolean('is_default');
        $isActive = $request->boolean('is_active');

        if ($priceList->is_default && ! $isDefault) {
            return back()->withErrors([
                'is_default' => 'This is the tenant\'s default price list. Assign a new default before removing it.',
            ])->withInput();
        }

        DB::transaction(function () use ($priceList, $data, $isDefault, $isActive) {
            if ($isDefault && ! $priceList->is_default) {
                $this->clearDefaults();
                $priceList->update([
                    'name' => $data['name'],
                    'is_default' => true,
                    'is_active' => $isActive,
                ]);
            } else {
                $priceList->update([
                    'name' => $data['name'],
                    'is_default' => $isDefault,
                    'is_active' => $isActive,
                ]);
            }
        });

        return redirect()->route('price-lists.show', $priceList)->with('status', 'Price list updated.');
    }

    public function deactivate(PriceList $priceList): RedirectResponse
    {
        $this->authorize('deactivate', $priceList);

        abort_if(TenantContext::currentId() === null || $priceList->tenant_id !== TenantContext::currentId(), 403);

        if ($priceList->is_default && $priceList->is_active) {
            return back()->withErrors([
                'is_active' => 'The default price list cannot be deactivated. Promote another list first.',
            ]);
        }

        $priceList->update(['is_active' => ! $priceList->is_active]);

        return back()->with('status', $priceList->is_active ? 'Price list activated.' : 'Price list deactivated.');
    }

    // -------------------------------------------------------------------------
    // Item management
    // -------------------------------------------------------------------------

    public function storeItem(PriceList $priceList, Request $request): RedirectResponse
    {
        $this->authorize('managePrices', $priceList);

        abort_if(TenantContext::currentId() === null || $priceList->tenant_id !== TenantContext::currentId(), 403);

        $tenantId = TenantContext::currentId();

        $validated = $request->validate([
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)->whereNull('deleted_at'))],
            'price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
        ]);

        $existingItem = PriceListItem::query()
            ->where('tenant_id', $tenantId)
            ->where('price_list_id', $priceList->id)
            ->where('product_id', $validated['product_id'])
            ->first();

        if ($existingItem) {
            return back()->withErrors([
                'product_id' => 'This product is already priced in this list.',
            ])->withInput();
        }

        PriceListItem::create([
            'tenant_id' => $tenantId,
            'price_list_id' => $priceList->id,
            'product_id' => $validated['product_id'],
            'price' => $validated['price'],
        ]);

        return redirect()->route('price-lists.show', $priceList)->with('status', 'Item added to price list.');
    }

    public function updateItem(PriceListItem $priceListItem, Request $request): RedirectResponse
    {
        $this->authorize('update', $priceListItem);

        abort_if(TenantContext::currentId() === null || $priceListItem->tenant_id !== TenantContext::currentId(), 403);

        $validated = $request->validate([
            'price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
        ]);

        $priceListItem->update(['price' => $validated['price']]);

        return back()->with('status', 'Price list item updated.');
    }

    public function destroyItem(PriceListItem $priceListItem): RedirectResponse
    {
        $this->authorize('delete', $priceListItem);

        abort_if(TenantContext::currentId() === null || $priceListItem->tenant_id !== TenantContext::currentId(), 403);

        $priceListItem->delete();

        return back()->with('status', 'Item removed from price list.');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function clearDefaults(): void
    {
        PriceList::query()
            ->where('tenant_id', TenantContext::currentId())
            ->where('is_default', true)
            ->update(['is_default' => false]);
    }
}
