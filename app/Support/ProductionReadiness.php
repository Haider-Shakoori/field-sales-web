<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class ProductionReadiness
{
    public function configurationChecks(): array
    {
        return [
            'environment_is_production' => app()->isProduction(),
            'debug_is_disabled' => ! (bool) config('app.debug'),
            'application_key_is_configured' => filled(config('app.key')),
            'application_url_uses_https' => str_starts_with(
                strtolower((string) config('app.url')),
                'https://',
            ),
            'session_cookie_is_secure' => (bool) config('session.secure'),
            'session_payload_is_encrypted' => (bool) config('session.encrypt'),
            'queue_is_asynchronous' => config('queue.default') !== 'sync',
            'cache_is_persistent' => config('cache.default') !== 'array',
            'database_is_mysql' => config('database.default') === 'mysql',
        ];
    }

    public function serviceChecks(): array
    {
        $database = false;
        $infrastructureTables = false;

        try {
            DB::connection()->getPdo();
            DB::select('select 1');
            $database = true;

            $infrastructureTables = Schema::hasTable('migrations')
                && Schema::hasTable('jobs')
                && Schema::hasTable('failed_jobs');
        } catch (Throwable) {
            // Fail closed. Readiness callers receive only aggregate status.
        }

        $schedulerHeartbeat = true;

        if ((bool) config('operations.monitoring.require_scheduler_heartbeat')) {
            $heartbeat = Cache::get('field-sales:scheduler-heartbeat');
            $maxAge = max(30, (int) config('operations.monitoring.scheduler_heartbeat_max_age_seconds', 180));
            $schedulerHeartbeat = is_numeric($heartbeat)
                && now()->getTimestamp() - (int) $heartbeat <= $maxAge;
        }

        return [
            'database_connection' => $database,
            'infrastructure_tables' => $infrastructureTables,
            'storage_is_writable' => is_writable(storage_path()),
            'bootstrap_cache_is_writable' => is_writable(base_path('bootstrap/cache')),
            'scheduler_heartbeat_is_fresh' => $schedulerHeartbeat,
        ];
    }

    public function servicesAreReady(): bool
    {
        return ! in_array(false, $this->serviceChecks(), true);
    }
}
