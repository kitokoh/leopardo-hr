<?php

/**
 * Routes du module transverse Geo (BC-33 GEO ; GEO-01/#8350, GEO-02/#8351).
 *
 * Chargé depuis routes/api.php à l'intérieur du groupe /v1 — ne JAMAIS
 * re-préfixer `v1` (règle AGENTS.md).
 *
 * Middleware du groupe (convention modules, cf. delivery.php) :
 *   - throttle:api     → limite globale de l'API
 *   - auth:sanctum     → authentification (Sanctum)
 *   - token.refresh    → auto-refresh du token
 *   - tenant           → résolution de la company + garde-fous statut/archive
 *   - throttle:api-plan→ limite selon le plan tarifaire
 *   - module.geo       → feature flag companies.features.geo
 *
 * BC-33 GEO : core géospatial transverse (PostGIS) — distance, plus-proches,
 * dans-un-rayon — réutilisable par toute verticale (VTC en premier, BC-34).
 * Référence : docs/specifications/MODULE_GEOCORE_ET_VERTICAL_VTC.md (§4).
 */

use App\Modules\Geo\Interfaces\Api\V1\Controllers\GeoCapabilitiesController;
use App\Modules\Geo\Interfaces\Api\V1\Controllers\GeoDistanceController;
use App\Modules\Geo\Interfaces\Api\V1\Controllers\GeoHealthController;
use App\Modules\Geo\Interfaces\Api\V1\Controllers\GeoNearestController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:api', 'auth:sanctum', 'token.refresh', 'tenant', 'throttle:api-plan', 'module.geo'])
    ->prefix('geo')
    ->group(function (): void {
        // Smoke test du module (GEO-02/#8351) — lecture pure.
        Route::get('/ping', [GeoHealthController::class, 'ping']);

        // GEO-05 (#8354) — API v1 du core géospatial.
        Route::post('/distance', GeoDistanceController::class);
        Route::get('/nearest', [GeoNearestController::class, 'index']);

        // Diagnostic réservé admin tenant (garde geo.admin, deny-by-default).
        Route::get('/capabilities', [GeoCapabilitiesController::class, 'show'])
            ->middleware('geo.admin');
    });
