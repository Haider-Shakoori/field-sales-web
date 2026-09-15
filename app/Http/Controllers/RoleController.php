<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use App\Support\Tenancy\TenantContext;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Role::class);

        $roles = Role::with(['permissions' => fn ($query) => $query->orderBy('name')])
            ->orderBy('name')
            ->get()
            ->filter(fn (Role $role) => $this->roleVisibleInContext($role));

        $permissions = Permission::orderBy('name')->get();

        return view('pages.settings.roles', compact('roles', 'permissions'));
    }

    /**
     * In a tenant context, hide platform roles (super_admin).
     */
    private function roleVisibleInContext(Role $role): bool
    {
        if (TenantContext::hasContext()) {
            return $role->name !== 'super_admin';
        }

        return true;
    }
}
