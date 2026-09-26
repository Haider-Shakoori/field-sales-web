<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Collection;
use App\Models\CurrentLocation;
use App\Models\CustomerVisit;
use App\Models\Expense;
use App\Models\Order;
use App\Models\Salesman;
use App\Models\SalesmanAssignment;
use App\Models\SupervisorAssignment;
use App\Models\WorkSession;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection as SupportCollection;

class MobileTeamController extends Controller
{
    public function overview(Request $request): JsonResponse
    {
        $user = $request->user()->loadMissing(['tenant', 'supervisor']);
        abort_unless(
            $user->hasAnyRole(['supervisor', 'sales_manager', 'owner', 'company_admin']),
            403
        );

        $timezone = $user->tenant->timezone ?: 'UTC';
        $localNow = now($timezone);
        $date = $localNow->toDateString();
        $dayStart = $localNow->copy()->startOfDay()->utc();
        $dayEnd = $localNow->copy()->endOfDay()->utc();

        $salesmen = $this->visibleSalesmen($request, $date);
        $ids = $salesmen->pluck('id');

        $sessions = WorkSession::query()
            ->whereIn('salesman_id', $ids)
            ->whereDate('date', $date)
            ->get()
            ->keyBy('salesman_id');

        $locations = CurrentLocation::query()
            ->whereIn('salesman_id', $ids)
            ->get()
            ->keyBy('salesman_id');

        $visitCounts = CustomerVisit::query()
            ->whereIn('salesman_id', $ids)
            ->whereBetween('checked_in_at', [$dayStart, $dayEnd])
            ->selectRaw('salesman_id, COUNT(*) as aggregate')
            ->groupBy('salesman_id')
            ->pluck('aggregate', 'salesman_id');

        $orderStats = Order::query()
            ->whereIn('salesman_id', $ids)
            ->whereBetween('ordered_at', [$dayStart, $dayEnd])
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->selectRaw('salesman_id, COUNT(*) as order_count, COALESCE(SUM(grand_total),0) as order_total')
            ->groupBy('salesman_id')
            ->get()
            ->keyBy('salesman_id');

        $collectionStats = Collection::query()
            ->whereIn('salesman_id', $ids)
            ->whereBetween('collected_at', [$dayStart, $dayEnd])
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->selectRaw('salesman_id, COUNT(*) as collection_count, COALESCE(SUM(amount),0) as collection_total')
            ->groupBy('salesman_id')
            ->get()
            ->keyBy('salesman_id');

        $expenseStats = Expense::query()
            ->whereIn('salesman_id', $ids)
            ->whereBetween('spent_at', [$dayStart, $dayEnd])
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->selectRaw('salesman_id, COUNT(*) as expense_count, COALESCE(SUM(amount),0) as expense_total')
            ->groupBy('salesman_id')
            ->get()
            ->keyBy('salesman_id');

        $members = $salesmen->map(function (Salesman $salesman) use (
            $sessions,
            $locations,
            $visitCounts,
            $orderStats,
            $collectionStats,
            $expenseStats
        ): array {
            $assignment = $salesman->assignments->first();
            $session = $sessions->get($salesman->id);
            $location = $locations->get($salesman->id);
            $orders = $orderStats->get($salesman->id);
            $collections = $collectionStats->get($salesman->id);
            $expenses = $expenseStats->get($salesman->id);

            return [
                'id' => $salesman->uuid,
                'employee_code' => $salesman->employee_code,
                'name' => $salesman->full_name,
                'user_id' => $salesman->user?->uuid,
                'supervisor' => $assignment?->supervisor ? [
                    'id' => $assignment->supervisor->uuid,
                    'name' => $assignment->supervisor->full_name,
                ] : null,
                'territory' => $assignment?->territory ? [
                    'id' => $assignment->territory->uuid,
                    'name' => $assignment->territory->name,
                ] : null,
                'route' => $assignment?->route ? [
                    'id' => $assignment->route->uuid,
                    'name' => $assignment->route->name,
                ] : null,
                'work_status' => match ($session?->status) {
                    'active' => 'working',
                    'completed' => 'completed',
                    default => 'not_started',
                },
                'started_at' => $session?->start_time?->toIso8601String(),
                'ended_at' => $session?->end_time?->toIso8601String(),
                'visits' => (int) ($visitCounts[$salesman->id] ?? 0),
                'orders' => [
                    'count' => (int) ($orders?->order_count ?? 0),
                    'total' => (float) ($orders?->order_total ?? 0),
                ],
                'collections' => [
                    'count' => (int) ($collections?->collection_count ?? 0),
                    'total' => (float) ($collections?->collection_total ?? 0),
                ],
                'expenses' => [
                    'count' => (int) ($expenses?->expense_count ?? 0),
                    'total' => (float) ($expenses?->expense_total ?? 0),
                ],
                'location' => $location ? [
                    'latitude' => (float) $location->latitude,
                    'longitude' => (float) $location->longitude,
                    'accuracy' => (float) $location->horizontal_accuracy,
                    'recorded_at' => $location->recorded_at?->toIso8601String(),
                ] : null,
            ];
        })->values();

        return ApiResponse::success([
            'role' => $user->role,
            'date' => $date,
            'summary' => [
                'team_size' => $members->count(),
                'working' => $members->where('work_status', 'working')->count(),
                'completed' => $members->where('work_status', 'completed')->count(),
                'not_started' => $members->where('work_status', 'not_started')->count(),
                'visits' => $members->sum('visits'),
                'orders' => $members->sum('orders.count'),
                'order_total' => round((float) $members->sum('orders.total'), 4),
                'collections' => $members->sum('collections.count'),
                'collection_total' => round((float) $members->sum('collections.total'), 4),
                'expenses' => $members->sum('expenses.count'),
                'expense_total' => round((float) $members->sum('expenses.total'), 4),
            ],
            'members' => $members,
        ]);
    }

    private function visibleSalesmen(Request $request, string $date): SupportCollection
    {
        $user = $request->user()->loadMissing('supervisor');

        if ($user->hasAnyRole(['owner', 'company_admin'])) {
            return Salesman::query()
                ->active()
                ->with([
                    'user',
                    'assignments' => fn ($query) => $query
                        ->current($date)
                        ->with(['supervisor', 'territory', 'route'])
                        ->latest('effective_from'),
                ])
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get();
        }

        $supervisorIds = collect();

        if ($user->hasAnyRole(['supervisor']) && $user->supervisor?->is_active) {
            $supervisorIds->push($user->supervisor->id);
        }

        if ($user->hasAnyRole(['sales_manager'])) {
            $supervisorIds = $supervisorIds->merge(
                SupervisorAssignment::query()
                    ->current($date)
                    ->where('sales_manager_id', $user->id)
                    ->pluck('supervisor_id')
            );
        }

        $supervisorIds = $supervisorIds->unique()->values();

        if ($supervisorIds->isEmpty()) {
            return collect();
        }

        $salesmanIds = SalesmanAssignment::query()
            ->current($date)
            ->whereIn('supervisor_id', $supervisorIds)
            ->pluck('salesman_id')
            ->unique();

        return Salesman::query()
            ->active()
            ->whereIn('id', $salesmanIds)
            ->with([
                'user',
                'assignments' => fn ($query) => $query
                    ->current($date)
                    ->with(['supervisor', 'territory', 'route'])
                    ->latest('effective_from'),
            ])
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();
    }
}
