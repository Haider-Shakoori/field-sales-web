<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CustomerVisit;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VisitController extends Controller
{
    public function index(Request $request): View
    {
        $status = trim((string) $request->string('status'));
        $flagged = $request->boolean('flagged');

        $visits = CustomerVisit::with(['customer', 'salesman.user', 'route'])
            ->withCount('suspiciousFlags')
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($flagged, fn ($query) => $query->whereHas('suspiciousFlags', fn ($flags) => $flags->whereNull('reviewed_at')))
            ->orderByDesc('checked_in_at')
            ->paginate(30)
            ->withQueryString();

        return view('admin.visits.index', compact('visits', 'status', 'flagged'));
    }

    public function show(CustomerVisit $visit): View
    {
        return view('admin.visits.show', [
            'visit' => $visit->load([
                'customer',
                'salesman.user',
                'device',
                'route',
                'workSession',
                'photos', 'voiceNotes.user',
                'suspiciousFlags.reviewer',
                'formSubmissions.answers',
            ]),
        ]);
    }
}