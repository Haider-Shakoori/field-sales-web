<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\TerritoryHeatMapService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TerritoryHeatMapController extends Controller
{
    public function __invoke(Request $request, TerritoryHeatMapService $heatMap): View
    {
        $validated = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'metric' => ['nullable', Rule::in(TerritoryHeatMapService::METRICS)],
            'currency' => ['nullable', 'string', 'size:3'],
        ]);

        if (! empty($validated['date_from']) && ! empty($validated['date_to'])) {
            $days = CarbonImmutable::parse($validated['date_from'])->diffInDays(
                CarbonImmutable::parse($validated['date_to']),
            );
            abort_if($days > 366, 422, 'Date range cannot exceed 366 days.');
        }

        return view('admin.territory-heat-map.index', [
            'heatMap' => $heatMap->build($request->user(), $validated),
            'metrics' => TerritoryHeatMapService::METRICS,
        ]);
    }
}
