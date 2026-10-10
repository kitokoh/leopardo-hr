<?php

/**
 * Routes de la verticale VTC/taxi (BC-34 VTC ; VTC-01/#8357).
 *
 * Chargé depuis routes/api.php à l'intérieur du groupe /v1 — ne JAMAIS
 * re-préfixer `v1` (règle AGENTS.md).
 *
 * Middleware du groupe (convention modules, cf. delivery.php) :
 *   - throttle:api     → limite globale de l'API
 *   - auth:sanctum     → authentification (Sanctum)
 *   - token.refresh    → auto-refresh du token
 *   - tenant           → résolution de la company + garde-fous statut/archive
 *   - throttle:api-plan→ limite selon le plan tarifaire
 *   - module.vtc       → feature flag companies.features.vtc
 *
 * BC-34 VTC : réservation de courses, dispatch au chauffeur disponible le
 * plus proche (via le core géospatial BC-33), tarification, suivi.
 * Référence : docs/specifications/MODULE_GEOCORE_ET_VERTICAL_VTC.md (§5).
 */

use App\Modules\Vtc\Interfaces\Api\V1\Controllers\VtcDispatchController;
use App\Modules\Vtc\Interfaces\Api\V1\Controllers\VtcDriverAdminController;
use App\Modules\Vtc\Interfaces\Api\V1\Controllers\VtcDriverAvailabilityController;
use App\Modules\Vtc\Interfaces\Api\V1\Controllers\VtcDriverOfferController;
use App\Modules\Vtc\Interfaces\Api\V1\Controllers\VtcDriverPositionController;
use App\Modules\Vtc\Interfaces\Api\V1\Controllers\VtcDriverRideController;
use App\Modules\Vtc\Interfaces\Api\V1\Controllers\VtcFareProfileAdminController;
use App\Modules\Vtc\Interfaces\Api\V1\Controllers\VtcHealthController;
use App\Modules\Vtc\Interfaces\Api\V1\Controllers\VtcRideController;
use App\Modules\Vtc\Interfaces\Api\V1\Controllers\VtcRideEstimateController;
use App\Modules\Vtc\Interfaces\Api\V1\Controllers\VtcVehicleAdminController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:api', 'auth:sanctum', 'token.refresh', 'tenant', 'throttle:api-plan', 'module.vtc'])
    ->prefix('vtc')
    ->group(function (): void {
        // Smoke test du module (VTC-01/#8357) — lecture pure.
        Route::get('/ping', [VtcHealthController::class, 'ping']);

        // VTC-03 (#8359) — API passager : devis + demande de course
        // idempotente + consultation/annulation (throttle renforcé sur la
        // création, spec §7).
        Route::post('/rides/estimate', VtcRideEstimateController::class)
            ->middleware('throttle:30,1');
        Route::post('/rides', [VtcRideController::class, 'store'])
            ->middleware('throttle:10,1');
        Route::get('/rides/{id}', [VtcRideController::class, 'show'])
            ->whereNumber('id');
        Route::post('/rides/{id}/cancel', [VtcRideController::class, 'cancel'])
            ->whereNumber('id');

        // VTC-05 (#8361) — API chauffeur : offres, transitions de course,
        // positions (throttle strict + idempotence), disponibilité — rôle
        // vtc.driver (deny-by-default, périmètre = SES courses).
        Route::middleware('vtc.role:driver')->group(function (): void {
            Route::get('/driver/offers', [VtcDriverOfferController::class, 'index']);
            Route::post('/driver/rides/{id}/accept', [VtcDriverRideController::class, 'accept'])
                ->whereNumber('id');
            Route::post('/driver/rides/{id}/decline', [VtcDriverRideController::class, 'decline'])
                ->whereNumber('id');
            Route::post('/driver/rides/{id}/arrive', [VtcDriverRideController::class, 'arrive'])
                ->whereNumber('id');
            Route::post('/driver/rides/{id}/start', [VtcDriverRideController::class, 'start'])
                ->whereNumber('id');
            Route::post('/driver/rides/{id}/complete', [VtcDriverRideController::class, 'complete'])
                ->whereNumber('id');
            Route::post('/driver/position', [VtcDriverPositionController::class, 'store'])
                ->middleware('throttle:30,1');
            Route::post('/driver/availability', [VtcDriverAvailabilityController::class, 'update']);
        });

        // VTC-06 (#8362) — console de répartition (polling v1) : rôle
        // vtc.dispatcher, isolation tenant par scope BelongsToCompany.
        Route::middleware('vtc.role:dispatcher')->group(function (): void {
            Route::get('/dispatch/rides', [VtcDispatchController::class, 'rides']);
            Route::get('/dispatch/drivers', [VtcDispatchController::class, 'drivers']);
        });

        // VTC-06 (#8362) — administration de la verticale : rôle vtc.admin
        // (matrice docs/architecture/VTC_RBAC.md).
        Route::middleware('vtc.role:admin')->group(function (): void {
            Route::get('/fare-profiles', [VtcFareProfileAdminController::class, 'index']);
            Route::post('/fare-profiles', [VtcFareProfileAdminController::class, 'store']);
            Route::put('/fare-profiles/{id}', [VtcFareProfileAdminController::class, 'update'])
                ->whereNumber('id');
            Route::delete('/fare-profiles/{id}', [VtcFareProfileAdminController::class, 'destroy'])
                ->whereNumber('id');

            Route::get('/vehicles', [VtcVehicleAdminController::class, 'index']);
            Route::post('/vehicles', [VtcVehicleAdminController::class, 'store']);
            Route::put('/vehicles/{id}', [VtcVehicleAdminController::class, 'update'])
                ->whereNumber('id');
            Route::delete('/vehicles/{id}', [VtcVehicleAdminController::class, 'destroy'])
                ->whereNumber('id');

            Route::get('/drivers', [VtcDriverAdminController::class, 'index']);
            Route::post('/drivers', [VtcDriverAdminController::class, 'store']);
            Route::put('/drivers/{id}', [VtcDriverAdminController::class, 'update'])
                ->whereNumber('id');
            Route::delete('/drivers/{id}', [VtcDriverAdminController::class, 'destroy'])
                ->whereNumber('id');
        });
    });
