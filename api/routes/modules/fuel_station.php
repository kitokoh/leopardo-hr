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
use App\Modules\FuelStation\Interfaces\Api\V1\Controllers\FuelIncidentController;
use App\Modules\FuelStation\Interfaces\Api\V1\Controllers\FuelMaintenanceTaskController;
use App\Modules\FuelStation\Interfaces\Api\V1\Controllers\FuelMeterReadingController;
use App\Modules\FuelStation\Interfaces\Api\V1\Controllers\FuelPresenceController;
use App\Modules\FuelStation\Interfaces\Api\V1\Controllers\FuelProductController;
use App\Modules\FuelStation\Interfaces\Api\V1\Controllers\FuelPumpController;
use App\Modules\FuelStation\Interfaces\Api\V1\Controllers\FuelSaleController;
use App\Modules\FuelStation\Interfaces\Api\V1\Controllers\FuelShiftController;
use App\Modules\FuelStation\Interfaces\Api\V1\Controllers\FuelSiteController;
use App\Modules\FuelStation\Interfaces\Api\V1\Controllers\FuelStationController;
use App\Modules\FuelStation\Interfaces\Api\V1\Controllers\FuelStockController;
use App\Modules\FuelStation\Interfaces\Api\V1\Controllers\FuelTankController;
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
    // FUEL-010 (#5804) — signalement d'incident (tout employé authentifié).
    Route::post('/fuel-station/stations/{station}/incidents', [FuelIncidentController::class, 'store'])
        ->middleware('throttle:fuel-station-write')
        ->whereNumber('station');

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
        // FUEL-011 (#5805) : référentiel manager — stations, sites, pompes,
        // cuves, produits (CRUD tenant-scoped, 404 sûr cross-tenant).
        Route::get('/fuel-station/stations', [FuelStationController::class, 'index']);
        Route::post('/fuel-station/stations', [FuelStationController::class, 'store']);
        Route::get('/fuel-station/stations/{station}', [FuelStationController::class, 'show'])->whereNumber('station');
        Route::put('/fuel-station/stations/{station}', [FuelStationController::class, 'update'])->whereNumber('station');
        Route::delete('/fuel-station/stations/{station}', [FuelStationController::class, 'destroy'])->whereNumber('station');
        Route::get('/fuel-station/stations/{station}/sites', [FuelSiteController::class, 'index'])->whereNumber('station');
        Route::post('/fuel-station/stations/{station}/sites', [FuelSiteController::class, 'store'])->whereNumber('station');
        Route::get('/fuel-station/sites/{site}', [FuelSiteController::class, 'show'])->whereNumber('site');
        Route::put('/fuel-station/sites/{site}', [FuelSiteController::class, 'update'])->whereNumber('site');
        Route::delete('/fuel-station/sites/{site}', [FuelSiteController::class, 'destroy'])->whereNumber('site');
        Route::get('/fuel-station/stations/{station}/pumps', [FuelPumpController::class, 'index'])->whereNumber('station');
        Route::post('/fuel-station/stations/{station}/pumps', [FuelPumpController::class, 'store'])->whereNumber('station');
        Route::get('/fuel-station/pumps/{pump}', [FuelPumpController::class, 'show'])->whereNumber('pump');
        Route::put('/fuel-station/pumps/{pump}', [FuelPumpController::class, 'update'])->whereNumber('pump');
        Route::delete('/fuel-station/pumps/{pump}', [FuelPumpController::class, 'destroy'])->whereNumber('pump');
        Route::get('/fuel-station/stations/{station}/tanks', [FuelTankController::class, 'index'])->whereNumber('station');
        Route::post('/fuel-station/stations/{station}/tanks', [FuelTankController::class, 'store'])->whereNumber('station');
        Route::get('/fuel-station/tanks/{tank}', [FuelTankController::class, 'show'])->whereNumber('tank');
        Route::put('/fuel-station/tanks/{tank}', [FuelTankController::class, 'update'])->whereNumber('tank');
        Route::delete('/fuel-station/tanks/{tank}', [FuelTankController::class, 'destroy'])->whereNumber('tank');
        Route::get('/fuel-station/products', [FuelProductController::class, 'index']);
        Route::post('/fuel-station/products', [FuelProductController::class, 'store']);
        Route::get('/fuel-station/products/{product}', [FuelProductController::class, 'show'])->whereNumber('product');
        Route::put('/fuel-station/products/{product}', [FuelProductController::class, 'update'])->whereNumber('product');
        Route::delete('/fuel-station/products/{product}', [FuelProductController::class, 'destroy'])->whereNumber('product');
        // FUEL-009 (#5803) : stocks, livraisons, ajustements, rapprochements.
        Route::get('/fuel-station/stations/{station}/stock', [FuelStockController::class, 'index'])->whereNumber('station');
        Route::get('/fuel-station/stations/{station}/deliveries', [FuelStockController::class, 'deliveries'])->whereNumber('station');
        Route::post('/fuel-station/stations/{station}/deliveries', [FuelStockController::class, 'storeDelivery'])->middleware('throttle:fuel-station-write')->whereNumber('station');
        Route::post('/fuel-station/stations/{station}/adjustments', [FuelStockController::class, 'storeAdjustment'])->middleware('throttle:fuel-station-write')->whereNumber('station');
        Route::get('/fuel-station/stations/{station}/reconciliations', [FuelStockController::class, 'reconciliations'])->whereNumber('station');
        // FUEL-010 (#5804) : incidents + tâches de maintenance (manager).
        Route::get('/fuel-station/incidents', [FuelIncidentController::class, 'index']);
        Route::get('/fuel-station/incidents/{incident}', [FuelIncidentController::class, 'show'])->whereNumber('incident');
        Route::put('/fuel-station/incidents/{incident}', [FuelIncidentController::class, 'update'])->whereNumber('incident');
        Route::post('/fuel-station/incidents/{incident}/assign', [FuelIncidentController::class, 'assign'])->middleware('throttle:fuel-station-write')->whereNumber('incident');
        Route::post('/fuel-station/incidents/{incident}/start', [FuelIncidentController::class, 'start'])->middleware('throttle:fuel-station-write')->whereNumber('incident');
        Route::post('/fuel-station/incidents/{incident}/resolve', [FuelIncidentController::class, 'resolve'])->middleware('throttle:fuel-station-write')->whereNumber('incident');
        Route::post('/fuel-station/incidents/{incident}/close', [FuelIncidentController::class, 'close'])->middleware('throttle:fuel-station-write')->whereNumber('incident');
        Route::get('/fuel-station/maintenance-tasks', [FuelMaintenanceTaskController::class, 'index']);
        Route::post('/fuel-station/maintenance-tasks', [FuelMaintenanceTaskController::class, 'store'])->middleware('throttle:fuel-station-write');
        Route::get('/fuel-station/maintenance-tasks/{task}', [FuelMaintenanceTaskController::class, 'show'])->whereNumber('task');
        Route::put('/fuel-station/maintenance-tasks/{task}', [FuelMaintenanceTaskController::class, 'update'])->whereNumber('task');
        Route::post('/fuel-station/maintenance-tasks/{task}/complete', [FuelMaintenanceTaskController::class, 'complete'])->middleware('throttle:fuel-station-write')->whereNumber('task');
    });
});
