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
use App\Modules\Delivery\Interfaces\Api\V1\Controllers\DeliveryEventController;
use App\Modules\Delivery\Interfaces\Api\V1\Controllers\DeliveryRouteController;
use App\Modules\Delivery\Interfaces\Api\V1\Controllers\DeliveryReportController;
use App\Modules\Delivery\Interfaces\Api\V1\Controllers\DeliveryRiderController;
use App\Modules\Delivery\Interfaces\Api\V1\Controllers\PublicDeliveryTrackingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:api', 'auth:sanctum', 'token.refresh', 'tenant', 'throttle:api-plan', 'module.delivery'])
    ->prefix('delivery')
    ->group(function (): void {
        // Smoke test du module (DELIVERY-101/#6282) — lecture pure.
        Route::get('/ping', [DeliveryHealthController::class, 'ping']);

        // Mobile livreur (DELIVERY-203/#6287) — tournée du jour + statuts
        // d'arrêts. PAS de garde manager : l'accès est vérifié dans le
        // contrôleur par PROPRIÉTÉ (driver_id = employé authentifié) ou rôle
        // manager — la matrice RBAC fine (delivery.role) est BC-26-D05.
        Route::get('/deliveries/routes/today', [DeliveryRiderController::class, 'today']);
        Route::post('/deliveries/stops/{stop}/status', [DeliveryRiderController::class, 'status'])->whereNumber('stop');

        // CRUD livraisons (DELIVERY-201/#6285), tournées (202), tracking (204),
        // rapports (207) — RBAC manager (la matrice fine est BC-26-D05/#6312).
        Route::middleware('api.manager')->group(function (): void {
            Route::get('/deliveries', [DeliveryController::class, 'index']);
            Route::post('/deliveries', [DeliveryController::class, 'store']);
            Route::get('/deliveries/{delivery}', [DeliveryController::class, 'show'])->whereNumber('delivery');

            // Tournées (DELIVERY-202/#6286) — planification du dispatcher.
            Route::post('/deliveries/routes', [DeliveryRouteController::class, 'store']);
            Route::post('/deliveries/routes/{route}/assign', [DeliveryRouteController::class, 'assign'])->whereNumber('route');
            Route::post('/deliveries/routes/{route}/close', [DeliveryRouteController::class, 'close'])->whereNumber('route');
            Route::get('/deliveries/routes/{route}', [DeliveryRouteController::class, 'show'])->whereNumber('route');

            // Tracking (DELIVERY-204/#6288) — événements, lien public, timeline.
            Route::post('/deliveries/events', [DeliveryEventController::class, 'store']);
            Route::post('/deliveries/{delivery}/tracking-link', [DeliveryEventController::class, 'link'])->whereNumber('delivery');
            Route::get('/deliveries/{delivery}/tracking', [DeliveryEventController::class, 'timeline'])->whereNumber('delivery');

            // Rapports & KPIs (DELIVERY-207/#6291).
            Route::get('/deliveries/reports/summary', [DeliveryReportController::class, 'summary']);
            Route::get('/deliveries/reports/export', [DeliveryReportController::class, 'export']);
        });
    });

    // Suivi public par lien borné (DELIVERY-204/#6288) — PAS d'auth : le
    // token 64 chars expirant EST la credential (pattern AccountingDocumentShare).
    Route::get('/deliveries/tracking/{token}', [PublicDeliveryTrackingController::class, 'show'])
        ->middleware('throttle:60,1')
        ->where('token', '[A-Za-z0-9]{64}');
