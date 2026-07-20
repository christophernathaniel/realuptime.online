<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('monitors:run-due')
    ->everyThirtySeconds()
    ->withoutOverlapping()
    ->onOneServer();

if (config('realuptime.retention.automatic_pruning_enabled', true)) {
    Schedule::command('realuptime:prune-monitoring-data')
        ->name('realuptime:prune-monitoring-data')
        ->dailyAt((string) config('realuptime.retention.prune_at', '03:15'))
        ->timezone(config('app.timezone'))
        ->withoutOverlapping(360)
        ->runInBackground()
        ->evenInMaintenanceMode()
        ->onOneServer();
}
