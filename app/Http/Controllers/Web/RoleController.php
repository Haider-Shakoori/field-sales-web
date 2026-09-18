<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Role::class);

        return view('admin.roles.index', [
            'roles' => Role::withCount('users')
                ->with('permissions')
                ->orderByDesc('is_system')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Role::class);

        return view('admin.roles.create', [
            'permissions' => $this->permissions(),
        ]);
    }

    public function store(StoreRoleRequest $request, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validated();

        $role = Role::create([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'is_system' => false,
        ]);

        $role->permissions()->sync($validated['permission_ids'] ?? []);

        $audit->record('role.created', $role, [], $this->auditValues($role));

        return redirect()
            ->route('admin.roles.index')
            ->with('status', 'Role created.');
    }

    public function edit(Role $role): View
    {
        Gate::authorize('update', $role);

        return view('admin.roles.edit', [
            'role' => $role->load('permissions'),
            'permissions' => $this->permissions(),
        ]);
    }

    public function update(
        UpdateRoleRequest $request,
        Role $role,
        AuditLogger $audit,
    ): RedirectResponse {
        $before = $this->auditValues($role->load('permissions'));
        $validated = $request->validated();

        $role->update([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
        ]);
        $role->permissions()->sync($validated['permission_ids'] ?? []);
        $role->load('permissions');

        $audit->record('role.updated', $role, $before, $this->auditValues($role));

        return redirect()
            ->route('admin.roles.index')
            ->with('status', 'Role updated.');
    }

    public function destroy(Role $role, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('delete', $role);

        if ($role->users()->exists()) {
            throw ValidationException::withMessages([
                'role' => 'This role is assigned to one or more users.',
            ]);
        }

        $before = $this->auditValues($role->load('permissions'));

        $audit->record('role.deleted', $role, $before);
        $role->delete();

        return redirect()
            ->route('admin.roles.index')
            ->with('status', 'Role deleted.');
    }

    private function permissions()
    {
        return Permission::orderBy('group')
            ->orderBy('name')
            ->get()
            ->groupBy('group');
    }

    private function auditValues(Role $role): array
    {
        return [
            'name' => $role->name,
            'slug' => $role->slug,
            'is_system' => $role->is_system,
            'permissions' => $role->permissions->pluck('slug')->values()->all(),
        ];
    }
}
