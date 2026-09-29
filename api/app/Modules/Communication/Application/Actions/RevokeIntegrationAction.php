<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Actions;

use App\Modules\Communication\Domain\Models\CommunicationIntegration;
use App\Modules\Communication\Infrastructure\Services\GoogleGmailOAuthService;
use App\Modules\Communication\Infrastructure\Services\GoogleGmailSyncService;
use Illuminate\Log\LogManager;

/**
 * Cas d'usage « révoquer une intégration boîte mail » (BC-29
 * COMMUNICATION, R1/R2) : déconnexion = révocation côté Google + purge
 * des tokens (statut `revoked`) + PURGE COMPLÈTE des fils/messages
 * synchronisés (exigence R2 #7687 : plus aucun corps de message en base
 * après déconnexion — une reconnexion repart d'une full sync propre).
 *
 * Extrait de `CommunicationIntegrationController::destroy` (BOS-024e,
 * #8216). La Policy `delete` et la garde tenant (404 cross-tenant avant
 * la policy) restent au niveau interface ; l'audit (sans PII sensible)
 * est injecté via `LogManager` (garde #6568 — pas de facades ici).
 */
final class RevokeIntegrationAction
{
    public function __construct(
        private readonly GoogleGmailOAuthService $google,
        private readonly GoogleGmailSyncService $sync,
        private readonly LogManager $logs,
    ) {}

    public function execute(CommunicationIntegration $integration): CommunicationIntegration
    {
        $this->google->revoke($integration);

        // Purge R2 : threads + messages (corps chiffrés compris) + curseurs
        // de sync.
        $this->sync->purge($integration);

        $this->logs->channel('audit')->info('communication.google.revoked', [
            'company_id' => $integration->company_id,
            'employee_id' => $integration->employee_id,
            'integration_id' => $integration->id,
        ]);

        return $integration->refresh();
    }
}
