<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\ManagementIntelligenceService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ManagementIntelligenceController extends Controller
{
    public function index(
        Request $request,
        ManagementIntelligenceService $intelligence,
    ): View {
        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'supervisor' => ['nullable', 'uuid'],
        ]);

        $user = $request->user()->loadMissing([
            'tenant',
            'supervisor',
        ]);
        $timezone = $user->tenant?->timezone
            ?: config('app.timezone', 'UTC');
        $date = $validated['date']
            ?? CarbonImmutable::now($timezone)->toDateString();
        $supervisorUuid = $user->hasAnyRole(['supervisor'])
            ? $user->supervisor?->uuid
            : ($validated['supervisor'] ?? null);

        return view('admin.management-intelligence.index', [
            'intelligence' => $intelligence->build(
                $user,
                $date,
                $supervisorUuid,
            ),
            'supervisors' => $intelligence->supervisorOptions(
                $user,
            ),
            'filters' => [
                'date' => $date,
                'supervisor' => $supervisorUuid,
            ],
            'supervisorLocked' => $user->hasAnyRole([
                'supervisor',
            ]),
        ]);
    }
}
