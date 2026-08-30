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

use App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers\RestaurantBillController;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers\RestaurantBranchController;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers\RestaurantCategoryController;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers\RestaurantHealthController;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers\RestaurantHourController;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers\RestaurantIngredientController;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers\RestaurantInventoryCountController;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers\RestaurantInventoryMovementController;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers\RestaurantKitchenController;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers\RestaurantMenuController;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers\RestaurantMenuItemController;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers\RestaurantOrderController;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers\RestaurantOrderItemController;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers\RestaurantOrderTransitionController;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers\RestaurantPaymentController;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers\RestaurantPosSessionController;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers\RestaurantProductController;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers\RestaurantProductIngredientController;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers\RestaurantPurchaseOrderController;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers\RestaurantPurchaseOrderItemController;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers\RestaurantReceivingController;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers\RestaurantRefundController;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers\RestaurantReservationAvailabilityController;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers\RestaurantReservationController;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers\RestaurantStockLevelController;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers\RestaurantSupplierController;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers\RestaurantTableController;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers\RestaurantTableSessionController;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers\RestaurantTaxRateController;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers\RestaurantUnitController;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers\RestaurantZoneController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:api', 'auth:sanctum', 'token.refresh', 'tenant', 'throttle:api-plan', 'module.restaurantmanager'])
    ->prefix('restaurant')
    ->group(function (): void {
        // Smoke test de la verticale (RESTO-101/#6158) — lecture pure.
        Route::get('/ping', [RestaurantHealthController::class, 'ping']);

        // Référentiel — établissements, plan de salle (RESTO-301/#6182).
        Route::get('/branches', [RestaurantBranchController::class, 'index']);
        Route::post('/branches', [RestaurantBranchController::class, 'store']);
        Route::get('/branches/{restaurantBranch}', [RestaurantBranchController::class, 'show']);
        Route::put('/branches/{restaurantBranch}', [RestaurantBranchController::class, 'update']);
        Route::delete('/branches/{restaurantBranch}', [RestaurantBranchController::class, 'destroy']);
        Route::get('/branches/{restaurantBranch}/zones', [RestaurantZoneController::class, 'indexForBranch']);

        Route::get('/zones', [RestaurantZoneController::class, 'index']);
        Route::post('/zones', [RestaurantZoneController::class, 'store']);
        Route::get('/zones/{restaurantZone}', [RestaurantZoneController::class, 'show']);
        Route::put('/zones/{restaurantZone}', [RestaurantZoneController::class, 'update']);
        Route::delete('/zones/{restaurantZone}', [RestaurantZoneController::class, 'destroy']);

        Route::get('/tables', [RestaurantTableController::class, 'index']);
        Route::post('/tables', [RestaurantTableController::class, 'store']);
        Route::get('/tables/{restaurantTable}', [RestaurantTableController::class, 'show']);
        Route::put('/tables/{restaurantTable}', [RestaurantTableController::class, 'update']);
        Route::delete('/tables/{restaurantTable}', [RestaurantTableController::class, 'destroy']);

        // Référentiel — catalogue & recettes (RESTO-302/#6183).
        Route::get('/categories', [RestaurantCategoryController::class, 'index']);
        Route::post('/categories', [RestaurantCategoryController::class, 'store']);
        Route::get('/categories/{restaurantCategory}', [RestaurantCategoryController::class, 'show']);
        Route::put('/categories/{restaurantCategory}', [RestaurantCategoryController::class, 'update']);
        Route::delete('/categories/{restaurantCategory}', [RestaurantCategoryController::class, 'destroy']);

        Route::get('/products', [RestaurantProductController::class, 'index']);
        Route::post('/products', [RestaurantProductController::class, 'store']);
        Route::get('/products/{restaurantProduct}', [RestaurantProductController::class, 'show']);
        Route::put('/products/{restaurantProduct}', [RestaurantProductController::class, 'update']);
        Route::delete('/products/{restaurantProduct}', [RestaurantProductController::class, 'destroy']);
        Route::get('/products/{restaurantProduct}/ingredients', [RestaurantProductIngredientController::class, 'index']);
        Route::post('/products/{restaurantProduct}/ingredients', [RestaurantProductIngredientController::class, 'store']);
        Route::delete('/products/{restaurantProduct}/ingredients/{restaurantProductIngredient}', [RestaurantProductIngredientController::class, 'destroy']);

        // Référentiel — matières & fiscalité (RESTO-303/#6184).
        Route::get('/ingredients', [RestaurantIngredientController::class, 'index']);
        Route::post('/ingredients', [RestaurantIngredientController::class, 'store']);
        Route::get('/ingredients/{restaurantIngredient}', [RestaurantIngredientController::class, 'show']);
        Route::put('/ingredients/{restaurantIngredient}', [RestaurantIngredientController::class, 'update']);
        Route::delete('/ingredients/{restaurantIngredient}', [RestaurantIngredientController::class, 'destroy']);

        Route::get('/units', [RestaurantUnitController::class, 'index']);
        Route::post('/units', [RestaurantUnitController::class, 'store']);
        Route::get('/units/{restaurantUnit}', [RestaurantUnitController::class, 'show']);
        Route::put('/units/{restaurantUnit}', [RestaurantUnitController::class, 'update']);
        Route::delete('/units/{restaurantUnit}', [RestaurantUnitController::class, 'destroy']);

        Route::get('/tax-rates', [RestaurantTaxRateController::class, 'index']);
        Route::post('/tax-rates', [RestaurantTaxRateController::class, 'store']);
        Route::get('/tax-rates/{restaurantTaxRate}', [RestaurantTaxRateController::class, 'show']);
        Route::put('/tax-rates/{restaurantTaxRate}', [RestaurantTaxRateController::class, 'update']);
        Route::delete('/tax-rates/{restaurantTaxRate}', [RestaurantTaxRateController::class, 'destroy']);

        // Référentiel — menus & horaires (RESTO-304/#6185).
        Route::get('/menus', [RestaurantMenuController::class, 'index']);
        Route::post('/menus', [RestaurantMenuController::class, 'store']);
        Route::get('/menus/{restaurantMenu}', [RestaurantMenuController::class, 'show']);
        Route::put('/menus/{restaurantMenu}', [RestaurantMenuController::class, 'update']);
        Route::delete('/menus/{restaurantMenu}', [RestaurantMenuController::class, 'destroy']);
        Route::get('/menus/{restaurantMenu}/items', [RestaurantMenuItemController::class, 'index']);
        Route::post('/menus/{restaurantMenu}/items', [RestaurantMenuItemController::class, 'store']);
        Route::put('/menus/{restaurantMenu}/items/{restaurantMenuItem}', [RestaurantMenuItemController::class, 'update']);
        Route::delete('/menus/{restaurantMenu}/items/{restaurantMenuItem}', [RestaurantMenuItemController::class, 'destroy']);

        Route::get('/hours', [RestaurantHourController::class, 'index']);
        Route::post('/hours', [RestaurantHourController::class, 'store']);
        Route::get('/hours/{restaurantHour}', [RestaurantHourController::class, 'show']);
        Route::put('/hours/{restaurantHour}', [RestaurantHourController::class, 'update']);
        Route::delete('/hours/{restaurantHour}', [RestaurantHourController::class, 'destroy']);

        // Référentiel — fournisseurs (RESTO-305/#6186).
        Route::get('/suppliers', [RestaurantSupplierController::class, 'index']);
        Route::post('/suppliers', [RestaurantSupplierController::class, 'store']);
        Route::get('/suppliers/{restaurantSupplier}', [RestaurantSupplierController::class, 'show']);
        Route::put('/suppliers/{restaurantSupplier}', [RestaurantSupplierController::class, 'update']);
        Route::delete('/suppliers/{restaurantSupplier}', [RestaurantSupplierController::class, 'destroy']);

        // ── POS & caisse (RESTO-401/#6188) ──────────────────────────────────
        // Ouverture / consultation / clôture d'une session de caisse.
        // `current` est déclaré AVANT `{restaurantPosSession}` (routage
        // littéral prioritaire).
        Route::post('/pos-sessions', [RestaurantPosSessionController::class, 'store']);
        Route::get('/pos-sessions/current', [RestaurantPosSessionController::class, 'current']);
        Route::get('/pos-sessions/{restaurantPosSession}', [RestaurantPosSessionController::class, 'show']);
        Route::post('/pos-sessions/{restaurantPosSession}/close', [RestaurantPosSessionController::class, 'close']);

        // ── Commandes (RESTO-402/403/404/405, #6189/#6190/#6191/#6192) ──────
        Route::get('/orders', [RestaurantOrderController::class, 'index']);
        Route::post('/orders', [RestaurantOrderController::class, 'store']);
        Route::get('/orders/{restaurantOrder}', [RestaurantOrderController::class, 'show']);
        Route::post('/orders/{restaurantOrder}/items', [RestaurantOrderItemController::class, 'store']);
        Route::post('/orders/{restaurantOrder}/items/{restaurantOrderItem}/cancel', [RestaurantOrderItemController::class, 'cancel']);
        Route::post('/orders/{restaurantOrder}/submit', [RestaurantOrderTransitionController::class, 'submit']);
        Route::post('/orders/{restaurantOrder}/confirm', [RestaurantOrderTransitionController::class, 'confirm']);
        Route::post('/orders/{restaurantOrder}/serve', [RestaurantOrderTransitionController::class, 'serve']);
        Route::post('/orders/{restaurantOrder}/cancel', [RestaurantOrderTransitionController::class, 'cancel']);
        Route::get('/orders/{restaurantOrder}/bill', [RestaurantBillController::class, 'show']);

        // ── Paiements & remboursements (RESTO-407/408, #6194/#6195) ─────────
        Route::post('/orders/{restaurantOrder}/pay', [RestaurantPaymentController::class, 'pay']);
        Route::post('/orders/{restaurantOrder}/refund', [RestaurantRefundController::class, 'store']);

        // ── Occupation des tables (RESTO-409/#6196) ─────────────────────────
        Route::post('/tables/{restaurantTable}/open', [RestaurantTableSessionController::class, 'open']);
        Route::post('/tables/{restaurantTable}/close', [RestaurantTableSessionController::class, 'close']);

        // ── File cuisine (RESTO-410/#6197) ──────────────────────────────────
        Route::get('/kitchen/orders', [RestaurantKitchenController::class, 'index']);
        Route::post('/kitchen/orders/{restaurantOrder}/start', [RestaurantKitchenController::class, 'start']);
        Route::post('/kitchen/orders/{restaurantOrder}/ready', [RestaurantKitchenController::class, 'ready']);

        // ── Stock & mouvements (RESTO-501/#6200) ─────────────────────────────
        Route::get('/stock-levels', [RestaurantStockLevelController::class, 'index']);
        Route::put('/stock-levels/{restaurantStockLevel}', [RestaurantStockLevelController::class, 'update']);
        Route::get('/inventory-movements', [RestaurantInventoryMovementController::class, 'index']);
        Route::post('/inventory-movements', [RestaurantInventoryMovementController::class, 'store']);

        // ── Achats : bons de commande & réceptions (RESTO-502/503) ───────────
        Route::get('/purchase-orders', [RestaurantPurchaseOrderController::class, 'index']);
        Route::post('/purchase-orders', [RestaurantPurchaseOrderController::class, 'store']);
        Route::get('/purchase-orders/{restaurantPurchaseOrder}', [RestaurantPurchaseOrderController::class, 'show']);
        Route::put('/purchase-orders/{restaurantPurchaseOrder}', [RestaurantPurchaseOrderController::class, 'update']);
        Route::delete('/purchase-orders/{restaurantPurchaseOrder}', [RestaurantPurchaseOrderController::class, 'destroy']);
        Route::post('/purchase-orders/{restaurantPurchaseOrder}/send', [RestaurantPurchaseOrderController::class, 'send']);
        Route::post('/purchase-orders/{restaurantPurchaseOrder}/receive', [RestaurantPurchaseOrderController::class, 'receive']);
        Route::post('/purchase-orders/{restaurantPurchaseOrder}/cancel', [RestaurantPurchaseOrderController::class, 'cancel']);
        Route::post('/purchase-orders/{restaurantPurchaseOrder}/items', [RestaurantPurchaseOrderItemController::class, 'store']);
        Route::delete('/purchase-orders/{restaurantPurchaseOrder}/items/{restaurantPurchaseOrderItem}', [RestaurantPurchaseOrderItemController::class, 'destroy']);

        Route::get('/receivings', [RestaurantReceivingController::class, 'index']);
        Route::post('/receivings', [RestaurantReceivingController::class, 'store']);

        // ── Inventaires physiques (RESTO-504/#6203) ──────────────────────────
        Route::get('/inventory-counts', [RestaurantInventoryCountController::class, 'index']);
        Route::post('/inventory-counts', [RestaurantInventoryCountController::class, 'store']);
        Route::get('/inventory-counts/{restaurantInventoryCount}', [RestaurantInventoryCountController::class, 'show']);
        Route::put('/inventory-counts/{restaurantInventoryCount}/items/{restaurantInventoryCountItem}', [RestaurantInventoryCountController::class, 'updateItem']);
        Route::post('/inventory-counts/{restaurantInventoryCount}/submit', [RestaurantInventoryCountController::class, 'submit']);
        Route::post('/inventory-counts/{restaurantInventoryCount}/approve', [RestaurantInventoryCountController::class, 'approve']);

        // ── Réservations & disponibilité (RESTO-601/602/#6206/#6207) ─────────
        // `availability` est déclaré AVANT `{restaurantReservation}` (littéral
        // prioritaire) — sinon le paramètre capterait la route.
        Route::get('/reservations/availability', RestaurantReservationAvailabilityController::class);
        Route::get('/reservations', [RestaurantReservationController::class, 'index']);
        Route::post('/reservations', [RestaurantReservationController::class, 'store']);
        Route::get('/reservations/{restaurantReservation}', [RestaurantReservationController::class, 'show']);
        Route::put('/reservations/{restaurantReservation}', [RestaurantReservationController::class, 'update']);
        Route::post('/reservations/{restaurantReservation}/confirm', [RestaurantReservationController::class, 'confirm']);
        Route::post('/reservations/{restaurantReservation}/check-in', [RestaurantReservationController::class, 'checkIn']);
        Route::post('/reservations/{restaurantReservation}/no-show', [RestaurantReservationController::class, 'noShow']);
        Route::post('/reservations/{restaurantReservation}/cancel', [RestaurantReservationController::class, 'cancel']);

        // ── Boutique publique : gestion du jeton (RESTO-805/#6226) ─────────
        Route::get('/shop/token', [RestaurantPublicShopController::class, 'token']);
        Route::post('/shop/token/rotate', [RestaurantPublicShopController::class, 'rotateToken']);

        // ── Mobile (RESTO-801..804/#6222..#6225) — surfaces des apps ───────
        Route::prefix('mobile')->group(function (): void {
            // Serveur (RESTO-801/#6222) : file de service, tables, encaissement cash.
            Route::get('/server/orders', [RestaurantMobileServerController::class, 'orders']);
            Route::get('/server/tables', [RestaurantMobileServerController::class, 'tables']);
            Route::post('/server/orders/{restaurantOrder}/serve', [RestaurantMobileServerController::class, 'serve']);
            Route::post('/server/orders/{restaurantOrder}/pay', [RestaurantMobileServerController::class, 'pay']);

            // Livreur (RESTO-802/#6223) : tournées assignées, transitions.
            Route::get('/rider/deliveries', [RestaurantMobileRiderController::class, 'deliveries']);
            Route::get('/rider/deliveries/{restaurantDelivery}', [RestaurantMobileRiderController::class, 'show']);
            Route::post('/rider/deliveries/{restaurantDelivery}/out-for-delivery', [RestaurantMobileRiderController::class, 'outForDelivery']);
            Route::post('/rider/deliveries/{restaurantDelivery}/deliver', [RestaurantMobileRiderController::class, 'deliver']);

            // Gérant (RESTO-803/#6224) : KPIs, alertes stock, clôture de caisse.
            Route::get('/manager/kpis', [RestaurantMobileManagerController::class, 'kpis']);
            Route::get('/manager/stock-alerts', [RestaurantMobileManagerController::class, 'stockAlerts']);
            Route::get('/manager/pos-sessions/current', [RestaurantMobileManagerController::class, 'currentPosSession']);
            Route::post('/manager/pos-sessions/{restaurantPosSession}/close', [RestaurantMobileManagerController::class, 'closePosSession']);

            // Synchronisation offline (RESTO-804/#6225) : file idempotente.
            Route::post('/sync', [RestaurantMobileSyncController::class, 'sync']);
        });
    });
