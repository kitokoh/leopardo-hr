<?php

/**
 * Routes privées du module Communication (BC-29 COMMUNICATION, R0 #7685).
 *
 * Chargé depuis routes/api.php à l'intérieur du groupe /v1 — ne JAMAIS
 * re-préfixer `v1` (règle AGENTS.md).
 *
 * Middleware du groupe (convention modules, cf. catalog.php) :
 *   - throttle:api            → limite globale de l'API
 *   - auth:sanctum            → authentification (Sanctum)
 *   - token.refresh           → auto-refresh du token
 *   - tenant                  → résolution de la company + garde-fous statut/archive
 *   - throttle:api-plan       → limite selon le plan tarifaire
 *   - module.communication    → feature flag companies.features.communication
 *
 * Squelette R0 : seul l'endpoint d'état du module est exposé. Les routes
 * métier (intégrations OAuth, threads, messages, relances — kebab-case
 * pluriel) arrivent avec les lots R1→R5.
 * Référence : docs/specifications/MODULE_COMMUNICATION_EMAIL_IA.md.
 */

use App\Modules\Communication\Interfaces\Api\V1\Controllers\CommunicationModuleStatusController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:api', 'auth:sanctum', 'token.refresh', 'tenant', 'throttle:api-plan', 'module.communication'])
    ->prefix('communication')
    ->group(function (): void {
        // État/santé du module pour le tenant courant (sonde d'activation).
        Route::get('/status', [CommunicationModuleStatusController::class, 'show']);
    });
