<?php

namespace App\Jobs;

use App\Models\BusinessOsSyncRun;
use App\Models\Tenant;
use App\Services\BusinessOs\SyncService;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SyncBusinessOsRun implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [30, 120, 300];

    public function __construct(
        public int $tenantId,
        public string $runUuid,
    ) {}

    public function handle(
        TenantContext $context,
        SyncService $sync,
    ): void {
        $tenant = $context->withPlatformScope(
            fn () => Tenant::query()->findOrFail($this->tenantId),
        );

        $context->withTenant(
            $tenant,
            function () use ($sync, $tenant): void {
                $run = BusinessOsSyncRun::query()
                    ->where('uuid', $this->runUuid)
                    ->firstOrFail();

                $sync->execute($tenant, $run);
            },
        );
    }

    public function failed(Throwable $exception): void
    {
        $context = app(TenantContext::class);
        $tenant = $context->withPlatformScope(
            fn () => Tenant::query()->find($this->tenantId),
        );

        if (! $tenant) {
            return;
        }

        $context->withTenant(
            $tenant,
            function () use ($exception): void {
                BusinessOsSyncRun::query()
                    ->where('uuid', $this->runUuid)
                    ->whereIn('status', ['queued', 'running'])
                    ->update([
                        'status' => 'failed',
                        'finished_at' => now(),
                        'error_message' => mb_substr(
                            $exception->getMessage(),
                            0,
                            8000,
                        ),
                    ]);
            },
        );
    }
}
