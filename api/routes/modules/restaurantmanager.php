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

use App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers\RestaurantCancellationPolicyController;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers\RestaurantCogsController;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers\RestaurantDeliveryZoneController;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers\RestaurantHealthController;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers\RestaurantInventoryCountController;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers\RestaurantInventoryMovementController;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers\RestaurantPurchaseOrderController;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers\RestaurantReceivingController;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers\RestaurantReservationController;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers\RestaurantStockAlertController;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers\RestaurantStockLevelController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:api', 'auth:sanctum', 'token.refresh', 'tenant', 'throttle:api-plan', 'module.restaurantmanager'])
    ->prefix('restaurant')
    ->group(function (): void {
        // Smoke test de la verticale (RESTO-101/#6158) — lecture pure.
        Route::get('/ping', [RestaurantHealthController::class, 'ping']);

        // Stock — niveaux & mouvements (RESTO-501/#6200).
        Route::get('/stock-levels', [RestaurantStockLevelController::class, 'index']);
        Route::post('/stock-levels', [RestaurantStockLevelController::class, 'store']);
        Route::get('/stock-levels/{restaurantStockLevel}', [RestaurantStockLevelController::class, 'show']);
        Route::put('/stock-levels/{restaurantStockLevel}', [RestaurantStockLevelController::class, 'update']);
        Route::delete('/stock-levels/{restaurantStockLevel}', [RestaurantStockLevelController::class, 'destroy']);

        Route::get('/inventory-movements', [RestaurantInventoryMovementController::class, 'index']);
        Route::post('/inventory-movements', [RestaurantInventoryMovementController::class, 'store']);
        Route::get('/inventory-movements/{restaurantInventoryMovement}', [RestaurantInventoryMovementController::class, 'show']);

        // Stock — alertes de seuil (RESTO-505/#6204).
        Route::get('/stock/alerts', [RestaurantStockAlertController::class, 'index']);

        // Stock — bons de commande fournisseurs (RESTO-502/#6201).
        Route::get('/purchase-orders', [RestaurantPurchaseOrderController::class, 'index']);
        Route::post('/purchase-orders', [RestaurantPurchaseOrderController::class, 'store']);
        Route::get('/purchase-orders/{restaurantPurchaseOrder}', [RestaurantPurchaseOrderController::class, 'show']);
        Route::put('/purchase-orders/{restaurantPurchaseOrder}', [RestaurantPurchaseOrderController::class, 'update']);
        Route::delete('/purchase-orders/{restaurantPurchaseOrder}', [RestaurantPurchaseOrderController::class, 'destroy']);
        Route::post('/purchase-orders/{restaurantPurchaseOrder}/send', [RestaurantPurchaseOrderController::class, 'send']);
        Route::post('/purchase-orders/{restaurantPurchaseOrder}/receive', [RestaurantPurchaseOrderController::class, 'receive']);

        // Stock — réceptions (RESTO-503/#6202).
        Route::get('/receivings', [RestaurantReceivingController::class, 'index']);
        Route::post('/receivings', [RestaurantReceivingController::class, 'store']);
        Route::get('/receivings/{restaurantReceiving}', [RestaurantReceivingController::class, 'show']);

        // Stock — inventaires physiques (RESTO-504/#6203).
        Route::get('/inventory-counts', [RestaurantInventoryCountController::class, 'index']);
        Route::post('/inventory-counts', [RestaurantInventoryCountController::class, 'store']);
        Route::get('/inventory-counts/{restaurantInventoryCount}', [RestaurantInventoryCountController::class, 'show']);
        Route::put('/inventory-counts/{restaurantInventoryCount}/items/{item}', [RestaurantInventoryCountController::class, 'recordItem']);
        Route::post('/inventory-counts/{restaurantInventoryCount}/submit', [RestaurantInventoryCountController::class, 'submit']);
        Route::post('/inventory-counts/{restaurantInventoryCount}/approve', [RestaurantInventoryCountController::class, 'approve']);

        // COGS — coût des marchandises vendues à la clôture (RESTO-506/#6205).
        Route::get('/pos-sessions/{restaurantPosSession}/cogs', [RestaurantCogsController::class, 'show']);

        // Réservations — CRUD + transitions + conflit de créneau (RESTO-601/#6206).
        Route::get('/reservations', [RestaurantReservationController::class, 'index']);
        Route::post('/reservations', [RestaurantReservationController::class, 'store']);
        Route::get('/reservations/{restaurantReservation}', [RestaurantReservationController::class, 'show']);
        Route::put('/reservations/{restaurantReservation}', [RestaurantReservationController::class, 'update']);
        Route::post('/reservations/{restaurantReservation}/confirm', [RestaurantReservationController::class, 'confirm']);
        Route::post('/reservations/{restaurantReservation}/check-in', [RestaurantReservationController::class, 'checkIn']);
        Route::post('/reservations/{restaurantReservation}/no-show', [RestaurantReservationController::class, 'noShow']);
        Route::post('/reservations/{restaurantReservation}/cancel', [RestaurantReservationController::class, 'cancel']);
        Route::post('/reservations/{restaurantReservation}/deposit', [RestaurantReservationController::class, 'deposit']);

        // Réservations — disponibilité de créneaux (RESTO-602/#6207).
        Route::get('/reservations/availability', [RestaurantReservationController::class, 'availability']);

        // Réservations — politique d'annulation par branche (RESTO-603/#6208).
        Route::put('/branches/{restaurantBranch}/cancellation-policy', [RestaurantCancellationPolicyController::class, 'update']);

        // Livraison — zones + frais (RESTO-604/#6209).
        Route::get('/delivery-zones', [RestaurantDeliveryZoneController::class, 'index']);
        Route::post('/delivery-zones', [RestaurantDeliveryZoneController::class, 'store']);
        Route::get('/delivery-zones/{restaurantDeliveryZone}', [RestaurantDeliveryZoneController::class, 'show']);
        Route::put('/delivery-zones/{restaurantDeliveryZone}', [RestaurantDeliveryZoneController::class, 'update']);
        Route::delete('/delivery-zones/{restaurantDeliveryZone}', [RestaurantDeliveryZoneController::class, 'destroy']);
        Route::get('/delivery-zones/{restaurantDeliveryZone}/quote', [RestaurantDeliveryZoneController::class, 'quote']);
    });
