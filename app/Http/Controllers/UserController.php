<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', User::class);

        $query = User::query()->with('tenant');

        if (TenantContext::hasContext()) {
            $query->where('tenant_id', TenantContext::currentId());
        } else {
            $query->whereNull('tenant_id');
        }

        $users = $query->orderBy('name')->paginate(20);

        return view('pages.users.index', compact('users'));
    }

    public function create(): View
    {
        $this->authorize('create', User::class);

        $roles = $this->companyRoles();

        return view('pages.users.create', compact('roles'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', User::class);

        $tenantId = TenantContext::currentId();

        abort_if($tenantId === null, 403, 'A tenant context is required to create users.');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'role' => ['required', 'in:'.implode(',', array_keys(config('tenancy.roles')))],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
            'tenant_id' => $tenantId,
            'is_active' => true,
        ]);

        $user->assignRole($data['role'], $tenantId);

        AuditLogger::log('user.created', $user, [], [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $data['role'],
        ]);

        return redirect()->route('users.index')->with('status', 'User created.');
    }

    public function show(User $user): View
    {
        $this->authorize('view', $user);

        return view('pages.users.show', compact('user'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $tenantId = TenantContext::currentId();

        abort_if($tenantId === null || $user->tenant_id !== $tenantId, 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'phone' => ['nullable', 'string', 'max:50'],
            'role' => ['required', 'in:'.implode(',', array_keys(config('tenancy.roles')))],
        ]);

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
        ])->save();

        if (! $user->hasRole($data['role'])) {
            $user->assignRole($data['role'], $tenantId);
        }

        AuditLogger::log('user.updated', $user, [], $this->trackedChanges($user, $data));

        return back()->with('status', 'User updated.');
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
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function trackedChanges(User $user, array $data): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $data['role'],
        ];
    }

    /**
     * Available assignable roles.
     *
     * @return array<string, string>
     */
    private function companyRoles(): array
    {
        return config('tenancy.roles');
    }
}
