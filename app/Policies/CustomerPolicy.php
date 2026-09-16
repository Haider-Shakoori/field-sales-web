<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('customers:view');
    }

    public function view(User $user, Customer $customer): bool
    {
        if (! $user->hasPermission('customers:view')) {
            return false;
        }

        // Tenant isolation is handled by the TenantScope
        // Additional branch/territory scoping based on role
        if ($user->hasRole('supervisor')) {
            return $this->supervisorCanView($user, $customer);
        }

        if ($user->hasRole('salesman')) {
            return $this->salesmanCanView($user, $customer);
        }

        return $customer->tenant_id === $user->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('customers:create');
    }

    public function update(User $user, Customer $customer): bool
    {
        if (! ($user->hasPermission('customers:update') && $customer->tenant_id === $user->tenant_id)) {
            return false;
        }

        // Salesmen can only update their own assigned customers
        if ($user->hasRole('salesman')) {
            return $this->salesmanCanUpdate($user, $customer);
        }

        return true;
    }

    public function deactivate(User $user, Customer $customer): bool
    {
        if (! ($user->hasPermission('customers:deactivate') && $customer->tenant_id === $user->tenant_id)) {
            return false;
        }

        return true;
    }

    private function supervisorCanView(User $user, Customer $customer): bool
    {
        $supervisor = $user->supervisorProfile;
        if (! $supervisor) {
            return false;
        }

        // Supervisor can view customers in their assigned territories
        $assignedTerritoryIds = $supervisor->currentAssignments()
            ->pluck('territory_id')
            ->filter()
            ->toArray();

        return in_array($customer->territory_id, $assignedTerritoryIds);
    }

    private function salesmanCanView(User $user, Customer $customer): bool
    {
        $salesman = $user->salesmanProfile;
        if (! $salesman) {
            return false;
        }

        // Salesman can view customers assigned to them or in their territory/route
        $currentAssignment = $salesman->currentAssignment();
        if (! $currentAssignment) {
            return false;
        }

        if ($customer->assigned_salesman_id === $salesman->id) {
            return true;
        }

        if ($currentAssignment->territory_id && $customer->territory_id === $currentAssignment->territory_id) {
            return true;
        }

        if ($currentAssignment->route_id && $customer->route_id === $currentAssignment->route_id) {
            return true;
        }

        return false;
    }

    private function salesmanCanUpdate(User $user, Customer $customer): bool
    {
        $salesman = $user->salesmanProfile;
        if (! $salesman) {
            return false;
        }

        // Salesman can only update customers assigned to them
        return $customer->assigned_salesman_id === $salesman->id;
    }
}
