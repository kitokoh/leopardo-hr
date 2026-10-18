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
 * RBAC par OPÉRATION (pas par ressource) — matrice deny-by-default portée par
 * `TradeRoleResolver` (TRADE-001) :
 *   - `purchase`  : côté acheteur (émettre une RFQ, la diffuser, accepter une offre)
 *   - `sales`     : côté vendeur (répondre à une RFQ, émettre une offre proactive)
 *   - `logistics` : transport, coûts rendus, préparation douanière
 *   - `admin`     : administration de la verticale
 *
 * Les opérations LECTURE sont ouvertes à plusieurs rôles métier : acheteur et
 * vendeur doivent voir les mêmes objets partagés depuis leur espace respectif
 * (le filtrage `direction` sur `/quotes` distingue « offres reçues » de
 * « offres émises » — un même contrôleur, deux vues).
 *
 * BC-36 TRADE : commerce international & B2B domestique — noyau commercial
 * partagé (profils, catalogue, RFQ, offres, commandes, transport, coûts,
 * documents) avec vues acheteur/vendeur.
 * Référence : docs/specifications/VERTICAL_TRADE_OS.md.
 */

use App\Modules\Trade\Interfaces\Api\V1\Controllers\TradeCostEstimateController;
use App\Modules\Trade\Interfaces\Api\V1\Controllers\TradeHealthController;
use App\Modules\Trade\Interfaces\Api\V1\Controllers\TradeMetaController;
use App\Modules\Trade\Interfaces\Api\V1\Controllers\TradeQuoteController;
use App\Modules\Trade\Interfaces\Api\V1\Controllers\TradeRfqController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:api', 'auth:sanctum', 'token.refresh', 'tenant', 'throttle:api-plan', 'module.trade'])
    ->prefix('trade')
    ->group(function (): void {
        // Smoke test du module (TRADE-001/#8476) — lecture pure.
        Route::get('/ping', [TradeHealthController::class, 'ping']);

        // État RÉEL des adaptateurs externes (TRADE-007/#8482, spec §8) : c'est
        // ce que les écrans consomment pour signaler « non configuré » au lieu
        // de présenter une fonction indisponible comme opérationnelle.
        Route::get('/meta/integrations', [TradeMetaController::class, 'integrations']);

        // ── Demandes de prix (RFQ) — TRADE-003/#8478 ────────────────────────
        // Émettre/consulter une RFQ est une opération d'ACHAT.
        Route::middleware('trade.role:purchase,admin')->group(function (): void {
            Route::get('/rfqs', [TradeRfqController::class, 'index']);
            Route::post('/rfqs', [TradeRfqController::class, 'store']);
            // Diffuser la RFQ : génère les liens de réponse (retournés une fois).
            Route::post('/rfqs/{rfq}/send', [TradeRfqController::class, 'send']);
        });

        // Le VENDEUR consulte la RFQ sur laquelle il est invité : même objet,
        // autre espace — la lecture est donc ouverte aux deux rôles.
        Route::middleware('trade.role:purchase,sales,admin')->group(function (): void {
            Route::get('/rfqs/{rfq}', [TradeRfqController::class, 'show']);

            // ── Offres commerciales — TRADE-004/#8479 ───────────────────────
            // Lecture partagée : `?direction=incoming|outgoing` sélectionne la
            // vue (offres reçues côté acheteur, émises côté vendeur).
            Route::get('/quotes', [TradeQuoteController::class, 'index']);
            Route::get('/quotes/{quote}', [TradeQuoteController::class, 'show']);
        });

        // Émettre une offre est une opération de VENTE ; l'accepter est une
        // opération d'ACHAT (l'engagement est pris par l'acheteur).
        Route::middleware('trade.role:sales,admin')->group(function (): void {
            Route::post('/quotes', [TradeQuoteController::class, 'store']);
        });

        Route::middleware('trade.role:purchase,admin')->group(function (): void {
            Route::post('/quotes/{quote}/accept', [TradeQuoteController::class, 'accept']);
        });

        // ── Coût total rendu & préparation douanière — TRADE-007/#8482 ──────
        Route::middleware('trade.role:purchase,logistics,admin')->group(function (): void {
            Route::get('/cost-estimates', [TradeCostEstimateController::class, 'index']);
            Route::post('/cost-estimates', [TradeCostEstimateController::class, 'store']);
            Route::get('/cost-estimates/{costEstimate}', [TradeCostEstimateController::class, 'show']);
            // Checklist documentaire — toujours accompagnée du disclaimer.
            Route::get('/customs/checklist', [TradeCostEstimateController::class, 'customs']);
        });
    });
