<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\DailyBeatPlan;
use App\Models\Salesman;
use App\Services\AuditLogger;
use App\Services\DailyBeatPlanner;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DailyBeatPlanController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user()->loadMissing('tenant');
        $timezone = $user->tenant?->timezone ?: config('app.timezone', 'UTC');

        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'salesman' => ['nullable', 'uuid'],
        ]);

        $date = $validated['date'] ?? now($timezone)->toDateString();
        $selectedSalesman = null;

        if (! empty($validated['salesman'])) {
            $selectedSalesman = Salesman::with('user')
                ->where('uuid', $validated['salesman'])
                ->firstOrFail();
        }

        $plans = DailyBeatPlan::with(['salesman.user', 'route'])
            ->withCount([
                'stops',
                'stops as completed_stops_count' => fn ($query) => $query
                    ->whereNotNull('completed_visit_id'),
            ])
            ->whereDate('plan_date', $date)
            ->orderBy('salesman_id')
            ->get();

        $selectedPlan = $selectedSalesman
            ? DailyBeatPlan::with([
                'salesman.user',
                'route',
                'generator',
                'stops.customer',
                'stops.completedVisit',
            ])
                ->where('salesman_id', $selectedSalesman->id)
                ->whereDate('plan_date', $date)
                ->first()
            : null;

        return view('admin.daily-plans.index', [
            'date' => $date,
            'timezone' => $timezone,
            'salesmen' => Salesman::active()->with('user')->orderBy('employee_code')->get(),
            'selectedSalesman' => $selectedSalesman,
            'plans' => $plans,
            'selectedPlan' => $selectedPlan,
        ]);
    }

    public function generate(
        Request $request,
        DailyBeatPlanner $planner,
        AuditLogger $audit,
    ): RedirectResponse {
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'salesman' => [
                'required',
                'uuid',
                Rule::exists('salesmen', 'uuid')
                    ->where('tenant_id', $tenantId)
                    ->where('is_active', true),
            ],
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        $user = $request->user()->loadMissing('tenant');
        $timezone = $user->tenant?->timezone ?: config('app.timezone', 'UTC');
        $salesman = Salesman::where('uuid', $validated['salesman'])
            ->active()
            ->firstOrFail();

        $plan = $planner->generate(
            $salesman,
            CarbonImmutable::parse($validated['date'], $timezone),
            $user,
        );

        $audit->record('daily_beat_plan.generated', $plan, [], [
            'salesman_id' => $salesman->id,
            'plan_date' => $plan->plan_date?->toDateString(),
            'source_type' => $plan->source_type,
            'route_id' => $plan->route_id,
            'total_stops' => $plan->total_stops,
            'estimated_distance_m' => $plan->estimated_distance_m,
            'algorithm_version' => $plan->algorithm_version,
        ]);

        return redirect()
            ->route('admin.daily-plans.index', [
                'date' => $validated['date'],
                'salesman' => $salesman->uuid,
            ])
            ->with('status', __('Daily plan generated.'));
    }
}
