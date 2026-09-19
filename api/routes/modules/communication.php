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

use App\Modules\Communication\Interfaces\Api\V1\Controllers\CommunicationCategoryController;
use App\Modules\Communication\Interfaces\Api\V1\Controllers\CommunicationContactProposalController;
use App\Modules\Communication\Interfaces\Api\V1\Controllers\CommunicationFollowUpController;
use App\Modules\Communication\Interfaces\Api\V1\Controllers\CommunicationFollowUpOptOutController;
use App\Modules\Communication\Interfaces\Api\V1\Controllers\CommunicationFollowUpRuleController;
use App\Modules\Communication\Interfaces\Api\V1\Controllers\CommunicationIntegrationController;
use App\Modules\Communication\Interfaces\Api\V1\Controllers\CommunicationMessageClassificationController;
use App\Modules\Communication\Interfaces\Api\V1\Controllers\CommunicationModuleStatusController;
use App\Modules\Communication\Interfaces\Api\V1\Controllers\CommunicationPendingReplyController;
use App\Modules\Communication\Interfaces\Api\V1\Controllers\CommunicationReplyPolicyController;
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

        // R3 (#7688) — classification IA + liaison contacts CRM.
        // Taxonomie du tenant (lecture: tout employe ; ecriture: principal/rh).
        Route::get('/categories', [CommunicationCategoryController::class, 'index']);
        Route::post('/categories', [CommunicationCategoryController::class, 'store']);
        Route::patch('/categories/{category}', [CommunicationCategoryController::class, 'update'])->whereUuid('category');
        Route::delete('/categories/{category}', [CommunicationCategoryController::class, 'destroy'])->whereUuid('category');

        // Re-classification manuelle (proprietaire de la boite uniquement).
        Route::post('/messages/{message}/classify', [CommunicationMessageClassificationController::class, 'classify'])->whereUuid('message');

        // Propositions de contact CRM (jamais de creation silencieuse) :
        // le proprietaire de la boite accepte ou ecarte.
        Route::get('/contact-proposals', [CommunicationContactProposalController::class, 'index']);
        Route::post('/contact-proposals/{proposal}/accept', [CommunicationContactProposalController::class, 'accept'])->whereUuid('proposal');
        Route::post('/contact-proposals/{proposal}/dismiss', [CommunicationContactProposalController::class, 'dismiss'])->whereUuid('proposal');

        // R4 (#7689) — relances automatiques : regles/sequences PERSONNELLES
        // (boite du proprietaire), file d'attente consultable + annulation,
        // opt-outs du tenant (garde-fou protecteur).
        Route::get('/follow-up-rules', [CommunicationFollowUpRuleController::class, 'index']);
        Route::post('/follow-up-rules', [CommunicationFollowUpRuleController::class, 'store']);
        Route::patch('/follow-up-rules/{rule}', [CommunicationFollowUpRuleController::class, 'update'])->whereUuid('rule');
        Route::delete('/follow-up-rules/{rule}', [CommunicationFollowUpRuleController::class, 'destroy'])->whereUuid('rule');

        Route::get('/follow-ups', [CommunicationFollowUpController::class, 'index']);
        Route::post('/follow-ups/{followUp}/cancel', [CommunicationFollowUpController::class, 'cancel'])->whereUuid('followUp');

        Route::get('/follow-up-opt-outs', [CommunicationFollowUpOptOutController::class, 'index']);
        Route::post('/follow-up-opt-outs', [CommunicationFollowUpOptOutController::class, 'store']);
        Route::delete('/follow-up-opt-outs/{optOut}', [CommunicationFollowUpOptOutController::class, 'destroy'])->whereUuid('optOut');

        // R5 (#7690) — reponses assistees : politique PERSONNELLE par boite
        // × categorie (off/draft/confirm/auto) et file Pending durable —
        // edition/approbation/rejet reserves au proprietaire de la boite
        // (approve = SEUL chemin d'envoi du mode confirm).
        Route::get('/reply-policies', [CommunicationReplyPolicyController::class, 'index']);
        Route::post('/reply-policies', [CommunicationReplyPolicyController::class, 'store']);

        Route::get('/pending-replies', [CommunicationPendingReplyController::class, 'index']);
        Route::patch('/pending-replies/{pendingReply}', [CommunicationPendingReplyController::class, 'update'])->whereUuid('pendingReply');
        Route::post('/pending-replies/{pendingReply}/approve', [CommunicationPendingReplyController::class, 'approve'])->whereUuid('pendingReply');
        Route::post('/pending-replies/{pendingReply}/reject', [CommunicationPendingReplyController::class, 'reject'])->whereUuid('pendingReply');
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
