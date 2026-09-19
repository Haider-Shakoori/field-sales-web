<?php

namespace App\Console\Commands;

use App\Support\ProductionReadiness;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\ExecutableFinder;
use Throwable;

class OpsCheck extends Command
{
    protected $signature = 'field-sales:ops-check {--backup-tooling : Include backup path and binary checks}';

    protected $description = 'Check runtime dependencies and database queue health for monitoring.';

    public function handle(ProductionReadiness $readiness): int
    {
        $checks = $readiness->serviceChecks();

        try {
            $checks = array_merge($checks, $this->queueChecks());
        } catch (Throwable $exception) {
            report($exception);
            $checks['queue_health_query'] = false;
        }

        if ($this->option('backup-tooling')) {
            $checks = array_merge($checks, $this->backupToolingChecks());
        }

        $this->table(
            ['Check', 'Status'],
            collect($checks)
                ->map(fn (bool $passed, string $name): array => [
                    str($name)->replace('_', ' ')->title()->toString(),
                    $passed ? 'PASS' : 'FAIL',
                ])
                ->values()
                ->all(),
        );

        if (in_array(false, $checks, true)) {
            $this->error('Operational health check failed.');

            return self::FAILURE;
        }

        $this->info('Operational health check passed.');

        return self::SUCCESS;
    }

    private function backupToolingChecks(): array
    {
        $backupDirectory = (string) config('operations.backup.directory');
        $finder = new ExecutableFinder;

        return [
            'backup_directory_is_writable' => is_dir($backupDirectory) && is_writable($backupDirectory),
            'mysqldump_is_available' => $finder->find('mysqldump') !== null,
            'tar_is_available' => $finder->find('tar') !== null,
            'gzip_extension_is_available' => function_exists('gzopen'),
        ];
    }

    private function queueChecks(): array
    {
        if (! Schema::hasTable('jobs') || ! Schema::hasTable('failed_jobs')) {
            return [
                'queue_tables_available' => false,
            ];
        }

        $waitingJobs = DB::table('jobs')->whereNull('reserved_at');
        $queuedJobs = (int) (clone $waitingJobs)->count();
        $failedJobs = (int) DB::table('failed_jobs')->count();
        $oldestCreatedAt = (clone $waitingJobs)->min('created_at');
        $retryAfter = max(1, (int) config('queue.connections.database.retry_after', 90));
        $staleReservedJobs = (int) DB::table('jobs')
            ->whereNotNull('reserved_at')
            ->where('reserved_at', '<', now()->getTimestamp() - $retryAfter)
            ->count();
        $oldestAge = $oldestCreatedAt
            ? max(0, now()->getTimestamp() - (int) $oldestCreatedAt)
            : 0;

        return [
            'queue_backlog_within_limit' => $queuedJobs <= max(
                0,
                (int) config('operations.monitoring.max_queued_jobs', 1000),
            ),
            'failed_jobs_within_limit' => $failedJobs <= max(
                0,
                (int) config('operations.monitoring.max_failed_jobs', 10),
            ),
            'oldest_queued_job_within_limit' => $oldestAge <= max(
                1,
                (int) config('operations.monitoring.max_oldest_job_seconds', 600),
            ),
            'stale_reserved_jobs_absent' => $staleReservedJobs === 0,
        ];
    }
}
