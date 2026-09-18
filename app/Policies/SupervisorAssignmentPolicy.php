<?php

namespace App\Policies;

use App\Models\SupervisorAssignment;
use App\Models\User;

class SupervisorAssignmentPolicy extends TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->can($user, 'sales-team:view');
    }

    public function view(User $user, SupervisorAssignment $assignment): bool
    {
        return $this->can($user, 'sales-team:view')
            && $this->owns($user, $assignment);
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'sales-team:manage');
    }

    public function update(User $user, SupervisorAssignment $assignment): bool
    {
        return $this->can($user, 'sales-team:manage')
            && $this->owns($user, $assignment);
    }

    public function delete(User $user, SupervisorAssignment $assignment): bool
    {
        return $this->update($user, $assignment);
    }
}
