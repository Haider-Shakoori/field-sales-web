<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSalesmanAssignmentRequest;
use App\Http\Requests\UpdateSalesmanAssignmentRequest;
use App\Models\Branch;
use App\Models\Route;
use App\Models\Salesman;
use App\Models\SalesmanAssignment;
use App\Models\Supervisor;
use App\Models\Territory;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SalesmanAssignmentController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', SalesmanAssignment::class);

        $query = SalesmanAssignment::query()->with(['salesman', 'branch', 'territory', 'route', 'supervisor', 'createdBy']);

        if (TenantContext::hasContext()) {
            $query->where('tenant_id', TenantContext::currentId());
        }

        if ($search = $request->string('search')->trim()->toString()) {
            $query->whereHas('salesman', fn ($q) => $q
                ->where('employee_code', 'like', "%{$search}%")
                ->orWhere('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%"));
        }

        if ($salesmanId = $request->string('salesman_id')->toString()) {
            $query->where('salesman_id', $salesmanId);
        }

        if ($territoryId = $request->string('territory_id')->toString()) {
            $query->where('territory_id', $territoryId);
        }

        $assignments = $query->orderByDesc('effective_from')->paginate(20)->withQueryString();

        $salesmen = Salesman::query()
            ->where('tenant_id', TenantContext::currentId())
            ->active()
            ->orderBy('first_name')
            ->get();

        return view('pages.salesman-assignments.index', compact('assignments', 'salesmen'));
    }

    public function create(): View
    {
        $this->authorize('create', SalesmanAssignment::class);

        $tenantId = TenantContext::currentId();
        abort_if($tenantId === null, 403, 'A tenant context is required to create salesman assignments.');

        $salesmen = Salesman::query()
            ->where('tenant_id', $tenantId)
            ->active()
            ->orderBy('first_name')
            ->get();

        $branches = Branch::query()
            ->where('tenant_id', $tenantId)
            ->active()
            ->orderBy('name')
            ->get();

        $territories = Territory::query()
            ->where('tenant_id', $tenantId)
            ->active()
            ->orderBy('name')
            ->get();

        $routes = Route::query()
            ->where('tenant_id', $tenantId)
            ->active()
            ->orderBy('name')
            ->get();

        $supervisors = Supervisor::query()
            ->where('tenant_id', $tenantId)
            ->active()
            ->orderBy('first_name')
            ->get();

        return view('pages.salesman-assignments.create', compact(
            'salesmen', 'branches', 'territories', 'routes', 'supervisors'
        ));
    }

    public function store(StoreSalesmanAssignmentRequest $request): RedirectResponse
    {
        $tenantId = TenantContext::currentId();
        abort_if($tenantId === null, 403, 'A tenant context is required to create salesman assignments.');

        $data = $request->validated();

        // Close any existing active assignment for this salesman
        $existingActive = SalesmanAssignment::where('tenant_id', $tenantId)
            ->where('salesman_id', $data['salesman_id'])
            ->where(function ($q) {
                $q->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', now()->toDateString());
            })
            ->where('effective_from', '<=', now()->toDateString())
            ->first();

        if ($existingActive) {
            $existingActive->update(['effective_to' => now()->subDay()->toDateString()]);
            AuditLogger::log('salesman.assignment_ended', $existingActive, [], [
                'salesman_id' => $existingActive->salesman_id,
                'effective_to' => $existingActive->effective_to?->toDateString(),
            ]);
        }

        $assignment = SalesmanAssignment::create([
            'tenant_id' => $tenantId,
            'salesman_id' => $data['salesman_id'],
            'branch_id' => $data['branch_id'],
            'territory_id' => $data['territory_id'],
            'route_id' => $data['route_id'] ?? null,
            'supervisor_id' => $data['supervisor_id'] ?? null,
            'effective_from' => $data['effective_from'],
            'effective_to' => $data['effective_to'] ?? null,
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('salesman-assignments.show', $assignment)->with('status', 'Salesman assignment created.');
    }

    public function show(SalesmanAssignment $assignment): View
    {
        $this->authorize('view', $assignment);

        $assignment->load(['salesman', 'branch', 'territory', 'route', 'supervisor', 'createdBy']);

        return view('pages.salesman-assignments.show', compact('assignment'));
    }

    public function edit(SalesmanAssignment $assignment): View
    {
        $this->authorize('update', $assignment);

        $tenantId = TenantContext::currentId();
        abort_if($tenantId === null || $assignment->tenant_id !== $tenantId, 403);

        $salesmen = Salesman::query()
            ->where('tenant_id', $tenantId)
            ->active()
            ->orderBy('first_name')
            ->get();

        $branches = Branch::query()
            ->where('tenant_id', $tenantId)
            ->active()
            ->orderBy('name')
            ->get();

        $territories = Territory::query()
            ->where('tenant_id', $tenantId)
            ->active()
            ->orderBy('name')
            ->get();

        $routes = Route::query()
            ->where('tenant_id', $tenantId)
            ->active()
            ->orderBy('name')
            ->get();

        $supervisors = Supervisor::query()
            ->where('tenant_id', $tenantId)
            ->active()
            ->orderBy('first_name')
            ->get();

        return view('pages.salesman-assignments.edit', compact(
            'assignment', 'salesmen', 'branches', 'territories', 'routes', 'supervisors'
        ));
    }

    public function update(UpdateSalesmanAssignmentRequest $request, SalesmanAssignment $assignment): RedirectResponse
    {
        abort_if(TenantContext::currentId() === null || $assignment->tenant_id !== TenantContext::currentId(), 403);

        $data = $request->validated();

        $oldValues = [
            'salesman_id' => $assignment->salesman_id,
            'branch_id' => $assignment->branch_id,
            'territory_id' => $assignment->territory_id,
            'route_id' => $assignment->route_id,
            'supervisor_id' => $assignment->supervisor_id,
            'effective_from' => $assignment->effective_from?->toDateString(),
            'effective_to' => $assignment->effective_to?->toDateString(),
        ];

        $assignment->update([
            'salesman_id' => $data['salesman_id'],
            'branch_id' => $data['branch_id'],
            'territory_id' => $data['territory_id'],
            'route_id' => $data['route_id'] ?? null,
            'supervisor_id' => $data['supervisor_id'] ?? null,
            'effective_from' => $data['effective_from'],
            'effective_to' => $data['effective_to'] ?? null,
        ]);

        $newValues = [
            'salesman_id' => $assignment->salesman_id,
            'branch_id' => $assignment->branch_id,
            'territory_id' => $assignment->territory_id,
            'route_id' => $assignment->route_id,
            'supervisor_id' => $assignment->supervisor_id,
            'effective_from' => $assignment->effective_from?->toDateString(),
            'effective_to' => $assignment->effective_to?->toDateString(),
        ];

        AuditLogger::log('salesman.assignment_updated', $assignment, $oldValues, $newValues);

        return redirect()->route('salesman-assignments.show', $assignment)->with('status', 'Salesman assignment updated.');
    }

    public function destroy(SalesmanAssignment $assignment): RedirectResponse
    {
        $this->authorize('deactivate', $assignment);

        $tenantId = TenantContext::currentId();
        abort_if($tenantId === null || $assignment->tenant_id !== $tenantId, 403);

        $assignment->delete();

        AuditLogger::log('salesman.assignment_ended', $assignment, [], [
            'salesman_id' => $assignment->salesman_id,
            'effective_to' => $assignment->effective_to?->toDateString(),
        ]);

        return redirect()->route('salesman-assignments.index')->with('status', 'Salesman assignment deleted.');
    }
}
