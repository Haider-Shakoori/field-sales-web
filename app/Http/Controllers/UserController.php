<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $query = User::query()->with(['tenant', 'branch']);

        if (TenantContext::hasContext()) {
            $query->where('tenant_id', TenantContext::currentId());
        } else {
            $query->whereNull('tenant_id');
        }

        if ($search = $request->string('search')->trim()->toString()) {
            $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%"));
        }

        if ($role = $request->string('role')->toString()) {
            $query->where('role', $role);
        }

        if ($branch = $request->string('branch')->toString()) {
            $query->where('branch_id', $branch);
        }

        if ($status = $request->string('status')->toString()) {
            $query->where('is_active', $status === 'active');
        }

        $users = $query->orderBy('name')->paginate(20)->withQueryString();

        $roles = $this->assignableRoles();
        $branches = TenantContext::hasContext() ? Branch::orderBy('name')->get() : collect();

        return view('pages.users.index', compact('users', 'roles', 'branches'));
    }

    public function create(): View
    {
        $this->authorize('create', User::class);

        $roles = $this->assignableRoles();
        $branches = Branch::orderBy('name')->get();

        return view('pages.users.create', compact('roles', 'branches'));
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $tenantId = TenantContext::currentId();

        abort_if($tenantId === null, 403, 'A tenant context is required to create users.');

        $data = $request->validated();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
            'tenant_id' => $tenantId,
            'branch_id' => $data['branch_id'] ?? null,
            'is_active' => true,
        ]);

        $user->assignRole($data['role'], $tenantId);

        AuditLogger::log('user.created', $user, [], [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $data['role'],
            'branch_id' => $data['branch_id'] ?? null,
        ]);

        return redirect()->route('users.index')->with('status', 'User created.');
    }

    public function show(User $user): View
    {
        $this->authorize('view', $user);

        $user->load('branch');

        $roles = $this->assignableRoles();
        $branches = Branch::orderBy('name')->get();

        return view('pages.users.show', compact('user', 'roles', 'branches'));
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $tenantId = TenantContext::currentId();

        abort_if($tenantId === null || $user->tenant_id !== $tenantId, 403);

        $data = $request->validated();

        $oldRole = $user->role;
        $oldBranch = $user->branch_id;

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'branch_id' => $data['branch_id'] ?? null,
        ]);

        if ($data['password'] ?? null) {
            $user->password = $data['password'];
        }

        $user->save();

        if (! $user->hasRole($data['role'])) {
            $user->assignRole($data['role'], $tenantId);

            AuditLogger::log('user.role.changed', $user, ['role' => $oldRole], ['role' => $data['role']]);
        }

        if ($oldBranch !== ($data['branch_id'] ?? null)) {
            AuditLogger::log('user.branch.changed', $user, ['branch_id' => $oldBranch], ['branch_id' => $data['branch_id'] ?? null]);
        }

        AuditLogger::log('user.updated', $user, [], [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->role,
            'branch_id' => $user->branch_id,
        ]);

        return redirect()->route('users.show', $user)->with('status', 'User updated.');
    }

    public function deactivate(User $user): RedirectResponse
    {
        $this->authorize('deactivate', $user);

        abort_if(TenantContext::currentId() === null || $user->tenant_id !== TenantContext::currentId(), 403);

        $user->update(['is_active' => ! $user->is_active]);

        AuditLogger::log($user->is_active ? 'user.activated' : 'user.deactivated', $user, [], [
            'is_active' => $user->is_active,
        ]);

        return back()->with('status', $user->is_active ? 'User activated.' : 'User deactivated.');
    }

    /**
     * Roles assignable by the current user: company system roles plus custom
     * roles owned by the tenant. Platform roles are excluded.
     *
     * @return array<string, string>
     */
    private function assignableRoles(): array
    {
        $roles = collect(config('tenancy.roles'));

        Role::query()
            ->where('tenant_id', TenantContext::currentId())
            ->orderBy('name')
            ->get()
            ->each(fn (Role $role) => $roles->put($role->name, ucfirst(str_replace('_', ' ', $role->name))));

        return $roles->all();
    }
}
