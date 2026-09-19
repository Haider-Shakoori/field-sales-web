<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Stage2Batch17OperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_operational_health_check_passes_on_healthy_runtime(): void
    {
        $exitCode = Artisan::call('field-sales:ops-check');

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString(
            'Operational health check passed.',
            Artisan::output(),
        );
    }

    public function test_operational_health_check_fails_when_failed_job_threshold_is_exceeded(): void
    {
        config()->set('operations.monitoring.max_failed_jobs', 0);

        DB::table('failed_jobs')->insert([
            'uuid' => '11111111-1111-4111-8111-111111111111',
            'connection' => 'database',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => 'Synthetic Batch 17 monitoring fixture.',
            'failed_at' => now(),
        ]);

        $exitCode = Artisan::call('field-sales:ops-check');

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString(
            'Operational health check failed.',
            Artisan::output(),
        );
    }

    public function test_backup_command_fails_closed_on_non_mysql_connection(): void
    {
        $this->assertSame('sqlite', config('database.default'));

        $exitCode = Artisan::call('field-sales:backup', ['--label' => 'ci']);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString(
            'Backup failed.',
            Artisan::output(),
        );
        $this->assertStringNotContainsString('password', Artisan::output());
    }

    public function test_scheduler_contains_failed_job_retention_cleanup(): void
    {
        Artisan::call('schedule:list');

        $this->assertStringContainsString(
            'queue:prune-failed --hours=168',
            Artisan::output(),
        );
    }

    public function test_production_operations_artifacts_are_present(): void
    {
        $paths = [
            'ops/README.md',
            'ops/nginx/field-sales-bootstrap.conf',
            'ops/nginx/field-sales.conf',
            'ops/php/99-field-sales.ini',
            'ops/scripts/deploy.sh',
            'ops/scripts/rollback.sh',
            'ops/scripts/restore-backup.sh',
            'ops/scripts/monitor.sh',
            'ops/systemd/field-sales-queue@.service',
            'ops/systemd/field-sales-scheduler.timer',
            'ops/systemd/field-sales-backup.timer',
            'ops/systemd/field-sales-monitor.timer',
        ];

        foreach ($paths as $path) {
            $this->assertFileExists(base_path($path), $path);
        }
    }
}
