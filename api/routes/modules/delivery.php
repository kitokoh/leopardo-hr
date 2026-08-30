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

use App\Modules\Delivery\Interfaces\Api\V1\Controllers\DeliveryController;
use App\Modules\Delivery\Interfaces\Api\V1\Controllers\DeliveryHealthController;
use App\Modules\Delivery\Interfaces\Api\V1\Controllers\DeliveryRouteController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:api', 'auth:sanctum', 'token.refresh', 'tenant', 'throttle:api-plan', 'module.delivery'])
    ->prefix('delivery')
    ->group(function (): void {
        // Smoke test du module (DELIVERY-101/#6282) — lecture pure.
        Route::get('/ping', [DeliveryHealthController::class, 'ping']);

        // CRUD livraisons (DELIVERY-201/#6285) — RBAC manager (`api.manager`) ;
        // la matrice fine livreur/dispatcher/admin est le scope de BC-26-D05.
        Route::middleware('api.manager')->group(function (): void {
            Route::get('/deliveries', [DeliveryController::class, 'index']);
            Route::post('/deliveries', [DeliveryController::class, 'store']);
            Route::get('/deliveries/{delivery}', [DeliveryController::class, 'show'])->whereNumber('delivery');

            // Tournées (DELIVERY-202/#6286) — création, affectation idempotente,
            // clôture idempotente, détail avec stops ordonnés.
            Route::post('/deliveries/routes', [DeliveryRouteController::class, 'store']);
            Route::post('/deliveries/routes/{route}/assign', [DeliveryRouteController::class, 'assign'])->whereNumber('route');
            Route::post('/deliveries/routes/{route}/close', [DeliveryRouteController::class, 'close'])->whereNumber('route');
            Route::get('/deliveries/routes/{route}', [DeliveryRouteController::class, 'show'])->whereNumber('route');
        });
    });
