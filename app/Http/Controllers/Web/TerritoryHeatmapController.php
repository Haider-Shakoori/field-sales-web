<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\TerritoryHeatmapService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TerritoryHeatmapController extends Controller
{
    public function __invoke(Request $request, TerritoryHeatmapService $service): View
    {
        $validated = $request->validate([
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'metric' => ['nullable', Rule::in(['sales', 'visits', 'collections', 'customers'])],
            'currency' => ['nullable', Rule::in(['AFN', 'USD', 'PKR'])],
        ]);
        $user = $request->user()->load('tenant');
        $tz = $user->tenant?->timezone ?: config('app.timezone');
        $from = $validated['date_from'] ?? now($tz)->subDays(29)->toDateString();
        $to = $validated['date_to'] ?? now($tz)->toDateString();
        if (CarbonImmutable::parse($from)->diffInDays(CarbonImmutable::parse($to)) > 90) {
            throw ValidationException::withMessages(['date_to' => __('Heat-map ranges cannot exceed 90 days.')]);
        }
        $metric = $validated['metric'] ?? 'sales';
        $currency = $validated['currency'] ?? 'AFN';
        $start = CarbonImmutable::parse($from.' 00:00:00', $tz)->utc();
        $end = CarbonImmutable::parse($to.' 23:59:59', $tz)->utc();
        $result = $service->build($start, $end, $metric, $currency);

        return view('admin.territory-heatmap.index', [
            ...$result,
            'metric' => $metric,
            'from' => $from,
            'to' => $to,
            'currency' => $currency,
        ]);
    }
}