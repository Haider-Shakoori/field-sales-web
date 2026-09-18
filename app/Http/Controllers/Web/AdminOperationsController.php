<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Collection;
use App\Models\CurrentLocation;
use App\Models\Customer;
use App\Models\CustomerVisit;
use App\Models\Expense;
use App\Models\SalesOrder;
use App\Models\Salesman;
use App\Models\Target;
use App\Models\WorkSession;
use App\Services\FieldScopeService;
use App\Services\TenantClock;
use App\Services\TrackingSettingsService;
use Illuminate\Http\Request;

class AdminOperationsController extends Controller
{
    public function dashboard(Request $request, FieldScopeService $scope, TenantClock $clock, TrackingSettingsService $settings)
    {
        $user = $request->user()->load('tenant');
        $salesmanIds = $scope->salesmanIds($user);
        $date = $clock->now($user->tenant)->toDateString();
        [$from, $to] = $clock->dayBoundsUtc($user->tenant, $date);
        $tracking = $settings->get($user->tenant);
        $staleBefore = now()->subMinutes($tracking['gps_stale_after_minutes']);

        $activeSalesmen = Salesman::where('tenant_id', $user->tenant_id)->whereIn('id', $salesmanIds)->where('is_active', true)->count();
        $sessions = WorkSession::where('tenant_id', $user->tenant_id)->whereIn('salesman_id', $salesmanIds)->whereDate('date', $date);
        $working = (clone $sessions)->where('status', 'active')->count();
        $started = (clone $sessions)->count();
        $online = CurrentLocation::where('tenant_id', $user->tenant_id)->whereIn('salesman_id', $salesmanIds)->where('recorded_at', '>=', $staleBefore)->count();
        $visits = CustomerVisit::where('tenant_id', $user->tenant_id)->whereIn('salesman_id', $salesmanIds)->whereBetween('checked_in_at', [$from, $to])->count();
        $orders = SalesOrder::where('tenant_id', $user->tenant_id)->whereIn('salesman_id', $salesmanIds)->whereBetween('ordered_at', [$from, $to])->whereNotIn('status', ['cancelled']);
        $collections = Collection::where('tenant_id', $user->tenant_id)->whereIn('salesman_id', $salesmanIds)->whereBetween('collected_at', [$from, $to])->sum('amount');

        $team = Salesman::with(['user', 'currentLocation'])
            ->where('tenant_id', $user->tenant_id)
            ->whereIn('id', $salesmanIds)
            ->where('is_active', true)
            ->orderBy('employee_code')
            ->get();

        return view('admin.dashboard', [
            'date' => $date,
            'timezone' => $tracking['timezone'],
            'metrics' => [
                'active_salesmen' => $activeSalesmen,
                'started' => $started,
                'not_started' => max(0, $activeSalesmen - $started),
                'working' => $working,
                'tracking_online' => $online,
                'tracking_offline' => max(0, $working - $online),
                'visits' => $visits,
                'orders' => (clone $orders)->count(),
                'sales' => (float) (clone $orders)->sum('total_amount'),
                'collections' => (float) $collections,
            ],
            'team' => $team,
            'staleBefore' => $staleBefore,
        ]);
    }

    public function attendance(Request $request, FieldScopeService $scope)
    {
        $ids = $scope->salesmanIds($request->user());
        $query = WorkSession::with(['salesman.user', 'device'])
            ->where('tenant_id', $request->user()->tenant_id)
            ->whereIn('salesman_id', $ids);

        if ($request->filled('date')) $query->whereDate('date', $request->string('date'));
        if ($request->filled('status')) $query->where('status', $request->string('status'));

        return view('admin.attendance', ['rows' => $query->latest('date')->latest('start_time')->paginate(50)]);
    }

    public function locations(Request $request, FieldScopeService $scope, TrackingSettingsService $settings)
    {
        $ids = $scope->salesmanIds($request->user());
        $rows = CurrentLocation::with('salesman.user')
            ->where('tenant_id', $request->user()->tenant_id)
            ->whereIn('salesman_id', $ids)
            ->orderByDesc('recorded_at')
            ->get();

        $policy = $settings->get($request->user()->tenant);

        return view('admin.current-locations', [
            'rows' => $rows,
            'staleBefore' => now()->subMinutes($policy['gps_stale_after_minutes']),
        ]);
    }

    public function customers(Request $request)
    {
        $query = Customer::with(['branch', 'territory', 'route', 'salesman.user'])
            ->where('tenant_id', $request->user()->tenant_id);

        if ($request->filled('q')) {
            $term = '%'.$request->string('q').'%';
            $query->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('shop_name', 'like', $term)->orWhere('code', 'like', $term));
        }

        return view('admin.customers', ['rows' => $query->orderBy('name')->paginate(50)]);
    }

    public function visits(Request $request, FieldScopeService $scope)
    {
        $rows = CustomerVisit::with(['customer', 'salesman.user'])
            ->where('tenant_id', $request->user()->tenant_id)
            ->whereIn('salesman_id', $scope->salesmanIds($request->user()))
            ->latest('checked_in_at')
            ->paginate(50);

        return view('admin.visits', compact('rows'));
    }

    public function orders(Request $request, FieldScopeService $scope)
    {
        $rows = SalesOrder::with(['customer', 'salesman.user'])
            ->where('tenant_id', $request->user()->tenant_id)
            ->whereIn('salesman_id', $scope->salesmanIds($request->user()))
            ->latest('ordered_at')
            ->paginate(50);

        return view('admin.orders', compact('rows'));
    }

    public function collections(Request $request, FieldScopeService $scope)
    {
        $rows = Collection::with(['customer'])
            ->where('tenant_id', $request->user()->tenant_id)
            ->whereIn('salesman_id', $scope->salesmanIds($request->user()))
            ->latest('collected_at')
            ->paginate(50);

        return view('admin.collections', compact('rows'));
    }

    public function expenses(Request $request, FieldScopeService $scope)
    {
        $rows = Expense::where('tenant_id', $request->user()->tenant_id)
            ->whereIn('salesman_id', $scope->salesmanIds($request->user()))
            ->latest('spent_at')
            ->paginate(50);

        return view('admin.expenses', compact('rows'));
    }

    public function targets(Request $request)
    {
        $rows = Target::where('tenant_id', $request->user()->tenant_id)->latest('period_start')->paginate(50);

        return view('admin.targets', compact('rows'));
    }
}
