<?php

namespace App\Http\Controllers;

use App\Models\CurrentLocation;
use App\Models\Salesman;
use App\Support\Tenancy\TenantContext;
use App\Support\Tracking\AttendanceTrackingSettings;
use App\Support\Tracking\VisibleSalesmen;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Minimal latest-location verification view (Batch 7 diagnostic).
 *
 * This deliberately is NOT the Batch 13 live map: no map, no polling, no
 * route reconstruction. It reads the canonical `current_locations` table.
 */
class CurrentLocationController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->hasPermission('tracking:view'), 403);

        $visibleSalesmanIds = VisibleSalesmen::idsFor($user);

        $query = CurrentLocation::query()->with(['user', 'salesman.assignments.branch', 'device']);

        if (TenantContext::hasContext()) {
            $query->where('tenant_id', TenantContext::currentId());
        }

        if ($visibleSalesmanIds !== null) {
            $query->whereIn('salesman_id', $visibleSalesmanIds);
        }

        if ($salesmanId = $request->string('salesman_id')->toString()) {
            $query->where('salesman_id', $salesmanId);
        }

        if ($request->boolean('mock_only')) {
            $query->where('is_mock_location', true);
        }

        $locations = $query->orderByDesc('recorded_at')->paginate(25)->withQueryString();

        $salesmen = Salesman::query()
            ->where('tenant_id', TenantContext::currentId())
            ->when($visibleSalesmanIds !== null, fn ($q) => $q->whereIn('id', $visibleSalesmanIds))
            ->orderBy('first_name')
            ->get();

        return view('pages.current-locations.index', [
            'locations' => $locations,
            'salesmen' => $salesmen,
            'staleAfterMinutes' => AttendanceTrackingSettings::effective()['values']['gps_stale_after_minutes'],
        ]);
    }
}
