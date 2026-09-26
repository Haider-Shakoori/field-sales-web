<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\MobileDiagnostic;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MobileDiagnosticController extends Controller
{
    public function index(Request $request): View
    {
        auth()->shouldUse('web');

        $filters = $request->validate([
            'severity' => ['nullable', 'in:info,warning,error,critical'],
            'area' => ['nullable', 'string', 'max:80'],
            'user' => ['nullable', 'string', 'max:191'],
        ]);

        $query = MobileDiagnostic::query()
            ->with(['user', 'device'])
            ->latest('occurred_at');

        if (! empty($filters['severity'])) {
            $query->where('severity', $filters['severity']);
        }

        if (! empty($filters['area'])) {
            $query->where('area', $filters['area']);
        }

        if (! empty($filters['user'])) {
            $term = trim($filters['user']);

            $query->whereHas('user', fn ($user) => $user
                ->where('name', 'like', '%'.$term.'%')
                ->orWhere('email', 'like', '%'.$term.'%'));
        }

        return view('admin.mobile-diagnostics.index', [
            'diagnostics' => $query->paginate(40)->withQueryString(),
            'areas' => MobileDiagnostic::query()
                ->select('area')
                ->distinct()
                ->orderBy('area')
                ->pluck('area'),
            'filters' => $filters,
        ]);
    }
}
