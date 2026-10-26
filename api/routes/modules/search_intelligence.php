<?php

declare(strict_types=1);

/**
 * GSI-1 (#8512) / GSI-2 (#8513) — routes du module Growth & Search Intelligence.
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
 * Lecture = `search_intel.view` ; toute écriture = `search_intel.manage`.
 */
use App\Modules\SearchIntelligence\Interfaces\Api\V1\Controllers\SearchIntelIntegrationController;
use App\Modules\SearchIntelligence\Interfaces\Api\V1\Controllers\SearchIntelOverviewController;
use App\Modules\SearchIntelligence\Interfaces\Api\V1\Controllers\SearchIntelScanController;
use App\Modules\SearchIntelligence\Interfaces\Api\V1\Controllers\SearchIntelSeoIssueController;
use Illuminate\Support\Facades\Route;

Route::prefix('search-intel')->group(function (): void {
    // GSI-9 (#8520) — vue d'ensemble : compteurs réels + états « non connecté »
    // honnêtes. Lecture seule.
    Route::get('/overview', SearchIntelOverviewController::class)
        ->middleware('platform.permission:search_intel.view');

    // GSI-8 (#8519) — état des connecteurs GSC/GA4/Google Ads/MS Ads.
    // N'expose jamais les secrets (cf. SearchIntelIntegrationController).
    Route::get('/integrations', [SearchIntelIntegrationController::class, 'index'])
        ->middleware('platform.permission:search_intel.view');

    // GSI-2 (#8513) — SE0 : métadonnées du workflow (libellés, gravités).
    Route::get('/seo/meta', [SearchIntelSeoIssueController::class, 'meta'])
        ->middleware('platform.permission:search_intel.view');

    // File de traitement des anomalies, triée par gravité.
    Route::get('/seo/issues', [SearchIntelSeoIssueController::class, 'index'])
        ->middleware('platform.permission:search_intel.view');

    // Transition de workflow — auditée. `manage` : c'est une écriture d'état.
    Route::patch('/seo/issues/{issue}/transition', [SearchIntelSeoIssueController::class, 'transition'])
        ->whereNumber('issue')
        ->middleware('platform.permission:search_intel.manage');

    // Déclenchement d'un scan — LECTURE SEULE côté site, écriture côté base.
    Route::post('/seo/scan', SearchIntelScanController::class)
        ->middleware('platform.permission:search_intel.manage');
});
