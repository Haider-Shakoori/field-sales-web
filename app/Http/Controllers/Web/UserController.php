<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', User::class);

        return view('admin.users.index', [
            'users' => User::with(['branch', 'roles'])
                ->orderBy('name')
                ->paginate(25),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', User::class);

        return view('admin.users.create', $this->formData());
    }

    public function store(StoreUserRequest $request, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validated();
        $role = Role::findOrFail($validated['role_id']);

        $user = User::create([
            'uuid' => (string) Str::uuid(),
            'branch_id' => $validated['branch_id'] ?? null,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $role->slug,
            'is_active' => (bool) $validated['is_active'],
        ]);

        $user->syncPrimaryRole($role);

        $audit->record('user.created', $user, [], $this->auditValues($user, $role));

        return redirect()
            ->route('admin.users.index')
            ->with('status', 'User created.');
    }

    public function edit(User $user): View
    {
        Gate::authorize('update', $user);

        return view('admin.users.edit', [
            ...$this->formData(),
            'managedUser' => $user->load('roles'),
        ]);
    }

    public function update(
        UpdateUserRequest $request,
        User $user,
        AuditLogger $audit,
    ): RedirectResponse {
        $validated = $request->validated();
        $role = Role::findOrFail($validated['role_id']);

        if ($request->user()->is($user) && ! (bool) $validated['is_active']) {
            throw ValidationException::withMessages([
                'is_active' => 'You cannot deactivate your own account.',
            ]);
        }

        $beforeRole = $user->roles()->first();
        $before = $this->auditValues($user, $beforeRole);

        $changes = [
            'branch_id' => $validated['branch_id'] ?? null,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'is_active' => (bool) $validated['is_active'],
        ];

        if (! empty($validated['password'])) {
            $changes['password'] = Hash::make($validated['password']);
        }

        $user->update($changes);
        $user->syncPrimaryRole($role);

        $audit->record('user.updated', $user, $before, $this->auditValues($user, $role));

        return redirect()
            ->route('admin.users.index')
            ->with('status', 'User updated.');
    }

    public function destroy(User $user, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('delete', $user);

        if ($user->salesman()->exists() || $user->supervisor()->exists()) {
            throw ValidationException::withMessages([
                'user' => 'This user has a sales-team profile and cannot be deleted. Deactivate the account instead.',
            ]);
        }

        $beforeRole = $user->roles()->first();
        $before = $this->auditValues($user, $beforeRole);

        $audit->record('user.deleted', $user, $before);
        $user->tokens()->delete();
        $user->delete();

        return redirect()
            ->route('admin.users.index')
            ->with('status', 'User deleted.');
    }

    private function formData(): array
    {
        return [
            'roles' => Role::orderBy('name')->get(),
            'branches' => Branch::active()->orderBy('name')->get(),
        ];
    }

    private function auditValues(User $user, ?Role $role): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'branch_id' => $user->branch_id,
            'role' => $role?->slug,
            'is_active' => $user->is_active,
        ];
    }
}
