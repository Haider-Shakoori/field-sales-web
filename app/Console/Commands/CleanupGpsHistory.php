<?php

namespace App\Console\Commands;

use App\Models\LocationHistory;
use App\Models\LocationSyncBatch;
use App\Models\Tenant;
use App\Services\TrackingSettingsService;
use App\Tenancy\TenantContext;
use Illuminate\Console\Command;

class CleanupGpsHistory extends Command
{
    protected $signature = 'field-sales:cleanup-gps';

    protected $description = 'Apply tenant GPS retention policy to historical locations and old sync batches.';

    public function handle(TrackingSettingsService $settingsService, TenantContext $context): int
    {
        Tenant::chunkById(100, function ($tenants) use ($settingsService, $context): void {
            foreach ($tenants as $tenant) {
                $context->withTenant($tenant, function () use ($settingsService): void {
                    $settings = $settingsService->get(app(TenantContext::class)->hasTenant()
                        ? Tenant::findOrFail(app(TenantContext::class)->tenantId())
                        : throw new \LogicException('Tenant context missing.'));

                    $historyCutoff = now()->subDays($settings['gps_retention_days']);

                    LocationHistory::where('recorded_at', '<', $historyCutoff)->delete();

                    LocationSyncBatch::where('received_at', '<', now()->subDays(30))->delete();
                });
            }
        });

        return self::SUCCESS;
    }
}
