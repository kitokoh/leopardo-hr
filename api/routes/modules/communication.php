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
 * Squelette R0 + lot R1 (#7686) + lot R2 (#7687) : etat du module,
 * connexion Google par utilisateur (OAuth serveur) et consultation des
 * fils/messages Gmail synchronises. Les routes metier suivantes
 * (relances, reponses — kebab-case pluriel) arrivent avec les lots R4/R5.
 * Référence : docs/specifications/MODULE_COMMUNICATION_EMAIL_IA.md.
 */

use App\Modules\Communication\Interfaces\Api\V1\Controllers\CommunicationIntegrationController;
use App\Modules\Communication\Interfaces\Api\V1\Controllers\CommunicationModuleStatusController;
use App\Modules\Communication\Interfaces\Api\V1\Controllers\CommunicationThreadController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:api', 'auth:sanctum', 'token.refresh', 'tenant', 'throttle:api-plan', 'module.communication'])
    ->prefix('communication')
    ->group(function (): void {
        // État/santé du module pour le tenant courant (sonde d'activation).
        Route::get('/status', [CommunicationModuleStatusController::class, 'show']);

        // R1 (#7686) — connexion Google par utilisateur (OAuth serveur).
        // La boite est PERSONNELLE : l'index ne renvoie que les integrations
        // de l'employe courant ; la revocation passe par la policy.
        Route::get('/integrations', [CommunicationIntegrationController::class, 'index']);
        Route::post('/integrations/google', [CommunicationIntegrationController::class, 'connectGoogle']);
        Route::delete('/integrations/{integration}', [CommunicationIntegrationController::class, 'destroy'])->whereUuid('integration');

        // R2 (#7687) — fils/messages Gmail synchronises. Boite PERSONNELLE :
        // l'index est borne aux boites de l'appelant, le detail (corps
        // dechiffre) est reserve au proprietaire (policy view).
        Route::get('/threads', [CommunicationThreadController::class, 'index']);
        Route::get('/threads/{thread}/messages', [CommunicationThreadController::class, 'messages'])->whereUuid('thread');
    });

// R1 (#7686) — callback OAuth Google : route PUBLIQUE par construction (le
// navigateur revient de Google sans bearer token ni cookie de session). La
// preuve d'identite est le state anti-CSRF a usage unique pose par
// POST /communication/integrations/google (cache, TTL 10 min) ; le gate
// module est re-verifie a la main sur la company portee par le state
// (fail-closed). Bucket auth-sensitive : meme politique que /auth/google.
Route::middleware(['throttle:auth-sensitive'])
    ->prefix('communication')
    ->group(function (): void {
        Route::get('/integrations/google/callback', [CommunicationIntegrationController::class, 'googleCallback']);
    });
