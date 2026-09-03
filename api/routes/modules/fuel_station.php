<?php

declare(strict_types=1);

/**
 * Routes FuelStation (solution verticale) — FUEL-002..008.
 *
 * Toutes les routes sont tenant-scoped et soumises au feature flag
 * `fuel_station` (activation #5795) : solution inactive → 403
 * (contrôle `assertSolutionActive()` dans chaque contrôleur).
 *
 * Chemins : /fuel-station/... (ids numériques bigint, whereNumber).
 * RBAC : CRUD shifts + affectations + rostre présence = manager
 * (middleware api.manager + Policies) ; /fuel-station/me/* = tout
 * employé authentifié (scope employee_id) ; sessions de caisse et
 * ventes = policy par propriétaire (opened_by/employee_id).
 */

use App\Modules\FuelStation\Interfaces\Api\V1\Controllers\FuelCashSessionController;
use App\Modules\FuelStation\Interfaces\Api\V1\Controllers\FuelMeterReadingController;
use App\Modules\FuelStation\Interfaces\Api\V1\Controllers\FuelPresenceController;
use App\Modules\FuelStation\Interfaces\Api\V1\Controllers\FuelSaleController;
use App\Modules\FuelStation\Interfaces\Api\V1\Controllers\FuelShiftController;
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

    // Self-service pompiste : affectations, présence, sessions de caisse,
    // ventes (scope employee_id, aucune fuite tenant possible).
    Route::get('/fuel-station/me/shifts', [FuelShiftController::class, 'myShifts']);
    Route::get('/fuel-station/me/presence', [FuelPresenceController::class, 'myPresence']);
    Route::get('/fuel-station/me/cash-sessions', [FuelCashSessionController::class, 'mySessions']);
    Route::get('/fuel-station/me/sales', [FuelSaleController::class, 'mySales']);

    // FUEL-008 (#5802) — enregistrement d'une vente (tout employé authentifié).
    Route::post('/fuel-station/sales', [FuelSaleController::class, 'store']);

    // FUEL-007 (#5801) — cycle de vie des sessions de caisse (policy par
    // opened_by : pompiste = ses sessions ; approbation manager).
    Route::post('/fuel-station/cash-sessions', [FuelCashSessionController::class, 'store']);
    Route::post('/fuel-station/cash-sessions/{session}/movements', [FuelCashSessionController::class, 'addMovement'])
        ->whereNumber('session');
    Route::post('/fuel-station/cash-sessions/{session}/close', [FuelCashSessionController::class, 'close'])
        ->whereNumber('session');

    // FUEL-005..008 — administration manager (CRUD shifts + affectations,
    // rostre présence, sessions de caisse, ventes).
    Route::middleware('api.manager')->group(function (): void {
        Route::get('/fuel-station/shifts', [FuelShiftController::class, 'index']);
        Route::post('/fuel-station/shifts', [FuelShiftController::class, 'store']);
        Route::get('/fuel-station/shifts/{shift}', [FuelShiftController::class, 'show'])->whereNumber('shift');
        Route::put('/fuel-station/shifts/{shift}', [FuelShiftController::class, 'update'])->whereNumber('shift');
        Route::delete('/fuel-station/shifts/{shift}', [FuelShiftController::class, 'destroy'])->whereNumber('shift');
        Route::get('/fuel-station/shifts/{shift}/assignments', [FuelShiftController::class, 'assignments'])->whereNumber('shift');
        Route::post('/fuel-station/shifts/{shift}/assignments', [FuelShiftController::class, 'assign'])->whereNumber('shift');
        Route::delete('/fuel-station/shift-assignments/{assignment}', [FuelShiftController::class, 'cancelAssignment'])->whereNumber('assignment');
        // FUEL-006 (#5800) : rostre de présence du shift pour une date (Y-m-d).
        Route::get('/fuel-station/shifts/{shift}/presence', [FuelPresenceController::class, 'shiftPresence'])->whereNumber('shift');
        // FUEL-007 (#5801) : gestion des sessions de caisse (manager).
        Route::get('/fuel-station/cash-sessions', [FuelCashSessionController::class, 'index']);
        Route::get('/fuel-station/cash-sessions/{session}', [FuelCashSessionController::class, 'show'])->whereNumber('session');
        Route::post('/fuel-station/cash-sessions/{session}/approve', [FuelCashSessionController::class, 'approve'])->whereNumber('session');
        // FUEL-008 (#5802) : ventes (manager).
        Route::get('/fuel-station/sales', [FuelSaleController::class, 'index']);
        Route::get('/fuel-station/sales/{sale}', [FuelSaleController::class, 'show'])->whereNumber('sale');
    });
});
