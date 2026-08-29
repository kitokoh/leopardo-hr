<?php

/**
 * Routes de la verticale TravelAgency (BC-24 TRAVEL).
 *
 * Chargé depuis routes/api.php à l'intérieur du groupe /v1 — ne JAMAIS
 * re-préfixer `v1` (règle AGENTS.md).
 *
 * Middleware du groupe (convention modules, cf. crm.php) :
 *   - throttle:api     → limite globale de l'API
 *   - auth:sanctum     → authentification (Sanctum)
 *   - token.refresh    → auto-refresh du token
 *   - tenant           → résolution de la company + garde-fous statut/archive
 *   - throttle:api-plan→ limite selon le plan tarifaire
 *   - module.travelagency → feature flag companies.features.travelagency
 *
 * Référence : docs/specifications/SOLUTION_TRAVEL_AGENCY.md (§7 API v1).
 */

use App\Modules\TravelAgency\Interfaces\Api\V1\Controllers\TravelCarrierController;
use App\Modules\TravelAgency\Interfaces\Api\V1\Controllers\TravelCityController;
use App\Modules\TravelAgency\Interfaces\Api\V1\Controllers\TravelClassController;
use App\Modules\TravelAgency\Interfaces\Api\V1\Controllers\TravelCountryController;
use App\Modules\TravelAgency\Interfaces\Api\V1\Controllers\TravelHealthController;
use App\Modules\TravelAgency\Interfaces\Api\V1\Controllers\TravelOfficeController;
use App\Modules\TravelAgency\Interfaces\Api\V1\Controllers\TravelStationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:api', 'auth:sanctum', 'token.refresh', 'tenant', 'throttle:api-plan', 'module.travelagency'])
    ->prefix('travel')
    ->group(function (): void {
        // Smoke test de la verticale (TRAVEL-101/#5977) — lecture pure.
        Route::get('/ping', [TravelHealthController::class, 'ping']);

        // Référentiel géographique en lecture (TRAVEL-301/#6031).
        Route::get('/countries', [TravelCountryController::class, 'index']);
        Route::get('/cities', [TravelCityController::class, 'index']);

        // Gares/terminaux (TRAVEL-302/#6032).
        Route::get('/stations', [TravelStationController::class, 'index']);
        Route::post('/stations', [TravelStationController::class, 'store']);
        Route::get('/stations/{travelStation}', [TravelStationController::class, 'show']);
        Route::put('/stations/{travelStation}', [TravelStationController::class, 'update']);
        Route::delete('/stations/{travelStation}', [TravelStationController::class, 'destroy']);

        // Bureaux de vente (TRAVEL-303/#6033).
        Route::get('/offices', [TravelOfficeController::class, 'index']);
        Route::post('/offices', [TravelOfficeController::class, 'store']);
        Route::get('/offices/{travelOffice}', [TravelOfficeController::class, 'show']);
        Route::put('/offices/{travelOffice}', [TravelOfficeController::class, 'update']);
        Route::delete('/offices/{travelOffice}', [TravelOfficeController::class, 'destroy']);

        // Compagnies de transport (TRAVEL-304/#6034).
        Route::get('/carriers', [TravelCarrierController::class, 'index']);
        Route::post('/carriers', [TravelCarrierController::class, 'store']);
        Route::get('/carriers/{travelCarrier}', [TravelCarrierController::class, 'show']);
        Route::put('/carriers/{travelCarrier}', [TravelCarrierController::class, 'update']);
        Route::delete('/carriers/{travelCarrier}', [TravelCarrierController::class, 'destroy']);

        // Classes de service (TRAVEL-305/#6035).
        Route::get('/classes', [TravelClassController::class, 'index']);
        Route::post('/classes', [TravelClassController::class, 'store']);
        Route::get('/classes/{travelClass}', [TravelClassController::class, 'show']);
        Route::put('/classes/{travelClass}', [TravelClassController::class, 'update']);
        Route::delete('/classes/{travelClass}', [TravelClassController::class, 'destroy']);
    });
