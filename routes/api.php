<?php

use App\Http\Controllers\Api\GateSyncController;
use Illuminate\Support\Facades\Route;

Route::prefix('gate/v1')
    ->name('api.gate.')
    ->middleware(['gate-terminal', 'throttle:60,1'])
    ->group(function (): void {
        Route::get('/snapshot', [GateSyncController::class, 'snapshot'])->name('snapshot');
        Route::post('/synchronize', [GateSyncController::class, 'synchronize'])->name('synchronize');
        Route::post('/closeout', [GateSyncController::class, 'closeout'])->name('closeout');
    });
