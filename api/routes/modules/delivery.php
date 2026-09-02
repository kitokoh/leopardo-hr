<?php

/**
 * Routes du module Delivery (BC-26 DELIVERY).
 *
 * Chargé depuis routes/api.php à l'intérieur du groupe /v1 — ne JAMAIS
 * re-préfixer `v1` (règle AGENTS.md).
 *
 * Middleware du groupe (convention modules, cf. restaurantmanager.php) :
 *   - throttle:api     → limite globale de l'API
 *   - auth:sanctum     → authentification (Sanctum)
 *   - token.refresh    → auto-refresh du token
 *   - tenant           → résolution de la company + garde-fous statut/archive
 *   - throttle:api-plan→ limite selon le plan tarifaire
 *   - module.delivery  → feature flag companies.features.delivery
 *
 * BC-26 DELIVERY : module de livraison dernier-kilomètre générique activable
 * par tout tenant qui livre (agence, restaurant, retail, e-commerce, CRM,
 * pharmacie).
 * Référence : docs/specifications/SOLUTION_DELIVERY.md (§4 API v1).
 */

use App\Modules\Delivery\Interfaces\Api\V1\Controllers\DeliveryHealthController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:api', 'auth:sanctum', 'token.refresh', 'tenant', 'throttle:api-plan', 'module.delivery'])
    ->prefix('delivery')
    ->group(function (): void {
        // Smoke test du module (DELIVERY-101/#6282) — lecture pure.
        Route::get('/ping', [DeliveryHealthController::class, 'ping']);
    });
