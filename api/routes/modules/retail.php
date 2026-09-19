<?php

/**
 * Routes privées du module Retail (BC-17 RETAIL, #7672).
 *
 * Chargé depuis routes/api.php à l'intérieur du groupe /v1 — ne JAMAIS
 * re-préfixer `v1` (règle AGENTS.md).
 *
 * Middleware du groupe (convention modules, cf. catalog.php) :
 *   - throttle:api      → limite globale de l'API
 *   - auth:sanctum      → authentification (Sanctum)
 *   - token.refresh     → auto-refresh du token
 *   - tenant            → résolution de la company + garde-fous statut/archive
 *   - throttle:api-plan → limite selon le plan tarifaire
 *   - module.retail     → feature flag companies.features.retail
 *
 * Référence : programme BC-17 RETAIL (#7672 fondations backend).
 */

use App\Modules\Retail\Interfaces\Api\V1\Controllers\RetailCategoryController;
use App\Modules\Retail\Interfaces\Api\V1\Controllers\RetailProductController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:api', 'auth:sanctum', 'token.refresh', 'tenant', 'throttle:api-plan', 'module.retail'])
    ->prefix('retail')
    ->group(function (): void {
        // Catégories (gestion réservée principal/rh — RetailCategoryPolicy).
        Route::get('/categories', [RetailCategoryController::class, 'index']);
        Route::post('/categories', [RetailCategoryController::class, 'store']);
        Route::get('/categories/{category}', [RetailCategoryController::class, 'show'])->whereNumber('category');
        Route::put('/categories/{category}', [RetailCategoryController::class, 'update'])->whereNumber('category');
        Route::delete('/categories/{category}', [RetailCategoryController::class, 'destroy'])->whereNumber('category');

        // Produits (CRUD + publication — RetailProductPolicy).
        Route::get('/products', [RetailProductController::class, 'index']);
        Route::post('/products', [RetailProductController::class, 'store']);
        Route::get('/products/{product}', [RetailProductController::class, 'show'])->whereNumber('product');
        Route::put('/products/{product}', [RetailProductController::class, 'update'])->whereNumber('product');
        Route::delete('/products/{product}', [RetailProductController::class, 'destroy'])->whereNumber('product');
        Route::post('/products/{product}/publish', [RetailProductController::class, 'publish'])->whereNumber('product');
        Route::post('/products/{product}/unpublish', [RetailProductController::class, 'unpublish'])->whereNumber('product');
    });
