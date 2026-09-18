<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('field-sales:about', function () {
    $this->info('Field Sales web/backend is ready.');
});

Schedule::command('field-sales:tracking-reminders')->everyTenMinutes()->withoutOverlapping();
Schedule::command('field-sales:cleanup-gps')->dailyAt('02:30')->withoutOverlapping();
