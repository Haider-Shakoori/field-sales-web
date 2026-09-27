<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Salesman;
use App\Models\VisitAssignment;
use App\Services\DailyRoutePlannerService;
use App\Services\RouteExecutionAnalyticsService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DailyRoutePlannerController extends Controller
{
    public function assign(
        Request $request,
        DailyRoutePlannerService $planner,
    ): RedirectResponse {
        $user = $request->user()->loadMissing('tenant');
        $timezone = $user->tenant?->timezone ?: config('app.timezone', 'UTC');

        $validated = $request->validate([
            'salesman' => ['required', 'uuid'],
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        $salesman = Salesman::query()
            ->where('uuid', $validated['salesman'])
            ->active()
            ->firstOrFail();
        $date = CarbonImmutable::parse($validated['date'], $timezone)->startOfDay();
        $plan = $planner->planFor($salesman, $date);

        if (($plan['enabled'] ?? true) === false || ! ($plan['source'] ?? null)) {
            return back()->withErrors([
                'planner' => 'A daily plan is not available for this salesman and date.',
            ]);
        }

        $stops = collect($plan['stops'] ?? [])
            ->where('visited_today', false)
            ->values();
        $customers = Customer::query()
            ->whereIn('uuid', $stops->pluck('customer_id')->filter()->all())
            ->get()
            ->keyBy('uuid');
        $created = 0;
        $skipped = 0;

        DB::transaction(function () use (
            $request,
            $salesman,
            $date,
            $stops,
            $customers,
            $timezone,
            &$created,
            &$skipped,
        ): void {
            foreach ($stops as $stop) {
                $customer = $customers->get($stop['customer_id'] ?? null);

                if (! $customer) {
                    continue;
                }

                $exists = VisitAssignment::query()
                    ->where('salesman_id', $salesman->id)
                    ->where('customer_id', $customer->id)
                    ->whereDate('visit_date', $date->toDateString())
                    ->whereIn('status', ['scheduled', 'in_progress', 'completed'])
                    ->exists();

                if ($exists) {
                    $skipped++;

                    continue;
                }

                $arrival = filled($stop['estimated_arrival_at'] ?? null)
                    ? CarbonImmutable::parse($stop['estimated_arrival_at'])->setTimezone($timezone)
                    : null;
                $priority = in_array($stop['priority'] ?? null, ['urgent', 'high'], true)
                    ? $stop['priority']
                    : 'normal';

                VisitAssignment::create([
                    'salesman_id' => $salesman->id,
                    'customer_id' => $customer->id,
                    'created_by' => $request->user()->id,
                    'visit_date' => $date->toDateString(),
                    'scheduled_time' => $arrival?->format('H:i'),
                    'purpose' => 'sales',
                    'priority' => $priority,
                    'expected_duration_minutes' => max(5, (int) ($stop['planned_visit_minutes'] ?? 10)),
                    'notes' => 'Auto-scheduled from Daily Planner.',
                    'status' => 'scheduled',
                ]);

                $created++;
            }
        });

        $deferred = (int) data_get($plan, 'summary.deferred_stops', 0);

        return back()->with(
            'success',
            $created.' planned visit(s) sent to the salesman mobile app.'
                .($skipped > 0 ? ' '.$skipped.' existing assignment(s) skipped.' : '')
                .($deferred > 0 ? ' '.$deferred.' stop(s) remain deferred by workday capacity.' : ''),
        );
    }

    public function index(
        Request $request,
        DailyRoutePlannerService $planner,
        RouteExecutionAnalyticsService $execution,
    ): View {
        $user = $request->user()->loadMissing('tenant');
        $timezone = $user->tenant?->timezone ?: config('app.timezone', 'UTC');

        $validated = $request->validate([
            'salesman' => ['nullable', 'uuid'],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $salesmen = Salesman::active()
            ->orderBy('employee_code')
            ->get();

        $salesman = isset($validated['salesman'])
            ? Salesman::where('uuid', $validated['salesman'])->active()->first()
            : $salesmen->first();

        if (isset($validated['salesman']) && ! $salesman) {
            throw ValidationException::withMessages([
                'salesman' => 'The selected salesman is invalid.',
            ]);
        }

        $date = isset($validated['date'])
            ? CarbonImmutable::parse($validated['date'], $timezone)->startOfDay()
            : CarbonImmutable::now($timezone)->startOfDay();

        return view('admin.daily-planner.index', [
            'salesmen' => $salesmen,
            'selectedSalesman' => $salesman,
            'selectedDate' => $date->toDateString(),
            'plan' => $salesman ? $planner->planFor($salesman, $date) : null,
            'execution' => $salesman
                ? collect($execution->build(
                    $user,
                    $date->toDateString(),
                    $salesman->employee_code,
                )['salesmen'])->first()
                : null,
        ]);
    }
}
