<?php

namespace App\Support;

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

        return [
            'database_connection' => $database,
            'infrastructure_tables' => $infrastructureTables,
            'storage_is_writable' => is_writable(storage_path()),
            'bootstrap_cache_is_writable' => is_writable(base_path('bootstrap/cache')),
        ];
    }

    public function servicesAreReady(): bool
    {
        return ! in_array(false, $this->serviceChecks(), true);
    }
}
