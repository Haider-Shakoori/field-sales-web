<?php

namespace App\Http\Controllers;

use App\Support\Tenancy\TenantContext;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $this->authorize('dashboard:view');

        $user = request()->user();
        $tenant = TenantContext::tenant();

        return view('pages.dashboard.index', compact('user', 'tenant'));
    }
}
