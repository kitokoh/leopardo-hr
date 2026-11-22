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

use App\Modules\Trade\Interfaces\Api\V1\Controllers\TradeCompanyProfileController;
use App\Modules\Trade\Interfaces\Api\V1\Controllers\TradeCostEstimateController;
use App\Modules\Trade\Interfaces\Api\V1\Controllers\TradeCounterpartyController;
use App\Modules\Trade\Interfaces\Api\V1\Controllers\TradeDocumentController;
use App\Modules\Trade\Interfaces\Api\V1\Controllers\TradeHealthController;
use App\Modules\Trade\Interfaces\Api\V1\Controllers\TradeMetaController;
use App\Modules\Trade\Interfaces\Api\V1\Controllers\TradeOrderController;
use App\Modules\Trade\Interfaces\Api\V1\Controllers\TradeProductController;
use App\Modules\Trade\Interfaces\Api\V1\Controllers\TradePromotionKitController;
use App\Modules\Trade\Interfaces\Api\V1\Controllers\TradeQuoteController;
use App\Modules\Trade\Interfaces\Api\V1\Controllers\TradeRfqController;
use App\Modules\Trade\Interfaces\Api\V1\Controllers\TradeSearchController;
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
            // Faire avancer la consultation : `open -> awarded | closed |
            // cancelled`. Sans cette route, une RFQ diffusée restait `open`
            // pour toujours : le vendeur pouvait demander des prix et recevoir
            // des offres, mais jamais désigner le gagnant ni clore. Le
            // sourcing s'arrêtait au moment de décider.
            Route::post('/rfqs/{rfq}/transition', [TradeRfqController::class, 'transition']);
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
            // Faire avancer l'offre : `rejected | withdrawn | expired |
            // under_review | negotiating`. `accepted` est exclu — accepter crée
            // une commande, c'est la route `accept` ci-dessous. Sans cette
            // route, une offre refusée restait `submitted` indéfiniment et le
            // vendeur voyait « en attente » des affaires déjà mortes.
            Route::post('/quotes/{quote}/transition', [TradeQuoteController::class, 'transition']);
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

        // ── Catalogue produits B2B — #9767 ───────────────────────────────────
        // Lecture : tous les rôles métier (acheteur ET vendeur lisent le
        // catalogue — c'est un référentiel partagé, comme les RFQ/offres).
        Route::middleware('trade.role:purchase,sales,logistics,admin')->group(function (): void {
            Route::get('/products', [TradeProductController::class, 'index']);
            Route::get('/products/{product}', [TradeProductController::class, 'show']);
        });

        // Écriture : le catalogue est l'outil du VENDEUR (et de l'admin).
        Route::middleware('trade.role:sales,admin')->group(function (): void {
            Route::post('/products', [TradeProductController::class, 'store']);
            Route::put('/products/{product}', [TradeProductController::class, 'update']);
            // Acte distinct : archiver n'est pas enregistrer un champ.
            Route::post('/products/{product}/archive', [TradeProductController::class, 'archive']);
        });

        // ── Recherche transverse — #9781 ─────────────────────────────────────
        // « Je ne sais plus où c'est » : un seul champ qui interroge les
        // contreparties, le catalogue et les dossiers. Ouvert à tous les rôles
        // métier — chercher n'est pas une opération commerciale.
        Route::middleware('trade.role:purchase,sales,logistics,reports,admin')->group(function (): void {
            Route::get('/search', [TradeSearchController::class, 'index']);
        });

        // ── Contreparties : clients, fournisseurs, transporteurs — #9780 ──────
        // Sans ces routes, `trade_counterparties` (table, modèle, factory,
        // enums depuis TRADE-003/#8478) restait INATTEIGNABLE : un tenant
        // pouvait émettre des RFQ et des offres sans jamais créer un
        // fournisseur ni un client, et `trade_deal.trade_counterparty_id`
        // ne pouvait jamais être renseigné.
        //
        // Lecture : tous les rôles métier (acheteur, vendeur et logisticien
        // travaillent sur le même répertoire partagé).
        Route::middleware('trade.role:purchase,sales,logistics,admin')->group(function (): void {
            Route::get('/counterparties', [TradeCounterpartyController::class, 'index']);
            Route::get('/counterparties/{counterparty}', [TradeCounterpartyController::class, 'show']);
            Route::post('/counterparties', [TradeCounterpartyController::class, 'store']);
            Route::put('/counterparties/{counterparty}', [TradeCounterpartyController::class, 'update']);
            Route::delete('/counterparties/{counterparty}', [TradeCounterpartyController::class, 'destroy']);
        });

        // Vérification : acte réservé à l'admin de la verticale. `verified`
        // affirme « contrôlé par l'équipe » — ce n'est pas une opération
        // commerciale ordinaire, et la route le reflète.
        Route::middleware('trade.role:admin')->group(function (): void {
            Route::post('/counterparties/{counterparty}/verify', [TradeCounterpartyController::class, 'verify']);
        });

        // ── Commandes — #9779 ───────────────────────────────────────────────
        // `AcceptQuoteAction` cree une `TradeOrder` en `confirmed` et
        // `TradeOrderStateMachine` implementait tout le cycle... mais aucune
        // route ne servait la ressource : la commande naissait puis
        // disparaissait de l'espace vendeur. Une commande qu'on ne peut ni voir
        // ni faire avancer n'est pas une commande.
        //
        // Lecture partagee : `?direction=purchase|sale` distingue ce que
        // l'entreprise achete de ce qu'elle vend.
        Route::middleware('trade.role:purchase,sales,logistics,admin')->group(function (): void {
            Route::get('/orders', [TradeOrderController::class, 'index']);
            Route::get('/orders/{order}', [TradeOrderController::class, 'show']);
            // Faire avancer une commande : celui qui vend expedie, celui qui
            // achete constate la livraison. Le verdict reste a la machine a
            // etats — le controleur ne tranche pas.
            Route::post('/orders/{order}/transition', [TradeOrderController::class, 'transition']);
        });

        // ── Documents commerciaux — #9779 ───────────────────────────────────
        // `trade_documents` existait depuis #8480 (avec ses 11 types et le
        // disque `private`) sans aucune route : l'exportateur ne pouvait pas
        // deposer la facture commerciale que la checklist douaniere lui
        // demande. Le telechargement passe par une route autorisee, jamais par
        // une URL de stockage.
        Route::middleware('trade.role:purchase,sales,logistics,admin')->group(function (): void {
            Route::get('/documents', [TradeDocumentController::class, 'index']);
            Route::get('/documents/{document}', [TradeDocumentController::class, 'show']);
            Route::get('/documents/{document}/download', [TradeDocumentController::class, 'download']);
            Route::post('/documents', [TradeDocumentController::class, 'store']);
            Route::delete('/documents/{document}', [TradeDocumentController::class, 'destroy']);
        });

        // ── Profil commercial & publication vitrine (#9617) ────────────────
        // Sans ces routes, `trade_company_profiles.is_public` restait à `false`
        // pour tout le monde : `/entreprises` et `/entreprises/{slug}` ne
        // pouvaient jamais rien afficher, et le CTA « Devenir tenant » de la
        // vitrine B2B menait à un espace où l'on ne pouvait pas se publier.
        //
        // Lecture : membres du tenant. Écriture : rôle `admin` de la verticale
        // (TradeCompanyProfilePolicy, deny-by-default via TradeRoleResolver).
        Route::get('/profile', [TradeCompanyProfileController::class, 'show']);
        Route::put('/profile', [TradeCompanyProfileController::class, 'update']);
        // Acte distinct : publier n'est pas enregistrer un champ commercial.
        Route::put('/profile/publication', [TradeCompanyProfileController::class, 'publish']);

        // ── Kit de promotion (#9783) ─────────────────────────────────────────
        // « Être capable de promouvoir son entreprise » : le kit liste ce qui
        // manque à la fiche publique et propose un brouillon adossé aux données
        // réelles. Il ne publie RIEN — la publication reste la route ci-dessus.
        Route::middleware('trade.role:admin')->group(function (): void {
            Route::get('/promotion-kit', [TradePromotionKitController::class, 'show']);
        });
    });
