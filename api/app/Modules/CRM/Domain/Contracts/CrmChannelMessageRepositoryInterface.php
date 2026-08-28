<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Contracts;

use App\Modules\CRM\Domain\Models\CrmChannelMessage;
use Illuminate\Support\Carbon;

/**
 * Port de persistance des messages de canal CRM (issue #5725).
 */
interface CrmChannelMessageRepositoryInterface
{
    /**
     * Retrouve un message par (company_id, provider_message_id) — clé
     * d'idempotence des webhooks fournisseur.
     */
    public function findByProviderMessageId(string $companyId, string $providerMessageId): ?CrmChannelMessage;

    /**
     * Persiste un message outbound avant envoi (status queued).
     */
    public function createOutbound(string $channelId, string $provider, ?string $toAddress, ?string $body, ?string $templateName, int $maxAttempts): CrmChannelMessage;

    /**
     * Persiste un message inbound reçu par webhook.
     */
    public function createInbound(string $channelId, ?string $conversationId, string $provider, string $providerMessageId, ?string $fromAddress, ?string $body): CrmChannelMessage;

    public function markSent(string $messageId, string $providerMessageId, ?float $cost): void;

    /**
     * Applique une mise à jour de statut de livraison (webhook delivery).
     */
    public function applyDeliveryStatus(string $companyId, string $providerMessageId, string $status, ?Carbon $at): ?CrmChannelMessage;

    /**
     * Incrémente les tentatives ; retourne le message mis à jour.
     */
    public function incrementAttempts(string $messageId): CrmChannelMessage;

    /**
     * Passe un message en dead-letter (état terminal, plus aucun retry).
     */
    public function deadLetter(string $messageId, string $errorCode, string $errorMessage): void;

    /**
     * Marque un échec retryable (statut failed, tentative comptée).
     */
    public function markFailedRetryable(string $messageId, string $errorCode, string $errorMessage): void;
}
