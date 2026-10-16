<?php

declare(strict_types=1);

/**
 * Routes du module Farm OS (verticale agricole, BC-37 AGRICULTURE — spec
 * docs/specifications/SOLUTION_FARM_OS.md §7).
 *
 * Chargé depuis routes/api.php à l'intérieur du groupe /v1 (jamais de
 * re-préfixe `v1`).
 *
 * Gate `module.farm_os` : feature flag companies.features.farm_os posé par
 * `SolutionActivator` à l'activation de la solution (fail-closed — tenant sans
 * le flag ⇒ 403). Les capacités (13 outils activables) sont contrôlées PLUS FIN
 * dans les contrôleurs, via `FarmCapabilityGate` : un outil non activé n'est
 * jamais accessible, même par appel direct de l'API.
 */

use App\Modules\FarmOs\Interfaces\Api\V1\Controllers\CropCycleController;
use App\Modules\FarmOs\Interfaces\Api\V1\Controllers\FarmDashboardController;
use App\Modules\FarmOs\Interfaces\Api\V1\Controllers\FarmOperationController;
use App\Modules\FarmOs\Interfaces\Api\V1\Controllers\FarmSettingsController;
use App\Modules\FarmOs\Interfaces\Api\V1\Controllers\FarmSiteController;
use App\Modules\FarmOs\Interfaces\Api\V1\Controllers\ParcelController;
use Illuminate\Support\Facades\Route;

Route::middleware(['module.farm_os'])
    ->prefix('farm')
    ->group(function (): void {
        // ── Réglages : activités déclarées, outils activés, référentiels ──
        Route::get('/settings', [FarmSettingsController::class, 'show']);
        Route::put('/activities', [FarmSettingsController::class, 'updateActivities']);
        Route::get('/capabilities/available', [FarmSettingsController::class, 'availableCapabilities']);
        Route::put('/capabilities', [FarmSettingsController::class, 'updateCapabilities']);

        // ── Tableau de bord de l'exploitation ──
        Route::get('/dashboard', [FarmDashboardController::class, 'index']);

        // ── Exploitations / sites ──
        Route::get('/sites', [FarmSiteController::class, 'index']);
        Route::post('/sites', [FarmSiteController::class, 'store']);
        Route::get('/sites/{site}', [FarmSiteController::class, 'show']);

        // ── Parcelles ──
        Route::get('/parcels', [ParcelController::class, 'index']);
        Route::post('/sites/{site}/parcels', [ParcelController::class, 'store']);
        Route::get('/parcels/{parcel}', [ParcelController::class, 'show']);
        Route::get('/parcels/{parcel}/history', [ParcelController::class, 'history']);

        // ── Cycles culturaux ──
        Route::get('/crop-cycles', [CropCycleController::class, 'index']);
        Route::post('/parcels/{parcel}/crop-cycles', [CropCycleController::class, 'store']);
        Route::post('/crop-cycles/{cycle}/close', [CropCycleController::class, 'close']);

        // ── Opérations agricoles (interventions) ──
        Route::get('/operations', [FarmOperationController::class, 'index']);
        Route::get('/operations/today', [FarmOperationController::class, 'today']);
        Route::get('/operations/late', [FarmOperationController::class, 'late']);
        Route::post('/sites/{site}/operations', [FarmOperationController::class, 'store']);
        Route::get('/operations/{operation}', [FarmOperationController::class, 'show']);
        Route::post('/operations/{operation}/start', [FarmOperationController::class, 'start']);
        Route::post('/operations/{operation}/complete', [FarmOperationController::class, 'complete']);
        Route::post('/operations/{operation}/skip', [FarmOperationController::class, 'skip']);
        Route::post('/operations/{operation}/cancel', [FarmOperationController::class, 'cancel']);
        Route::post('/operations/{operation}/assign', [FarmOperationController::class, 'assign']);
        Route::post('/operations/{operation}/progress', [FarmOperationController::class, 'progress']);
        Route::post('/operations/{operation}/report-problem', [FarmOperationController::class, 'reportProblem']);
    });
