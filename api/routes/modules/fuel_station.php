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
use App\Modules\FuelStation\Interfaces\Api\V1\Controllers\FuelCrmController;
use App\Modules\FuelStation\Interfaces\Api\V1\Controllers\FuelImportController;
use App\Modules\FuelStation\Interfaces\Api\V1\Controllers\FuelIncidentController;
use App\Modules\FuelStation\Interfaces\Api\V1\Controllers\FuelMeterReadingController;
use App\Modules\FuelStation\Interfaces\Api\V1\Controllers\FuelPresenceController;
use App\Modules\FuelStation\Interfaces\Api\V1\Controllers\FuelReferenceController;
use App\Modules\FuelStation\Interfaces\Api\V1\Controllers\FuelReportController;
use App\Modules\FuelStation\Interfaces\Api\V1\Controllers\FuelSaleController;
use App\Modules\FuelStation\Interfaces\Api\V1\Controllers\FuelShiftController;
use App\Modules\FuelStation\Interfaces\Api\V1\Controllers\FuelStockController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:api', 'auth:sanctum', 'token.refresh', 'tenant', 'throttle:api-plan', 'throttle:fuel'])->group(function (): void {
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

    // FUEL-010 (#5804) — signalement d'incident (tout employé du tenant).
    Route::post('/fuel-station/incidents', [FuelIncidentController::class, 'store']);    // FUEL-007 (#5801) — cycle de vie des sessions de caisse (policy par
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

        // FUEL-009 (#5803) : stocks, cuves et rapprochement (manager).
        Route::get('/fuel-station/stocks', [FuelStockController::class, 'stocks']);
        Route::post('/fuel-station/tanks/{tank}/deliveries', [FuelStockController::class, 'storeDelivery'])
            ->whereNumber('tank');
        Route::post('/fuel-station/stations/{station}/reconciliations', [FuelStockController::class, 'runReconciliation'])
            ->whereNumber('station');
        Route::get('/fuel-station/reconciliations', [FuelStockController::class, 'reconciliations']);
        Route::get('/fuel-station/reconciliations/{run}', [FuelStockController::class, 'showReconciliation'])
            ->whereNumber('run');

        // FUEL-010 (#5804) : incidents & maintenance (manager).
        Route::get('/fuel-station/incidents', [FuelIncidentController::class, 'index']);
        Route::get('/fuel-station/incidents/{incident}', [FuelIncidentController::class, 'show'])
            ->whereNumber('incident');
        Route::post('/fuel-station/incidents/{incident}/assign', [FuelIncidentController::class, 'assign'])
            ->whereNumber('incident');
        Route::post('/fuel-station/incidents/{incident}/resolve', [FuelIncidentController::class, 'resolve'])
            ->whereNumber('incident');
        Route::post('/fuel-station/incidents/{incident}/close', [FuelIncidentController::class, 'close'])
            ->whereNumber('incident');
        Route::get('/fuel-station/maintenance-tasks', [FuelIncidentController::class, 'tasks']);
        Route::post('/fuel-station/maintenance-tasks', [FuelIncidentController::class, 'storeTask']);
        Route::post('/fuel-station/maintenance-tasks/{task}/transition', [FuelIncidentController::class, 'transitionTask'])
            ->whereNumber('task');

        // FUEL-017 (#5811) : reporting opérationnel (manager).
        Route::get('/fuel-station/reports/{type}', [FuelReportController::class, 'show'])
            ->whereIn('type', ['pump_volumes', 'sales', 'shifts', 'variances', 'stock', 'station_performance']);
        // FUEL-018 (#5812) : export CSV contrôlé depuis les snapshots.
        Route::get('/fuel-station/reports/{type}/export', [FuelImportController::class, 'export'])
            ->whereIn('type', ['pump_volumes', 'sales', 'shifts', 'variances', 'stock', 'station_performance']);

        // FUEL-018 (#5812) : imports CSV (preview → commit/cancel).
        Route::get('/fuel-station/imports', [FuelImportController::class, 'index']);
        Route::post('/fuel-station/imports/preview', [FuelImportController::class, 'preview']);
        Route::post('/fuel-station/imports/{import}/commit', [FuelImportController::class, 'commit'])
            ->whereNumber('import');
        Route::post('/fuel-station/imports/{import}/cancel', [FuelImportController::class, 'cancel'])
            ->whereNumber('import');

        // FUEL-016 (#5810) : comptes professionnels & visites (manager).
        Route::get('/fuel-station/accounts', [FuelCrmController::class, 'index']);
        Route::post('/fuel-station/accounts', [FuelCrmController::class, 'store']);
        Route::get('/fuel-station/accounts/{account}', [FuelCrmController::class, 'show'])
            ->whereNumber('account');
        Route::get('/fuel-station/accounts/{account}/visits', [FuelCrmController::class, 'visits'])
            ->whereNumber('account');
        Route::post('/fuel-station/accounts/{account}/visits', [FuelCrmController::class, 'recordVisit'])
            ->whereNumber('account');
        Route::put('/fuel-station/accounts/{account}/consents', [FuelCrmController::class, 'updateConsents'])
            ->whereNumber('account');

        // FUEL-011 (#5805) : référentiel CRUD manager (stations, sites,
        // pompes, cuves, compteurs, produits) — deny-by-default, filtres
        // allowlist, pagination bornée.
        Route::get('/fuel-station/{resource}', [FuelReferenceController::class, 'index'])
            ->whereIn('resource', ['stations', 'sites', 'pumps', 'tanks', 'meters', 'products']);
        Route::post('/fuel-station/{resource}', [FuelReferenceController::class, 'store'])
            ->whereIn('resource', ['stations', 'sites', 'pumps', 'tanks', 'meters', 'products']);
        Route::get('/fuel-station/{resource}/{id}', [FuelReferenceController::class, 'show'])
            ->whereIn('resource', ['stations', 'sites', 'pumps', 'tanks', 'meters', 'products'])
            ->whereNumber('id');
        Route::put('/fuel-station/{resource}/{id}', [FuelReferenceController::class, 'update'])
            ->whereIn('resource', ['stations', 'sites', 'pumps', 'tanks', 'meters', 'products'])
            ->whereNumber('id');
        Route::delete('/fuel-station/{resource}/{id}', [FuelReferenceController::class, 'destroy'])
            ->whereIn('resource', ['stations', 'sites', 'pumps', 'tanks', 'meters', 'products'])
            ->whereNumber('id');
    });
});
