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

use App\Modules\TravelAgency\Interfaces\Api\V1\Controllers\TravelHealthController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:api', 'auth:sanctum', 'token.refresh', 'tenant', 'throttle:api-plan', 'module.travelagency'])
    ->prefix('travel')
    ->group(function (): void {
        // Smoke test de la verticale (TRAVEL-101/#5977) — lecture pure.
        Route::get('/ping', [TravelHealthController::class, 'ping']);
    });
