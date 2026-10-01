<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Salesman;
use App\Models\WorkSession;
use App\Services\AuditLogger;
use App\Services\MileageService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MileageController extends Controller
{
    public function index(
        Request $request,
        MileageService $mileage,
    ): View {
        $validated = $request->validate([
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => [
                'nullable',
                'date_format:Y-m-d',
                'after_or_equal:date_from',
            ],
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
                'date_to' => 'Mileage ranges cannot exceed 366 days.',
            ]);
        }

        $salesmen = Salesman::query()
            ->with('user.branch')
            ->orderBy('employee_code')
            ->get();

        $selectedSalesman = null;

        if (! empty($validated['salesman'])) {
            $selectedSalesman = $salesmen->firstWhere(
                'uuid',
                $validated['salesman'],
            );

            abort_unless($selectedSalesman, 404);
        }

        $rows = $mileage->rows(
            $user->tenant,
            $dateFrom,
            $dateTo,
            $selectedSalesman?->id,
        );

        $distance = round($rows->sum('effective_distance_km'), 3);
        $fuelLitres = round($rows->sum('fuel_liters'), 3);
        $fuelCost = [];

        foreach ($rows as $row) {
            foreach ($row['fuel_cost_by_currency'] as $currency => $amount) {
                $fuelCost[$currency] = round(
                    ($fuelCost[$currency] ?? 0) + (float) $amount,
                    4,
                );
            }
        }

        return view('admin.mileage.index', [
            'rows' => $rows,
            'salesmen' => $salesmen,
            'canManage' => $user->hasPermission('attendance:manage'),
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'salesman' => $selectedSalesman?->uuid,
            ],
            'summary' => [
                'sessions' => $rows->count(),
                'distance_km' => $distance,
                'fuel_liters' => $fuelLitres,
                'km_per_liter' => $fuelLitres > 0
                    ? round($distance / $fuelLitres, 2)
                    : null,
                'fuel_cost_by_currency' => $fuelCost,
            ],
        ]);
    }

    public function update(
        Request $request,
        WorkSession $session,
        AuditLogger $audit,
    ): RedirectResponse {
        $validated = $request->validate([
            'vehicle_reference' => ['nullable', 'string', 'max:120'],
            'odometer_start_km' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'odometer_end_km' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'correction_reason' => ['required', 'string', 'max:5000'],
        ]);

        $start = $validated['odometer_start_km'] ?? null;
        $end = $validated['odometer_end_km'] ?? null;

        if ($start !== null && $end !== null && (float) $end < (float) $start) {
            throw ValidationException::withMessages([
                'odometer_end_km' => 'End odometer must be greater than or equal to start odometer.',
            ]);
        }

        $before = $this->auditValues($session);
        $after = [
            'vehicle_reference' => trim((string) ($validated['vehicle_reference'] ?? '')) ?: null,
            'odometer_start_km' => $start === null ? null : round((float) $start, 2),
            'odometer_end_km' => $end === null ? null : round((float) $end, 2),
        ];

        $corrections = is_array($session->corrections)
            ? $session->corrections
            : [];

        $corrections[] = [
            'type' => 'management_mileage_correction',
            'corrected_at' => now()->toIso8601String(),
            'user_id' => $request->user()->id,
            'reason' => trim($validated['correction_reason']),
            'before' => $before,
            'after' => $after,
        ];

        $session->update([
            ...$after,
            'corrections' => $corrections,
        ]);

        $session->refresh();
        $audit->record(
            'mileage.corrected',
            $session,
            $before,
            [
                ...$this->auditValues($session),
                'correction_reason' => trim($validated['correction_reason']),
            ],
        );

        return redirect()
            ->back()
            ->with('status', 'Mileage/odometer correction saved and audited.');
    }

    private function auditValues(WorkSession $session): array
    {
        return [
            'date' => $session->date?->toDateString(),
            'salesman_id' => $session->salesman_id,
            'vehicle_reference' => $session->vehicle_reference,
            'odometer_start_km' => $session->odometer_start_km,
            'odometer_end_km' => $session->odometer_end_km,
            'gps_distance_km' => $session->gps_distance_km,
            'status' => $session->status,
        ];
    }
}
