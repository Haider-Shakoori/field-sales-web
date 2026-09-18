<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSalesRouteRequest;
use App\Http\Requests\UpdateSalesRouteRequest;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\SalesRoute;
use App\Models\Territory;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SalesRouteController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', SalesRoute::class);

        return view('admin.routes.index', [
            'routes' => SalesRoute::with(['branch', 'territory'])
                ->withCount('customerMemberships')
                ->orderBy('name')
                ->paginate(30),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', SalesRoute::class);

        return view('admin.routes.create', $this->formData());
    }

    public function store(
        StoreSalesRouteRequest $request,
        AuditLogger $audit,
    ): RedirectResponse {
        $validated = $request->validated();

        $route = SalesRoute::create([
            ...$validated,
            'code' => strtoupper($validated['code']),
            'weekdays' => array_values(array_unique($validated['weekdays'] ?? [])),
        ]);

        $audit->record('route.created', $route, [], $this->auditValues($route));

        return redirect()
            ->route('admin.routes.show', $route)
            ->with('status', 'Route created.');
    }

    public function show(SalesRoute $route): View
    {
        Gate::authorize('view', $route);

        $route->load([
            'branch',
            'territory',
            'customerMemberships.customer',
        ]);

        return view('admin.routes.show', [
            'route' => $route,
            'availableCustomers' => Customer::active()
                ->whereNotIn('id', $route->customerMemberships->pluck('customer_id'))
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function edit(SalesRoute $route): View
    {
        Gate::authorize('update', $route);

        return view('admin.routes.edit', [
            ...$this->formData(),
            'route' => $route,
        ]);
    }

    public function update(
        UpdateSalesRouteRequest $request,
        SalesRoute $route,
        AuditLogger $audit,
    ): RedirectResponse {
        $before = $this->auditValues($route);
        $validated = $request->validated();

        $route->update([
            ...$validated,
            'code' => strtoupper($validated['code']),
            'weekdays' => array_values(array_unique($validated['weekdays'] ?? [])),
        ]);

        $audit->record('route.updated', $route, $before, $this->auditValues($route));

        return redirect()
            ->route('admin.routes.show', $route)
            ->with('status', 'Route updated.');
    }

    public function destroy(SalesRoute $route, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('delete', $route);

        if ($route->salesmanAssignments()->exists()) {
            throw ValidationException::withMessages([
                'route' => 'This route is referenced by salesman assignment history. Deactivate it instead.',
            ]);
        }

        $before = $this->auditValues($route);
        $audit->record('route.deleted', $route, $before);
        $route->delete();

        return redirect()
            ->route('admin.routes.index')
            ->with('status', 'Route deleted.');
    }

    private function formData(): array
    {
        return [
            'branches' => Branch::active()->orderBy('name')->get(),
            'territories' => Territory::active()->with('branch')->orderBy('name')->get(),
        ];
    }

    private function auditValues(SalesRoute $route): array
    {
        return [
            'branch_id' => $route->branch_id,
            'territory_id' => $route->territory_id,
            'code' => $route->code,
            'name' => $route->name,
            'weekdays' => $route->weekdays,
            'description' => $route->description,
            'is_active' => $route->is_active,
        ];
    }
}
