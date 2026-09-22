<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Salesman;
use App\Services\DailyRoutePlannerService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DailyRoutePlannerController extends Controller
{
    public function index(
        Request $request,
        DailyRoutePlannerService $planner,
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
        ]);
    }
}
