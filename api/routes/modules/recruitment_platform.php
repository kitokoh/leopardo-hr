<?php

declare(strict_types=1);

/**
 * BC-10 RECRUITMENT — routes plateforme du portail public d'offres.
 *
 * Chargé depuis `routes/api.php` à l'INTÉRIEUR du groupe `platform` :
 *  - préfixe déjà `platform` côté api.php, on ajoute `recruitment` ici ;
 *  - garde `auth:super_admin_api` déjà appliquée par le groupe ;
 *  - `platform.permission:*` appliqué route par route (convention Platform).
 *
 * Aucune route n'est exposée à un tenant : la publication d'offres par la
 * plateforme et la configuration des agrégateurs sont des gestes internes, et
 * un token Sanctum employee n'a même pas le guard `super_admin_api` → 401.
 *
 * Règle de permission, appliquée sans exception :
 *  - **lecture** → `recruitment.view` (marketing, finance, ops, admin) ;
 *  - **écriture** → `recruitment.manage` (admin, super_admin).
 *
 * @see docs/specifications/MODULE_RECRUITMENT_PUBLIC_PORTAL.md §5.3
 */
use App\Modules\Recruitment\Interfaces\Api\V1\Controllers\PlatformJobPostingController;
use App\Modules\Recruitment\Interfaces\Api\V1\Controllers\PlatformJobSourceController;
use Illuminate\Support\Facades\Route;

Route::prefix('recruitment')->group(function (): void {
    // ─── Offres publiées par la plateforme ───────────────────────────────────
    Route::get('/jobs', [PlatformJobPostingController::class, 'index'])
        ->middleware('platform.permission:recruitment.view');

    Route::post('/jobs', [PlatformJobPostingController::class, 'store'])
        ->middleware('platform.permission:recruitment.manage');

    Route::post('/jobs/{companyId}/{jobPosting}/publish', [PlatformJobPostingController::class, 'publish'])
        ->whereNumber('jobPosting')
        ->middleware('platform.permission:recruitment.manage');

    Route::post('/jobs/{companyId}/{jobPosting}/close', [PlatformJobPostingController::class, 'close'])
        ->whereNumber('jobPosting')
        ->middleware('platform.permission:recruitment.manage');

    // ─── Offres agrégées importées ───────────────────────────────────────────
    Route::get('/offers', [PlatformJobPostingController::class, 'offers'])
        ->middleware('platform.permission:recruitment.view');

    // ─── Connecteurs d'agrégateurs ───────────────────────────────────────────
    Route::get('/sources', [PlatformJobSourceController::class, 'index'])
        ->middleware('platform.permission:recruitment.view');

    Route::put('/sources/{provider}', [PlatformJobSourceController::class, 'configure'])
        ->whereIn('provider', ['indeed', 'linkedin', 'glassdoor', 'adzuna', 'jooble', 'custom_feed'])
        ->middleware('platform.permission:recruitment.manage');

    Route::post('/sources/{provider}/test', [PlatformJobSourceController::class, 'test'])
        ->whereIn('provider', ['indeed', 'linkedin', 'glassdoor', 'adzuna', 'jooble', 'custom_feed'])
        ->middleware('platform.permission:recruitment.manage');

    // Synchronisation synchrone : l'écran affiche le résultat réel.
    Route::post('/sources/{provider}/sync', [PlatformJobSourceController::class, 'sync'])
        ->whereIn('provider', ['indeed', 'linkedin', 'glassdoor', 'adzuna', 'jooble', 'custom_feed'])
        ->middleware('platform.permission:recruitment.manage');

    // Variante en file, pour les gros volumes.
    Route::post('/sources/{provider}/queue-sync', [PlatformJobSourceController::class, 'queueSync'])
        ->whereIn('provider', ['indeed', 'linkedin', 'glassdoor', 'adzuna', 'jooble', 'custom_feed'])
        ->middleware('platform.permission:recruitment.manage');

    Route::delete('/sources/{provider}', [PlatformJobSourceController::class, 'revoke'])
        ->whereIn('provider', ['indeed', 'linkedin', 'glassdoor', 'adzuna', 'jooble', 'custom_feed'])
        ->middleware('platform.permission:recruitment.manage');
});
