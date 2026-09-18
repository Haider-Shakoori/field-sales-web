<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Product::class);

        $search = trim((string) $request->string('search'));

        return view('admin.products.index', [
            'products' => Product::query()
                ->when($search !== '', function ($query) use ($search): void {
                    $query->where(function ($nested) use ($search): void {
                        $nested->where('name', 'like', "%{$search}%")
                            ->orWhere('sku', 'like', "%{$search}%")
                            ->orWhere('barcode', 'like', "%{$search}%");
                    });
                })
                ->orderBy('name')
                ->paginate(30)
                ->withQueryString(),
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Product::class);

        return view('admin.products.create');
    }

    public function store(StoreProductRequest $request, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validated();

        $product = Product::create([
            ...$validated,
            'sku' => strtoupper($validated['sku']),
            'barcode' => $validated['barcode'] ?: null,
            'currency' => strtoupper($validated['currency']),
        ]);

        $audit->record('product.created', $product, [], $this->auditValues($product));

        return redirect()
            ->route('admin.products.show', $product)
            ->with('status', 'Product created.');
    }

    public function show(Product $product): View
    {
        Gate::authorize('view', $product);

        return view('admin.products.show', [
            'product' => $product->load([
                'priceListItems.priceList',
            ]),
        ]);
    }

    public function edit(Product $product): View
    {
        Gate::authorize('update', $product);

        return view('admin.products.edit', compact('product'));
    }

    public function update(
        UpdateProductRequest $request,
        Product $product,
        AuditLogger $audit,
    ): RedirectResponse {
        $before = $this->auditValues($product);
        $validated = $request->validated();

        $product->update([
            ...$validated,
            'sku' => strtoupper($validated['sku']),
            'barcode' => $validated['barcode'] ?: null,
            'currency' => strtoupper($validated['currency']),
        ]);

        $audit->record('product.updated', $product, $before, $this->auditValues($product));

        return redirect()
            ->route('admin.products.show', $product)
            ->with('status', 'Product updated.');
    }

    public function destroy(Product $product, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('delete', $product);

        if ($product->priceListItems()->exists()) {
            throw ValidationException::withMessages([
                'product' => 'This product is referenced by price lists. Deactivate it instead.',
            ]);
        }

        $before = $this->auditValues($product);
        $audit->record('product.deleted', $product, $before);
        $product->delete();

        return redirect()
            ->route('admin.products.index')
            ->with('status', 'Product deleted.');
    }

    private function auditValues(Product $product): array
    {
        return [
            'sku' => $product->sku,
            'barcode' => $product->barcode,
            'name' => $product->name,
            'description' => $product->description,
            'unit' => $product->unit,
            'base_price' => $product->base_price,
            'currency' => $product->currency,
            'is_active' => $product->is_active,
        ];
    }
}
