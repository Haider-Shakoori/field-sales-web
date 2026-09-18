<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBranchRequest;
use App\Http\Requests\UpdateBranchRequest;
use App\Models\Branch;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BranchController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Branch::class);

        return view('admin.branches.index', [
            'branches' => Branch::withCount('users')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Branch::class);

        return view('admin.branches.create');
    }

    public function store(StoreBranchRequest $request, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validated();

        $branch = Branch::create([
            'name' => $validated['name'],
            'code' => strtoupper($validated['code']),
            'is_active' => (bool) $validated['is_active'],
        ]);

        $audit->record('branch.created', $branch, [], $branch->only([
            'name',
            'code',
            'is_active',
        ]));

        return redirect()
            ->route('admin.branches.index')
            ->with('status', 'Branch created.');
    }

    public function edit(Branch $branch): View
    {
        Gate::authorize('update', $branch);

        return view('admin.branches.edit', compact('branch'));
    }

    public function update(
        UpdateBranchRequest $request,
        Branch $branch,
        AuditLogger $audit,
    ): RedirectResponse {
        $before = $branch->only(['name', 'code', 'is_active']);
        $validated = $request->validated();

        $branch->update([
            'name' => $validated['name'],
            'code' => strtoupper($validated['code']),
            'is_active' => (bool) $validated['is_active'],
        ]);

        $audit->record(
            'branch.updated',
            $branch,
            $before,
            $branch->only(['name', 'code', 'is_active'])
        );

        return redirect()
            ->route('admin.branches.index')
            ->with('status', 'Branch updated.');
    }

    public function destroy(Branch $branch, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('delete', $branch);

        if ($branch->users()->exists()) {
            throw ValidationException::withMessages([
                'branch' => 'This branch is assigned to one or more users.',
            ]);
        }

        $before = $branch->only(['name', 'code', 'is_active']);

        $audit->record('branch.deleted', $branch, $before);
        $branch->delete();

        return redirect()
            ->route('admin.branches.index')
            ->with('status', 'Branch deleted.');
    }
}
