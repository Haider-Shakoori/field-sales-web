<?php

namespace App\Policies;

use App\Models\SalesmanAssignment;
use App\Models\User;

class SalesmanAssignmentPolicy extends TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->can($user, 'sales-team:view');
    }

    public function view(User $user, SalesmanAssignment $assignment): bool
    {
        return $this->can($user, 'sales-team:view')
            && $this->owns($user, $assignment);
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'sales-team:manage');
    }

    public function update(User $user, SalesmanAssignment $assignment): bool
    {
        return $this->can($user, 'sales-team:manage')
            && $this->owns($user, $assignment);
    }

    public function delete(User $user, SalesmanAssignment $assignment): bool
    {
        return $this->update($user, $assignment);
    }
}
