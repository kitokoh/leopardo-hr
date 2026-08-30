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
 * RBAC (BC-26-D05/#6294) : matrice deny-by-default `delivery.role`
 * (docs/architecture/DELIVERY_RBAC.md) — delivery.admin/dispatcher/manager/
 * rider/reports ; décisions par ressource (ownership livreur, scope tenant)
 * portées par DeliveryPolicy / DeliveryRoutePolicy.
 *
 * BC-26 DELIVERY : module de livraison dernier-kilomètre générique activable
 * par tout tenant qui livre (agence, restaurant, retail, e-commerce, CRM,
 * pharmacie).
 * Référence : docs/specifications/SOLUTION_DELIVERY.md (§4 API v1).
 */

use App\Modules\Delivery\Interfaces\Api\V1\Controllers\DeliveryCodSettlementController;
use App\Modules\Delivery\Interfaces\Api\V1\Controllers\DeliveryController;
use App\Modules\Delivery\Interfaces\Api\V1\Controllers\DeliveryHealthController;
use App\Modules\Delivery\Interfaces\Api\V1\Controllers\DeliveryEventController;
use App\Modules\Delivery\Interfaces\Api\V1\Controllers\DeliveryNotificationController;
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

        // ── Mobile livreur (DELIVERY-203/#6287) — tournée du jour + statuts
        // d'arrêts. Rider + dispatcher/admin ; le contrôleur vérifie la
        // PROPRIÉTÉ (driver_id = employé authentifié) pour les riders.
        Route::middleware('delivery.role:rider,dispatcher,admin')->group(function (): void {
            Route::get('/deliveries/routes/today', [DeliveryRiderController::class, 'today']);
            Route::post('/deliveries/stops/{stop}/status', [DeliveryRiderController::class, 'status'])->whereNumber('stop');
        });

        // ── BC-26-D05 (#6294) : RBAC fin deny-by-default — matrice
        // `docs/architecture/DELIVERY_RBAC.md`. Chaque groupe est borné aux
        // rôles qui ont droit à l'action ; les décisions par ressource
        // (ownership livreur, scope tenant) sont dans les Policies.
        //
        // Gestion des livraisons/tournées/tracking-links : dispatcher + admin.
        Route::middleware('delivery.role:dispatcher,admin')->group(function (): void {
            Route::get('/deliveries', [DeliveryController::class, 'index']);
            Route::post('/deliveries', [DeliveryController::class, 'store']);
            Route::get('/deliveries/{delivery}', [DeliveryController::class, 'show'])->whereNumber('delivery');
            Route::post('/deliveries/{delivery}/tracking-link', [DeliveryEventController::class, 'link'])->whereNumber('delivery');

            // Tournées (DELIVERY-202/#6286) — création, affectation idempotente,
            // clôture idempotente, détail avec stops ordonnés.
            Route::post('/deliveries/routes', [DeliveryRouteController::class, 'store']);
            Route::post('/deliveries/routes/{route}/assign', [DeliveryRouteController::class, 'assign'])->whereNumber('route');
            Route::post('/deliveries/routes/{route}/close', [DeliveryRouteController::class, 'close'])->whereNumber('route');
            Route::get('/deliveries/routes/{route}', [DeliveryRouteController::class, 'show'])->whereNumber('route');

            // Règlement COD & commissions (DELIVERY-205/#6289) — cycle de vie
            // pending→collected→settled→reconciled, idempotent. settle/reconcile
            // restent réservés à l'admin (check dans le contrôleur).
            Route::post('/deliveries/routes/{route}/settlement', [DeliveryCodSettlementController::class, 'store'])->whereNumber('route');
            Route::post('/deliveries/cod-settlements/{settlement}/collect', [DeliveryCodSettlementController::class, 'collect'])->whereNumber('settlement');
            Route::post('/deliveries/cod-settlements/{settlement}/settle', [DeliveryCodSettlementController::class, 'settle'])->whereNumber('settlement');
            Route::post('/deliveries/cod-settlements/{settlement}/reconcile', [DeliveryCodSettlementController::class, 'reconcile'])->whereNumber('settlement');
            Route::get('/deliveries/cod-settlements', [DeliveryCodSettlementController::class, 'index']);
            Route::get('/deliveries/cod-settlements/report', [DeliveryCodSettlementController::class, 'report']);

        });

        // Notifications destinataire (DELIVERY-206/#6290) — outbox + opt-out.
        // Lecture ouverte aux managers (numéros masqués RGPD hors admin) ;
        // opt-out (écriture) réservé dispatcher/admin.
        Route::middleware('delivery.role:dispatcher,admin')->group(function (): void {
            Route::post('/deliveries/notifications/opt-out', [DeliveryNotificationController::class, 'optOut']);
        });
        Route::middleware('delivery.role:dispatcher,admin,manager')->group(function (): void {
            Route::get('/deliveries/notifications', [DeliveryNotificationController::class, 'index']);
        });

        // Suivi temps réel (DELIVERY-204/#6288) :
        //  - écriture : dispatcher/admin en secours, livreur mobile pour SES
        //    propres tournées (ownership porté par DeliveryPolicy) ;
        //  - lecture timeline interne : dispatcher/admin/manager + rider
        //    (ses livraisons uniquement, via la Policy).
        Route::middleware('delivery.role:dispatcher,admin,rider')->group(function (): void {
            Route::post('/deliveries/events', [DeliveryEventController::class, 'store']);
        });
        Route::middleware('delivery.role:dispatcher,admin,manager,rider')->group(function (): void {
            Route::get('/deliveries/{delivery}/tracking', [DeliveryEventController::class, 'timeline'])->whereNumber('delivery');
        });

        // Rapports & KPIs : manager + admin (lecture/export) — DELIVERY-207/#6291.
        Route::middleware('delivery.role:manager,admin')->group(function (): void {
            Route::get('/deliveries/reports/summary', [DeliveryReportController::class, 'summary']);
            Route::get('/deliveries/reports/export', [DeliveryReportController::class, 'export']);
        });
    });

    // Suivi public par lien borné (DELIVERY-204/#6288) — PAS d'auth : le
    // token 64 chars expirant EST la credential (pattern AccountingDocumentShare).
    Route::get('/deliveries/tracking/{token}', [PublicDeliveryTrackingController::class, 'show'])
        ->middleware('throttle:60,1')
        ->where('token', '[A-Za-z0-9]{64}');
