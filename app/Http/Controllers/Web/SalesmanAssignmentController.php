<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSalesmanAssignmentRequest;
use App\Http\Requests\UpdateSalesmanAssignmentRequest;
use App\Models\Branch;
use App\Models\Salesman;
use App\Models\SalesmanAssignment;
use App\Models\Supervisor;
use App\Services\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SalesmanAssignmentController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', SalesmanAssignment::class);

        return view('admin.salesman-assignments.index', [
            'assignments' => SalesmanAssignment::with(['salesman', 'branch', 'supervisor'])
                ->latest('effective_from')
                ->paginate(30),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', SalesmanAssignment::class);

        return view('admin.salesman-assignments.create', $this->formData());
    }

    public function store(
        StoreSalesmanAssignmentRequest $request,
        AuditLogger $audit,
    ): RedirectResponse {
        $validated = $request->validated();
        $start = CarbonImmutable::parse($validated['effective_from']);
        $end = isset($validated['effective_to'])
            ? CarbonImmutable::parse($validated['effective_to'])
            : null;

        $query = SalesmanAssignment::where('salesman_id', $validated['salesman_id']);

        $sameStart = (clone $query)
            ->whereDate('effective_from', $start)
            ->exists();

        if ($sameStart) {
            throw ValidationException::withMessages([
                'effective_from' => 'An assignment already starts on this date.',
            ]);
        }

        $prior = (clone $query)
            ->whereDate('effective_from', '<', $start)
            ->where(function ($window) use ($start): void {
                $window->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $start);
            })
            ->latest('effective_from')
            ->first();

        if ($prior) {
            $before = $this->auditValues($prior);
            $prior->update(['effective_to' => $start->subDay()->toDateString()]);
            $audit->record('salesman_assignment.ended', $prior, $before, $this->auditValues($prior));
        }

        $futureOverlap = (clone $query)
            ->whereDate('effective_from', '>', $start)
            ->when($end, fn ($future) => $future->whereDate('effective_from', '<=', $end))
            ->when(! $end, fn ($future) => $future)
            ->exists();

        if ($futureOverlap) {
            throw ValidationException::withMessages([
                'effective_to' => 'This window overlaps a future assignment.',
            ]);
        }

        $assignment = SalesmanAssignment::create([
            ...$validated,
            'created_by' => $request->user()->id,
        ]);

        $audit->record('salesman_assignment.created', $assignment, [], $this->auditValues($assignment));

        return redirect()
            ->route('admin.salesman-assignments.show', $assignment)
            ->with('status', 'Salesman assignment created.');
    }

    public function show(SalesmanAssignment $assignment): View
    {
        Gate::authorize('view', $assignment);

        return view('admin.salesman-assignments.show', [
            'assignment' => $assignment->load(['salesman.user', 'branch', 'supervisor.user', 'creator']),
        ]);
    }

    public function edit(SalesmanAssignment $assignment): View
    {
        Gate::authorize('update', $assignment);

        return view('admin.salesman-assignments.edit', [
            ...$this->formData(),
            'assignment' => $assignment,
        ]);
    }

    public function update(
        UpdateSalesmanAssignmentRequest $request,
        SalesmanAssignment $assignment,
        AuditLogger $audit,
    ): RedirectResponse {
        $validated = $request->validated();

        if ($this->overlaps(
            $assignment->salesman_id,
            $validated['effective_from'],
            $validated['effective_to'] ?? null,
            $assignment->id,
        )) {
            throw ValidationException::withMessages([
                'effective_from' => 'This assignment overlaps another historical window.',
            ]);
        }

        $before = $this->auditValues($assignment);
        $assignment->update($validated);

        $audit->record('salesman_assignment.updated', $assignment, $before, $this->auditValues($assignment));

        return redirect()
            ->route('admin.salesman-assignments.show', $assignment)
            ->with('status', 'Salesman assignment updated.');
    }

    public function destroy(
        SalesmanAssignment $assignment,
        AuditLogger $audit,
    ): RedirectResponse {
        Gate::authorize('delete', $assignment);

        $before = $this->auditValues($assignment);
        $audit->record('salesman_assignment.deleted', $assignment, $before);
        $assignment->delete();

        return redirect()
            ->route('admin.salesman-assignments.index')
            ->with('status', 'Salesman assignment deleted.');
    }

    private function overlaps(
        int $salesmanId,
        string $start,
        ?string $end,
        ?int $ignoreId = null,
    ): bool {
        return SalesmanAssignment::where('salesman_id', $salesmanId)
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
            'salesmen' => Salesman::active()->orderBy('employee_code')->get(),
            'branches' => Branch::active()->orderBy('name')->get(),
            'supervisors' => Supervisor::active()->orderBy('employee_code')->get(),
        ];
    }

    private function auditValues(SalesmanAssignment $assignment): array
    {
        return [
            'salesman_id' => $assignment->salesman_id,
            'branch_id' => $assignment->branch_id,
            'territory_id' => $assignment->territory_id,
            'route_id' => $assignment->route_id,
            'supervisor_id' => $assignment->supervisor_id,
            'effective_from' => $assignment->effective_from?->toDateString(),
            'effective_to' => $assignment->effective_to?->toDateString(),
        ];
    }
}
