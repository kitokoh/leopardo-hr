<?php

/**
 * Routes PUBLIQUES du module Library (Leopardo Library).
 *
 * Chargé depuis routes/api.php à l'intérieur du groupe /v1 — ne JAMAIS
 * re-préfixer `v1` (règle AGENTS.md). URLs réelles :
 *   GET  /api/v1/public/library
 *   GET  /api/v1/public/library/{slug}
 *   GET  /api/v1/public/library/{slug}/download
 *   POST /api/v1/public/library/{slug}/reports
 *
 * Isolées des routes privées (public vs privé strictement séparés) : PAS
 * d'auth Sanctum ni de middleware tenant — le catalogue public Leopardo
 * (`company_id` NULL) est servi sans compte, throttling renforcé
 * `throttle:shop-public` (anti-scraping par IP).
 *
 * DTO public dédié (LibraryPublicController) : 0 donnée interne
 * (ni company_id, ni file_key, ni meta interne). Cache Redis TTL invalidé à
 * la publication. Les fichiers ne sont jamais exposés par une URL publique
 * permanente : la route download renvoie une URL temporaire signée (ou 403).
 */

use App\Modules\Library\Interfaces\Api\V1\Controllers\LibraryPublicController;
use App\Modules\Library\Interfaces\Api\V1\Controllers\LibraryReportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:shop-public'])
    ->prefix('public/library')
    ->group(function (): void {
        Route::get('/', [LibraryPublicController::class, 'index'])
            ->name('library.public.index');
        Route::get('/{slug}', [LibraryPublicController::class, 'show'])
            ->name('library.public.show');
        Route::get('/{slug}/download', [LibraryPublicController::class, 'download'])
            ->name('library.public.download');
        Route::post('/{slug}/reports', [LibraryReportController::class, 'store'])
            ->name('library.public.reports.store');
    });
