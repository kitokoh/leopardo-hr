<?php

declare(strict_types=1);

/**
 * Routes HealthManager (solution verticale hôpitaux & cliniques) — BC-30.
 *
 * Toutes les routes sont tenant-scoped et soumises au feature flag
 * `healthmanager` (HC-001 #7785) : solution inactive → 403 fail-closed
 * (contrôle `assertSolutionActive()` dans chaque contrôleur).
 *
 * Chemins : /health-manager/... (ids numériques bigint, whereNumber).
 * RBAC (HealthAccess + policies deny-by-default) : direction = gestion
 * complète ; accueil = registre patients ; praticien = lecture ; employé
 * lambda = 403. Données de santé JAMAIS exposées hors tenant.
 */

use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:api', 'auth:sanctum', 'token.refresh', 'tenant', 'throttle:api-plan'])->group(function (): void {
    // HC-002 (#7786) — référentiel structure clinique et HC-003 (#7787) —
    // registre patients : les ressources arrivent avec leurs tranches.
});
