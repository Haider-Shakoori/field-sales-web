<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Salesman;
use App\Models\SalesmanAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AppointmentAccessService
{
    public function scopedQuery(User $user): Builder
    {
        $query = Appointment::query();

        if ($this->canSeeAll($user)) {
            return $query;
        }

        $user->loadMissing(['salesman', 'supervisor']);

        if ($user->salesman?->is_active) {
            return $query->where(
                'assigned_salesman_id',
                $user->salesman->id,
            );
        }

        if ($user->supervisor?->is_active) {
            $ids = $this->supervisedSalesmanIds($user);

            return $query->whereIn('assigned_salesman_id', $ids);
        }

        return $query->whereRaw('1 = 0');
    }

    public function assignableSalesmen(User $user): Collection
    {
        if ($this->canSeeAll($user)) {
            return Salesman::active()
                ->with('user')
                ->orderBy('employee_code')
                ->get();
        }

        $user->loadMissing(['salesman', 'supervisor']);

        if ($user->salesman?->is_active) {
            return collect([$user->salesman]);
        }

        if ($user->supervisor?->is_active) {
            return Salesman::active()
                ->with('user')
                ->whereIn('id', $this->supervisedSalesmanIds($user))
                ->orderBy('employee_code')
                ->get();
        }

        return collect();
    }

    public function canUseSalesman(User $user, ?int $salesmanId): bool
    {
        if ($salesmanId === null) {
            return $this->canSeeAll($user);
        }

        return $this->assignableSalesmen($user)
            ->contains(fn (Salesman $salesman) => $salesman->id === $salesmanId);
    }

    public function canManage(User $user, Appointment $appointment): bool
    {
        if (! $user->hasPermission('appointments:manage')) {
            return false;
        }

        if ($this->canSeeAll($user)) {
            return true;
        }

        $user->loadMissing(['salesman', 'supervisor']);

        if ($user->salesman?->is_active) {
            return (int) $appointment->assigned_salesman_id
                === (int) $user->salesman->id;
        }

        if ($user->supervisor?->is_active) {
            return $this->supervisedSalesmanIds($user)
                ->contains((int) $appointment->assigned_salesman_id);
        }

        return false;
    }

    private function supervisedSalesmanIds(User $user): Collection
    {
        $user->loadMissing('supervisor');

        if (! $user->supervisor) {
            return collect();
        }

        return SalesmanAssignment::query()
            ->current()
            ->where('supervisor_id', $user->supervisor->id)
            ->pluck('salesman_id')
            ->unique()
            ->values();
    }

    private function canSeeAll(User $user): bool
    {
        return $user->hasAnyRole([
            'owner',
            'company_admin',
            'sales_manager',
            'auditor',
        ]);
    }
}
