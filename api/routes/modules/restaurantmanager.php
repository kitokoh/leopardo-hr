<?php

/**
 * Routes de la verticale RestaurantManager (BC-25 RESTAURANT).
 *
 * Chargé depuis routes/api.php à l'intérieur du groupe /v1 — ne JAMAIS
 * re-préfixer `v1` (règle AGENTS.md).
 *
 * Middleware du groupe (convention modules, cf. travelagency.php) :
 *   - throttle:api     → limite globale de l'API
 *   - auth:sanctum     → authentification (Sanctum)
 *   - token.refresh    → auto-refresh du token
 *   - tenant           → résolution de la company + garde-fous statut/archive
 *   - throttle:api-plan→ limite selon le plan tarifaire
 *   - module.restaurantmanager → feature flag companies.features.restaurantmanager
 *
 * Référence : docs/specifications/SOLUTION_RESTAURANT_MANAGER.md (§5 API v1).
 */

use App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers\RestaurantHealthController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:api', 'auth:sanctum', 'token.refresh', 'tenant', 'throttle:api-plan', 'module.restaurantmanager'])
    ->prefix('restaurant')
    ->group(function (): void {
        // Smoke test de la verticale (RESTO-101/#6158) — lecture pure.
        Route::get('/ping', [RestaurantHealthController::class, 'ping']);
    });
