<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Product::class);

        $query = Product::query()->withCount('priceListItems');

        if (TenantContext::hasContext()) {
            $query->where('tenant_id', TenantContext::currentId());
        }

        if ($search = $request->string('search')->trim()->toString()) {
            $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('sku', 'like', "%{$search}%"));
        }

        if ($category = $request->string('category')->toString()) {
            $query->where('category', $category);
        }

        if ($status = $request->string('status')->toString()) {
            $query->where('is_active', $status === 'active');
        }

        $products = $query->orderBy('name')->paginate(20)->withQueryString();

        $categories = Product::query()
            ->where('tenant_id', TenantContext::currentId())
            ->whereNotNull('category')
            ->distinct()
            ->pluck('category')
            ->sort()
            ->values();

        return view('pages.products.index', compact('products', 'categories'));
    }

    public function create(): View
    {
        $this->authorize('create', Product::class);

        return view('pages.products.create');
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $tenantId = TenantContext::currentId();
        abort_if($tenantId === null, 403, 'A tenant context is required to create products.');

        $data = $request->validated();

        $product = Product::create([
            'tenant_id' => $tenantId,
            'name' => $data['name'],
            'sku' => $data['sku'],
            'unit' => $data['unit'],
            'price' => $data['price'],
            'is_active' => $data['is_active'] ?? true,
            'category' => $data['category'] ?? null,
        ]);

        return redirect()->route('products.show', $product)->with('status', 'Product created.');
    }

    public function show(Product $product): View
    {
        $this->authorize('view', $product);

        $product->load(['priceListItems.priceList']);

        return view('pages.products.show', compact('product'));
    }

    public function edit(Product $product): View
    {
        $this->authorize('update', $product);

        return view('pages.products.edit', compact('product'));
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        abort_if(TenantContext::currentId() === null || $product->tenant_id !== TenantContext::currentId(), 403);

        $data = $request->validated();

        $product->update([
            'name' => $data['name'],
            'sku' => $data['sku'],
            'unit' => $data['unit'],
            'price' => $data['price'],
            'is_active' => $request->boolean('is_active'),
            'category' => $data['category'] ?? null,
        ]);

        return redirect()->route('products.show', $product)->with('status', 'Product updated.');
    }

    public function deactivate(Product $product): RedirectResponse
    {
        $this->authorize('deactivate', $product);

        abort_if(TenantContext::currentId() === null || $product->tenant_id !== TenantContext::currentId(), 403);

        $product->update(['is_active' => ! $product->is_active]);

        return back()->with('status', $product->is_active ? 'Product activated.' : 'Product deactivated.');
    }
}
