<?php

/**
 * Routes de la verticale International Trade OS (BC-36 TRADE ; TRADE-001/#8476).
 *
 * Chargé depuis routes/api.php à l'intérieur du groupe /v1 — ne JAMAIS
 * re-préfixer `v1` (règle AGENTS.md).
 *
 * Middleware du groupe (convention modules, cf. vtc.php / delivery.php) :
 *   - throttle:api     → limite globale de l'API
 *   - auth:sanctum     → authentification (Sanctum)
 *   - token.refresh    → auto-refresh du token
 *   - tenant           → résolution de la company + garde-fous statut/archive
 *   - throttle:api-plan→ limite selon le plan tarifaire
 *   - module.trade     → feature flag companies.features.trade
 *
 * BC-36 TRADE : commerce international & B2B domestique — noyau commercial
 * partagé (profils, catalogue, RFQ, offres, commandes, transport, coûts,
 * documents) avec vues acheteur/vendeur.
 * Référence : docs/specifications/VERTICAL_TRADE_OS.md.
 */

use App\Modules\Trade\Interfaces\Api\V1\Controllers\TradeHealthController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:api', 'auth:sanctum', 'token.refresh', 'tenant', 'throttle:api-plan', 'module.trade'])
    ->prefix('trade')
    ->group(function (): void {
        // Smoke test du module (TRADE-001/#8476) — lecture pure.
        Route::get('/ping', [TradeHealthController::class, 'ping']);
    });
