<?php

/**
 * Routes privées du module Catalog B2B (BC-28 CATALOG, #6881).
 *
 * Chargé depuis routes/api.php à l'intérieur du groupe /v1 — ne JAMAIS
 * re-préfixer `v1` (règle AGENTS.md).
 *
 * Middleware du groupe (convention modules, cf. cameras.php) :
 *   - throttle:api      → limite globale de l'API
 *   - auth:sanctum      → authentification (Sanctum)
 *   - token.refresh     → auto-refresh du token
 *   - tenant            → résolution de la company + garde-fous statut/archive
 *   - throttle:api-plan → limite selon le plan tarifaire
 *   - module.catalog    → feature flag companies.features.b2b_catalog
 *
 * Référence : docs/specifications/SOLUTION_CATALOGUE_B2B.md (§6 API privée).
 */

use App\Modules\Catalog\Interfaces\Api\V1\Controllers\CatalogCategoryController;
use App\Modules\Catalog\Interfaces\Api\V1\Controllers\CatalogProductController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:api', 'auth:sanctum', 'token.refresh', 'tenant', 'throttle:api-plan', 'module.catalog'])
    ->prefix('catalog')
    ->group(function (): void {
        // Catégories (gestion réservée principal/rh — CatalogCategoryPolicy).
        Route::get('/categories', [CatalogCategoryController::class, 'index']);
        Route::post('/categories', [CatalogCategoryController::class, 'store']);
        Route::get('/categories/{category}', [CatalogCategoryController::class, 'show'])->whereNumber('category');
        Route::put('/categories/{category}', [CatalogCategoryController::class, 'update'])->whereNumber('category');
        Route::delete('/categories/{category}', [CatalogCategoryController::class, 'destroy'])->whereNumber('category');

        // Produits (CRUD + publication — CatalogProductPolicy).
        Route::get('/products', [CatalogProductController::class, 'index']);
        Route::post('/products', [CatalogProductController::class, 'store']);
        Route::get('/products/{product}', [CatalogProductController::class, 'show'])->whereNumber('product');
        Route::put('/products/{product}', [CatalogProductController::class, 'update'])->whereNumber('product');
        Route::delete('/products/{product}', [CatalogProductController::class, 'destroy'])->whereNumber('product');
        Route::post('/products/{product}/publish', [CatalogProductController::class, 'publish'])->whereNumber('product');
        Route::post('/products/{product}/unpublish', [CatalogProductController::class, 'unpublish'])->whereNumber('product');
    });
