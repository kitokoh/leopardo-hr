<?php

declare(strict_types=1);

/**
 * Routes du module Growth & Search Intelligence (GSI).
 *
 * Chargé depuis `routes/api.php` à l'INTÉRIEUR du groupe `platform` :
 *  - préfixe déjà `platform` côté api.php, on ajoute `search-intel` ici ;
 *  - garde `auth:super_admin_api` déjà appliquée par le groupe ;
 *  - `platform.permission:*` appliqué route par route (convention Platform).
 *
 * Aucune route n'est exposée à un tenant : ces agrégats sont cross-tenant par
 * nature (le parc de sites public est unique), et un token Sanctum employee
 * n'a même pas le guard `super_admin_api` → 401 systématique.
 *
 * Règle de permission, appliquée sans exception :
 *  - **lecture** → `search_intel.view` (marketing, finance, ops, admin) ;
 *  - **écriture** → `search_intel.manage` (marketing, admin).
 */
use App\Modules\SearchIntelligence\Interfaces\Api\V1\Controllers\SearchIntelAeoController;
use App\Modules\SearchIntelligence\Interfaces\Api\V1\Controllers\SearchIntelCompetitorController;
use App\Modules\SearchIntelligence\Interfaces\Api\V1\Controllers\SearchIntelContentController;
use App\Modules\SearchIntelligence\Interfaces\Api\V1\Controllers\SearchIntelGeoController;
use App\Modules\SearchIntelligence\Interfaces\Api\V1\Controllers\SearchIntelIntegrationController;
use App\Modules\SearchIntelligence\Interfaces\Api\V1\Controllers\SearchIntelOverviewController;
use App\Modules\SearchIntelligence\Interfaces\Api\V1\Controllers\SearchIntelScanController;
use App\Modules\SearchIntelligence\Interfaces\Api\V1\Controllers\SearchIntelSeaController;
use App\Modules\SearchIntelligence\Interfaces\Api\V1\Controllers\SearchIntelSeoIssueController;
use Illuminate\Support\Facades\Route;

Route::prefix('search-intel')->group(function (): void {
    // ─── Vue d'ensemble et intégrations ─────────────────────────────────────
    Route::get('/overview', SearchIntelOverviewController::class)
        ->middleware('platform.permission:search_intel.view');

    Route::get('/integrations', [SearchIntelIntegrationController::class, 'index'])
        ->middleware('platform.permission:search_intel.view');

    // ─── GSI-2 : SEO technique et workflow de traitement ────────────────────
    Route::get('/seo/meta', [SearchIntelSeoIssueController::class, 'meta'])
        ->middleware('platform.permission:search_intel.view');

    Route::get('/seo/issues', [SearchIntelSeoIssueController::class, 'index'])
        ->middleware('platform.permission:search_intel.view');

    Route::patch('/seo/issues/{issue}/transition', [SearchIntelSeoIssueController::class, 'transition'])
        ->whereNumber('issue')
        ->middleware('platform.permission:search_intel.manage');

    Route::post('/seo/scan', SearchIntelScanController::class)
        ->middleware('platform.permission:search_intel.manage');

    // ─── GSI-3 : SEA, campagnes et relevés de performance ───────────────────
    Route::get('/sea/campaigns', [SearchIntelSeaController::class, 'index'])
        ->middleware('platform.permission:search_intel.view');

    Route::get('/sea/campaigns/compare', [SearchIntelSeaController::class, 'compare'])
        ->middleware('platform.permission:search_intel.view');

    Route::post('/sea/campaigns', [SearchIntelSeaController::class, 'store'])
        ->middleware('platform.permission:search_intel.manage');

    Route::get('/sea/campaigns/{campaign}', [SearchIntelSeaController::class, 'show'])
        ->whereNumber('campaign')
        ->middleware('platform.permission:search_intel.view');

    Route::patch('/sea/campaigns/{campaign}', [SearchIntelSeaController::class, 'update'])
        ->whereNumber('campaign')
        ->middleware('platform.permission:search_intel.manage');

    Route::post('/sea/campaigns/{campaign}/transition', [SearchIntelSeaController::class, 'transition'])
        ->whereNumber('campaign')
        ->middleware('platform.permission:search_intel.manage');

    // Validation humaine : seule elle autorise l'activation.
    Route::post('/sea/campaigns/{campaign}/validate', [SearchIntelSeaController::class, 'validateCampaign'])
        ->whereNumber('campaign')
        ->middleware('platform.permission:search_intel.manage');

    Route::post('/sea/campaigns/{campaign}/metrics', [SearchIntelSeaController::class, 'storeMetric'])
        ->whereNumber('campaign')
        ->middleware('platform.permission:search_intel.manage');

    // ─── GSI-4 : AEO, révision humaine obligatoire ──────────────────────────
    Route::get('/aeo/questions', [SearchIntelAeoController::class, 'index'])
        ->middleware('platform.permission:search_intel.view');

    Route::post('/aeo/questions', [SearchIntelAeoController::class, 'store'])
        ->middleware('platform.permission:search_intel.manage');

    Route::patch('/aeo/questions/{question}', [SearchIntelAeoController::class, 'update'])
        ->whereNumber('question')
        ->middleware('platform.permission:search_intel.manage');

    // Transitions : soumission, VALIDATION (fige la réponse), publication.
    Route::post('/aeo/questions/{question}/submit', [SearchIntelAeoController::class, 'submit'])
        ->whereNumber('question')
        ->middleware('platform.permission:search_intel.manage');

    Route::post('/aeo/questions/{question}/approve', [SearchIntelAeoController::class, 'approve'])
        ->whereNumber('question')
        ->middleware('platform.permission:search_intel.manage');

    Route::post('/aeo/questions/{question}/publish', [SearchIntelAeoController::class, 'publish'])
        ->whereNumber('question')
        ->middleware('platform.permission:search_intel.manage');

    Route::post('/aeo/questions/{question}/to-update', [SearchIntelAeoController::class, 'toUpdate'])
        ->whereNumber('question')
        ->middleware('platform.permission:search_intel.manage');

    // ─── GSI-5 : GEO, méthode de preuve explicite ───────────────────────────
    Route::get('/geo/queries', [SearchIntelGeoController::class, 'indexQueries'])
        ->middleware('platform.permission:search_intel.view');

    Route::post('/geo/queries', [SearchIntelGeoController::class, 'storeQuery'])
        ->middleware('platform.permission:search_intel.manage');

    Route::get('/geo/queries/{geoQuery}/checks', [SearchIntelGeoController::class, 'indexChecks'])
        ->whereNumber('geoQuery')
        ->middleware('platform.permission:search_intel.view');

    Route::post('/geo/queries/{geoQuery}/checks', [SearchIntelGeoController::class, 'storeCheck'])
        ->whereNumber('geoQuery')
        ->middleware('platform.permission:search_intel.manage');

    // Pas de PATCH ni DELETE sur un check : l'historique est immuable.

    // ─── GSI-6 : contenu marketing, statuts contrôlés ───────────────────────
    Route::get('/content', [SearchIntelContentController::class, 'index'])
        ->middleware('platform.permission:search_intel.view');

    // L'export doit être déclaré AVANT `/content/{...}` pour ne pas être
    // capté par un paramètre de route.
    Route::get('/content/export', [SearchIntelContentController::class, 'export'])
        ->middleware('platform.permission:search_intel.view');

    Route::post('/content', [SearchIntelContentController::class, 'store'])
        ->middleware('platform.permission:search_intel.manage');

    Route::patch('/content/{content}', [SearchIntelContentController::class, 'update'])
        ->whereNumber('content')
        ->middleware('platform.permission:search_intel.manage');

    Route::post('/content/{content}/transition', [SearchIntelContentController::class, 'transition'])
        ->whereNumber('content')
        ->middleware('platform.permission:search_intel.manage');

    // ─── GSI-7 : veille concurrentielle, sources datées obligatoires ────────
    Route::get('/competitors', [SearchIntelCompetitorController::class, 'index'])
        ->middleware('platform.permission:search_intel.view');

    Route::post('/competitors', [SearchIntelCompetitorController::class, 'store'])
        ->middleware('platform.permission:search_intel.manage');

    Route::patch('/competitors/{competitor}', [SearchIntelCompetitorController::class, 'update'])
        ->whereNumber('competitor')
        ->middleware('platform.permission:search_intel.manage');

    Route::post('/competitors/{competitor}/review', [SearchIntelCompetitorController::class, 'review'])
        ->whereNumber('competitor')
        ->middleware('platform.permission:search_intel.manage');
});
