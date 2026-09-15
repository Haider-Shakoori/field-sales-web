<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class RoleController extends Controller
{
    private const ACTION_ORDER = ['view', 'create', 'update', 'delete', 'manage'];

    public function index(): View
    {
        $this->authorize('viewAny', Role::class);

        $roles = Role::query()
            ->visibleIn()
            ->with(['permissions' => fn ($query) => $query->orderBy('name')])
            ->orderBy('name')
            ->get()
            ->when(TenantContext::hasContext(), fn ($collection) => $collection->filter(
                fn (Role $role) => $role->name !== 'super_admin',
            ))
            ->sortBy(fn (Role $role) => $role->isSystem() ? 0 : 1)
            ->values()
            ->map(function (Role $role): array {
                return [
                    'role' => $role,
                    'user_count' => $role->userCount(),
                ];
            });

        return view('pages.settings.roles', compact('roles'));
    }

    public function show(Role $role): View
    {
        $this->authorize('view', $role);

        $role->load(['permissions' => fn ($query) => $query->orderBy('name')]);
        $matrix = $this->permissionMatrix();

        return view('pages.settings.roles.show', compact('role', 'matrix'));
    }

    public function create(): View
    {
        $this->authorize('create', Role::class);

        $matrix = $this->permissionMatrix();

        return view('pages.settings.roles.create', compact('matrix'));
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $tenantId = TenantContext::currentId();

        if ($tenantId === null && ! $request->user()->isSuperAdmin()) {
            abort(403);
        }

        $role = Role::create([
            'name' => $request->validated('name'),
            'guard_name' => 'web',
            'tenant_id' => $tenantId,
        ]);

        $role->permissions()->sync($request->validated('permissions'));

        AuditLogger::log('role.created', $role, [], [
            'name' => $role->name,
            'permissions' => $role->permissions()->pluck('name')->all(),
        ]);

        return redirect()->route('settings.roles.index')->with('status', 'Role created.');
    }

    public function edit(Role $role): View
    {
        $this->authorize('update', $role);

        $role->load(['permissions' => fn ($query) => $query->orderBy('name')]);
        $matrix = $this->permissionMatrix();

        return view('pages.settings.roles.edit', compact('role', 'matrix'));
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        $this->authorize('update', $role);

        $oldPermissions = $role->permissions()->pluck('name')->sort()->values()->all();

        $role->update(['name' => $request->validated('name')]);
        $role->permissions()->sync($request->validated('permissions'));

        $newPermissions = $role->permissions()->pluck('name')->sort()->values()->all();

        if ($oldPermissions !== $newPermissions) {
            AuditLogger::log('role.permissions.changed', $role, [
                'permissions' => $oldPermissions,
            ], [
                'permissions' => $newPermissions,
            ]);
        }

        AuditLogger::log('role.updated', $role, ['name' => $request->old('name')], [
            'name' => $role->name,
        ]);

        return redirect()->route('settings.roles.index')->with('status', 'Role updated.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        $this->authorize('delete', $role);

        abort_if($role->userCount() > 0, 409, 'Cannot delete a role that is assigned to users.');

        $role->permissions()->detach();
        $role->delete();

        AuditLogger::log('role.deleted', $role, [], []);

        return redirect()->route('settings.roles.index')->with('status', 'Role deleted.');
    }

    /**
     * Permission matrix grouped by resource with an ordered action grid.
     *
     * @return array<string, array{resource: string, actions: array<string, ?int>}>
     */
    protected function permissionMatrix(): array
    {
        $catalog = Permission::orderBy('name')->get();

        if (! request()->user()->isSuperAdmin()) {
            $catalog = $catalog->whereNotIn('name', config('tenancy.platform_permissions', []));
        }

        $matrix = $catalog->groupBy(fn (Permission $permission) => str($permission->name)->before(':'))
            ->map(fn (Collection $permissions, string $resource) => [
                'resource' => $resource,
                'actions' => collect($this->actionOrder($permissions))
                    ->mapWithKeys(fn (string $action) => [
                        $action => $permissions->firstWhere(fn (Permission $permission) => str($permission->name)->after(':')->value() === $action)?->id,
                    ])
                    ->all(),
            ])
            ->sortBy('resource')
            ->values()
            ->all();

        return $matrix;
    }

    /**
     * @param  Collection<int, Permission>  $permissions
     * @return list<string>
     */
    protected function actionOrder(Collection $permissions): array
    {
        return $permissions->map(fn (Permission $permission) => str($permission->name)->after(':')->value())
            ->sortBy(fn (string $action) => array_search($action, self::ACTION_ORDER, true) !== false
                ? array_search($action, self::ACTION_ORDER, true)
                : count(self::ACTION_ORDER))
            ->values()
            ->all();
    }
}
