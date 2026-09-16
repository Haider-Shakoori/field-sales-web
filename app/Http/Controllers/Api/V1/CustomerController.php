<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreCustomerRequest;
use App\Http\Requests\Api\V1\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Models\SalesmanAssignment;
use App\Models\SupervisorAssignment;
use App\Support\FieldProfiles\EmployeeCodeGenerator;
use App\Support\Http\ApiResponse;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CustomerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Customer::query()->with(['branch', 'category', 'territory', 'route', 'assignedSalesman', 'priceList']);

        if (TenantContext::hasContext()) {
            $query->where('tenant_id', TenantContext::currentId());
        }

        $this->applyRoleScope($request->user(), $query);

        // Filters
        if ($branchId = $request->string('filter.branch_id')->toString()) {
            $query->where('branch_id', $branchId);
        }

        if ($categoryId = $request->string('filter.category_id')->toString()) {
            $query->where('category_id', $categoryId);
        }

        if ($territoryId = $request->string('filter.territory_id')->toString()) {
            $query->where('territory_id', $territoryId);
        }

        if ($routeId = $request->string('filter.route_id')->toString()) {
            $query->where('route_id', $routeId);
        }

        if ($salesmanId = $request->string('filter.assigned_salesman_id')->toString()) {
            $query->where('assigned_salesman_id', $salesmanId);
        }

        if ($status = $request->string('filter.is_active')->toString()) {
            $query->where('is_active', $status === 'true');
        }

        // Search
        if ($search = $request->string('filter.search')->toString()) {
            $query->where(fn ($q) => $q
                ->where('code', 'like', "%{$search}%")
                ->orWhere('business_name', 'like', "%{$search}%")
                ->orWhere('contact_person', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%"));
        }

        // Sorting
        $sort = $request->string('sort')->toString() ?? '-created_at';
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $sortField = ltrim($sort, '-');
        $allowedSortFields = ['created_at', 'updated_at', 'business_name', 'code', 'contact_person'];
        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $direction);
        }

        $perPage = min($request->integer('per_page', 25), 100);
        $customers = $query->paginate($perPage);

        return ApiResponse::success(
            CustomerResource::collection($customers),
            [
                'page' => $customers->currentPage(),
                'per_page' => $customers->perPage(),
                'total' => $customers->total(),
                'last_page' => $customers->lastPage(),
            ]
        );
    }

    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $tenantId = TenantContext::currentId();
        abort_if($tenantId === null, 403, 'A tenant context is required to create customers.');

        $user = $request->user();
        $salesman = $user->salesmanProfile;

        $validated = $request->validated();

        // Check for existing customer with same offline_uuid (idempotency)
        if ($validated['offline_uuid'] ?? null) {
            $existing = Customer::where('tenant_id', $tenantId)
                ->where('uuid', $validated['offline_uuid'])
                ->first();

            if ($existing) {
                $existing->load(['branch', 'category', 'territory', 'route', 'assignedSalesman', 'priceList']);

                return ApiResponse::success(new CustomerResource($existing), status: 200);
            }
        }

        // Auto-assign to salesman if not provided and user is a salesman
        if ($salesman && ! ($validated['assigned_salesman_id'] ?? null)) {
            $validated['assigned_salesman_id'] = $salesman->id;
        }

        // Generate code if not provided
        $code = $validated['code'] ?? EmployeeCodeGenerator::nextCustomerCode($tenantId);

        $customer = Customer::create([
            'tenant_id' => $tenantId,
            'uuid' => $validated['offline_uuid'] ?? Str::uuid(),
            'branch_id' => $validated['branch_id'],
            'code' => $code,
            'business_name' => $validated['business_name'] ?? $validated['name'],
            'contact_person' => $validated['contact_person'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'whatsapp' => $validated['whatsapp'] ?? null,
            'email' => $validated['email'] ?? null,
            'category_id' => $validated['category_id'] ?? null,
            'province' => $validated['province'] ?? null,
            'district' => $validated['district'] ?? null,
            'address' => $validated['address'] ?? null,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'geofence_radius' => $validated['geofence_radius'] ?? 100,
            'photo_url' => $validated['photo_url'] ?? null,
            'assigned_salesman_id' => $validated['assigned_salesman_id'] ?? null,
            'territory_id' => $validated['territory_id'],
            'route_id' => $validated['route_id'] ?? null,
            'credit_limit' => $validated['credit_limit'] ?? 0,
            'outstanding_balance' => $validated['outstanding_balance'] ?? 0,
            'price_list_id' => $validated['price_list_id'] ?? null,
            'visit_frequency' => $validated['visit_frequency'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
            'notes' => $validated['notes'] ?? null,
        ]);

        $customer->load(['branch', 'category', 'territory', 'route', 'assignedSalesman', 'priceList']);

        return ApiResponse::success(new CustomerResource($customer), status: 201);
    }

    public function show(Customer $customer): JsonResponse
    {
        abort_if(TenantContext::currentId() !== null && $customer->tenant_id !== TenantContext::currentId(), 404);

        $customer->load(['branch', 'category', 'territory', 'route', 'assignedSalesman', 'priceList']);

        return ApiResponse::success(new CustomerResource($customer));
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): JsonResponse
    {
        abort_if(TenantContext::currentId() !== null && $customer->tenant_id !== TenantContext::currentId(), 404);

        // Policy check - user must be authorized to update this customer
        $user = $request->user();
        abort_if(! $user->can('update', $customer), 403, 'Insufficient permissions to update this customer.');

        $tenantId = TenantContext::currentId();
        $salesman = $user->salesmanProfile;

        $validated = $request->validated();

        // Salesman cannot assign customer to another salesman
        if ($salesman && ($validated['assigned_salesman_id'] ?? $customer->assigned_salesman_id) !== $customer->assigned_salesman_id) {
            abort_if($validated['assigned_salesman_id'] !== $salesman->id, 403, 'Salesmen cannot assign customers to other salesmen.');
        }

        $customer->update([
            'branch_id' => $validated['branch_id'],
            'code' => $validated['code'] ?? $customer->code,
            'business_name' => $validated['business_name'] ?? $validated['name'],
            'contact_person' => $validated['contact_person'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'whatsapp' => $validated['whatsapp'] ?? null,
            'email' => $validated['email'] ?? null,
            'category_id' => $validated['category_id'] ?? null,
            'province' => $validated['province'] ?? null,
            'district' => $validated['district'] ?? null,
            'address' => $validated['address'] ?? null,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'geofence_radius' => $validated['geofence_radius'] ?? 100,
            'photo_url' => $validated['photo_url'] ?? null,
            'assigned_salesman_id' => $validated['assigned_salesman_id'] ?? null,
            'territory_id' => $validated['territory_id'],
            'route_id' => $validated['route_id'] ?? null,
            'credit_limit' => $validated['credit_limit'] ?? 0,
            'outstanding_balance' => $validated['outstanding_balance'] ?? 0,
            'price_list_id' => $validated['price_list_id'] ?? null,
            'visit_frequency' => $validated['visit_frequency'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
            'notes' => $validated['notes'] ?? null,
        ]);

        // Location history is handled by the model's booted() method

        $customer->load(['branch', 'category', 'territory', 'route', 'assignedSalesman', 'priceList']);

        return ApiResponse::success(new CustomerResource($customer));
    }

    /**
     * Restrict the customer list to what the authenticated user may see.
     * Salesmen see customers assigned to them or in their assigned territories;
     * supervisors see customers in their assigned territories. Owners and other
     * manager roles keep full tenant visibility.
     */
    private function applyRoleScope(?object $user, $query): void
    {
        if ($user === null) {
            return;
        }

        $tenantId = TenantContext::currentId();

        $salesman = $user->salesmanProfile;
        if ($salesman !== null) {
            $territoryIds = SalesmanAssignment::query()
                ->where('tenant_id', $tenantId)
                ->where('salesman_id', $salesman->id)
                ->active()
                ->pluck('territory_id');

            $query->where(function ($q) use ($salesman, $territoryIds) {
                $q->where('assigned_salesman_id', $salesman->id);

                if ($territoryIds->isNotEmpty()) {
                    $q->orWhereIn('territory_id', $territoryIds);
                }
            });

            return;
        }

        $supervisor = $user->supervisorProfile;
        if ($supervisor !== null) {
            $territoryIds = SupervisorAssignment::query()
                ->where('tenant_id', $tenantId)
                ->where('supervisor_id', $supervisor->id)
                ->active()
                ->pluck('territory_id');

            if ($territoryIds->isEmpty()) {
                $query->whereRaw('0 = 1');
            } else {
                $query->whereIn('territory_id', $territoryIds);
            }
        }
    }
}
