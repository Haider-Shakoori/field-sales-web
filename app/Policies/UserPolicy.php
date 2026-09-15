<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('users:view');
    }

    public function view(User $user, User $subject): bool
    {
        return $subject->tenant_id === $user->tenant_id
            && ($user->hasPermission('users:view') || $user->hasPermission('users:manage'));
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('users:manage');
    }

    public function update(User $user, User $subject): bool
    {
        if (! ($user->hasPermission('users:manage') && $subject->tenant_id === $user->tenant_id)) {
            return false;
        }

        // Self-escalation prevention: a user may not change their own role.
        $requestedRole = request()->input('role');
        if ($subject->is($user) && is_string($requestedRole) && $requestedRole !== $subject->role) {
            return false;
        }

        return ! $this->isLastActiveOwner($subject);
    }

    public function deactivate(User $user, User $subject): bool
    {
        if (! ($user->hasPermission('users:manage')
            && $subject->tenant_id === $user->tenant_id
            && $subject->isNot($user))) {
            return false;
        }

        return ! $this->isLastActiveOwner($subject);
    }

    /**
     * The subject holds the sole active owner role for the tenant.
     */
    protected function isLastActiveOwner(User $subject): bool
    {
        if (! $subject->hasRole('owner')) {
            return false;
        }

        $activeOwners = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->join('users', 'users.id', '=', 'model_has_roles.user_id')
            ->where('roles.name', 'owner')
            ->where('model_has_roles.tenant_id', $subject->tenant_id)
            ->where('users.is_active', true)
            ->whereNull('users.deleted_at')
            ->count();

        return $activeOwners === 1;
    }
}
