<?php

/**
 * Routes de la verticale VTC/taxi (BC-34 VTC ; VTC-01/#8357).
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
 *   - module.vtc       → feature flag companies.features.vtc
 *
 * BC-34 VTC : réservation de courses, dispatch au chauffeur disponible le
 * plus proche (via le core géospatial BC-33), tarification, suivi.
 * Référence : docs/specifications/MODULE_GEOCORE_ET_VERTICAL_VTC.md (§5).
 */

use App\Modules\Vtc\Interfaces\Api\V1\Controllers\VtcHealthController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:api', 'auth:sanctum', 'token.refresh', 'tenant', 'throttle:api-plan', 'module.vtc'])
    ->prefix('vtc')
    ->group(function (): void {
        // Smoke test du module (VTC-01/#8357) — lecture pure.
        Route::get('/ping', [VtcHealthController::class, 'ping']);
    });
