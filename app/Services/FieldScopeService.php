<?php

namespace App\Services;

use App\Models\Salesman;
use App\Models\SalesmanAssignment;
use App\Models\User;
use Illuminate\Support\Collection;

final class FieldScopeService
{
    public function salesmanIds(User $viewer, ?string $at = null): Collection
    {
        if ($viewer->hasAnyRole(['super_admin', 'owner', 'admin', 'company_admin', 'sales_manager'])) {
            return Salesman::where('tenant_id', $viewer->tenant_id)->pluck('id');
        }

        if ($viewer->role === 'salesman' && $viewer->salesman) {
            return collect([$viewer->salesman->id]);
        }

        if ($viewer->hasAnyRole(['supervisor'])) {
            $date = $at ?: now()->toDateString();

            return SalesmanAssignment::where('tenant_id', $viewer->tenant_id)
                ->where('supervisor_user_id', $viewer->id)
                ->whereDate('effective_from', '<=', $date)
                ->where(function ($query) use ($date) {
                    $query->whereNull('effective_to')
                        ->orWhereDate('effective_to', '>=', $date);
                })
                ->pluck('salesman_id');
        }

        return collect();
    }

    public function canSeeSalesman(User $viewer, int $salesmanId, ?string $at = null): bool
    {
        return $this->salesmanIds($viewer, $at)->contains($salesmanId);
    }
}
