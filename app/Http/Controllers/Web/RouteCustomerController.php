<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReorderRouteCustomersRequest;
use App\Http\Requests\StoreRouteCustomerRequest;
use App\Models\RouteCustomer;
use App\Models\SalesRoute;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class RouteCustomerController extends Controller
{
    public function store(
        StoreRouteCustomerRequest $request,
        SalesRoute $route,
        AuditLogger $audit,
    ): RedirectResponse {
        Gate::authorize('update', $route);

        $validated = $request->validated();
        $nextSequence = ((int) $route->customerMemberships()->max('sequence_number')) + 1;

        $membership = RouteCustomer::create([
            'route_id' => $route->id,
            'customer_id' => $validated['customer_id'],
            'sequence_number' => $nextSequence,
            'planned_visit_minutes' => $validated['planned_visit_minutes'],
            'notes' => $validated['notes'] ?? null,
        ]);

        $audit->record('route_customer.created', $membership, [], [
            'route_id' => $route->id,
            'customer_id' => $membership->customer_id,
            'sequence_number' => $membership->sequence_number,
            'planned_visit_minutes' => $membership->planned_visit_minutes,
            'notes' => $membership->notes,
        ]);

        return back()->with('status', 'Customer added to route.');
    }

    public function reorder(
        ReorderRouteCustomersRequest $request,
        SalesRoute $route,
        AuditLogger $audit,
    ): RedirectResponse {
        Gate::authorize('update', $route);

        $positions = collect($request->validated('positions'))
            ->mapWithKeys(fn ($position, $id) => [(int) $id => (int) $position]);

        if ($positions->values()->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages([
                'positions' => 'Each route position must be unique.',
            ]);
        }

        $memberships = RouteCustomer::where('route_id', $route->id)
            ->whereIn('id', $positions->keys())
            ->get();

        if ($memberships->count() !== $positions->count()) {
            throw ValidationException::withMessages([
                'positions' => 'One or more route-customer rows do not belong to this route.',
            ]);
        }

        $before = $memberships
            ->mapWithKeys(fn (RouteCustomer $membership) => [
                $membership->id => $membership->sequence_number,
            ])
            ->all();

        DB::transaction(function () use ($memberships, $positions): void {
            foreach ($memberships as $membership) {
                $membership->update([
                    'sequence_number' => $membership->sequence_number + 10000,
                ]);
            }

            foreach ($memberships as $membership) {
                $membership->update([
                    'sequence_number' => $positions[$membership->id],
                ]);
            }
        });

        $audit->record('route.customers_reordered', $route, $before, $positions->all());

        return back()->with('status', 'Route order updated.');
    }

    public function destroy(
        SalesRoute $route,
        RouteCustomer $routeCustomer,
        AuditLogger $audit,
    ): RedirectResponse {
        Gate::authorize('update', $route);

        if ((int) $routeCustomer->route_id !== (int) $route->id) {
            abort(404);
        }

        $before = [
            'route_id' => $routeCustomer->route_id,
            'customer_id' => $routeCustomer->customer_id,
            'sequence_number' => $routeCustomer->sequence_number,
            'planned_visit_minutes' => $routeCustomer->planned_visit_minutes,
            'notes' => $routeCustomer->notes,
        ];

        $audit->record('route_customer.deleted', $routeCustomer, $before);
        $routeCustomer->delete();

        $remaining = RouteCustomer::where('route_id', $route->id)
            ->orderBy('sequence_number')
            ->get();

        foreach ($remaining as $index => $membership) {
            $membership->update(['sequence_number' => $index + 1]);
        }

        return back()->with('status', 'Customer removed from route.');
    }
}
