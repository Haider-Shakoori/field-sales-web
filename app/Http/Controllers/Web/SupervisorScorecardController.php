<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\SupervisorScorecardService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SupervisorScorecardController extends Controller
{
    public function index(
        Request $request,
        SupervisorScorecardService $scorecards,
    ): View {
        $validated = $request->validate([
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'supervisor' => ['nullable', 'uuid'],
        ]);

        $user = $request->user()->loadMissing('tenant');
        $timezone = $user->tenant?->timezone ?: config('app.timezone', 'UTC');
        $today = CarbonImmutable::now($timezone);
        $dateFrom = $validated['date_from']
            ?? $today->startOfMonth()->toDateString();
        $dateTo = $validated['date_to'] ?? $today->toDateString();

        if (
            CarbonImmutable::parse($dateFrom, $timezone)
                ->diffInDays(CarbonImmutable::parse($dateTo, $timezone)) > 366
        ) {
            throw ValidationException::withMessages([
                'date_to' => __('Scorecard ranges cannot exceed 366 days.'),
            ]);
        }

        $supervisorUuid = $user->hasAnyRole(['supervisor'])
            ? $user->supervisor?->uuid
            : ($validated['supervisor'] ?? null);

        return view('admin.scorecards.index', [
            'scorecard' => $scorecards->build(
                $user,
                $dateFrom,
                $dateTo,
                $supervisorUuid,
            ),
            'supervisors' => $scorecards->supervisorOptions($user),
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'supervisor' => $supervisorUuid,
            ],
            'supervisorLocked' => $user->hasAnyRole(['supervisor']),
        ]);
    }
}
