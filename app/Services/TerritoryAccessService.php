<?php

namespace App\Services;

use App\Models\SalesmanAssignment;
use App\Models\SupervisorAssignment;
use App\Models\Territory;
use App\Models\User;
use Illuminate\Support\Collection;

final class TerritoryAccessService
{
    public function territoryIds(User $actor, mixed $date = null): ?Collection
    {
        $date ??= today();

        if ($actor->hasAnyRole(['sales_manager'])) {
            $assignments = SupervisorAssignment::query()
                ->where('sales_manager_id', $actor->id)
                ->current($date)
                ->get(['branch_id', 'territory_id']);

            $direct = $assignments->pluck('territory_id')->filter();
            $branchIds = $assignments->pluck('branch_id')->filter()->unique();

            $branchTerritories = Territory::query()
                ->whereIn('branch_id', $branchIds)
                ->pluck('id');

            return $direct->merge($branchTerritories)->unique()->values();
        }

        if ($actor->hasAnyRole(['supervisor'])) {
            $supervisor = $actor->supervisor;
            if (! $supervisor) {
                return collect();
            }

            $direct = SupervisorAssignment::query()
                ->where('supervisor_id', $supervisor->id)
                ->current($date)
                ->whereNotNull('territory_id')
                ->pluck('territory_id');

            $salesmanTerritories = SalesmanAssignment::query()
                ->where('supervisor_id', $supervisor->id)
                ->current($date)
                ->whereNotNull('territory_id')
                ->pluck('territory_id');

            return $direct->merge($salesmanTerritories)->unique()->values();
        }

        return null;
    }

    public function salesmanIds(User $actor, mixed $date = null): ?Collection
    {
        $territories = $this->territoryIds($actor, $date);
        if ($territories === null) {
            return null;
        }
        if ($territories->isEmpty()) {
            return collect();
        }

        return SalesmanAssignment::query()
            ->current($date ?? today())
            ->whereIn('territory_id', $territories)
            ->pluck('salesman_id')
            ->unique()
            ->values();
    }

    public function canAccessTerritory(User $actor, ?int $territoryId): bool
    {
        $territories = $this->territoryIds($actor);

        return $territories === null || ($territoryId !== null && $territories->contains($territoryId));
    }
}
