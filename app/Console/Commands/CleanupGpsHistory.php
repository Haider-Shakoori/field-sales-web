<?php

namespace App\Console\Commands;

use App\Models\LocationHistory;
use App\Models\LocationSyncBatch;
use App\Models\Tenant;
use App\Services\TrackingSettingsService;
use Illuminate\Console\Command;

class CleanupGpsHistory extends Command
{
    protected $signature = 'field-sales:cleanup-gps';
    protected $description = 'Apply tenant GPS retention policy to historical locations and old sync batches.';

    public function handle(TrackingSettingsService $settingsService): int
    {
        Tenant::chunkById(100, function ($tenants) use ($settingsService) {
            foreach ($tenants as $tenant) {
                $settings = $settingsService->get($tenant);
                $historyCutoff = now()->subDays($settings['gps_retention_days']);

                LocationHistory::where('tenant_id', $tenant->id)
                    ->where('recorded_at', '<', $historyCutoff)
                    ->delete();

                LocationSyncBatch::where('tenant_id', $tenant->id)
                    ->where('received_at', '<', now()->subDays(30))
                    ->delete();
            }
        });

        return self::SUCCESS;
    }
}
