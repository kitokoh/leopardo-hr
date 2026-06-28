<?php

/**
 * Routes HR App — Application Mobile RH (Leopardo RH)
 *
 * Ces routes sont exclusivement dédiées à l'application mobile RH.
 * Seuls les employés avec manager_role IN ('rh', 'principal') peuvent y accéder.
 *
 * Architecture multi-app Leopardo :
 *   - App Manager  → routes /api/v1/ classiques protégées par api.manager:principal
 *   - App RH       → ces routes /api/v1/hr/** protégées par api.manager:rh,principal
 *   - App Employee → routes /api/v1/me/** (self-service)
 *   - App Admin    → routes /api/v1/platform/** (super_admin_api guard)
 *
 * Le header optionnel X-App-Context: rh peut être envoyé pour qu'un
 * éventuel middleware d'audit sache d'où vient la requête. Les contrôles
 * de rôle restent au niveau middleware api.manager et dans le controller.
 */

use App\Http\Controllers\Api\V1\HrController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:api', 'auth:sanctum', 'tenant', 'throttle:api-plan', 'api.manager:rh,principal'])
    ->prefix('hr')
    ->group(function (): void {

    // ── Profil de l'utilisateur RH ────────────────────────────────────────────
    Route::get('/me', [HrController::class, 'me']);

    // ── Dashboard RH ──────────────────────────────────────────────────────────
    Route::get('/dashboard', [HrController::class, 'dashboard']);

    // ── Vue d'ensemble de l'équipe ────────────────────────────────────────────
    Route::get('/team-overview', [HrController::class, 'teamOverview']);

    // ── Gestion des employés (lecture + ajout + modification) ─────────────────
    // Le RH peut ajouter et modifier les employés, mais pas changer les rôles manager.
    // L'assignation de rôles manager est réservée au principal (RoleAssignmentController).
    Route::get('/employees', [HrController::class, 'employees']);
    Route::post('/employees', [HrController::class, 'addEmployee']);
    Route::get('/employees/{employee}', [HrController::class, 'showEmployee'])->whereNumber('employee');
    Route::patch('/employees/{employee}', [HrController::class, 'updateEmployee'])->whereNumber('employee');

    // ── Réutilisation des modules RH existants (scope RH) ─────────────────────
    // Le RH accède aux mêmes endpoints que le manager pour les absences,
    // les avancements sur salaire, le planning, etc. Ces routes redirigent
    // vers les controllers existants — pas de duplication.
    // (Voir modules/rh.php, modules/dashboard.php, modules/planning.php)
});
