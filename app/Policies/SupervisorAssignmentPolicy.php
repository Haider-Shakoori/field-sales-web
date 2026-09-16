<?php

namespace App\Policies;

use App\Models\SupervisorAssignment;
use App\Models\User;

class SupervisorAssignmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('assignments:view');
    }

    public function view(User $user, SupervisorAssignment $assignment): bool
    {
        if (! $user->hasPermission('assignments:view')) {
            return false;
        }

        return $assignment->tenant_id === $user->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('assignments:manage');
    }

    public function update(User $user, SupervisorAssignment $assignment): bool
    {
        if (! ($user->hasPermission('assignments:manage') && $assignment->tenant_id === $user->tenant_id)) {
            return false;
        }

        return true;
    }

    public function deactivate(User $user, SupervisorAssignment $assignment): bool
    {
        if (! ($user->hasPermission('assignments:manage') && $assignment->tenant_id === $user->tenant_id)) {
            return false;
        }

        return true;
    }
}
