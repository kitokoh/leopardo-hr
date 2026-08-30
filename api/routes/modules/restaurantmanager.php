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

use App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers\RestaurantBranchController;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers\RestaurantCategoryController;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers\RestaurantHealthController;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers\RestaurantHourController;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers\RestaurantIngredientController;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers\RestaurantMenuController;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers\RestaurantMenuItemController;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers\RestaurantProductController;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers\RestaurantProductIngredientController;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers\RestaurantSupplierController;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers\RestaurantTableController;
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
    });
