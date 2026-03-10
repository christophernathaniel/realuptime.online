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

Schedule::command('realuptime:prune-monitoring-data')
    ->dailyAt('03:15')
    ->withoutOverlapping()
    ->onOneServer();
