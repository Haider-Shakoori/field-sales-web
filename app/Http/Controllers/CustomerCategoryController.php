<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomerCategoryRequest;
use App\Http\Requests\UpdateCustomerCategoryRequest;
use App\Models\Customer;
use App\Models\CustomerCategory;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerCategoryController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', CustomerCategory::class);

        $query = CustomerCategory::query()->withCount('customers');

        if (TenantContext::hasContext()) {
            $query->where('tenant_id', TenantContext::currentId());
        }

        if ($search = $request->string('search')->trim()->toString()) {
            $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%"));
        }

        if ($status = $request->string('status')->toString()) {
            $query->where('is_active', $status === 'active');
        }

        $categories = $query->orderBy('name')->paginate(20)->withQueryString();

        return view('pages.customer-categories.index', compact('categories'));
    }

    public function create(): View
    {
        $this->authorize('create', CustomerCategory::class);

        return view('pages.customer-categories.create');
    }

    public function store(StoreCustomerCategoryRequest $request): RedirectResponse
    {
        $tenantId = TenantContext::currentId();
        abort_if($tenantId === null, 403, 'A tenant context is required to create customer categories.');

        $data = $request->validated();

        $category = CustomerCategory::create([
            'tenant_id' => $tenantId,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        return redirect()->route('customer-categories.show', $category)->with('status', 'Customer category created.');
    }

    public function show(CustomerCategory $category): View
    {
        $this->authorize('view', $category);

        $category->loadCount('customers');

        return view('pages.customer-categories.show', compact('category'));
    }

    public function edit(CustomerCategory $category): View
    {
        $this->authorize('update', $category);

        $tenantId = TenantContext::currentId();
        abort_if($tenantId === null || $category->tenant_id !== $tenantId, 403);

        return view('pages.customer-categories.edit', compact('category'));
    }

    public function update(UpdateCustomerCategoryRequest $request, CustomerCategory $category): RedirectResponse
    {
        abort_if(TenantContext::currentId() === null || $category->tenant_id !== TenantContext::currentId(), 403);

        $data = $request->validated();
        $oldValues = [
            'name' => $category->name,
            'description' => $category->description,
            'is_active' => $category->is_active,
        ];

        $category->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        $newValues = [
            'name' => $category->name,
            'description' => $category->description,
            'is_active' => $category->is_active,
        ];

        AuditLogger::log('customer_category.updated', $category, $oldValues, $newValues);

        return redirect()->route('customer-categories.show', $category)->with('status', 'Customer category updated.');
    }

    public function destroy(CustomerCategory $category): RedirectResponse
    {
        $this->authorize('deactivate', $category);

        $tenantId = TenantContext::currentId();
        abort_if($tenantId === null || $category->tenant_id !== $tenantId, 403);

        // Check if category is referenced by any customers
        if (Customer::where('category_id', $category->id)->exists()) {
            return back()->withErrors(['category' => 'Cannot delete category. It is referenced by existing customers.'])->withInput();
        }

        $category->delete();

        AuditLogger::log('customer_category.deleted', $category, [], [
            'name' => $category->name,
        ]);

        return redirect()->route('customer-categories.index')->with('status', 'Customer category deleted.');
    }

    public function deactivate(CustomerCategory $category): RedirectResponse
    {
        $this->authorize('deactivate', $category);

        $tenantId = TenantContext::currentId();
        abort_if($tenantId === null || $category->tenant_id !== $tenantId, 403);

        $category->update(['is_active' => ! $category->is_active]);

        AuditLogger::log($category->is_active ? 'customer_category.activated' : 'customer_category.deactivated', $category, [], [
            'is_active' => $category->is_active,
            'name' => $category->name,
        ]);

        return back()->with('status', $category->is_active ? 'Customer category activated.' : 'Customer category deactivated.');
    }
}
