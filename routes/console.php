<?php

use App\Models\Tenant;
use App\Services\AiConversationService;
use App\Services\AppointmentReminderService;
use App\Support\ProductionReadiness;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Artisan::command('field-sales:about', function (): void {
    $this->info('Field Sales web/backend is ready.');
});

Artisan::command('field-sales:production-check {--services : Include database and filesystem readiness checks}', function (): int {
    $readiness = app(ProductionReadiness::class);
    $checks = $readiness->configurationChecks();

    if ($this->option('services')) {
        $checks = array_merge($checks, $readiness->serviceChecks());
    }

    $rows = collect($checks)
        ->map(fn (bool $passed, string $name): array => [
            str($name)->replace('_', ' ')->title()->toString(),
            $passed ? 'PASS' : 'FAIL',
        ])
        ->values()
        ->all();

    $this->table(['Check', 'Status'], $rows);

    if (in_array(false, $checks, true)) {
        $this->error('Production readiness checks failed.');

        return 1;
    }

    $this->info('Production readiness checks passed.');

    return 0;
});

Artisan::command('field-sales:send-appointment-reminders', function (): int {
    $context = app(TenantContext::class);
    $tenants = $context->withPlatformScope(
        fn () => Tenant::query()->get(['id']),
    );
    $sent = 0;

    foreach ($tenants as $tenant) {
        $sent += $context->withTenant(
            $tenant,
            fn () => app(AppointmentReminderService::class)->sendDue(),
        );
    }

    $this->info("Sent {$sent} appointment reminder(s).");

    return 0;
});

Artisan::command('field-sales:prune-ai-history', function (): int {
    $context = app(TenantContext::class);
    $tenants = $context->withPlatformScope(
        fn () => Tenant::query()->get(['id', 'settings']),
    );
    $deleted = 0;

    foreach ($tenants as $tenant) {
        $deleted += $context->withTenant(
            $tenant,
            fn () => app(AiConversationService::class)
                ->pruneExpiredForTenant($tenant),
        );
    }

    $this->info("Pruned {$deleted} expired Ask FieldPulse conversation(s).");

    return 0;
});

Schedule::command('field-sales:send-appointment-reminders')
    ->everyFiveMinutes()
    ->withoutOverlapping();

Schedule::command('field-sales:prune-ai-history')
    ->dailyAt('03:45')
    ->withoutOverlapping();

Schedule::command('queue:prune-failed --hours=168')
    ->dailyAt('03:30')
    ->withoutOverlapping();

Schedule::call(function (): void {
    Cache::put(
        'field-sales:scheduler-heartbeat',
        now()->getTimestamp(),
        now()->addMinutes(10),
    );
})
    ->name('field-sales:scheduler-heartbeat')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('field-sales:backup --label=scheduled')
    ->dailyAt('02:15')
    ->when(fn (): bool => (bool) config('operations.shared_hosting.scheduled_backups'))
    ->withoutOverlapping();
