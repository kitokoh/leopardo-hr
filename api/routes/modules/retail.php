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
use App\Modules\Retail\Interfaces\Api\V1\Controllers\RetailLocationController;
use App\Modules\Retail\Interfaces\Api\V1\Controllers\RetailOnlineOrderController;
use App\Modules\Retail\Interfaces\Api\V1\Controllers\RetailOnlineSettingsController;
use App\Modules\Retail\Interfaces\Api\V1\Controllers\RetailOrderController;
use App\Modules\Retail\Interfaces\Api\V1\Controllers\RetailPosSessionController;
use App\Modules\Retail\Interfaces\Api\V1\Controllers\RetailProductController;
use App\Modules\Retail\Interfaces\Api\V1\Controllers\RetailStockController;
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

        // Marketplace Leopardo Marché (#7807) : bascule de la visibilité
        // publique par produit (RetailProductPolicy@publish, opt-in).
        Route::post('/products/{product}/publish-online', [RetailProductController::class, 'publishOnline'])->whereNumber('product');
        Route::post('/products/{product}/unpublish-online', [RetailProductController::class, 'unpublishOnline'])->whereNumber('product');

        // Emplacements de stock (gestion réservée principal/rh — RetailLocationPolicy, #7673).
        Route::get('/locations', [RetailLocationController::class, 'index']);
        Route::post('/locations', [RetailLocationController::class, 'store']);
        Route::get('/locations/{location}', [RetailLocationController::class, 'show'])->whereNumber('location');
        Route::put('/locations/{location}', [RetailLocationController::class, 'update'])->whereNumber('location');
        Route::delete('/locations/{location}', [RetailLocationController::class, 'destroy'])->whereNumber('location');

        // Stocks : niveaux, mouvements tracés (seule voie d'écriture des
        // quantités — RetailStockService), alertes de stock bas (#7673).
        Route::get('/stock/levels', [RetailStockController::class, 'levels']);
        Route::post('/stock/movements', [RetailStockController::class, 'storeMovement']);
        Route::get('/stock/movements', [RetailStockController::class, 'movements']);
        Route::get('/stock/alerts', [RetailStockController::class, 'alerts']);

        // POS v1 (#7674) : sessions de caisse (une seule session ouverte par
        // emplacement), commandes de vente, paiements multi-moyens (le
        // passage à completed décrémente le stock via RetailStockService).
        Route::get('/pos/sessions', [RetailPosSessionController::class, 'index']);
        Route::post('/pos/sessions', [RetailPosSessionController::class, 'store']);
        Route::get('/pos/sessions/{session}', [RetailPosSessionController::class, 'show'])->whereNumber('session');
        Route::post('/pos/sessions/{session}/close', [RetailPosSessionController::class, 'close'])->whereNumber('session');

        Route::get('/pos/orders', [RetailOrderController::class, 'index']);
        Route::post('/pos/orders', [RetailOrderController::class, 'store']);
        Route::get('/pos/orders/{order}', [RetailOrderController::class, 'show'])->whereNumber('order');
        Route::post('/pos/orders/{order}/payments', [RetailOrderController::class, 'addPayment'])->whereNumber('order');
        Route::post('/pos/orders/{order}/cancel', [RetailOrderController::class, 'cancel'])->whereNumber('order');

        // Documents de vente (#7813) : ticket de caisse PDF imprimable
        // (ventes POS encaissees) et facture PDF (POS + commandes web) avec
        // numerotation legale par tenant (FAC-YYYY-NNNNNN, immuable).
        Route::get('/pos/orders/{order}/receipt', [RetailOrderController::class, 'receipt'])->whereNumber('order');
        Route::get('/orders/{order}/invoice', [RetailOrderController::class, 'invoice'])->whereNumber('order');

        // Boutique en ligne Leopardo Marché (#7807) : réglages publics du
        // vendeur (create-or-update, RetailOnlineSettingsPolicy).
        Route::get('/online/settings', [RetailOnlineSettingsController::class, 'show']);
        Route::put('/online/settings', [RetailOnlineSettingsController::class, 'update']);

        // Commandes en ligne Leopardo Marché (#7808) : liste/détail +
        // pilotage logistique pending→confirmed→ready→shipped→delivered
        // (+ cancel). La confirmation décrémente le stock (mouvements `sale`
        // via RetailStockService), l'annulation post-confirmation le
        // restaure (mouvements `return`) — transitions invalides → 422
        // INVALID_TRANSITION.
        Route::get('/online/orders', [RetailOnlineOrderController::class, 'index']);
        Route::get('/online/orders/{order}', [RetailOnlineOrderController::class, 'show'])->whereNumber('order');
        Route::post('/online/orders/{order}/confirm', [RetailOnlineOrderController::class, 'confirm'])->whereNumber('order');
        Route::post('/online/orders/{order}/ready', [RetailOnlineOrderController::class, 'ready'])->whereNumber('order');
        Route::post('/online/orders/{order}/ship', [RetailOnlineOrderController::class, 'ship'])->whereNumber('order');
        Route::post('/online/orders/{order}/deliver', [RetailOnlineOrderController::class, 'deliver'])->whereNumber('order');
        Route::post('/online/orders/{order}/cancel', [RetailOnlineOrderController::class, 'cancel'])->whereNumber('order');
    });
