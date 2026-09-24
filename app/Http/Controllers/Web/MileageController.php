<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Salesman;
use App\Services\MileageService;
use Carbon\CarbonImmutable;
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
}
