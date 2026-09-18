<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Collection;
use App\Models\Customer;
use App\Models\CustomerVisit;
use App\Models\SalesOrder;
use App\Models\Target;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class TargetController extends Controller
{
    public function current(Request $request)
    {
        $user = $request->user()->load('salesman');
        $today = now()->toDateString();

        $targets = Target::where('tenant_id', $user->tenant_id)
            ->where('is_active', true)
            ->whereDate('period_start', '<=', $today)
            ->whereDate('period_end', '>=', $today)
            ->orderBy('period_end')
            ->get();

        return ApiResponse::success($targets->map(function (Target $target) use ($user) {
            $actual = $this->actual($target, $user->salesman?->id);
            $targetValue = (float) $target->target_value;
            $achievement = $targetValue > 0 ? min(999.99, ($actual / $targetValue) * 100) : 0;

            return [
                'id' => $target->id,
                'uuid' => $target->uuid,
                'name' => $target->name,
                'metric' => $target->metric,
                'scope_type' => $target->scope_type,
                'period_start' => $target->period_start?->toDateString(),
                'period_end' => $target->period_end?->toDateString(),
                'target_value' => $targetValue,
                'actual_value' => round($actual, 2),
                'achievement_percent' => round($achievement, 2),
                'weight' => (float) $target->weight,
            ];
        })->values());
    }

    private function actual(Target $target, ?int $salesmanId): float
    {
        $from = $target->period_start->startOfDay();
        $to = $target->period_end->endOfDay();
        $tenantId = $target->tenant_id;

        return match ($target->metric) {
            'sales_value' => (float) SalesOrder::where('tenant_id', $tenantId)
                ->when($salesmanId && $target->scope_type === 'salesman', fn ($q) => $q->where('salesman_id', $salesmanId))
                ->whereBetween('ordered_at', [$from, $to])
                ->whereNotIn('status', ['cancelled'])
                ->sum('total_amount'),
            'collections_amount' => (float) Collection::where('tenant_id', $tenantId)
                ->when($salesmanId && $target->scope_type === 'salesman', fn ($q) => $q->where('salesman_id', $salesmanId))
                ->whereBetween('collected_at', [$from, $to])
                ->sum('amount'),
            'orders' => (float) SalesOrder::where('tenant_id', $tenantId)
                ->when($salesmanId && $target->scope_type === 'salesman', fn ($q) => $q->where('salesman_id', $salesmanId))
                ->whereBetween('ordered_at', [$from, $to])
                ->whereNotIn('status', ['cancelled'])
                ->count(),
            'visits' => (float) CustomerVisit::where('tenant_id', $tenantId)
                ->when($salesmanId && $target->scope_type === 'salesman', fn ($q) => $q->where('salesman_id', $salesmanId))
                ->whereBetween('checked_in_at', [$from, $to])
                ->count(),
            'new_customers' => (float) Customer::where('tenant_id', $tenantId)
                ->when($salesmanId && $target->scope_type === 'salesman', fn ($q) => $q->where('assigned_salesman_id', $salesmanId))
                ->whereBetween('created_at', [$from, $to])
                ->count(),
            default => 0.0,
        };
    }
}
