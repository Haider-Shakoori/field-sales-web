<?php

use App\Support\ProductionReadiness;
use Illuminate\Support\Facades\Artisan;

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
