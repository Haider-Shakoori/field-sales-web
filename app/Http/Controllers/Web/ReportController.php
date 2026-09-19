<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Salesman;
use App\Models\Territory;
use App\Services\ReportService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request, ReportService $reports): View
    {
        [$type, $filters] = $this->validatedFilters($request);
        $user = $request->user()->load('tenant');

        return view('admin.reports.index', [
            'type' => $type,
            'filters' => $filters,
            'report' => $reports->build($user, $type, $filters),
            'options' => $reports->options($user, $filters['date_to'] ?? null),
            'types' => ReportService::TYPES,
        ]);
    }

    public function csv(Request $request, ReportService $reports): StreamedResponse
    {
        [$type, $filters] = $this->validatedFilters($request);
        $report = $reports->build($request->user()->load('tenant'), $type, $filters);
        $filename = $type.'-report-'.$report['period'][0].'-'.$report['period'][1].'.csv';

        return response()->streamDownload(function () use ($report): void {
            $stream = fopen('php://output', 'wb');
            fputcsv($stream, array_values($report['columns']));

            foreach ($report['rows'] as $row) {
                fputcsv(
                    $stream,
                    array_map(
                        fn (string $key) => $row[$key] ?? '',
                        array_keys($report['columns']),
                    ),
                );
            }

            fclose($stream);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    private function validatedFilters(Request $request): array
    {
        $validated = $request->validate([
            'type' => ['nullable', Rule::in(ReportService::TYPES)],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'branch' => ['nullable', 'uuid'],
            'territory' => ['nullable', 'uuid'],
            'salesman' => ['nullable', 'uuid'],
        ]);

        if (
            isset($validated['date_from'], $validated['date_to'])
            && CarbonImmutable::parse($validated['date_from'])
                ->diffInDays(CarbonImmutable::parse($validated['date_to'])) > 366
        ) {
            throw ValidationException::withMessages([
                'date_to' => 'Report ranges cannot exceed 366 days.',
            ]);
        }

        $filters = [
            'date_from' => $validated['date_from'] ?? null,
            'date_to' => $validated['date_to'] ?? null,
            'branch_uuid' => $validated['branch'] ?? null,
            'territory_uuid' => $validated['territory'] ?? null,
            'salesman_uuid' => $validated['salesman'] ?? null,
        ];

        if ($filters['branch_uuid']) {
            $filters['branch_id'] = Branch::where('uuid', $filters['branch_uuid'])
                ->firstOrFail()
                ->id;
        }

        if ($filters['territory_uuid']) {
            $filters['territory_id'] = Territory::where('uuid', $filters['territory_uuid'])
                ->firstOrFail()
                ->id;
        }

        if ($filters['salesman_uuid']) {
            $filters['salesman_id'] = Salesman::where('uuid', $filters['salesman_uuid'])
                ->firstOrFail()
                ->id;
        }

        return [$validated['type'] ?? 'sales', array_filter(
            $filters,
            fn ($value) => $value !== null && $value !== '',
        )];
    }
}
