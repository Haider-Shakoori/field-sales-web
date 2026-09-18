<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSupervisorAssignmentRequest;
use App\Http\Requests\UpdateSupervisorAssignmentRequest;
use App\Models\Branch;
use App\Models\Supervisor;
use App\Models\SupervisorAssignment;
use App\Models\Territory;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SupervisorAssignmentController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', SupervisorAssignment::class);

        return view('admin.supervisor-assignments.index', [
            'assignments' => SupervisorAssignment::with(['supervisor', 'branch', 'territory'])
                ->latest('effective_from')
                ->paginate(30),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', SupervisorAssignment::class);

        return view('admin.supervisor-assignments.create', $this->formData());
    }

    public function store(
        StoreSupervisorAssignmentRequest $request,
        AuditLogger $audit,
    ): RedirectResponse {
        $validated = $request->validated();

        if ($this->overlaps(
            (int) $validated['supervisor_id'],
            $validated['branch_id'] ?? null,
            $validated['effective_from'],
            $validated['effective_to'] ?? null,
        )) {
            throw ValidationException::withMessages([
                'effective_from' => 'This supervisor already has an overlapping assignment for that branch.',
            ]);
        }

        $assignment = SupervisorAssignment::create([
            ...$validated,
            'created_by' => $request->user()->id,
        ]);

        $audit->record('supervisor_assignment.created', $assignment, [], $this->auditValues($assignment));

        return redirect()
            ->route('admin.supervisor-assignments.show', $assignment)
            ->with('status', 'Supervisor assignment created.');
    }

    public function show(SupervisorAssignment $assignment): View
    {
        Gate::authorize('view', $assignment);

        return view('admin.supervisor-assignments.show', [
            'assignment' => $assignment->load(['supervisor.user', 'branch', 'territory', 'creator']),
        ]);
    }

    public function edit(SupervisorAssignment $assignment): View
    {
        Gate::authorize('update', $assignment);

        return view('admin.supervisor-assignments.edit', [
            ...$this->formData(),
            'assignment' => $assignment,
        ]);
    }

    public function update(
        UpdateSupervisorAssignmentRequest $request,
        SupervisorAssignment $assignment,
        AuditLogger $audit,
    ): RedirectResponse {
        $validated = $request->validated();

        if ($this->overlaps(
            $assignment->supervisor_id,
            $validated['branch_id'] ?? null,
            $validated['effective_from'],
            $validated['effective_to'] ?? null,
            $assignment->id,
        )) {
            throw ValidationException::withMessages([
                'effective_from' => 'This supervisor already has an overlapping assignment for that branch.',
            ]);
        }

        $before = $this->auditValues($assignment);
        $assignment->update($validated);

        $audit->record('supervisor_assignment.updated', $assignment, $before, $this->auditValues($assignment));

        return redirect()
            ->route('admin.supervisor-assignments.show', $assignment)
            ->with('status', 'Supervisor assignment updated.');
    }

    public function destroy(
        SupervisorAssignment $assignment,
        AuditLogger $audit,
    ): RedirectResponse {
        Gate::authorize('delete', $assignment);

        $before = $this->auditValues($assignment);
        $audit->record('supervisor_assignment.deleted', $assignment, $before);
        $assignment->delete();

        return redirect()
            ->route('admin.supervisor-assignments.index')
            ->with('status', 'Supervisor assignment deleted.');
    }

    private function overlaps(
        int $supervisorId,
        ?int $branchId,
        string $start,
        ?string $end,
        ?int $ignoreId = null,
    ): bool {
        return SupervisorAssignment::where('supervisor_id', $supervisorId)
            ->when(
                $branchId === null,
                fn ($query) => $query->whereNull('branch_id'),
                fn ($query) => $query->where('branch_id', $branchId)
            )
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->where(function ($query) use ($start): void {
                $query->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $start);
            })
            ->when(
                $end,
                fn ($query) => $query->whereDate('effective_from', '<=', $end)
            )
            ->exists();
    }

    private function formData(): array
    {
        return [
            'supervisors' => Supervisor::active()->orderBy('employee_code')->get(),
            'branches' => Branch::active()->orderBy('name')->get(),
            'territories' => Territory::active()->orderBy('name')->get(),
        ];
    }

    private function auditValues(SupervisorAssignment $assignment): array
    {
        return [
            'supervisor_id' => $assignment->supervisor_id,
            'branch_id' => $assignment->branch_id,
            'territory_id' => $assignment->territory_id,
            'effective_from' => $assignment->effective_from?->toDateString(),
            'effective_to' => $assignment->effective_to?->toDateString(),
        ];
    }
}
