<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
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
}
