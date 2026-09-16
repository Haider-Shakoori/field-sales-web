<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSupervisorAssignmentRequest;
use App\Http\Requests\UpdateSupervisorAssignmentRequest;
use App\Models\Branch;
use App\Models\Supervisor;
use App\Models\SupervisorAssignment;
use App\Models\Territory;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupervisorAssignmentController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', SupervisorAssignment::class);

        $query = SupervisorAssignment::query()->with(['supervisor', 'branch', 'territory']);

        if (TenantContext::hasContext()) {
            $query->where('tenant_id', TenantContext::currentId());
        }

        if ($search = $request->string('search')->trim()->toString()) {
            $query->whereHas('supervisor', fn ($q) => $q
                ->where('employee_code', 'like', "%{$search}%")
                ->orWhere('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%"));
        }

        if ($supervisorId = $request->string('supervisor_id')->toString()) {
            $query->where('supervisor_id', $supervisorId);
        }

        if ($territoryId = $request->string('territory_id')->toString()) {
            $query->where('territory_id', $territoryId);
        }

        $assignments = $query->orderByDesc('effective_from')->paginate(20)->withQueryString();

        $supervisors = Supervisor::query()
            ->where('tenant_id', TenantContext::currentId())
            ->active()
            ->orderBy('first_name')
            ->get();

        return view('pages.supervisor-assignments.index', compact('assignments', 'supervisors'));
    }

    public function create(): View
    {
        $this->authorize('create', SupervisorAssignment::class);

        $tenantId = TenantContext::currentId();
        abort_if($tenantId === null, 403, 'A tenant context is required to create supervisor assignments.');

        $supervisors = Supervisor::query()
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

        return view('pages.supervisor-assignments.create', compact('supervisors', 'branches', 'territories'));
    }

    public function store(StoreSupervisorAssignmentRequest $request): RedirectResponse
    {
        $tenantId = TenantContext::currentId();
        abort_if($tenantId === null, 403, 'A tenant context is required to create supervisor assignments.');

        $data = $request->validated();

        // Check for duplicate active assignment (same supervisor + branch + territory)
        $duplicate = SupervisorAssignment::where('tenant_id', $tenantId)
            ->where('supervisor_id', $data['supervisor_id'])
            ->where('branch_id', $data['branch_id'])
            ->where('territory_id', $data['territory_id'])
            ->where(function ($q) use ($data) {
                $q->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $data['effective_from']);
            })
            ->where('effective_from', '<=', $data['effective_to'] ?? '9999-12-31')
            ->exists();

        if ($duplicate) {
            return back()->withErrors(['supervisor_id' => 'This supervisor already has an active assignment for the selected branch and territory during this period.'])->withInput();
        }

        $assignment = SupervisorAssignment::create([
            'tenant_id' => $tenantId,
            'supervisor_id' => $data['supervisor_id'],
            'branch_id' => $data['branch_id'],
            'territory_id' => $data['territory_id'],
            'effective_from' => $data['effective_from'],
            'effective_to' => $data['effective_to'] ?? null,
        ]);

        return redirect()->route('supervisor-assignments.show', $assignment)->with('status', 'Supervisor assignment created.');
    }

    public function show(SupervisorAssignment $assignment): View
    {
        $this->authorize('view', $assignment);

        $assignment->load(['supervisor', 'branch', 'territory']);

        return view('pages.supervisor-assignments.show', compact('assignment'));
    }

    public function edit(SupervisorAssignment $assignment): View
    {
        $this->authorize('update', $assignment);

        $tenantId = TenantContext::currentId();
        abort_if($tenantId === null || $assignment->tenant_id !== $tenantId, 403);

        $supervisors = Supervisor::query()
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

        return view('pages.supervisor-assignments.edit', compact('assignment', 'supervisors', 'branches', 'territories'));
    }

    public function update(UpdateSupervisorAssignmentRequest $request, SupervisorAssignment $assignment): RedirectResponse
    {
        abort_if(TenantContext::currentId() === null || $assignment->tenant_id !== TenantContext::currentId(), 403);

        $data = $request->validated();

        // Check for duplicate active assignment (same supervisor + branch + territory)
        $duplicate = SupervisorAssignment::where('tenant_id', $tenantId)
            ->where('supervisor_id', $data['supervisor_id'])
            ->where('branch_id', $data['branch_id'])
            ->where('territory_id', $data['territory_id'])
            ->where('id', '!=', $assignment->id)
            ->where(function ($q) use ($data) {
                $q->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $data['effective_from']);
            })
            ->where('effective_from', '<=', $data['effective_to'] ?? '9999-12-31')
            ->exists();

        if ($duplicate) {
            return back()->withErrors(['supervisor_id' => 'This supervisor already has an active assignment for the selected branch and territory during this period.'])->withInput();
        }

        $oldValues = [
            'supervisor_id' => $assignment->supervisor_id,
            'branch_id' => $assignment->branch_id,
            'territory_id' => $assignment->territory_id,
            'effective_from' => $assignment->effective_from?->toDateString(),
            'effective_to' => $assignment->effective_to?->toDateString(),
        ];

        $assignment->update([
            'supervisor_id' => $data['supervisor_id'],
            'branch_id' => $data['branch_id'],
            'territory_id' => $data['territory_id'],
            'effective_from' => $data['effective_from'],
            'effective_to' => $data['effective_to'] ?? null,
        ]);

        $newValues = [
            'supervisor_id' => $assignment->supervisor_id,
            'branch_id' => $assignment->branch_id,
            'territory_id' => $assignment->territory_id,
            'effective_from' => $assignment->effective_from?->toDateString(),
            'effective_to' => $assignment->effective_to?->toDateString(),
        ];

        AuditLogger::log('supervisor.assignment_updated', $assignment, $oldValues, $newValues);

        return redirect()->route('supervisor-assignments.show', $assignment)->with('status', 'Supervisor assignment updated.');
    }

    public function destroy(SupervisorAssignment $assignment): RedirectResponse
    {
        $this->authorize('deactivate', $assignment);

        $tenantId = TenantContext::currentId();
        abort_if($tenantId === null || $assignment->tenant_id !== $tenantId, 403);

        $assignment->delete();

        AuditLogger::log('supervisor.assignment_ended', $assignment, [], [
            'supervisor_id' => $assignment->supervisor_id,
            'effective_to' => $assignment->effective_to?->toDateString(),
        ]);

        return redirect()->route('supervisor-assignments.index')->with('status', 'Supervisor assignment deleted.');
    }
}
