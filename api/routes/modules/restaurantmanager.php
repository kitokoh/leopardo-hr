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
use App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers\RestaurantDeliveryController;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers\RestaurantDeliveryRiderController;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers\RestaurantHealthController;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers\RestaurantHourController;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers\RestaurantIngredientController;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers\RestaurantKitchenController;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers\RestaurantLoyaltyController;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers\RestaurantMenuController;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers\RestaurantMenuItemController;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers\RestaurantOrderController;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers\RestaurantOrderItemController;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers\RestaurantOrderTransitionController;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers\RestaurantPaymentController;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers\RestaurantPosSessionController;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers\RestaurantProductController;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers\RestaurantProductIngredientController;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers\RestaurantPromotionController;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers\RestaurantRefundController;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers\RestaurantReportController;
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

        // ── Livraison (RESTO-605/#6210) ─────────────────────────────────────
        Route::get('/delivery-riders', [RestaurantDeliveryRiderController::class, 'index']);
        Route::post('/delivery-riders', [RestaurantDeliveryRiderController::class, 'store']);
        Route::get('/delivery-riders/{restaurantDeliveryRider}', [RestaurantDeliveryRiderController::class, 'show']);
        Route::put('/delivery-riders/{restaurantDeliveryRider}', [RestaurantDeliveryRiderController::class, 'update']);
        Route::delete('/delivery-riders/{restaurantDeliveryRider}', [RestaurantDeliveryRiderController::class, 'destroy']);

        Route::post('/orders/{restaurantOrder}/delivery', [RestaurantDeliveryController::class, 'store']);
        Route::get('/deliveries/{restaurantDelivery}', [RestaurantDeliveryController::class, 'show']);
        Route::post('/deliveries/{restaurantDelivery}/assign', [RestaurantDeliveryController::class, 'transition']);
        Route::post('/deliveries/{restaurantDelivery}/out-for-delivery', [RestaurantDeliveryController::class, 'transition']);
        Route::post('/deliveries/{restaurantDelivery}/deliver', [RestaurantDeliveryController::class, 'transition']);
        Route::post('/deliveries/{restaurantDelivery}/cancel', [RestaurantDeliveryController::class, 'transition']);

        // ── Fidélité (RESTO-606/#6211) ──────────────────────────────────────
        Route::get('/loyalty-programs', [RestaurantLoyaltyController::class, 'indexPrograms']);
        Route::post('/loyalty-programs', [RestaurantLoyaltyController::class, 'storeProgram']);
        Route::get('/loyalty-programs/{restaurantLoyaltyProgram}', [RestaurantLoyaltyController::class, 'showProgram']);
        Route::put('/loyalty-programs/{restaurantLoyaltyProgram}', [RestaurantLoyaltyController::class, 'updateProgram']);

        Route::get('/loyalty-customers', [RestaurantLoyaltyController::class, 'indexCustomers']);
        Route::post('/loyalty-customers', [RestaurantLoyaltyController::class, 'storeCustomer']);
        Route::get('/loyalty-customers/{restaurantLoyaltyCustomer}', [RestaurantLoyaltyController::class, 'showCustomer']);
        Route::get('/loyalty-customers/{restaurantLoyaltyCustomer}/movements', [RestaurantLoyaltyController::class, 'customerMovements']);
        Route::post('/loyalty-customers/{restaurantLoyaltyCustomer}/redeem', [RestaurantLoyaltyController::class, 'redeem']);

        // ── Promotions (RESTO-607/#6212) ────────────────────────────────────
        Route::get('/promotions', [RestaurantPromotionController::class, 'index']);
        Route::post('/promotions', [RestaurantPromotionController::class, 'store']);
        Route::get('/promotions/{restaurantPromotion}', [RestaurantPromotionController::class, 'show']);
        Route::put('/promotions/{restaurantPromotion}', [RestaurantPromotionController::class, 'update']);
        Route::delete('/promotions/{restaurantPromotion}', [RestaurantPromotionController::class, 'destroy']);

        // ── Rapports & pilotage (RESTO-701/702/703, #6214/#6215/#6216) ──────
        Route::get('/reports/sales', [RestaurantReportController::class, 'sales']);
        Route::get('/reports/occupancy', [RestaurantReportController::class, 'occupancy']);
        Route::get('/reports/products', [RestaurantReportController::class, 'products']);
        Route::get('/reports/cogs', [RestaurantReportController::class, 'cogs']);
        Route::get('/reports/pos', [RestaurantReportController::class, 'pos']);
        Route::get('/reports/kpis', [RestaurantReportController::class, 'kpis']);
        Route::post('/reports/export', [RestaurantReportController::class, 'export']);
        Route::get('/reports/exports/{export}/download', [RestaurantReportController::class, 'download'])
            ->name('restaurant.reports.export.download');
    });
