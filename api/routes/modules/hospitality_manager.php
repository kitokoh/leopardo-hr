<?php

declare(strict_types=1);

/**
 * Routes HospitalityManager (solution verticale hôtels, résidences &
 * gestion locative multi-sites) — HOSP-001 (#7943), BC-32 HOSPITALITY.
 *
 * Chargé depuis routes/api.php à l'intérieur du groupe /v1 — ne JAMAIS
 * re-préfixer `v1` (règle AGENTS.md).
 *
 * Toutes les routes sont tenant-scoped et soumises au feature flag
 * `hospitality` (fail-closed) : solution inactive → 403
 * HOSPITALITY_SOLUTION_INACTIVE (contrôle `assertSolutionActive()` dans
 * chaque contrôleur, pattern HealthManager).
 *
 * Chemins : /hospitality/... (ids uuid, whereUuid).
 * Squelette HOSP-001 : sonde d'activation /status. Les routes métier
 * arrivent avec HOSP-002→006 (référentiel, équipe, réservations, locatif,
 * vitrine publique — spec docs/specifications/SOLUTION_HOSPITALITY.md).
 */

use App\Modules\HospitalityManager\Interfaces\Api\V1\Controllers\HospitalityModuleStatusController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:api', 'auth:sanctum', 'token.refresh', 'tenant', 'throttle:api-plan'])
    ->prefix('hospitality')
    ->group(function (): void {
        // État/santé du module pour le tenant courant (sonde d'activation).
        Route::get('/status', [HospitalityModuleStatusController::class, 'show']);
    });
