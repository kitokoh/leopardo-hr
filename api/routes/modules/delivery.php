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

use App\Modules\Delivery\Interfaces\Api\V1\Controllers\DeliveryCodSettlementController;
||||||||| merged common ancestors
=========
=======
use App\Modules\Delivery\Interfaces\Api\V1\Controllers\DeliveryAsyncExportController;
<<<<<<< HEAD
||||||| merged common ancestors
use App\Modules\Delivery\Interfaces\Api\V1\Controllers\DeliveryCodSettlementController;
>>>>>>>>> Temporary merge branch 2
=======
use App\Modules\Delivery\Interfaces\Api\V1\Controllers\DeliveryCodSettlementController;
>>>>>>> origin/bc/bc26-delivery-consolidation
use App\Modules\Delivery\Interfaces\Api\V1\Controllers\DeliveryController;
use App\Modules\Delivery\Interfaces\Api\V1\Controllers\DeliveryHealthController;
use App\Modules\Delivery\Interfaces\Api\V1\Controllers\DeliveryNotificationController;
use App\Modules\Delivery\Interfaces\Api\V1\Controllers\DeliveryEventController;
use App\Modules\Delivery\Interfaces\Api\V1\Controllers\DeliveryRouteController;
use App\Modules\Delivery\Interfaces\Api\V1\Controllers\DeliveryReportController;
use App\Modules\Delivery\Interfaces\Api\V1\Controllers\DeliveryRiderController;
use App\Modules\Delivery\Interfaces\Api\V1\Controllers\PublicDeliveryTrackingController;
use Illuminate\Support\Facades\Route;
use App\Modules\Delivery\Interfaces\Api\V1\Controllers\DeliveryController;
use App\Modules\Delivery\Interfaces\Api\V1\Controllers\DeliveryNotificationController;
use App\Modules\Delivery\Interfaces\Api\V1\Controllers\DeliveryEventController;
use App\Modules\Delivery\Interfaces\Api\V1\Controllers\DeliveryReportController;
use App\Modules\Delivery\Interfaces\Api\V1\Controllers\DeliveryRiderController;
use App\Modules\Delivery\Interfaces\Api\V1\Controllers\DeliveryRouteController;
use App\Modules\Delivery\Interfaces\Api\V1\Controllers\PublicDeliveryTrackingController;

Route::middleware(['throttle:api', 'auth:sanctum', 'token.refresh', 'tenant', 'throttle:api-plan', 'module.delivery'])
    ->prefix('delivery')
    ->group(function (): void {
        // Smoke test du module (DELIVERY-101/#6282) — lecture pure.
        Route::get('/ping', [DeliveryHealthController::class, 'ping']);
Route::get('/deliveries', [DeliveryController::class, 'index']);
Route::post('/deliveries', [DeliveryController::class, 'store']);
Route::get('/deliveries/{delivery}', [DeliveryController::class, 'show'])->whereNumber('delivery');
Route::get('/deliveries/routes/today', [DeliveryRiderController::class, 'today']);
Route::post('/deliveries/stops/{stop}/status', [DeliveryRiderController::class, 'status'])->whereNumber('stop');
Route::post('/deliveries/routes', [DeliveryRouteController::class, 'store']);
Route::post('/deliveries/routes/{route}/assign', [DeliveryRouteController::class, 'assign'])->whereNumber('route');
Route::post('/deliveries/routes/{route}/close', [DeliveryRouteController::class, 'close'])->whereNumber('route');
Route::get('/deliveries/routes/{route}', [DeliveryRouteController::class, 'show'])->whereNumber('route');
Route::post('/deliveries/events', [DeliveryEventController::class, 'store']);
Route::post('/deliveries/{delivery}/tracking-link', [DeliveryEventController::class, 'link'])->whereNumber('delivery');
Route::get('/deliveries/{delivery}/tracking', [DeliveryEventController::class, 'timeline'])->whereNumber('delivery');
Route::get('/deliveries/reports/summary', [DeliveryReportController::class, 'summary']);
Route::get('/deliveries/reports/export', [DeliveryReportController::class, 'export']);
Route::get('/deliveries/tracking/{token}', [PublicDeliveryTrackingController::class, 'show'])
        // Mobile livreur (DELIVERY-203/#6287) — tournée du jour + statuts
        // d'arrêts. PAS de garde manager : l'accès est vérifié dans le
        // contrôleur par PROPRIÉTÉ (driver_id = employé authentifié) ou rôle
        // manager — la matrice RBAC fine (delivery.role) est BC-26-D05.
        // CRUD livraisons (DELIVERY-201/#6285), tournées (202), tracking (204),
        // rapports (207) — RBAC manager (la matrice fine est BC-26-D05/#6312).
        Route::middleware('api.manager')->group(function (): void {
            // Tournées (DELIVERY-202/#6286) — planification du dispatcher.
            // Tracking (DELIVERY-204/#6288) — événements, lien public, timeline.
            Route::post('/deliveries/routes', [DeliveryRouteController::class, 'store']);
            Route::post('/deliveries/routes/{route}/assign', [DeliveryRouteController::class, 'assign'])->whereNumber('route');
            Route::post('/deliveries/routes/{route}/close', [DeliveryRouteController::class, 'close'])->whereNumber('route');
            Route::get('/deliveries/routes/{route}', [DeliveryRouteController::class, 'show'])->whereNumber('route');
            Route::post('/deliveries/events', [DeliveryEventController::class, 'store']);
            Route::post('/deliveries/{delivery}/tracking-link', [DeliveryEventController::class, 'link'])->whereNumber('delivery');
            Route::get('/deliveries/{delivery}/tracking', [DeliveryEventController::class, 'timeline'])->whereNumber('delivery');
            // Règlement COD & commissions (DELIVERY-205/#6289) — cycle de vie
            // pending→collected→settled→reconciled, idempotent. settle/reconcile
            // sont réservés à l'admin (check contrôleur) ; la matrice RBAC fine
            // (delivery.role) est portée par BC-26-D05/#6312.
            Route::post('/deliveries/routes/{route}/settlement', [DeliveryCodSettlementController::class, 'store'])->whereNumber('route');
            Route::post('/deliveries/cod-settlements/{settlement}/collect', [DeliveryCodSettlementController::class, 'collect'])->whereNumber('settlement');
            Route::post('/deliveries/cod-settlements/{settlement}/settle', [DeliveryCodSettlementController::class, 'settle'])->whereNumber('settlement');
            Route::post('/deliveries/cod-settlements/{settlement}/reconcile', [DeliveryCodSettlementController::class, 'reconcile'])->whereNumber('settlement');
            Route::get('/deliveries/cod-settlements', [DeliveryCodSettlementController::class, 'index']);
            Route::get('/deliveries/cod-settlements/report', [DeliveryCodSettlementController::class, 'report']);
            Route::post('/deliveries/events', [DeliveryEventController::class, 'store']);
            Route::post('/deliveries/{delivery}/tracking-link', [DeliveryEventController::class, 'link'])->whereNumber('delivery');
            Route::get('/deliveries/{delivery}/tracking', [DeliveryEventController::class, 'timeline'])->whereNumber('delivery');
            // Notifications destinataire (DELIVERY-206/#6290) — opt-out
            // effectif + outbox (numéros masqués RGPD hors admin).
            Route::post('/deliveries/notifications/opt-out', [DeliveryNotificationController::class, 'optOut']);
            Route::get('/deliveries/notifications', [DeliveryNotificationController::class, 'index']);
        // CRUD livraisons (DELIVERY-201/#6285) — RBAC fine (BC-26-D05/#6294) :
        // dispatcher/manager/admin (la création vient du dispatcher/manager).
        Route::middleware('delivery.permission:dispatcher|manager|admin')->group(function (): void {
            // Mobile livreur (DELIVERY-203/#6287) — tournée du jour scopée par
            // propriété (driver_id = employé) + statuts d'arrêts idempotents.
            Route::middleware('delivery.permission:rider|dispatcher|manager|admin')->group(function (): void {
            });
||||||||| merged common ancestors
        // CRUD livraisons (DELIVERY-201/#6285) — RBAC fine (BC-26-D05/#6294) :
        // dispatcher/manager/admin (la création vient du dispatcher/manager).
        Route::middleware('delivery.permission:dispatcher|manager|admin')->group(function (): void {
=========
        // Mobile livreur (DELIVERY-203/#6287) — tournée du jour + statuts
        // d'arrêts. PAS de garde manager : l'accès est vérifié dans le
        // contrôleur par PROPRIÉTÉ (driver_id = employé authentifié) ou rôle
        // manager — la matrice RBAC fine (delivery.role) est BC-26-D05.
        Route::get('/deliveries/routes/today', [DeliveryRiderController::class, 'today']);
        Route::post('/deliveries/stops/{stop}/status', [DeliveryRiderController::class, 'status'])->whereNumber('stop');

        // CRUD livraisons (DELIVERY-201/#6285), tournées (202), tracking (204),
        // rapports (207) — RBAC manager (la matrice fine est BC-26-D05/#6312).
        Route::middleware('api.manager')->group(function (): void {
>>>>>>>>> Temporary merge branch 2
||||||||| merged common ancestors
        // CRUD livraisons (DELIVERY-201/#6285) — RBAC fine (BC-26-D05/#6294) :
        // dispatcher/manager/admin (la création vient du dispatcher/manager).
        Route::middleware('delivery.permission:dispatcher|manager|admin')->group(function (): void {
=========
        // Mobile livreur (DELIVERY-203/#6287) — tournée du jour + statuts
        // d'arrêts. PAS de garde manager : l'accès est vérifié dans le
        // contrôleur par PROPRIÉTÉ (driver_id = employé authentifié) ou rôle
        // manager — la matrice RBAC fine (delivery.role) est BC-26-D05.
        Route::get('/deliveries/routes/today', [DeliveryRiderController::class, 'today']);
        Route::post('/deliveries/stops/{stop}/status', [DeliveryRiderController::class, 'status'])->whereNumber('stop');

        // CRUD livraisons (DELIVERY-201/#6285), tournées (202), tracking (204),
        // rapports (207) — RBAC manager (la matrice fine est BC-26-D05/#6312).
        Route::middleware('api.manager')->group(function (): void {
>>>>>>>>> Temporary merge branch 2
||||||||| merged common ancestors
        // CRUD livraisons (DELIVERY-201/#6285) — RBAC fine (BC-26-D05/#6294) :
        // dispatcher/manager/admin (la création vient du dispatcher/manager).
        Route::middleware('delivery.permission:dispatcher|manager|admin')->group(function (): void {
=========
        // Mobile livreur (DELIVERY-203/#6287) — tournée du jour + statuts
        // d'arrêts. PAS de garde manager : l'accès est vérifié dans le
        // contrôleur par PROPRIÉTÉ (driver_id = employé authentifié) ou rôle
        // manager — la matrice RBAC fine (delivery.role) est BC-26-D05.
        Route::get('/deliveries/routes/today', [DeliveryRiderController::class, 'today']);
        Route::post('/deliveries/stops/{stop}/status', [DeliveryRiderController::class, 'status'])->whereNumber('stop');

        // CRUD livraisons (DELIVERY-201/#6285), tournées (202), tracking (204),
        // rapports (207) — RBAC manager (la matrice fine est BC-26-D05/#6312).
        Route::middleware('api.manager')->group(function (): void {
>>>>>>>>> Temporary merge branch 2
||||||||| merged common ancestors
        // CRUD livraisons (DELIVERY-201/#6285) — RBAC fine (BC-26-D05/#6294) :
        // dispatcher/manager/admin (la création vient du dispatcher/manager).
        Route::middleware('delivery.permission:dispatcher|manager|admin')->group(function (): void {
=========
        // Mobile livreur (DELIVERY-203/#6287) — tournée du jour + statuts
        // d'arrêts. PAS de garde manager : l'accès est vérifié dans le
        // contrôleur par PROPRIÉTÉ (driver_id = employé authentifié) ou rôle
        // manager — la matrice RBAC fine (delivery.role) est BC-26-D05.
        Route::get('/deliveries/routes/today', [DeliveryRiderController::class, 'today']);
        Route::post('/deliveries/stops/{stop}/status', [DeliveryRiderController::class, 'status'])->whereNumber('stop');

        // CRUD livraisons (DELIVERY-201/#6285), tournées (202), tracking (204),
        // rapports (207) — RBAC manager (la matrice fine est BC-26-D05/#6312).
        Route::middleware('api.manager')->group(function (): void {
>>>>>>>>> Temporary merge branch 2
            Route::get('/deliveries', [DeliveryController::class, 'index']);
            Route::post('/deliveries', [DeliveryController::class, 'store']);
            Route::get('/deliveries/{delivery}', [DeliveryController::class, 'show'])->whereNumber('delivery');

<<<<<<<<< Temporary merge branch 1
<<<<<<<<< Temporary merge branch 1
<<<<<<<<< Temporary merge branch 1
<<<<<<<<< Temporary merge branch 1
<<<<<<<<< Temporary merge branch 1
            // Tournées (DELIVERY-202/#6286) — planification du dispatcher.
            Route::post('/deliveries/routes', [DeliveryRouteController::class, 'store']);
            Route::post('/deliveries/routes/{route}/assign', [DeliveryRouteController::class, 'assign'])->whereNumber('route');
            Route::post('/deliveries/routes/{route}/close', [DeliveryRouteController::class, 'close'])->whereNumber('route');
            Route::get('/deliveries/routes/{route}', [DeliveryRouteController::class, 'show'])->whereNumber('route');

            // Tracking (DELIVERY-204/#6288) — événements, lien public, timeline.
            Route::post('/deliveries/events', [DeliveryEventController::class, 'store']);
            Route::post('/deliveries/{delivery}/tracking-link', [DeliveryEventController::class, 'link'])->whereNumber('delivery');
            Route::get('/deliveries/{delivery}/tracking', [DeliveryEventController::class, 'timeline'])->whereNumber('delivery');

<<<<<<<<< Temporary merge branch 1
            // Règlement COD & commissions (DELIVERY-205/#6289) — cycle de vie
            // pending→collected→settled→reconciled, idempotent. settle/reconcile
            // sont réservés à l'admin (check contrôleur) ; la matrice RBAC fine
            // (delivery.role) est portée par BC-26-D05/#6312.
            Route::post('/deliveries/routes/{route}/settlement', [DeliveryCodSettlementController::class, 'store'])->whereNumber('route');
            Route::post('/deliveries/cod-settlements/{settlement}/collect', [DeliveryCodSettlementController::class, 'collect'])->whereNumber('settlement');
            Route::post('/deliveries/cod-settlements/{settlement}/settle', [DeliveryCodSettlementController::class, 'settle'])->whereNumber('settlement');
            Route::post('/deliveries/cod-settlements/{settlement}/reconcile', [DeliveryCodSettlementController::class, 'reconcile'])->whereNumber('settlement');
            Route::get('/deliveries/cod-settlements', [DeliveryCodSettlementController::class, 'index']);
            Route::get('/deliveries/cod-settlements/report', [DeliveryCodSettlementController::class, 'report']);
||||||||| merged common ancestors
            // Rapports & KPIs (DELIVERY-207/#6291).
            Route::get('/deliveries/reports/summary', [DeliveryReportController::class, 'summary']);
            Route::get('/deliveries/reports/export', [DeliveryReportController::class, 'export']);
=========
            // Règlement COD & commissions (DELIVERY-205/#6289) — cycle de vie
            // pending→collected→settled→reconciled, idempotent. settle/reconcile
            // sont réservés à l'admin (check contrôleur) ; la matrice RBAC fine
            // (delivery.role) est portée par BC-26-D05/#6312.
            Route::post('/deliveries/routes/{route}/settlement', [DeliveryCodSettlementController::class, 'store'])->whereNumber('route');
            Route::post('/deliveries/cod-settlements/{settlement}/collect', [DeliveryCodSettlementController::class, 'collect'])->whereNumber('settlement');
            Route::post('/deliveries/cod-settlements/{settlement}/settle', [DeliveryCodSettlementController::class, 'settle'])->whereNumber('settlement');
            Route::post('/deliveries/cod-settlements/{settlement}/reconcile', [DeliveryCodSettlementController::class, 'reconcile'])->whereNumber('settlement');
            Route::get('/deliveries/cod-settlements', [DeliveryCodSettlementController::class, 'index']);
            Route::get('/deliveries/cod-settlements/report', [DeliveryCodSettlementController::class, 'report']);
            // Notifications destinataire (DELIVERY-206/#6290) — opt-out
            // effectif + outbox (numéros masqués RGPD hors admin).
            Route::post('/deliveries/notifications/opt-out', [DeliveryNotificationController::class, 'optOut']);
            Route::get('/deliveries/notifications', [DeliveryNotificationController::class, 'index']);
>>>>>>>>> Temporary merge branch 2
||||||||| merged common ancestors
            // Tournées (DELIVERY-202/#6286) — création, affectation idempotente,
            // clôture idempotente, détail avec stops ordonnés.
            // Tracking (DELIVERY-204/#6288) — événements idempotents, lien
            // public borné, ligne du temps interne.
            Route::post('/deliveries/events', [DeliveryEventController::class, 'store']);
            Route::post('/deliveries/{delivery}/tracking-link', [DeliveryEventController::class, 'link'])->whereNumber('delivery');
            Route::get('/deliveries/{delivery}/tracking', [DeliveryEventController::class, 'timeline'])->whereNumber('delivery');

            // Tournées (DELIVERY-202/#6286) — création, affectation idempotente,
            // clôture idempotente, détail avec stops ordonnés.
            // Rapports & KPIs (DELIVERY-207/#6291) — read model déterministe
            // ventilé par source + export CSV streamé.
            Route::get('/deliveries/reports/summary', [DeliveryReportController::class, 'summary']);
            Route::get('/deliveries/reports/export', [DeliveryReportController::class, 'export']);

            // Tournées (DELIVERY-202/#6286) — création, affectation idempotente,
            // clôture idempotente, détail avec stops ordonnés.
            Route::post('/deliveries/routes', [DeliveryRouteController::class, 'store']);
            Route::post('/deliveries/routes/{route}/assign', [DeliveryRouteController::class, 'assign'])->whereNumber('route');
            Route::post('/deliveries/routes/{route}/close', [DeliveryRouteController::class, 'close'])->whereNumber('route');
            Route::get('/deliveries/routes/{route}', [DeliveryRouteController::class, 'show'])->whereNumber('route');
=========
            // Mobile livreur (DELIVERY-203/#6287) — tournée du jour scopée par
            // propriété (driver_id = employé) + statuts d'arrêts idempotents.
            Route::middleware('delivery.permission:rider|dispatcher|manager|admin')->group(function (): void {
                Route::get('/deliveries/routes/today', [DeliveryRiderController::class, 'today']);
                Route::post('/deliveries/stops/{stop}/status', [DeliveryRiderController::class, 'status'])->whereNumber('stop');
            });

||||||||| merged common ancestors
            // Mobile livreur (DELIVERY-203/#6287) — tournée du jour scopée par
            // propriété (driver_id = employé) + statuts d'arrêts idempotents.
            Route::middleware('delivery.permission:rider|dispatcher|manager|admin')->group(function (): void {
                Route::get('/deliveries/routes/today', [DeliveryRiderController::class, 'today']);
                Route::post('/deliveries/stops/{stop}/status', [DeliveryRiderController::class, 'status'])->whereNumber('stop');
            });
||||||||| merged common ancestors
            // Mobile livreur (DELIVERY-203/#6287) — tournée du jour scopée par
            // propriété (driver_id = employé) + statuts d'arrêts idempotents.
            Route::middleware('delivery.permission:rider|dispatcher|manager|admin')->group(function (): void {
                Route::get('/deliveries/routes/today', [DeliveryRiderController::class, 'today']);
                Route::post('/deliveries/stops/{stop}/status', [DeliveryRiderController::class, 'status'])->whereNumber('stop');
            });

            // Tournées (DELIVERY-202/#6286) — planification du dispatcher.
=======

        // CRUD livraisons (DELIVERY-201/#6285) — RBAC fine (BC-26-D05/#6294) :
        // dispatcher/manager/admin (la création vient du dispatcher/manager).
        Route::middleware('delivery.permission:dispatcher|manager|admin')->group(function (): void {
            Route::get('/deliveries', [DeliveryController::class, 'index']);
            Route::post('/deliveries', [DeliveryController::class, 'store']);
            Route::get('/deliveries/{delivery}', [DeliveryController::class, 'show'])->whereNumber('delivery');

            // Mobile livreur (DELIVERY-203/#6287) — tournée du jour scopée par
            // propriété (driver_id = employé) + statuts d'arrêts idempotents.
            Route::middleware('delivery.permission:rider|dispatcher|manager|admin')->group(function (): void {
                Route::get('/deliveries/routes/today', [DeliveryRiderController::class, 'today']);
                Route::post('/deliveries/stops/{stop}/status', [DeliveryRiderController::class, 'status'])->whereNumber('stop');
            });

            // Tournées (DELIVERY-202/#6286) — planification du dispatcher.
>>>>>>> origin/bc/bc26-delivery-consolidation
            Route::middleware('delivery.permission:dispatcher|admin|manager')->group(function (): void {
            // Tournées (DELIVERY-202/#6286) — planification du dispatcher.
            Route::post('/deliveries/routes', [DeliveryRouteController::class, 'store']);
            Route::post('/deliveries/routes/{route}/assign', [DeliveryRouteController::class, 'assign'])->whereNumber('route');
            Route::post('/deliveries/routes/{route}/close', [DeliveryRouteController::class, 'close'])->whereNumber('route');
            Route::get('/deliveries/routes/{route}', [DeliveryRouteController::class, 'show'])->whereNumber('route');

<<<<<<<<< Temporary merge branch 1
=========
>>>>>>>>> Temporary merge branch 2
            // Tournées (DELIVERY-202/#6286) — planification du dispatcher.
            Route::post('/deliveries/routes', [DeliveryRouteController::class, 'store']);
            Route::post('/deliveries/routes/{route}/assign', [DeliveryRouteController::class, 'assign'])->whereNumber('route');
            Route::post('/deliveries/routes/{route}/close', [DeliveryRouteController::class, 'close'])->whereNumber('route');
            Route::get('/deliveries/routes/{route}', [DeliveryRouteController::class, 'show'])->whereNumber('route');

            // Tracking (DELIVERY-204/#6288) — événements, lien public, timeline.
            Route::post('/deliveries/events', [DeliveryEventController::class, 'store']);
            Route::post('/deliveries/{delivery}/tracking-link', [DeliveryEventController::class, 'link'])->whereNumber('delivery');
            Route::get('/deliveries/{delivery}/tracking', [DeliveryEventController::class, 'timeline'])->whereNumber('delivery');
||||||||| merged common ancestors
            // Mobile livreur (DELIVERY-203/#6287) — tournée du jour scopée par
            // propriété (driver_id = employé) + statuts d'arrêts idempotents.
            Route::middleware('delivery.permission:rider|dispatcher|manager|admin')->group(function (): void {
                Route::get('/deliveries/routes/today', [DeliveryRiderController::class, 'today']);
                Route::post('/deliveries/stops/{stop}/status', [DeliveryRiderController::class, 'status'])->whereNumber('stop');
            });

||||||||| merged common ancestors
            // Mobile livreur (DELIVERY-203/#6287) — tournée du jour scopée par
            // propriété (driver_id = employé) + statuts d'arrêts idempotents.
            Route::middleware('delivery.permission:rider|dispatcher|manager|admin')->group(function (): void {
                Route::get('/deliveries/routes/today', [DeliveryRiderController::class, 'today']);
                Route::post('/deliveries/stops/{stop}/status', [DeliveryRiderController::class, 'status'])->whereNumber('stop');
            });

=========
>>>>>>>>> Temporary merge branch 2
            // Tournées (DELIVERY-202/#6286) — planification du dispatcher.
            Route::post('/deliveries/routes', [DeliveryRouteController::class, 'store']);
            Route::post('/deliveries/routes/{route}/assign', [DeliveryRouteController::class, 'assign'])->whereNumber('route');
            Route::post('/deliveries/routes/{route}/close', [DeliveryRouteController::class, 'close'])->whereNumber('route');
            Route::get('/deliveries/routes/{route}', [DeliveryRouteController::class, 'show'])->whereNumber('route');

<<<<<<<<< Temporary merge branch 1
=======
                Route::post('/deliveries/routes', [DeliveryRouteController::class, 'store']);
                Route::post('/deliveries/routes/{route}/assign', [DeliveryRouteController::class, 'assign'])->whereNumber('route');
                Route::post('/deliveries/routes/{route}/close', [DeliveryRouteController::class, 'close'])->whereNumber('route');
                Route::get('/deliveries/routes/{route}', [DeliveryRouteController::class, 'show'])->whereNumber('route');
            });

>>>>>>> origin/bc/bc26-delivery-consolidation
            // Tracking (DELIVERY-204/#6288) — l'écriture d'événements est
            // ouverte au rider (mobile livreur, DELIVERY-203).
            // Rapports & KPIs (DELIVERY-207/#6291).
            Route::middleware('delivery.permission:manager|admin')->group(function (): void {
            // pending→collected→settled→reconciled, idempotent.
            Route::post('/deliveries/routes/{route}/settlement', [DeliveryCodSettlementController::class, 'store'])
                ->middleware('delivery.permission:dispatcher|admin|manager')->whereNumber('route');
            Route::post('/deliveries/cod-settlements/{settlement}/collect', [DeliveryCodSettlementController::class, 'collect'])
                ->middleware('delivery.permission:admin|manager')->whereNumber('settlement');
            Route::post('/deliveries/cod-settlements/{settlement}/settle', [DeliveryCodSettlementController::class, 'settle'])
                ->middleware('delivery.permission:admin')->whereNumber('settlement');
            Route::post('/deliveries/cod-settlements/{settlement}/reconcile', [DeliveryCodSettlementController::class, 'reconcile'])
            Route::get('/deliveries/cod-settlements', [DeliveryCodSettlementController::class, 'index'])
                ->middleware('delivery.permission:admin|manager');
            Route::get('/deliveries/cod-settlements/report', [DeliveryCodSettlementController::class, 'report'])
            Route::post('/deliveries/notifications/opt-out', [DeliveryNotificationController::class, 'optOut'])
            Route::get('/deliveries/notifications', [DeliveryNotificationController::class, 'index'])
            // Export CSV async (BC-26-D07/#6295) — job tenant-scoped,
            // observable (pending → generating → done/failed).
            Route::post('/deliveries/reports/async-export', [DeliveryAsyncExportController::class, 'store'])
            Route::get('/deliveries/reports/async-export/{export}', [DeliveryAsyncExportController::class, 'show'])
                ->middleware('delivery.permission:admin|manager')->whereNumber('export');
            Route::get('/deliveries/reports/async-export/{export}/download', [DeliveryAsyncExportController::class, 'download'])
            // Tournées (DELIVERY-202/#6286) — planification du dispatcher.
            Route::post('/deliveries/routes', [DeliveryRouteController::class, 'store']);
            Route::post('/deliveries/routes/{route}/assign', [DeliveryRouteController::class, 'assign'])->whereNumber('route');
            Route::post('/deliveries/routes/{route}/close', [DeliveryRouteController::class, 'close'])->whereNumber('route');
            Route::get('/deliveries/routes/{route}', [DeliveryRouteController::class, 'show'])->whereNumber('route');
            // Tracking (DELIVERY-204/#6288) — événements, lien public, timeline.
            Route::post('/deliveries/events', [DeliveryEventController::class, 'store']);
            Route::post('/deliveries/{delivery}/tracking-link', [DeliveryEventController::class, 'link'])->whereNumber('delivery');
            Route::get('/deliveries/{delivery}/tracking', [DeliveryEventController::class, 'timeline'])->whereNumber('delivery');
||||||||| merged common ancestors
            // Tracking (DELIVERY-204/#6288) — l'écriture d'événements est
            // ouverte au rider (mobile livreur, DELIVERY-203).
            Route::middleware('delivery.permission:rider|dispatcher|manager|admin')->group(function (): void {
                Route::post('/deliveries/events', [DeliveryEventController::class, 'store']);
                Route::post('/deliveries/{delivery}/tracking-link', [DeliveryEventController::class, 'link'])->whereNumber('delivery');
                Route::get('/deliveries/{delivery}/tracking', [DeliveryEventController::class, 'timeline'])->whereNumber('delivery');
            });

            // Rapports & KPIs (DELIVERY-207/#6291).
            Route::middleware('delivery.permission:manager|admin')->group(function (): void {
                Route::get('/deliveries/reports/summary', [DeliveryReportController::class, 'summary']);
                Route::get('/deliveries/reports/export', [DeliveryReportController::class, 'export']);
            });
=========
            // Tracking (DELIVERY-204/#6288) — événements, lien public, timeline.
            Route::post('/deliveries/events', [DeliveryEventController::class, 'store']);
            Route::post('/deliveries/{delivery}/tracking-link', [DeliveryEventController::class, 'link'])->whereNumber('delivery');
            Route::get('/deliveries/{delivery}/tracking', [DeliveryEventController::class, 'timeline'])->whereNumber('delivery');
>>>>>>>>> Temporary merge branch 2
||||||||| merged common ancestors
            // Tracking (DELIVERY-204/#6288) — l'écriture d'événements est
            // ouverte au rider (mobile livreur, DELIVERY-203).
            Route::middleware('delivery.permission:rider|dispatcher|manager|admin')->group(function (): void {
                Route::post('/deliveries/events', [DeliveryEventController::class, 'store']);
                Route::post('/deliveries/{delivery}/tracking-link', [DeliveryEventController::class, 'link'])->whereNumber('delivery');
                Route::get('/deliveries/{delivery}/tracking', [DeliveryEventController::class, 'timeline'])->whereNumber('delivery');
            });

            // Rapports & KPIs (DELIVERY-207/#6291).
            Route::middleware('delivery.permission:manager|admin')->group(function (): void {
                Route::get('/deliveries/reports/summary', [DeliveryReportController::class, 'summary']);
                Route::get('/deliveries/reports/export', [DeliveryReportController::class, 'export']);
            });
=========
            // Tracking (DELIVERY-204/#6288) — événements, lien public, timeline.
            Route::post('/deliveries/events', [DeliveryEventController::class, 'store']);
            Route::post('/deliveries/{delivery}/tracking-link', [DeliveryEventController::class, 'link'])->whereNumber('delivery');
            Route::get('/deliveries/{delivery}/tracking', [DeliveryEventController::class, 'timeline'])->whereNumber('delivery');
>>>>>>>>> Temporary merge branch 2

            // Règlement COD & commissions (DELIVERY-205/#6289) — cycle de vie
<<<<<<<<< Temporary merge branch 1
            // pending→collected→settled→reconciled, idempotent. settle/reconcile
            // sont réservés à l'admin (check contrôleur) ; la matrice RBAC fine
            // (delivery.role) est portée par BC-26-D05/#6312.
            Route::post('/deliveries/routes/{route}/settlement', [DeliveryCodSettlementController::class, 'store'])->whereNumber('route');
            Route::post('/deliveries/cod-settlements/{settlement}/collect', [DeliveryCodSettlementController::class, 'collect'])->whereNumber('settlement');
            Route::post('/deliveries/cod-settlements/{settlement}/settle', [DeliveryCodSettlementController::class, 'settle'])->whereNumber('settlement');
            Route::post('/deliveries/cod-settlements/{settlement}/reconcile', [DeliveryCodSettlementController::class, 'reconcile'])->whereNumber('settlement');
            Route::get('/deliveries/cod-settlements', [DeliveryCodSettlementController::class, 'index']);
            Route::get('/deliveries/cod-settlements/report', [DeliveryCodSettlementController::class, 'report']);
<<<<<<<<< Temporary merge branch 1
            // Notifications destinataire (DELIVERY-206/#6290) — opt-out
            // effectif + outbox (numéros masqués RGPD hors admin).
            Route::post('/deliveries/notifications/opt-out', [DeliveryNotificationController::class, 'optOut'])
                ->middleware('delivery.permission:admin|manager');
            Route::get('/deliveries/notifications', [DeliveryNotificationController::class, 'index'])
                ->middleware('delivery.permission:admin|manager');

            // Export CSV async (BC-26-D07/#6295) — job tenant-scoped,
            // observable (pending → generating → done/failed).
            Route::post('/deliveries/reports/async-export', [DeliveryAsyncExportController::class, 'store'])
                ->middleware('delivery.permission:admin|manager');
            Route::get('/deliveries/reports/async-export/{export}', [DeliveryAsyncExportController::class, 'show'])
                ->middleware('delivery.permission:admin|manager')->whereNumber('export');
            Route::get('/deliveries/reports/async-export/{export}/download', [DeliveryAsyncExportController::class, 'download'])
                ->middleware('delivery.permission:admin|manager')->whereNumber('export');
>>>>>>>>> Temporary merge branch 2
||||||||| merged common ancestors
=========
||||||||| merged common ancestors
            // pending→collected→settled→reconciled, idempotent.
            Route::post('/deliveries/routes/{route}/settlement', [DeliveryCodSettlementController::class, 'store'])
                ->middleware('delivery.permission:dispatcher|admin|manager')->whereNumber('route');
            Route::post('/deliveries/cod-settlements/{settlement}/collect', [DeliveryCodSettlementController::class, 'collect'])
                ->middleware('delivery.permission:admin|manager')->whereNumber('settlement');
            Route::post('/deliveries/cod-settlements/{settlement}/settle', [DeliveryCodSettlementController::class, 'settle'])
                ->middleware('delivery.permission:admin')->whereNumber('settlement');
            Route::post('/deliveries/cod-settlements/{settlement}/reconcile', [DeliveryCodSettlementController::class, 'reconcile'])
                ->middleware('delivery.permission:admin')->whereNumber('settlement');
            Route::get('/deliveries/cod-settlements', [DeliveryCodSettlementController::class, 'index'])
                ->middleware('delivery.permission:admin|manager');
            Route::get('/deliveries/cod-settlements/report', [DeliveryCodSettlementController::class, 'report'])
                ->middleware('delivery.permission:admin|manager');

=========
            // pending→collected→settled→reconciled, idempotent. settle/reconcile
            // sont réservés à l'admin (check contrôleur) ; la matrice RBAC fine
            // (delivery.role) est portée par BC-26-D05/#6312.
            Route::post('/deliveries/routes/{route}/settlement', [DeliveryCodSettlementController::class, 'store'])->whereNumber('route');
            Route::post('/deliveries/cod-settlements/{settlement}/collect', [DeliveryCodSettlementController::class, 'collect'])->whereNumber('settlement');
            Route::post('/deliveries/cod-settlements/{settlement}/settle', [DeliveryCodSettlementController::class, 'settle'])->whereNumber('settlement');
            Route::post('/deliveries/cod-settlements/{settlement}/reconcile', [DeliveryCodSettlementController::class, 'reconcile'])->whereNumber('settlement');
            Route::get('/deliveries/cod-settlements', [DeliveryCodSettlementController::class, 'index']);
            Route::get('/deliveries/cod-settlements/report', [DeliveryCodSettlementController::class, 'report']);
>>>>>>>>> Temporary merge branch 2
            // Notifications destinataire (DELIVERY-206/#6290) — opt-out
            // effectif + outbox (numéros masqués RGPD hors admin).
            Route::post('/deliveries/notifications/opt-out', [DeliveryNotificationController::class, 'optOut']);
            Route::get('/deliveries/notifications', [DeliveryNotificationController::class, 'index']);
>>>>>>>>> Temporary merge branch 2
=======
                Route::get('/deliveries/reports/summary', [DeliveryReportController::class, 'summary']);
                Route::get('/deliveries/reports/export', [DeliveryReportController::class, 'export']);
            });

            // Règlement COD & commissions (DELIVERY-205/#6289) — cycle de vie
            // pending→collected→settled→reconciled, idempotent.
            Route::post('/deliveries/routes/{route}/settlement', [DeliveryCodSettlementController::class, 'store'])
                ->middleware('delivery.permission:dispatcher|admin|manager')->whereNumber('route');
            Route::post('/deliveries/cod-settlements/{settlement}/collect', [DeliveryCodSettlementController::class, 'collect'])
                ->middleware('delivery.permission:admin|manager')->whereNumber('settlement');
            Route::post('/deliveries/cod-settlements/{settlement}/settle', [DeliveryCodSettlementController::class, 'settle'])
                ->middleware('delivery.permission:admin')->whereNumber('settlement');
            Route::post('/deliveries/cod-settlements/{settlement}/reconcile', [DeliveryCodSettlementController::class, 'reconcile'])
                ->middleware('delivery.permission:admin')->whereNumber('settlement');
            Route::get('/deliveries/cod-settlements', [DeliveryCodSettlementController::class, 'index'])
                ->middleware('delivery.permission:admin|manager');
            Route::get('/deliveries/cod-settlements/report', [DeliveryCodSettlementController::class, 'report'])
                ->middleware('delivery.permission:admin|manager');

            // Notifications destinataire (DELIVERY-206/#6290) — opt-out
            // effectif + outbox (numéros masqués RGPD hors admin).
            Route::post('/deliveries/notifications/opt-out', [DeliveryNotificationController::class, 'optOut'])
                ->middleware('delivery.permission:admin|manager');
            Route::get('/deliveries/notifications', [DeliveryNotificationController::class, 'index'])
                ->middleware('delivery.permission:admin|manager');

            // Export CSV async (BC-26-D07/#6295) — job tenant-scoped,
            // observable (pending → generating → done/failed).
            Route::post('/deliveries/reports/async-export', [DeliveryAsyncExportController::class, 'store'])
                ->middleware('delivery.permission:admin|manager');
            Route::get('/deliveries/reports/async-export/{export}', [DeliveryAsyncExportController::class, 'show'])
                ->middleware('delivery.permission:admin|manager')->whereNumber('export');
            Route::get('/deliveries/reports/async-export/{export}/download', [DeliveryAsyncExportController::class, 'download'])
                ->middleware('delivery.permission:admin|manager')->whereNumber('export');
>>>>>>> origin/bc/bc26-delivery-consolidation
        });
    // Suivi public par lien borné (DELIVERY-204/#6288) — PAS d'auth : le
    // token 64 chars expirant EST la credential (pattern AccountingDocumentShare).
        ->middleware('throttle:60,1')
        ->where('token', '[A-Za-z0-9]{64}');
<<<<<<< HEAD
    });
||||||| merged common ancestors
>>>>>>>>> Temporary merge branch 2
=======
>>>>>>> origin/bc/bc26-delivery-consolidation
||||||| c1baa0189
=======
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
use App\Modules\Delivery\Interfaces\Api\V1\Controllers\DeliveryEventController;
use App\Modules\Delivery\Interfaces\Api\V1\Controllers\DeliveryHealthController;
use App\Modules\Delivery\Interfaces\Api\V1\Controllers\DeliveryNotificationController;
use App\Modules\Delivery\Interfaces\Api\V1\Controllers\DeliveryReportController;
use App\Modules\Delivery\Interfaces\Api\V1\Controllers\DeliveryRiderController;
use App\Modules\Delivery\Interfaces\Api\V1\Controllers\DeliveryRouteController;
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
>>>>>>> origin/pm/merge-delivery-socle
