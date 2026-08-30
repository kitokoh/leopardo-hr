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

use App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers\RestaurantDeliveryController;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers\RestaurantDeliveryRiderController;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers\RestaurantHealthController;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers\RestaurantLoyaltyController;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers\RestaurantPromotionController;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers\RestaurantReportController;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers\RestaurantReportExportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:api', 'auth:sanctum', 'token.refresh', 'tenant', 'throttle:api-plan', 'module.restaurantmanager'])
    ->prefix('restaurant')
    ->group(function (): void {
        // Smoke test de la verticale (RESTO-101/#6158) — lecture pure.
        Route::get('/ping', [RestaurantHealthController::class, 'ping']);

        // Livraison — livreurs & cycle de livraison (RESTO-605/#6210).
        Route::get('/delivery-riders', [RestaurantDeliveryRiderController::class, 'index']);
        Route::post('/delivery-riders', [RestaurantDeliveryRiderController::class, 'store']);
        Route::get('/delivery-riders/{restaurantDeliveryRider}', [RestaurantDeliveryRiderController::class, 'show']);
        Route::put('/delivery-riders/{restaurantDeliveryRider}', [RestaurantDeliveryRiderController::class, 'update']);
        Route::delete('/delivery-riders/{restaurantDeliveryRider}', [RestaurantDeliveryRiderController::class, 'destroy']);

        Route::get('/deliveries', [RestaurantDeliveryController::class, 'index']);
        Route::post('/deliveries', [RestaurantDeliveryController::class, 'store']);
        Route::get('/deliveries/{restaurantDelivery}', [RestaurantDeliveryController::class, 'show']);
        Route::post('/deliveries/{restaurantDelivery}/assign', [RestaurantDeliveryController::class, 'assign']);
        Route::post('/deliveries/{restaurantDelivery}/out-for-delivery', [RestaurantDeliveryController::class, 'outForDelivery']);
        Route::post('/deliveries/{restaurantDelivery}/deliver', [RestaurantDeliveryController::class, 'deliver']);
        Route::post('/deliveries/{restaurantDelivery}/cancel', [RestaurantDeliveryController::class, 'cancel']);

        // Fidélité — programme & clients (RESTO-606/#6211).
        Route::get('/loyalty-programs', [RestaurantLoyaltyController::class, 'indexProgram']);
        Route::post('/loyalty-programs', [RestaurantLoyaltyController::class, 'storeProgram']);
        Route::put('/loyalty-programs/{restaurantLoyaltyProgram}', [RestaurantLoyaltyController::class, 'updateProgram']);

        Route::get('/loyalty-customers', [RestaurantLoyaltyController::class, 'indexCustomers']);
        Route::post('/loyalty-customers', [RestaurantLoyaltyController::class, 'storeCustomer']);
        Route::post('/loyalty-customers/{restaurantLoyaltyCustomer}/credit', [RestaurantLoyaltyController::class, 'creditCustomer']);
        Route::post('/loyalty-customers/{restaurantLoyaltyCustomer}/redeem', [RestaurantLoyaltyController::class, 'redeemCustomer']);

        // Promotions (RESTO-607/#6212).
        Route::get('/promotions', [RestaurantPromotionController::class, 'index']);
        Route::post('/promotions', [RestaurantPromotionController::class, 'store']);
        Route::get('/promotions/{restaurantPromotion}', [RestaurantPromotionController::class, 'show']);
        Route::put('/promotions/{restaurantPromotion}', [RestaurantPromotionController::class, 'update']);
        Route::delete('/promotions/{restaurantPromotion}', [RestaurantPromotionController::class, 'destroy']);
        Route::post('/promotions/validate', [RestaurantPromotionController::class, 'validate']);

        // Rapports agrégés + dashboard KPIs (RESTO-701/#6214, RESTO-703/#6216).
        Route::get('/reports/sales', [RestaurantReportController::class, 'sales']);
        Route::get('/reports/occupancy', [RestaurantReportController::class, 'occupancy']);
        Route::get('/reports/products', [RestaurantReportController::class, 'products']);
        Route::get('/reports/cogs', [RestaurantReportController::class, 'cogs']);
        Route::get('/reports/pos', [RestaurantReportController::class, 'pos']);
        Route::get('/dashboard/kpis', [RestaurantReportController::class, 'kpis']);

        // Export CSV idempotent + URL signée (RESTO-702/#6215) — génération auth.
        Route::post('/reports/export', [RestaurantReportExportController::class, 'export']);

        // Livraison — livreurs & cycle de livraison (RESTO-605/#6210).
        Route::get('/delivery-riders', [RestaurantDeliveryRiderController::class, 'index']);
        Route::post('/delivery-riders', [RestaurantDeliveryRiderController::class, 'store']);
        Route::get('/delivery-riders/{restaurantDeliveryRider}', [RestaurantDeliveryRiderController::class, 'show']);
        Route::put('/delivery-riders/{restaurantDeliveryRider}', [RestaurantDeliveryRiderController::class, 'update']);
        Route::delete('/delivery-riders/{restaurantDeliveryRider}', [RestaurantDeliveryRiderController::class, 'destroy']);

        Route::get('/deliveries', [RestaurantDeliveryController::class, 'index']);
        Route::post('/deliveries', [RestaurantDeliveryController::class, 'store']);
        Route::get('/deliveries/{restaurantDelivery}', [RestaurantDeliveryController::class, 'show']);
        Route::post('/deliveries/{restaurantDelivery}/assign', [RestaurantDeliveryController::class, 'assign']);
        Route::post('/deliveries/{restaurantDelivery}/out-for-delivery', [RestaurantDeliveryController::class, 'outForDelivery']);
        Route::post('/deliveries/{restaurantDelivery}/deliver', [RestaurantDeliveryController::class, 'deliver']);
        Route::post('/deliveries/{restaurantDelivery}/cancel', [RestaurantDeliveryController::class, 'cancel']);

        // Fidélité — programme & clients (RESTO-606/#6211).
        Route::get('/loyalty-programs', [RestaurantLoyaltyController::class, 'indexProgram']);
        Route::post('/loyalty-programs', [RestaurantLoyaltyController::class, 'storeProgram']);
        Route::put('/loyalty-programs/{restaurantLoyaltyProgram}', [RestaurantLoyaltyController::class, 'updateProgram']);

        Route::get('/loyalty-customers', [RestaurantLoyaltyController::class, 'indexCustomers']);
        Route::post('/loyalty-customers', [RestaurantLoyaltyController::class, 'storeCustomer']);
        Route::post('/loyalty-customers/{restaurantLoyaltyCustomer}/credit', [RestaurantLoyaltyController::class, 'creditCustomer']);
        Route::post('/loyalty-customers/{restaurantLoyaltyCustomer}/redeem', [RestaurantLoyaltyController::class, 'redeemCustomer']);

        // Promotions (RESTO-607/#6212).
        Route::get('/promotions', [RestaurantPromotionController::class, 'index']);
        Route::post('/promotions', [RestaurantPromotionController::class, 'store']);
        Route::get('/promotions/{restaurantPromotion}', [RestaurantPromotionController::class, 'show']);
        Route::put('/promotions/{restaurantPromotion}', [RestaurantPromotionController::class, 'update']);
        Route::delete('/promotions/{restaurantPromotion}', [RestaurantPromotionController::class, 'destroy']);
        Route::post('/promotions/validate', [RestaurantPromotionController::class, 'validate']);

        // Rapports agrégés + dashboard KPIs (RESTO-701/#6214, RESTO-703/#6216).
        Route::get('/reports/sales', [RestaurantReportController::class, 'sales']);
        Route::get('/reports/occupancy', [RestaurantReportController::class, 'occupancy']);
        Route::get('/reports/products', [RestaurantReportController::class, 'products']);
        Route::get('/reports/cogs', [RestaurantReportController::class, 'cogs']);
        Route::get('/reports/pos', [RestaurantReportController::class, 'pos']);
        Route::get('/dashboard/kpis', [RestaurantReportController::class, 'kpis']);

        // Export CSV idempotent + URL signée (RESTO-702/#6215) — génération auth.
        Route::post('/reports/export', [RestaurantReportExportController::class, 'export']);
    });

// Téléchargement d'export signé — route publique (la signature EST l'auth).
// La signature est émise par `RestaurantReportExportService` (TTL 10 min).
Route::get('/restaurant/reports/export/{export}', [RestaurantReportExportController::class, 'download'])
    ->name('restaurant.reports.export.download')
    ->middleware('signed');
