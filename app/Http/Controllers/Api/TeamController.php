<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SalesmanAssignment;
use App\Models\SupervisorAssignment;
use App\Services\DashboardService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeamController extends Controller
{
    public function overview(Request $request, DashboardService $dashboard): JsonResponse
    {
        $actor = $request->user()->loadMissing(['tenant', 'supervisor']);

        abort_unless(
            $actor->hasAnyRole(['supervisor', 'sales_manager']),
            403,
        );

        $locations = $dashboard->liveLocations($actor);
        $summary = $dashboard->summary($actor, $locations);

        return ApiResponse::success([
            'role' => $actor->role,
            'variant' => $dashboard->dashboardVariant($actor),
            'summary' => $summary,
            'locations' => $locations,
            'recent_activity' => $dashboard->recentActivity($actor, 16),
            'hierarchy' => $this->hierarchy($actor, $summary['local_date']),
        ]);
    }

    private function hierarchy($actor, string $localDate): array
    {
        if ($actor->hasAnyRole(['supervisor'])) {
            if (! $actor->supervisor) {
                return [];
            }

            return [[
                'supervisor_id' => $actor->supervisor->uuid,
                'supervisor_name' => $actor->supervisor->full_name,
                'salesmen' => $this->salesmenForSupervisors(
                    [$actor->supervisor->id],
                    $localDate,
                ),
            ]];
        }

        $assignments = SupervisorAssignment::query()
            ->with('supervisor')
            ->where('sales_manager_id', $actor->id)
            ->current($localDate)
            ->orderBy('supervisor_id')
            ->get();

        return $assignments
            ->filter(fn ($assignment) => $assignment->supervisor !== null)
            ->map(fn ($assignment) => [
                'supervisor_id' => $assignment->supervisor->uuid,
                'supervisor_name' => $assignment->supervisor->full_name,
                'salesmen' => $this->salesmenForSupervisors(
                    [$assignment->supervisor_id],
                    $localDate,
                ),
            ])
            ->values()
            ->all();
    }

    private function salesmenForSupervisors(array $supervisorIds, string $localDate): array
    {
        return SalesmanAssignment::query()
            ->with(['salesman', 'branch', 'territory', 'route'])
            ->whereIn('supervisor_id', $supervisorIds)
            ->current($localDate)
            ->orderBy('salesman_id')
            ->get()
            ->filter(fn ($assignment) => $assignment->salesman?->is_active)
            ->map(fn ($assignment) => [
                'salesman_id' => $assignment->salesman->uuid,
                'employee_code' => $assignment->salesman->employee_code,
                'salesman_name' => $assignment->salesman->full_name,
                'branch' => $assignment->branch?->name,
                'territory' => $assignment->territory?->name,
                'route' => $assignment->route?->name,
            ])
            ->values()
            ->all();
    }
}
