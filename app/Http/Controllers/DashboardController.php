<?php

namespace App\Http\Controllers;

use App\Models\Salesman;
use App\Support\Tenancy\TenantContext;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $this->authorize('dashboard:view');

        $user = request()->user();
        $tenant = TenantContext::tenant();

        $salesmenTotal = null;
        $salesmenActive = null;

        if ($tenant !== null) {
            $salesmenTotal = Salesman::count();
            $salesmenActive = Salesman::where('is_active', true)->count();
        }

        return view('pages.dashboard.index', compact('user', 'tenant', 'salesmenTotal', 'salesmenActive'));
    }
}
