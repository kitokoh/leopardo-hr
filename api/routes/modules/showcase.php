<?php

declare(strict_types=1);

/**
 * Routes du module Showcase (BC-27 SHOWCASE).
 *
 * Chargé depuis routes/api.php à l'intérieur du groupe /v1 (jamais de
 * re-préfixe `v1`). Référence : docs/specifications/SOLUTION_SITE_VITRINE.md
 * (§6 API) et issues #6866/#6867.
 *
 * PRIVÉ (gestion tenant) — middlewares du groupe :
 *   - throttle:api, auth:sanctum, token.refresh, tenant, throttle:api-plan
 *     (hérités des modules, cf. crm.php) ;
 *   - module.showcase → feature flag companies.features.company_showcase
 *     (fail-closed : tenant sans le flag → 403) ;
 *   - api.manager:principal,rh → édition réservée au responsable du tenant
 *     (cohérent avec CompanyShowcasePolicy).
 *
 * PUBLIC (vitrine consultée sans auth) — groupe isolé `throttle:shop-public`
 * (aucun middleware tenant/utilisateur), DTO public dédié (#6867).
 */

use App\Modules\Showcase\Interfaces\Api\V1\Controllers\ShowcaseController;
use App\Modules\Showcase\Interfaces\Api\V1\Controllers\ShowcaseMediaController;
use App\Modules\Showcase\Interfaces\Api\V1\Controllers\ShowcaseMediaPublicController;
use App\Modules\Showcase\Interfaces\Api\V1\Controllers\ShowcasePublicController;
use App\Modules\Showcase\Interfaces\Api\V1\Controllers\ShowcaseSectionController;
use Illuminate\Support\Facades\Route;

// ── Publique (isolée, P0 #6867) ─────────────────────────────────────────────
Route::middleware(['throttle:shop-public'])
    ->prefix('public/vitrine')
    ->group(function (): void {
        // V-SEO #6873 : sitemap (vitrines publiees uniquement) + robots.
        Route::get('/sitemap.xml', [ShowcasePublicController::class, 'sitemap']);
        Route::get('/robots.txt', [ShowcasePublicController::class, 'robots']);

        // V-MEDIA #6872 : binaire public d'un média d'une vitrine PUBLIÉE
        // (brouillon → 404 ; aperçu privé via ?token=, non caché).
        Route::get('/{slug}/media/{media}', [ShowcaseMediaPublicController::class, 'show'])
            ->where('slug', '[A-Za-z0-9\-_]{1,160}')
            ->whereNumber('media');

        // V-PUBLIC-API #6867 (+ apercu prive V-PUBLISH #6871 via ?token=).
        Route::get('/{slug}', [ShowcasePublicController::class, 'show'])
            ->where('slug', '[A-Za-z0-9\-_]{1,160}');
    });

// ── Privée (gestion tenant, #6866) ──────────────────────────────────────────
Route::middleware(['throttle:api', 'auth:sanctum', 'token.refresh', 'tenant', 'throttle:api-plan', 'module.showcase', 'api.manager:principal,rh'])
    ->prefix('showcase')
    ->group(function (): void {
        // Vitrine du tenant (création 1-clic US1 — socle des issues #6866/#6870).
        Route::get('/', [ShowcaseController::class, 'show']);
        Route::post('/', [ShowcaseController::class, 'store']);

        // V-PUBLISH #6871 : workflow de publication + apercu prive.
        Route::post('/publish', [ShowcaseController::class, 'publish']);
        Route::post('/unpublish', [ShowcaseController::class, 'unpublish']);
        Route::post('/preview-token', [ShowcaseController::class, 'previewToken']);

        // V-THEMES #6868 / V-RGPD #6875 : theme, variables de marque, legal.
        Route::put('/settings', [ShowcaseController::class, 'updateSettings']);

        // Sections (contrat JSON Schema + CRUD, #6866).
        Route::get('/sections', [ShowcaseSectionController::class, 'index']);
        Route::post('/sections', [ShowcaseSectionController::class, 'store']);
        Route::post('/sections/reorder', [ShowcaseSectionController::class, 'reorder']);
        Route::patch('/sections/{section}', [ShowcaseSectionController::class, 'update'])->whereNumber('section');
        Route::delete('/sections/{section}', [ShowcaseSectionController::class, 'destroy'])->whereNumber('section');

        // Médias (V-MEDIA #6872) : upload logo / images de sections, liste et
        // suppression par id stable. Le DTO privé n'expose ni disk ni path.
        Route::get('/media', [ShowcaseMediaController::class, 'index']);
        Route::post('/media', [ShowcaseMediaController::class, 'store']);
        Route::delete('/media/{media}', [ShowcaseMediaController::class, 'destroy'])->whereNumber('media');
    });
