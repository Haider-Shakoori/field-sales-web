<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerCategory;
use App\Models\PriceList;
use App\Models\Route;
use App\Models\Salesman;
use App\Models\Territory;
use App\Support\Audit\AuditLogger;
use App\Support\FieldProfiles\EmployeeCodeGenerator;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Customer::class);

        $query = Customer::query()->with(['branch', 'category', 'territory', 'route', 'assignedSalesman']);

        if (TenantContext::hasContext()) {
            $query->where('tenant_id', TenantContext::currentId());
        }

        if ($search = $request->string('search')->trim()->toString()) {
            $query->where(fn ($q) => $q
                ->where('code', 'like', "%{$search}%")
                ->orWhere('business_name', 'like', "%{$search}%")
                ->orWhere('contact_person', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%"));
        }

        if ($branchId = $request->string('branch_id')->toString()) {
            $query->where('branch_id', $branchId);
        }

        if ($categoryId = $request->string('category_id')->toString()) {
            $query->where('category_id', $categoryId);
        }

        if ($territoryId = $request->string('territory_id')->toString()) {
            $query->where('territory_id', $territoryId);
        }

        if ($routeId = $request->string('route_id')->toString()) {
            $query->where('route_id', $routeId);
        }

        if ($assignedSalesmanId = $request->string('assigned_salesman_id')->toString()) {
            $query->where('assigned_salesman_id', $assignedSalesmanId);
        }

        if ($status = $request->string('status')->toString()) {
            $query->where('is_active', $status === 'active');
        }

        $customers = $query->orderBy('business_name')->paginate(20)->withQueryString();

        $branches = Branch::query()
            ->where('tenant_id', TenantContext::currentId())
            ->active()
            ->orderBy('name')
            ->get();

        $categories = CustomerCategory::query()
            ->where('tenant_id', TenantContext::currentId())
            ->active()
            ->orderBy('name')
            ->get();

        $territories = Territory::query()
            ->where('tenant_id', TenantContext::currentId())
            ->active()
            ->orderBy('name')
            ->get();

        $routes = Route::query()
            ->where('tenant_id', TenantContext::currentId())
            ->active()
            ->orderBy('name')
            ->get();

        $salesmen = Salesman::query()
            ->where('tenant_id', TenantContext::currentId())
            ->active()
            ->orderBy('first_name')
            ->get();

        return view('pages.customers.index', compact(
            'customers',
            'branches',
            'categories',
            'territories',
            'routes',
            'salesmen'
        ));
    }

    public function create(): View
    {
        $this->authorize('create', Customer::class);

        $tenantId = TenantContext::currentId();
        abort_if($tenantId === null, 403, 'A tenant context is required to create customers.');

        $branches = Branch::query()
            ->where('tenant_id', $tenantId)
            ->active()
            ->orderBy('name')
            ->get();

        $categories = CustomerCategory::query()
            ->where('tenant_id', $tenantId)
            ->active()
            ->orderBy('name')
            ->get();

        $territories = Territory::query()
            ->where('tenant_id', $tenantId)
            ->active()
            ->orderBy('name')
            ->get();

        $salesmen = Salesman::query()
            ->where('tenant_id', $tenantId)
            ->active()
            ->orderBy('first_name')
            ->get();

        $routes = collect();

        $priceLists = PriceList::query()
            ->where('tenant_id', $tenantId)
            ->active()
            ->orderBy('name')
            ->get();

        return view('pages.customers.create', compact(
            'branches',
            'categories',
            'territories',
            'routes',
            'salesmen',
            'priceLists'
        ));
    }

    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        $tenantId = TenantContext::currentId();
        abort_if($tenantId === null, 403, 'A tenant context is required to create customers.');

        $data = $request->validated();
        $code = EmployeeCodeGenerator::nextCustomerCode($tenantId, $data['code'] ?? null);

        $customer = Customer::create([
            'tenant_id' => $tenantId,
            'branch_id' => $data['branch_id'],
            'code' => $code,
            'business_name' => $data['business_name'],
            'contact_person' => $data['contact_person'] ?? null,
            'phone' => $data['phone'] ?? null,
            'whatsapp' => $data['whatsapp'] ?? null,
            'category_id' => $data['category_id'] ?? null,
            'province' => $data['province'] ?? null,
            'district' => $data['district'] ?? null,
            'address' => $data['address'] ?? null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'geofence_radius' => $data['geofence_radius'] ?? 100,
            'photo_url' => $data['photo_url'] ?? null,
            'assigned_salesman_id' => $data['assigned_salesman_id'] ?? null,
            'territory_id' => $data['territory_id'],
            'route_id' => $data['route_id'] ?? null,
            'credit_limit' => $data['credit_limit'] ?? 0,
            'outstanding_balance' => $data['outstanding_balance'] ?? 0,
            'price_list_id' => $data['price_list_id'] ?? null,
            'visit_frequency' => $data['visit_frequency'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'notes' => $data['notes'] ?? null,
        ]);

        return redirect()->route('customers.show', $customer)->with('status', 'Customer created.');
    }

    public function show(Customer $customer): View
    {
        $this->authorize('view', $customer);

        $customer->load(['branch', 'category', 'territory', 'route', 'assignedSalesman', 'priceList', 'locationHistory' => fn ($q) => $q->limit(10)]);

        return view('pages.customers.show', compact('customer'));
    }

    public function edit(Customer $customer): View
    {
        $this->authorize('update', $customer);

        $tenantId = TenantContext::currentId();
        abort_if($tenantId === null || $customer->tenant_id !== $tenantId, 403);

        $branches = Branch::query()
            ->where('tenant_id', $tenantId)
            ->active()
            ->orderBy('name')
            ->get();

        $categories = CustomerCategory::query()
            ->where('tenant_id', $tenantId)
            ->active()
            ->orderBy('name')
            ->get();

        $territories = Territory::query()
            ->where('tenant_id', $tenantId)
            ->active()
            ->orderBy('name')
            ->get();

        $salesmen = Salesman::query()
            ->where('tenant_id', $tenantId)
            ->active()
            ->orderBy('first_name')
            ->get();

        $routes = Route::query()
            ->where('tenant_id', $tenantId)
            ->active()
            ->orderBy('name')
            ->get();

        if ($customer->territory_id) {
            $routes = $routes->where('territory_id', $customer->territory_id);
        }

        $priceLists = PriceList::query()
            ->where('tenant_id', $tenantId)
            ->active()
            ->orderBy('name')
            ->get();

        return view('pages.customers.edit', compact(
            'customer',
            'branches',
            'categories',
            'territories',
            'routes',
            'salesmen',
            'priceLists'
        ));
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): RedirectResponse
    {
        abort_if(TenantContext::currentId() === null || $customer->tenant_id !== TenantContext::currentId(), 403);

        $data = $request->validated();
        $oldValues = [
            'code' => $customer->code,
            'business_name' => $customer->business_name,
            'contact_person' => $customer->contact_person,
            'phone' => $customer->phone,
            'whatsapp' => $customer->whatsapp,
            'category_id' => $customer->category_id,
            'province' => $customer->province,
            'district' => $customer->district,
            'address' => $customer->address,
            'latitude' => $customer->latitude,
            'longitude' => $customer->longitude,
            'geofence_radius' => $customer->geofence_radius,
            'photo_url' => $customer->photo_url,
            'assigned_salesman_id' => $customer->assigned_salesman_id,
            'territory_id' => $customer->territory_id,
            'route_id' => $customer->route_id,
            'credit_limit' => $customer->credit_limit,
            'outstanding_balance' => $customer->outstanding_balance,
            'price_list_id' => $customer->price_list_id,
            'visit_frequency' => $customer->visit_frequency,
            'is_active' => $customer->is_active,
            'notes' => $customer->notes,
        ];

        $customer->update([
            'branch_id' => $data['branch_id'],
            'code' => $data['code'] ?? $customer->code,
            'business_name' => $data['business_name'],
            'contact_person' => $data['contact_person'] ?? null,
            'phone' => $data['phone'] ?? null,
            'whatsapp' => $data['whatsapp'] ?? null,
            'category_id' => $data['category_id'] ?? null,
            'province' => $data['province'] ?? null,
            'district' => $data['district'] ?? null,
            'address' => $data['address'] ?? null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'geofence_radius' => $data['geofence_radius'] ?? 100,
            'photo_url' => $data['photo_url'] ?? null,
            'assigned_salesman_id' => $data['assigned_salesman_id'] ?? null,
            'territory_id' => $data['territory_id'],
            'route_id' => $data['route_id'] ?? null,
            'credit_limit' => $data['credit_limit'] ?? 0,
            'outstanding_balance' => $data['outstanding_balance'] ?? 0,
            'price_list_id' => $data['price_list_id'] ?? null,
            'visit_frequency' => $data['visit_frequency'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'notes' => $data['notes'] ?? null,
        ]);

        $newValues = [
            'code' => $customer->code,
            'business_name' => $customer->business_name,
            'contact_person' => $customer->contact_person,
            'phone' => $customer->phone,
            'whatsapp' => $customer->whatsapp,
            'category_id' => $customer->category_id,
            'province' => $customer->province,
            'district' => $customer->district,
            'address' => $customer->address,
            'latitude' => $customer->latitude,
            'longitude' => $customer->longitude,
            'geofence_radius' => $customer->geofence_radius,
            'photo_url' => $customer->photo_url,
            'assigned_salesman_id' => $customer->assigned_salesman_id,
            'territory_id' => $customer->territory_id,
            'route_id' => $customer->route_id,
            'credit_limit' => $customer->credit_limit,
            'outstanding_balance' => $customer->outstanding_balance,
            'price_list_id' => $customer->price_list_id,
            'visit_frequency' => $customer->visit_frequency,
            'is_active' => $customer->is_active,
            'notes' => $customer->notes,
        ];

        AuditLogger::log('customer.updated', $customer, $oldValues, $newValues);

        return redirect()->route('customers.show', $customer)->with('status', 'Customer updated.');
    }

    public function deactivate(Customer $customer): RedirectResponse
    {
        $this->authorize('deactivate', $customer);

        abort_if(TenantContext::currentId() === null || $customer->tenant_id !== TenantContext::currentId(), 403);

        $customer->update(['is_active' => ! $customer->is_active]);

        AuditLogger::log($customer->is_active ? 'customer.activated' : 'customer.deactivated', $customer, [], [
            'is_active' => $customer->is_active,
            'code' => $customer->code,
            'business_name' => $customer->business_name,
        ]);

        return back()->with('status', $customer->is_active ? 'Customer activated.' : 'Customer deactivated.');
    }
}
