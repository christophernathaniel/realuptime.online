<?php

use App\Http\Controllers\Api\IncidentController;
use App\Http\Controllers\Api\MonitorController;
use App\Http\Controllers\Api\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['api', 'throttle:api', 'api.token'])
    ->prefix('v1')
    ->group(function () {
        Route::get('workspace', [WorkspaceController::class, 'show']);
        Route::get('monitors', [MonitorController::class, 'index']);
        Route::get('monitors/{monitor}', [MonitorController::class, 'show']);
        Route::post('monitors/{monitor}/run-now', [MonitorController::class, 'runNow']);
        Route::get('incidents', [IncidentController::class, 'index']);
    });
