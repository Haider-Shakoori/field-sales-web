<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BranchController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Branch::class);

        abort_if(! TenantContext::hasContext(), 403, 'Branches require a tenant context.');

        $branches = Branch::orderBy('name')->paginate(20);

        return view('pages.branches.index', compact('branches'));
    }

    public function create(): View
    {
        $this->authorize('create', Branch::class);

        return view('pages.branches.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Branch::class);

        $tenantId = TenantContext::currentId();

        abort_if($tenantId === null, 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:30', 'unique:branches,code,NULL,NULL,tenant_id,'.$tenantId],
            'address' => ['nullable', 'string', 'max:1000'],
            'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:50'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $branch = Branch::create([
            ...$data,
            'tenant_id' => $tenantId,
            'is_active' => $request->boolean('is_active'),
        ]);

        AuditLogger::log('branch.created', $branch, [], [
            'name' => $branch->name,
            'code' => $branch->code,
        ]);

        return redirect()->route('branches.index')->with('status', 'Branch created.');
    }

    public function show(Branch $branch): View
    {
        $this->authorize('view', $branch);

        return view('pages.branches.show', compact('branch'));
    }

    public function update(Request $request, Branch $branch): RedirectResponse
    {
        $this->authorize('update', $branch);

        $tenantId = TenantContext::currentId();

        abort_if($tenantId === null || $branch->tenant_id !== $tenantId, 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:30', 'unique:branches,code,'.$branch->id.',id,tenant_id,'.$tenantId],
            'address' => ['nullable', 'string', 'max:1000'],
            'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:50'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $branch->update($data + ['is_active' => $request->boolean('is_active')]);

        AuditLogger::log('branch.updated', $branch, [], $data);

        return back()->with('status', 'Branch updated.');
    }

    public function destroy(Branch $branch): RedirectResponse
    {
        $this->authorize('delete', $branch);

        abort_if(TenantContext::currentId() === null || $branch->tenant_id !== TenantContext::currentId(), 403);

        $branch->delete();

        AuditLogger::log('branch.deleted', $branch, [], []);

        return back()->with('status', 'Branch deleted.');
    }
}
