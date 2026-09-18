<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSupervisorRequest;
use App\Http\Requests\UpdateSupervisorRequest;
use App\Models\Supervisor;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SupervisorController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Supervisor::class);

        return view('admin.supervisors.index', [
            'supervisors' => Supervisor::with(['user.branch', 'assignments' => fn ($query) => $query->current()->with('branch')])
                ->orderBy('employee_code')
                ->paginate(25),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Supervisor::class);

        return view('admin.supervisors.create', [
            'users' => User::whereDoesntHave('supervisor')
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(StoreSupervisorRequest $request, AuditLogger $audit): RedirectResponse
    {
        $supervisor = Supervisor::create($request->validated());

        $audit->record('supervisor.created', $supervisor, [], $this->auditValues($supervisor));

        return redirect()
            ->route('admin.supervisors.index')
            ->with('status', 'Supervisor profile created.');
    }

    public function show(Supervisor $supervisor): View
    {
        Gate::authorize('view', $supervisor);

        return view('admin.supervisors.show', [
            'supervisor' => $supervisor->load([
                'user.branch',
                'assignments' => fn ($query) => $query->with('branch')->latest('effective_from'),
                'supervisedSalesmanAssignments' => fn ($query) => $query->with(['salesman', 'branch'])->latest('effective_from'),
            ]),
        ]);
    }

    public function edit(Supervisor $supervisor): View
    {
        Gate::authorize('update', $supervisor);

        return view('admin.supervisors.edit', compact('supervisor'));
    }

    public function update(
        UpdateSupervisorRequest $request,
        Supervisor $supervisor,
        AuditLogger $audit,
    ): RedirectResponse {
        $before = $this->auditValues($supervisor);
        $supervisor->update($request->validated());

        $audit->record('supervisor.updated', $supervisor, $before, $this->auditValues($supervisor));

        return redirect()
            ->route('admin.supervisors.show', $supervisor)
            ->with('status', 'Supervisor profile updated.');
    }

    public function destroy(Supervisor $supervisor, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('delete', $supervisor);

        if ($supervisor->assignments()->exists() || $supervisor->supervisedSalesmanAssignments()->exists()) {
            throw ValidationException::withMessages([
                'supervisor' => 'This supervisor has assignment history. Deactivate the profile instead.',
            ]);
        }

        $before = $this->auditValues($supervisor);
        $audit->record('supervisor.deleted', $supervisor, $before);
        $supervisor->delete();

        return redirect()
            ->route('admin.supervisors.index')
            ->with('status', 'Supervisor profile deleted.');
    }

    private function auditValues(Supervisor $supervisor): array
    {
        return [
            'user_id' => $supervisor->user_id,
            'employee_code' => $supervisor->employee_code,
            'first_name' => $supervisor->first_name,
            'last_name' => $supervisor->last_name,
            'is_active' => $supervisor->is_active,
        ];
    }
}
