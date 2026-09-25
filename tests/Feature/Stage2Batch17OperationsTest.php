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

    public function test_operational_health_check_detects_stale_reserved_jobs(): void
    {
        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => '{}',
            'attempts' => 1,
            'reserved_at' => now()->subSeconds(120)->getTimestamp(),
            'available_at' => now()->getTimestamp(),
            'created_at' => now()->subSeconds(120)->getTimestamp(),
        ]);

        $exitCode = Artisan::call('field-sales:ops-check');

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString(
            'Stale Reserved Jobs Absent',
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

    public function test_queue_worker_timeout_stays_below_database_retry_window(): void
    {
        $unit = file_get_contents(base_path('ops/systemd/field-sales-queue@.service'));

        $this->assertNotFalse($unit);
        $this->assertSame(1, preg_match('/--timeout=(\d+)/', $unit, $matches));
        $this->assertLessThan(
            (int) config('queue.connections.database.retry_after'),
            (int) $matches[1],
        );
    }

    public function test_tls_nginx_configuration_propagates_secure_request_state(): void
    {
        $nginx = file_get_contents(base_path('ops/nginx/field-sales.conf'));

        $this->assertNotFalse($nginx);
        $this->assertStringContainsString('fastcgi_param HTTPS on;', $nginx);
    }


    public function test_scheduler_heartbeat_can_be_required_for_shared_hosting(): void
    {
        config()->set('operations.monitoring.require_scheduler_heartbeat', true);
        config()->set('operations.monitoring.scheduler_heartbeat_max_age_seconds', 180);

        cache()->forget('field-sales:scheduler-heartbeat');
        $this->assertFalse(app(\App\Support\ProductionReadiness::class)->serviceChecks()['scheduler_heartbeat_is_fresh']);

        cache()->put('field-sales:scheduler-heartbeat', now()->getTimestamp(), now()->addMinutes(10));
        $this->assertTrue(app(\App\Support\ProductionReadiness::class)->serviceChecks()['scheduler_heartbeat_is_fresh']);
    }

    public function test_cpanel_shared_hosting_artifacts_are_present(): void
    {
        $this->assertFileExists(base_path('ops/CPANEL_PRODUCTION.md'));
        $this->assertFileExists(base_path('ops/scripts/deploy-cpanel.sh'));

        $script = file_get_contents(base_path('ops/scripts/deploy-cpanel.sh'));
        $this->assertNotFalse($script);
        $this->assertStringContainsString('git pull --ff-only', $script);
        $this->assertStringContainsString('field-sales:backup --label=pre-deploy --database-only', $script);
        $this->assertStringContainsString('field-sales:production-check --services', $script);
        $this->assertStringContainsString('field-sales:ops-check', $script);
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
