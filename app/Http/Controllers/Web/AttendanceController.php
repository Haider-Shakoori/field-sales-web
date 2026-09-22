<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Salesman;
use App\Models\WorkSession;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'salesman' => ['nullable', 'uuid'],
        ]);

        $user = $request->user()->loadMissing('tenant');
        $timezone = $user->tenant?->timezone ?: config('app.timezone', 'UTC');
        $today = CarbonImmutable::now($timezone)->toDateString();

        $dateFrom = $validated['date_from']
            ?? CarbonImmutable::now($timezone)->startOfMonth()->toDateString();
        $dateTo = $validated['date_to'] ?? $today;

        if (
            CarbonImmutable::parse($dateFrom, $timezone)
                ->diffInDays(CarbonImmutable::parse($dateTo, $timezone)) > 366
        ) {
            throw ValidationException::withMessages([
                'date_to' => 'Attendance ranges cannot exceed 366 days.',
            ]);
        }

        $salesmanOptions = Salesman::query()
            ->with('user.branch')
            ->orderBy('employee_code')
            ->get();

        $selectedSalesman = null;

        if (! empty($validated['salesman'])) {
            $selectedSalesman = $salesmanOptions
                ->firstWhere('uuid', $validated['salesman']);

            abort_unless($selectedSalesman, 404);
        }

        $salesmen = $selectedSalesman
            ? $salesmanOptions->where('id', $selectedSalesman->id)->values()
            : $salesmanOptions;

        $salesmanIds = $salesmen->pluck('id');

        $aggregates = $salesmanIds->isEmpty()
            ? collect()
            : WorkSession::query()
                ->whereIn('salesman_id', $salesmanIds)
                ->whereBetween('date', [$dateFrom, $dateTo])
                ->selectRaw(
                    'salesman_id,
                     COUNT(*) as attendance_days,
                     COALESCE(SUM(duration_minutes), 0) as completed_minutes,
                     SUM(CASE WHEN is_late_start = 1 THEN 1 ELSE 0 END) as late_starts,
                     SUM(CASE WHEN is_early_finish = 1 THEN 1 ELSE 0 END) as early_finishes'
                )
                ->groupBy('salesman_id')
                ->get()
                ->keyBy('salesman_id');

        $todaySessions = $salesmanIds->isEmpty()
            ? collect()
            : WorkSession::query()
                ->whereIn('salesman_id', $salesmanIds)
                ->whereDate('date', $today)
                ->get()
                ->keyBy('salesman_id');

        $rangeIncludesToday = $dateFrom <= $today && $dateTo >= $today;
        $now = CarbonImmutable::now('UTC');

        $attendanceRows = $salesmen->map(function (Salesman $salesman) use (
            $aggregates,
            $todaySessions,
            $rangeIncludesToday,
            $now,
        ): array {
            $aggregate = $aggregates->get($salesman->id);
            $todaySession = $todaySessions->get($salesman->id);
            $attendanceDays = (int) ($aggregate?->attendance_days ?? 0);
            $totalMinutes = (int) ($aggregate?->completed_minutes ?? 0);

            if (
                $rangeIncludesToday
                && $todaySession
                && $todaySession->status === 'active'
                && $todaySession->duration_minutes === null
            ) {
                $totalMinutes += $this->runningMinutes($todaySession, $now);
            }

            return [
                'salesman' => $salesman,
                'today_session' => $todaySession,
                'attendance_days' => $attendanceDays,
                'total_minutes' => $totalMinutes,
                'average_minutes' => $attendanceDays > 0
                    ? (int) round($totalMinutes / $attendanceDays)
                    : 0,
                'late_starts' => (int) ($aggregate?->late_starts ?? 0),
                'early_finishes' => (int) ($aggregate?->early_finishes ?? 0),
            ];
        });

        $sessionsQuery = WorkSession::query()
            ->with(['salesman.user.branch'])
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->when(
                $selectedSalesman,
                fn ($query) => $query->where('salesman_id', $selectedSalesman->id)
            )
            ->orderByDesc('date')
            ->orderByDesc('start_time');

        $sessions = $sessionsQuery->paginate(50)->withQueryString();

        $sessions->getCollection()->transform(function (WorkSession $session) use (
            $today,
            $now,
        ): WorkSession {
            $minutes = $session->duration_minutes;

            if (
                $minutes === null
                && $session->status === 'active'
                && $session->date?->toDateString() === $today
            ) {
                $minutes = $this->runningMinutes($session, $now);
            }

            $session->setAttribute('display_duration_minutes', $minutes);

            return $session;
        });

        return view('admin.attendance.index', [
            'attendanceRows' => $attendanceRows,
            'salesmanOptions' => $salesmanOptions,
            'sessions' => $sessions,
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'salesman' => $selectedSalesman?->uuid,
            ],
            'summary' => [
                'salesmen' => $attendanceRows->count(),
                'attendance_days' => $attendanceRows->sum('attendance_days'),
                'total_minutes' => $attendanceRows->sum('total_minutes'),
                'active_now' => $todaySessions->filter(
                    fn (WorkSession $session) => $session->status === 'active'
                )->count(),
            ],
            'timezone' => $timezone,
            'today' => $today,
        ]);
    }

    private function runningMinutes(
        WorkSession $session,
        CarbonImmutable $now,
    ): int {
        if (! $session->start_time || $session->start_time->isAfter($now)) {
            return 0;
        }

        return (int) floor($session->start_time->diffInMinutes($now));
    }
}
