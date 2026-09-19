<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CustomerCallActivity;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CallActivityController extends Controller
{
    public function index(Request $request): View
    {
        $outcome = trim((string) $request->string('outcome'));

        $activities = CustomerCallActivity::with(['customer', 'salesman.user'])
            ->when($outcome !== '', fn ($query) => $query->where('outcome', $outcome))
            ->orderByDesc('called_at')
            ->paginate(30)
            ->withQueryString();

        return view('admin.call-activities.index', compact('activities', 'outcome'));
    }
}
