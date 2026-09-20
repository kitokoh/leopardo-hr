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
 * Chemins : /hospitality/... (ids numériques bigint, whereNumber).
 * HOSP-001 : sonde d'activation /status. HOSP-002 (#7944) : référentiel
 * établissements / types de chambres / unités + publication vitrine.
 * Équipe (HOSP-003), réservations (HOSP-004), locatif (HOSP-005) et
 * vitrine publique (HOSP-006) à venir — spec
 * docs/specifications/SOLUTION_HOSPITALITY.md.
 */

use App\Modules\HospitalityManager\Interfaces\Api\V1\Controllers\HospitalityModuleStatusController;
use App\Modules\HospitalityManager\Interfaces\Api\V1\Controllers\HospitalityPropertyController;
use App\Modules\HospitalityManager\Interfaces\Api\V1\Controllers\HospitalityPropertyStaffController;
use App\Modules\HospitalityManager\Interfaces\Api\V1\Controllers\HospitalityRoomTypeController;
use App\Modules\HospitalityManager\Interfaces\Api\V1\Controllers\HospitalityUnitController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:api', 'auth:sanctum', 'token.refresh', 'tenant', 'throttle:api-plan'])
    ->prefix('hospitality')
    ->group(function (): void {
        // État/santé du module pour le tenant courant (sonde d'activation).
        Route::get('/status', [HospitalityModuleStatusController::class, 'show']);

        // ── Référentiel établissements (HOSP-002 #7944) ───────────────
        Route::get('/properties', [HospitalityPropertyController::class, 'index']);
        Route::post('/properties', [HospitalityPropertyController::class, 'store']);
        Route::get('/properties/{property}', [HospitalityPropertyController::class, 'show'])->whereNumber('property');
        Route::patch('/properties/{property}', [HospitalityPropertyController::class, 'update'])->whereNumber('property');
        Route::delete('/properties/{property}', [HospitalityPropertyController::class, 'destroy'])->whereNumber('property');
        Route::post('/properties/{property}/publish', [HospitalityPropertyController::class, 'publish'])->whereNumber('property');
        Route::post('/properties/{property}/unpublish', [HospitalityPropertyController::class, 'unpublish'])->whereNumber('property');

        // ── Inventaire : types de chambres (HOSP-002 #7944) ───────────
        Route::get('/properties/{property}/room-types', [HospitalityRoomTypeController::class, 'index'])->whereNumber('property');
        Route::post('/properties/{property}/room-types', [HospitalityRoomTypeController::class, 'store'])->whereNumber('property');
        Route::patch('/room-types/{roomType}', [HospitalityRoomTypeController::class, 'update'])->whereNumber('roomType');
        Route::delete('/room-types/{roomType}', [HospitalityRoomTypeController::class, 'destroy'])->whereNumber('roomType');

        // ── Inventaire : unités physiques (HOSP-002 #7944) ────────────
        Route::get('/properties/{property}/units', [HospitalityUnitController::class, 'index'])->whereNumber('property');
        Route::post('/properties/{property}/units', [HospitalityUnitController::class, 'store'])->whereNumber('property');
        Route::patch('/units/{unit}', [HospitalityUnitController::class, 'update'])->whereNumber('unit');
        Route::delete('/units/{unit}', [HospitalityUnitController::class, 'destroy'])->whereNumber('unit');

        // ── Équipe par établissement (HOSP-003 #7945) ─────────────────
        Route::get('/properties/{property}/staff', [HospitalityPropertyStaffController::class, 'index'])->whereNumber('property');
        Route::post('/properties/{property}/staff', [HospitalityPropertyStaffController::class, 'store'])->whereNumber('property');
        Route::patch('/properties/{property}/staff/{assignment}', [HospitalityPropertyStaffController::class, 'update'])->whereNumber('property')->whereNumber('assignment');
        Route::delete('/properties/{property}/staff/{assignment}', [HospitalityPropertyStaffController::class, 'destroy'])->whereNumber('property')->whereNumber('assignment');
    });
