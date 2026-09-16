<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\RouteResource;
use App\Models\Customer;
use App\Models\Route;
use App\Models\RouteCustomer;
use App\Models\SalesmanAssignment;
use App\Models\SupervisorAssignment;
use App\Support\Http\ApiResponse;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RouteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Route::query()->with('territory.branch');

        if (TenantContext::hasContext()) {
            $query->where('tenant_id', TenantContext::currentId());
        }

        $this->applyRoleScope($request->user(), $query);

        if ($territoryId = $request->string('filter.territory_id')->toString()) {
            $query->where('territory_id', $territoryId);
        }

        if ($weekday = $request->string('filter.weekday')->toString()) {
            $query->where('weekday', $weekday);
        }

        if ($status = $request->string('filter.is_active')->toString()) {
            $query->where('is_active', $status === 'true');
        }

        if ($search = $request->string('filter.search')->toString()) {
            $query->where(fn ($q) => $q
                ->where('code', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%"));
        }

        $sort = $request->string('sort')->toString() ?? '-created_at';
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $sortField = ltrim($sort, '-');
        $allowedSortFields = ['created_at', 'updated_at', 'name', 'code', 'weekday'];
        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $direction);
        }

        $perPage = min($request->integer('per_page', 25), 100);
        $routes = $query->paginate($perPage);

        return ApiResponse::success(
            RouteResource::collection($routes),
            [
                'page' => $routes->currentPage(),
                'per_page' => $routes->perPage(),
                'total' => $routes->total(),
                'last_page' => $routes->lastPage(),
            ]
        );
    }

    public function store(Request $request): JsonResponse
    {
        $tenantId = TenantContext::currentId();
        abort_if($tenantId === null, 403, 'A tenant context is required to create routes.');
        abort_unless($request->user()->can('create', Route::class), 403, 'Insufficient permissions to create routes.');

        $validated = $request->validate([
            'territory_id' => ['required', Rule::exists('territories', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', Rule::unique('routes', 'code')->where('tenant_id', $tenantId)],
            'description' => ['nullable', 'string', 'max:500'],
            'weekday' => ['nullable', 'integer', 'between:0,6'],
            'is_active' => ['boolean'],
        ]);

        $route = Route::create([
            'tenant_id' => $tenantId,
            'territory_id' => $validated['territory_id'],
            'name' => $validated['name'],
            'code' => $validated['code'],
            'description' => $validated['description'] ?? null,
            'weekday' => $validated['weekday'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return ApiResponse::success(new RouteResource($route), status: 201);
    }

    public function show(Route $route): JsonResponse
    {
        abort_if(TenantContext::currentId() !== null && $route->tenant_id !== TenantContext::currentId(), 404);

        $route->load('territory.branch');

        return ApiResponse::success(new RouteResource($route));
    }

    public function update(Request $request, Route $route): JsonResponse
    {
        abort_if(TenantContext::currentId() !== null && $route->tenant_id !== TenantContext::currentId(), 404);
        abort_unless($request->user()->can('update', $route), 403, 'Insufficient permissions to update this route.');

        $tenantId = TenantContext::currentId();

        $validated = $request->validate([
            'territory_id' => ['sometimes', Rule::exists('territories', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['sometimes', 'string', 'max:50', Rule::unique('routes', 'code')->where('tenant_id', $tenantId)->ignore($route->id)],
            'description' => ['nullable', 'string', 'max:500'],
            'weekday' => ['nullable', 'integer', 'between:0,6'],
            'is_active' => ['boolean'],
        ]);

        $route->update($validated);

        $route->load('territory.branch');

        return ApiResponse::success(new RouteResource($route));
    }

    public function customers(Route $route, Request $request): JsonResponse
    {
        abort_if(TenantContext::currentId() !== null && $route->tenant_id !== TenantContext::currentId(), 404);

        $query = RouteCustomer::query()
            ->where('route_id', $route->id)
            ->where('tenant_id', TenantContext::currentId())
            ->with('customer.branch')
            ->orderBy('visit_order');

        // Filter by active
        if ($status = $request->string('filter.status')->toString()) {
            if ($status === 'active') {
                $query->where(function ($q) {
                    $q->whereNull('effective_to')
                        ->orWhere('effective_to', '>=', now()->startOfDay());
                })->where('effective_from', '<=', now()->endOfDay());
            }
        }

        $perPage = min($request->integer('per_page', 50), 200);
        $routeCustomers = $query->paginate($perPage);

        $customers = $routeCustomers->getCollection()->map(fn ($rc) => [
            'id' => $rc->customer->id,
            'uuid' => $rc->customer->uuid,
            'code' => $rc->customer->code,
            'name' => $rc->customer->business_name,
            'business_name' => $rc->customer->business_name,
            'contact_person' => $rc->customer->contact_person,
            'phone' => $rc->customer->phone,
            'address' => $rc->customer->address,
            'latitude' => $rc->customer->latitude ? (float) $rc->customer->latitude : null,
            'longitude' => $rc->customer->longitude ? (float) $rc->customer->longitude : null,
            'is_active' => $rc->customer->is_active,
            'visit_order' => $rc->visit_order,
            'effective_from' => $rc->effective_from?->toDateString(),
            'effective_to' => $rc->effective_to?->toDateString(),
            'route_customer_id' => $rc->id,
        ]);

        return ApiResponse::success(
            $customers,
            [
                'page' => $routeCustomers->currentPage(),
                'per_page' => $routeCustomers->perPage(),
                'total' => $routeCustomers->total(),
                'last_page' => $routeCustomers->lastPage(),
            ]
        );
    }

    public function addCustomer(Request $request, Route $route): JsonResponse
    {
        $tenantId = TenantContext::currentId();
        abort_if($tenantId === null, 403, 'A tenant context is required to assign customers to routes.');
        abort_if($route->tenant_id !== $tenantId, 404);
        abort_unless($request->user()->can('create', RouteCustomer::class), 403, 'Insufficient permissions to assign customers to routes.');

        $validated = $request->validate([
            'customer_id' => ['required', Rule::exists('customers', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)->where('is_active', true))],
            'visit_order' => ['required', 'integer', 'min:1'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ]);

        $customer = Customer::where('tenant_id', $tenantId)->findOrFail($validated['customer_id']);

        if ($customer->territory_id !== $route->territory_id) {
            return ApiResponse::error('The customer does not belong to the same territory as the route.', 422);
        }

        $effectiveFrom = $validated['effective_from'];
        $effectiveTo = $validated['effective_to'] ?? null;

        $duplicate = RouteCustomer::query()
            ->where('tenant_id', $tenantId)
            ->where('customer_id', $customer->id)
            ->where(function ($q) use ($effectiveFrom) {
                $q->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $effectiveFrom);
            })
            ->where('effective_from', '<=', $effectiveTo ?? '9999-12-31')
            ->exists();

        if ($duplicate) {
            return ApiResponse::error('This customer is already active on another route during this period.', 422);
        }

        $routeCustomer = RouteCustomer::create([
            'tenant_id' => $tenantId,
            'route_id' => $route->id,
            'customer_id' => $customer->id,
            'visit_order' => $validated['visit_order'],
            'effective_from' => $effectiveFrom,
            'effective_to' => $effectiveTo,
        ]);

        return ApiResponse::success([
            'id' => $routeCustomer->id,
            'route_id' => $routeCustomer->route_id,
            'customer_id' => $routeCustomer->customer_id,
            'visit_order' => $routeCustomer->visit_order,
            'effective_from' => $routeCustomer->effective_from?->toDateString(),
            'effective_to' => $routeCustomer->effective_to?->toDateString(),
        ], status: 201);
    }

    /**
     * Restrict routes to the territories a salesman or supervisor is assigned to.
     * Owners and other manager roles keep full tenant visibility.
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

            if ($territoryIds->isNotEmpty()) {
                $query->whereIn('territory_id', $territoryIds);

                return;
            }
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
