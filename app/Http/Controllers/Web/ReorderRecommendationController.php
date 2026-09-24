<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Salesman;
use App\Services\ReorderRecommendationService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReorderRecommendationController extends Controller
{
    public function __invoke(Request $request, ReorderRecommendationService $service): View
    {
        $salesman = $request->filled('salesman')
            ? Salesman::where('uuid', $request->string('salesman'))->firstOrFail()
            : null;

        return view('admin.reorder-recommendations.index', [
            'recommendations' => $service->forTenant($salesman),
            'salesmen' => Salesman::active()->orderBy('employee_code')->get(),
            'selectedSalesman' => $salesman?->uuid,
        ]);
    }
}
