<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Salesman;
use App\Models\User;
use App\Models\WorkSession;
use App\Support\Tenancy\TenantContext;
use App\Support\Tracking\VisibleSalesmen;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Admin attendance / work-session visibility (read-only in Batch 7).
 *
 * Supervisors only see their assigned salesmen; salesmen only see themselves.
 * Branch filtering follows the historical salesman assignment covering each
 * session's work date rather than the user's current branch column.
 */
class AttendanceController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->hasPermission('attendance:view'), 403);

        $visibleSalesmanIds = VisibleSalesmen::idsFor($user);

        $query = WorkSession::query()->with(['user', 'salesman.assignments.branch', 'device']);

        if (TenantContext::hasContext()) {
            $query->where('tenant_id', TenantContext::currentId());
        }

        $this->applyVisibility($query, $visibleSalesmanIds);

        if ($date = $request->string('date')->toString()) {
            $query->whereDate('date', $date);
        }

        if ($salesmanId = $request->string('salesman_id')->toString()) {
            $query->where('salesman_id', $salesmanId);
        }

        if ($branchId = $request->string('branch_id')->toString()) {
            $this->applyHistoricalBranchFilter($query, $branchId);
        }

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        $sessions = $query->orderByDesc('date')->orderByDesc('start_time')->paginate(20)->withQueryString();

        return view('pages.attendance.index', [
            'sessions' => $sessions,
            'salesmen' => $this->visibleSalesmen($visibleSalesmanIds),
            'branches' => Branch::query()
                ->where('tenant_id', TenantContext::currentId())
                ->active()
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function show(Request $request, WorkSession $session): View
    {
        $user = $request->user();
        abort_unless($user->hasPermission('attendance:view'), 403);

        $this->authorizeSession($user, $session);

        $session->load(['user', 'salesman.assignments.branch', 'device', 'correctedBy', 'approvedBy']);

        return view('pages.attendance.show', compact('session'));
    }

    private function applyVisibility(Builder $query, ?array $visibleSalesmanIds): void
    {
        if ($visibleSalesmanIds !== null) {
            $query->whereIn('salesman_id', $visibleSalesmanIds);
        }
    }

    private function applyHistoricalBranchFilter(Builder $query, string $branchId): void
    {
        $tenantId = TenantContext::currentId();

        $query->whereExists(function ($sub) use ($branchId, $tenantId): void {
            $sub->selectRaw('1')
                ->from('salesman_assignments as sa')
                ->whereColumn('sa.salesman_id', 'work_sessions.salesman_id')
                ->where('sa.branch_id', $branchId)
                ->where('sa.tenant_id', $tenantId)
                ->whereColumn('sa.effective_from', '<=', 'work_sessions.date')
                ->where(function ($inner): void {
                    $inner->whereNull('sa.effective_to')
                        ->orWhereColumn('sa.effective_to', '>=', 'work_sessions.date');
                });
        });
    }

    private function authorizeSession(User $user, WorkSession $session): void
    {
        $visibleSalesmanIds = VisibleSalesmen::idsFor($user);

        if ($visibleSalesmanIds === null) {
            return;
        }

        abort_unless(
            $session->salesman_id !== null && in_array((int) $session->salesman_id, $visibleSalesmanIds, true),
            403,
        );
    }

    private function visibleSalesmen(?array $visibleSalesmanIds): Collection
    {
        $query = Salesman::query()->where('tenant_id', TenantContext::currentId());

        if ($visibleSalesmanIds !== null) {
            $query->whereIn('id', $visibleSalesmanIds);
        }

        return $query->orderBy('first_name')->get();
    }
}
