<?php

/**
 * Routes PUBLIQUES de la marketplace Leopardo Marche (BC-17 RETAIL,
 * #7807/#7808 — epic Leopardo Marche).
 *
 * Chargé depuis routes/api.php à l'intérieur du groupe /v1, juste après
 * catalog_public.php — ne JAMAIS re-préfixer `v1` (règle AGENTS.md).
 * URLs réelles :
 *   GET  /api/v1/public/market/products
 *   GET  /api/v1/public/market/products/{id}
 *   GET  /api/v1/public/market/sellers
 *   GET  /api/v1/public/market/sellers/{sellerSlug}
 *   POST /api/v1/public/market/orders
 *   GET  /api/v1/public/market/orders/{reference}?token=
 *
 * Isolées des routes privées (spec §"Public vs privé strictement séparés",
 * pattern BC-27/BC-28) : PAS d'auth Sanctum ni de middleware tenant —
 * throttling renforcé `throttle:shop-public` (anti-scraping par IP,
 * TRAVEL-1001/#6114). Les routes cross-tenant (recherche, vendeurs,
 * checkout, suivi) sont bornées aux vendeurs OPT-IN par
 * RetailMarketplaceService ; la fiche vendeur résout le tenant par slug
 * public dans `market.public` (EnsureMarketPublicAccess, 404 fail-closed).
 *
 * DTO public strict : 0 donnée interne (ni stocks chiffrés, ni marges,
 * ni company_id, ni meta). Suivi de commande : jeton `tracking_token`
 * obligatoire, 404 par défaut.
 *
 * Référence : docs/specifications/MARKETPLACE_RETAIL_PUBLIC.md (§3.1/§3.2).
 */

use App\Modules\Retail\Interfaces\Api\V1\Controllers\RetailMarketOrderPublicController;
use App\Modules\Retail\Interfaces\Api\V1\Controllers\RetailMarketPublicController;
use App\Modules\Retail\Interfaces\Api\V1\Controllers\RetailPaymentWebhookPublicController;
use Illuminate\Support\Facades\Route;

// Surface cross-tenant (recherche globale, checkout par slug dans le corps,
// suivi par référence + jeton) — hors groupe `market.public` (pas de slug à
// résoudre) : le périmètre opt-in est appliqué par RetailMarketplaceService.
Route::middleware(['throttle:shop-public'])
    ->prefix('public/market')
    ->group(function (): void {
        Route::get('/products', [RetailMarketPublicController::class, 'products'])
            ->name('market.public.products');
        Route::get('/products/{id}', [RetailMarketPublicController::class, 'product'])
            ->whereNumber('id')
            ->name('market.public.product');
        Route::get('/sellers', [RetailMarketPublicController::class, 'sellers'])
            ->name('market.public.sellers');
        Route::post('/orders', [RetailMarketOrderPublicController::class, 'store'])
            ->name('market.public.orders.store');
        Route::get('/orders/{reference}', [RetailMarketOrderPublicController::class, 'track'])
            ->name('market.public.orders.track');

        // Webhook des providers de paiement (#7812) : verification de
        // signature OBLIGATOIRE fail-closed (401 sinon), idempotent
        // (rejeu → 200 sans double effet), provider inconnu → 404.
        Route::post('/payments/webhook/{provider}', [RetailPaymentWebhookPublicController::class, 'handle'])
            ->name('market.public.payments.webhook');
    });

// Surface mono-vendeur : tenant résolu par slug public dans `market.public`
// (fail-closed 404 : slug inconnu, company suspendue/expirée, feature flag
// retail absent ou boutique non activée).
Route::middleware(['throttle:shop-public', 'market.public'])
    ->prefix('public/market')
    ->group(function (): void {
        Route::get('/sellers/{sellerSlug}', [RetailMarketPublicController::class, 'seller'])
            ->name('market.public.seller');
    });
