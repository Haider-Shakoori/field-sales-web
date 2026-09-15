<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\View\View;

class PlatformController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Tenant::class);

        $tenants = Tenant::withCount(['users', 'branches'])
            ->orderBy('name')
            ->paginate(20);

        $summary = [
            'tenants' => Tenant::count(),
            'users' => User::count(),
            'active_trials' => Tenant::where('subscription_status', 'trial')->count(),
        ];

        return view('pages.platform.index', compact('tenants', 'summary'));
    }
}
