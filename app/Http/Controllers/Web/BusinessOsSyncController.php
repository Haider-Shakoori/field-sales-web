<?php

namespace App\Http\Controllers\Web;

use App\Contracts\BusinessOsConnector;
use App\Http\Controllers\Controller;
use App\Models\BusinessOsOutboxEvent;
use App\Models\BusinessOsSyncRun;
use App\Models\BusinessOsSyncState;
use App\Services\BusinessOs\SyncService;
use App\Services\BusinessOsIntegrationPolicyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class BusinessOsSyncController extends Controller
{
    public function index(
        Request $request,
        BusinessOsIntegrationPolicyService $policy,
    ): View {
        $tenant = $request->user()->tenant;
        $settings = $policy->settingsFor($tenant);
        $states = BusinessOsSyncState::query()
            ->orderBy('stream')
            ->get()
            ->keyBy('stream');
        $runs = BusinessOsSyncRun::query()
            ->with('requestedBy:id,name')
            ->latest()
            ->limit(20)
            ->get();
        $outbox = BusinessOsOutboxEvent::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($count) => (int) $count)
            ->all();

        return view('admin.businessos-sync.index', [
            'policy' => $settings,
            'states' => $states,
            'runs' => $runs,
            'outbox' => [
                'pending' => $outbox['pending'] ?? 0,
                'failed' => $outbox['failed'] ?? 0,
                'sent' => $outbox['sent'] ?? 0,
            ],
            'streams' => SyncService::STREAMS,
        ]);
    }

    public function sync(
        Request $request,
        SyncService $sync,
    ): RedirectResponse {
        $validated = $request->validate([
            'streams' => ['nullable', 'array'],
            'streams.*' => [
                'string',
                Rule::in(SyncService::STREAMS),
            ],
        ]);

        $run = $sync->queue(
            $request->user()->tenant,
            $request->user(),
            'manual',
            $validated['streams'] ?? null,
        );

        return back()->with(
            'status',
            $run->status === 'queued'
                ? 'BusinessOS sync queued.'
                : ($run->error_message ?: 'BusinessOS sync was not queued.'),
        );
    }

    public function retryFailed(
        Request $request,
        SyncService $sync,
    ): RedirectResponse {
        $retried = BusinessOsOutboxEvent::query()
            ->where('status', 'failed')
            ->update([
                'status' => 'pending',
                'last_error' => null,
            ]);

        $run = $sync->queue(
            $request->user()->tenant,
            $request->user(),
            'retry',
            ['push:customers', 'push:orders', 'push:collections'],
        );

        return back()->with(
            'status',
            $run->status === 'queued'
                ? "Queued retry for {$retried} failed event(s)."
                : ($run->error_message ?: 'Retry was not queued.'),
        );
    }

    public function health(
        Request $request,
        BusinessOsConnector $connector,
        BusinessOsIntegrationPolicyService $policy,
    ): RedirectResponse {
        $tenant = $request->user()->tenant;

        if (! $policy->enabled($tenant)) {
            return back()->with('businessos_health', [
                'ok' => false,
                'message' => 'BusinessOS integration is disabled or the server connector is not configured.',
            ]);
        }

        try {
            $response = $connector->health($tenant);
            $connected = (bool) (
                $response['connected']
                ?? in_array(
                    strtolower((string) ($response['status'] ?? '')),
                    ['ok', 'healthy', 'connected', 'success'],
                    true,
                )
            );

            return back()->with('businessos_health', [
                'ok' => $connected,
                'message' => $connected
                    ? 'BusinessOS connector responded successfully.'
                    : ((string) ($response['message'] ?? 'BusinessOS connector responded but did not report a healthy status.')),
            ]);
        } catch (Throwable $exception) {
            return back()->with('businessos_health', [
                'ok' => false,
                'message' => 'BusinessOS connection check failed: '.$exception->getMessage(),
            ]);
        }
    }
}
