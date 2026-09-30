<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Salesman;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FuelController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'salesman' => ['nullable', 'uuid'],
            'vehicle' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(Expense::STATUSES)],
        ]);

        $user = $request->user()->loadMissing('tenant');
        $timezone = $user->tenant?->timezone ?: config('app.timezone', 'UTC');
        $dateTo = $validated['date_to'] ?? CarbonImmutable::now($timezone)->toDateString();
        $dateFrom = $validated['date_from']
            ?? CarbonImmutable::parse($dateTo, $timezone)->subDays(29)->toDateString();

        $from = CarbonImmutable::parse($dateFrom, $timezone)->startOfDay()->utc();
        $to = CarbonImmutable::parse($dateTo, $timezone)->endOfDay()->utc();

        $salesmen = Salesman::query()->with('user.branch')->orderBy('employee_code')->get();
        $selectedSalesman = null;
        if (! empty($validated['salesman'])) {
            $selectedSalesman = $salesmen->firstWhere('uuid', $validated['salesman']);
            abort_unless($selectedSalesman, 404);
        }

        $vehicle = trim((string) ($validated['vehicle'] ?? ''));
        $status = $validated['status'] ?? null;

        $base = Expense::query()
            ->where('category', 'fuel')
            ->whereBetween('spent_at', [$from, $to])
            ->when($selectedSalesman, fn ($query) => $query->where('salesman_id', $selectedSalesman->id))
            ->when($vehicle !== '', fn ($query) => $query->where('vehicle_reference', $vehicle))
            ->when($status, fn ($query) => $query->where('status', $status));

        $all = (clone $base)->orderBy('spent_at')->get();
        $costs = $all->groupBy('currency')->map(
            fn (Collection $rows) => round($rows->sum(fn (Expense $row) => (float) $row->amount), 2)
        )->all();

        [$distance, $efficiencyLiters] = $this->fullTankDistance($all->where('status', 'approved'));
        $evidenceComplete = $all->filter(fn (Expense $row) => filled($row->vehicle_reference)
            && $row->fuel_liters !== null
            && $row->odometer_km !== null
            && filled($row->receipt_path)
        )->count();

        $expenses = (clone $base)
            ->with(['salesman.user', 'reviewer'])
            ->orderByDesc('spent_at')
            ->paginate(min(100, max(10, $request->integer('per_page', 30))))
            ->withQueryString();

        $vehicles = Expense::query()
            ->where('category', 'fuel')
            ->whereNotNull('vehicle_reference')
            ->where('vehicle_reference', '<>', '')
            ->distinct()
            ->orderBy('vehicle_reference')
            ->pluck('vehicle_reference');

        return view('admin.fuel.index', [
            'expenses' => $expenses,
            'salesmen' => $salesmen,
            'vehicles' => $vehicles,
            'filters' => [
                'dateFrom' => $dateFrom,
                'dateTo' => $dateTo,
                'salesman' => $selectedSalesman?->uuid,
                'vehicle' => $vehicle,
                'status' => $status,
            ],
            'summary' => [
                'entries' => $all->count(),
                'liters' => round($all->sum(fn (Expense $row) => (float) ($row->fuel_liters ?? 0)), 3),
                'costs' => $costs,
                'full_tank_distance_km' => round($distance, 2),
                'km_per_liter' => $efficiencyLiters > 0 ? round($distance / $efficiencyLiters, 2) : null,
                'evidence_coverage' => $all->count() > 0
                    ? round(($evidenceComplete / $all->count()) * 100, 1)
                    : 0,
            ],
        ]);
    }

    public function receipt(Expense $expense): StreamedResponse
    {
        abort_unless($expense->category === 'fuel' && filled($expense->receipt_path), 404);
        abort_unless(Storage::disk('public')->exists($expense->receipt_path), 404);

        return Storage::disk('public')->download(
            $expense->receipt_path,
            basename($expense->receipt_path),
        );
    }

    private function fullTankDistance(Collection $rows): array
    {
        $distance = 0.0;
        $liters = 0.0;

        foreach ($rows
            ->filter(fn (Expense $row) => $row->full_tank
                && filled($row->vehicle_reference)
                && $row->odometer_km !== null
                && (float) ($row->fuel_liters ?? 0) > 0
            )
            ->groupBy('vehicle_reference') as $vehicleRows) {
            $previous = null;
            foreach ($vehicleRows->sortBy('spent_at') as $row) {
                if ($previous !== null) {
                    $delta = (float) $row->odometer_km - (float) $previous->odometer_km;
                    if ($delta > 0 && $delta < 5000) {
                        $distance += $delta;
                        $liters += (float) $row->fuel_liters;
                    }
                }
                $previous = $row;
            }
        }

        return [$distance, $liters];
    }
}
