<?php

namespace App\Support\Tracking;

use App\Models\SalesmanAssignment;
use App\Models\User;
use App\Support\Tenancy\TenantContext;

/**
 * Resolves which salesmen a web user may see attendance/GPS data for.
 *
 *  - salesman profile    → own data only
 *  - supervisor profile  → salesmen with a historical assignment supervised by them
 *  - everyone else       → full tenant visibility (existing manager/admin convention)
 */
final class VisibleSalesmen
{
    /**
     * @return list<int>|null null means unrestricted within the tenant
     */
    public static function idsFor(User $user): ?array
    {
        $salesman = $user->salesmanProfile;

        if ($salesman !== null) {
            return [$salesman->id];
        }

        $supervisor = $user->supervisorProfile;

        if ($supervisor !== null) {
            return SalesmanAssignment::query()
                ->where('tenant_id', TenantContext::currentId())
                ->where('supervisor_id', $supervisor->id)
                ->distinct()
                ->pluck('salesman_id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();
        }

        return null;
    }
}
