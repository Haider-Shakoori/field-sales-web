<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRouteCustomerRequest;
use App\Http\Requests\StoreRouteRequest;
use App\Http\Requests\UpdateRouteCustomerRequest;
use App\Http\Requests\UpdateRouteRequest;
use App\Models\Customer;
use App\Models\Route;
use App\Models\RouteCustomer;
use App\Models\Territory;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RouteController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Route::class);

        $query = Route::query()->with(['territory.branch']);

        if (TenantContext::hasContext()) {
            $query->where('tenant_id', TenantContext::currentId());
        }

        if ($search = $request->string('search')->trim()->toString()) {
            $query->where(fn ($q) => $q
                ->where('code', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%"));
        }

        if ($territoryId = $request->string('territory_id')->toString()) {
            $query->where('territory_id', $territoryId);
        }

        if ($weekday = $request->string('weekday')->toString()) {
            $query->where('weekday', $weekday);
        }

        if ($status = $request->string('status')->toString()) {
            $query->where('is_active', $status === 'active');
        }

        $routes = $query->orderBy('name')->paginate(20)->withQueryString();

        $territories = Territory::query()
            ->where('tenant_id', TenantContext::currentId())
            ->active()
            ->orderBy('name')
            ->get();

        return view('pages.routes.index', compact('routes', 'territories'));
    }

    public function create(): View
    {
        $this->authorize('create', Route::class);

        $tenantId = TenantContext::currentId();
        abort_if($tenantId === null, 403, 'A tenant context is required to create routes.');

        $territories = Territory::query()
            ->where('tenant_id', $tenantId)
            ->active()
            ->orderBy('name')
            ->get();

        return view('pages.routes.create', compact('territories'));
    }

    public function store(StoreRouteRequest $request): RedirectResponse
    {
        $tenantId = TenantContext::currentId();
        abort_if($tenantId === null, 403, 'A tenant context is required to create routes.');

        $data = $request->validated();

        $route = Route::create([
            'tenant_id' => $tenantId,
            'territory_id' => $data['territory_id'],
            'name' => $data['name'],
            'code' => $data['code'],
            'description' => $data['description'] ?? null,
            'weekday' => $data['weekday'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        return redirect()->route('routes.show', $route)->with('status', 'Route created.');
    }

    public function show(Route $route): View
    {
        $this->authorize('view', $route);

        $route->load(['territory.branch', 'routeCustomers.customer']);

        return view('pages.routes.show', compact('route'));
    }

    public function edit(Route $route): View
    {
        $this->authorize('update', $route);

        $tenantId = TenantContext::currentId();
        abort_if($tenantId === null || $route->tenant_id !== $tenantId, 403);

        $territories = Territory::query()
            ->where('tenant_id', $tenantId)
            ->active()
            ->orderBy('name')
            ->get();

        return view('pages.routes.edit', compact('route', 'territories'));
    }

    public function update(UpdateRouteRequest $request, Route $route): RedirectResponse
    {
        abort_if(TenantContext::currentId() === null || $route->tenant_id !== TenantContext::currentId(), 403);

        $data = $request->validated();
        $oldValues = [
            'territory_id' => $route->territory_id,
            'name' => $route->name,
            'code' => $route->code,
            'description' => $route->description,
            'weekday' => $route->weekday,
            'is_active' => $route->is_active,
        ];

        $route->update([
            'territory_id' => $data['territory_id'],
            'name' => $data['name'],
            'code' => $data['code'],
            'description' => $data['description'] ?? null,
            'weekday' => $data['weekday'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        $newValues = [
            'territory_id' => $route->territory_id,
            'name' => $route->name,
            'code' => $route->code,
            'description' => $route->description,
            'weekday' => $route->weekday,
            'is_active' => $route->is_active,
        ];

        AuditLogger::log('route.updated', $route, $oldValues, $newValues);

        return redirect()->route('routes.show', $route)->with('status', 'Route updated.');
    }

    public function deactivate(Route $route): RedirectResponse
    {
        $this->authorize('deactivate', $route);

        abort_if(TenantContext::currentId() === null || $route->tenant_id !== TenantContext::currentId(), 403);

        $route->update(['is_active' => ! $route->is_active]);

        AuditLogger::log($route->is_active ? 'route.activated' : 'route.deactivated', $route, [], [
            'is_active' => $route->is_active,
            'code' => $route->code,
            'name' => $route->name,
        ]);

        return back()->with('status', $route->is_active ? 'Route activated.' : 'Route deactivated.');
    }

    // Route Customer Management
    public function addCustomer(Route $route, StoreRouteCustomerRequest $request): RedirectResponse
    {
        $this->authorize('create', RouteCustomer::class);

        abort_if(TenantContext::currentId() === null || $route->tenant_id !== TenantContext::currentId(), 403);

        $data = $request->validated();

        $routeCustomer = RouteCustomer::create([
            'tenant_id' => $route->tenant_id,
            'route_id' => $route->id,
            'customer_id' => $data['customer_id'],
            'visit_order' => $data['visit_order'],
            'effective_from' => $data['effective_from'],
            'effective_to' => $data['effective_to'] ?? null,
        ]);

        return redirect()->route('routes.show', $route)->with('status', 'Customer added to route.');
    }

    public function updateCustomer(Route $route, UpdateRouteCustomerRequest $request, RouteCustomer $routeCustomer): RedirectResponse
    {
        $this->authorize('update', $routeCustomer);

        abort_if(TenantContext::currentId() === null || $route->tenant_id !== TenantContext::currentId(), 403);
        abort_if($routeCustomer->route_id !== $route->id, 404);

        $data = $request->validated();

        $routeCustomer->update([
            'customer_id' => $data['customer_id'],
            'visit_order' => $data['visit_order'],
            'effective_from' => $data['effective_from'],
            'effective_to' => $data['effective_to'] ?? null,
        ]);

        return redirect()->route('routes.show', $route)->with('status', 'Route customer updated.');
    }

    public function removeCustomer(Route $route, RouteCustomer $routeCustomer): RedirectResponse
    {
        $this->authorize('deactivate', $routeCustomer);

        abort_if(TenantContext::currentId() === null || $route->tenant_id !== TenantContext::currentId(), 403);
        abort_if($routeCustomer->route_id !== $route->id, 404);

        $routeCustomer->delete();

        return redirect()->route('routes.show', $route)->with('status', 'Customer removed from route.');
    }
}
