<?php

declare(strict_types=1);

/**
 * Routes FuelStation (solution verticale) — FUEL-004.
 *
 * Toutes les routes sont tenant-scoped et soumises au feature flag
 * `fuel_station` (activation #5795) : solution inactive → 403.
 */

use App\Modules\FuelStation\Interfaces\Api\V1\Controllers\FuelMeterReadingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:api', 'auth:sanctum', 'token.refresh', 'tenant', 'throttle:api-plan'])->group(function (): void {
    // FUEL-004 — relevés de compteur par pompe (spec §13.4).
    Route::post('/fuel-station/stations/{station}/pumps/{pump}/meters/{meter}/readings', [FuelMeterReadingController::class, 'record'])
        ->whereNumber('station')
        ->whereNumber('pump')
        ->whereNumber('meter');
    Route::get('/fuel-station/stations/{station}/pumps/{pump}/meters/{meter}/readings', [FuelMeterReadingController::class, 'index'])
        ->whereNumber('station')
        ->whereNumber('pump')
        ->whereNumber('meter');
    Route::get('/fuel-station/stations/{station}/pumps/{pump}/meters/{meter}/intervals', [FuelMeterReadingController::class, 'intervals'])
        ->whereNumber('station')
        ->whereNumber('pump')
        ->whereNumber('meter');

    // Corrections et revues — manager principal/rh (Policy).
    Route::post('/fuel-station/meter-readings/{reading}/corrections', [FuelMeterReadingController::class, 'correct'])
        ->middleware('api.manager')
        ->whereNumber('reading');
    Route::post('/fuel-station/meter-intervals/{interval}/review', [FuelMeterReadingController::class, 'review'])
        ->middleware('api.manager')
        ->whereNumber('interval');
});
