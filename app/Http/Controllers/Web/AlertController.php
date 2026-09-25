<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\OperationalAnomaly;
use App\Models\SalesmanAssignment;
use App\Models\VisitSuspiciousFlag;
use App\Services\AlertService;
use App\Services\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AlertController extends Controller
{
    public function index(Request $request, AlertService $alerts): View
    {
        $validated = $request->validate([
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'severity' => ['nullable', Rule::in(['low', 'medium', 'high'])],
            'state' => ['nullable', Rule::in(['open', 'reviewed'])],
            'type' => ['nullable', Rule::in(['visit', 'gps', 'collection', 'expense', 'order'])],
        ]);

        if (
            isset($validated['date_from'], $validated['date_to'])
            && CarbonImmutable::parse($validated['date_from'])
                ->diffInDays(CarbonImmutable::parse($validated['date_to'])) > 90
        ) {
            throw ValidationException::withMessages([
                'date_to' => 'Alert ranges cannot exceed 90 days.',
            ]);
        }

        return view('admin.alerts.index', [
            'alerts' => $alerts->build($request->user()->load('tenant'), $validated),
            'filters' => $validated,
            'canReview' => $request->user()->hasPermission('visits:manage'),
        ]);
    }

    public function review(
        Request $request,
        VisitSuspiciousFlag $flag,
        AuditLogger $audit,
    ): RedirectResponse {
        $validated = $request->validate([
            'review_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        if ($flag->reviewed_at !== null) {
            return back()->with('status', 'Alert was already reviewed.');
        }

        $before = [
            'reviewed_at' => null,
            'review_notes' => $flag->review_notes,
        ];

        $flag->update([
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_notes' => $validated['review_notes'] ?? null,
        ]);

        $audit->record('visit_suspicious_flag.reviewed', $flag, $before, [
            'reviewed_at' => $flag->reviewed_at?->toISOString(),
            'review_notes' => $flag->review_notes,
        ]);

        return back()->with('status', 'Suspicious visit alert reviewed.');
    }

    public function reviewAnomaly(
        Request $request,
        OperationalAnomaly $anomaly,
        AuditLogger $audit,
    ): RedirectResponse {
        abort_unless($request->user()->hasPermission('visits:manage'), 403);
        $user = $request->user()->loadMissing(['tenant', 'supervisor']);

        if ($user->hasAnyRole(['supervisor'])) {
            abort_unless($user->supervisor, 404);
            $visible = SalesmanAssignment::query()
                ->where('supervisor_id', $user->supervisor->id)
                ->where('salesman_id', $anomaly->salesman_id)
                ->current(now($user->tenant->timezone)->toDateString())
                ->exists();
            abort_unless($visible, 404);
        }

        $validated = $request->validate([
            'review_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        if ($anomaly->state === 'reviewed') {
            return back()->with('status', 'Operational anomaly was already reviewed.');
        }

        $before = [
            'state' => $anomaly->state,
            'reviewed_at' => $anomaly->reviewed_at?->toISOString(),
            'review_notes' => $anomaly->review_notes,
        ];

        $anomaly->update([
            'state' => 'reviewed',
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
            'review_notes' => $validated['review_notes'] ?? null,
        ]);

        $audit->record('operational_anomaly.reviewed', $anomaly, $before, [
            'state' => $anomaly->state,
            'reviewed_at' => $anomaly->reviewed_at?->toISOString(),
            'review_notes' => $anomaly->review_notes,
        ]);

        return back()->with('status', 'Operational anomaly reviewed.');
    }
}
