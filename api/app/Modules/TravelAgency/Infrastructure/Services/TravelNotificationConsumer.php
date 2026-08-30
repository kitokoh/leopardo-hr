<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Infrastructure\Services;

use App\Modules\TravelAgency\Domain\Contracts\TravelOutboxConsumer;
use App\Modules\TravelAgency\Domain\Models\TravelOutboxEvent;

/**
 * TRAVEL-415 (#6067) — consommateur outbox → notifications voyageur.
 *
 * Traduit les événements travel (confirmé/annulé/payé/remboursé/billet) en
 * demandes de notification BC-13, sous réserve de consentement et de canal
 * configuré (TravelNotificationService). Idempotent : l'outbox garantit
 * zéro doublon (company_id, idempotency_key) ; le rejeu d'un événement
 * déjà `published` est ignoré par le dispatch.
 */
final class TravelNotificationConsumer implements TravelOutboxConsumer
{
    public function __construct(private readonly TravelNotificationService $notifications)
    {
    }

    public function supports(string $eventType): bool
    {
        return in_array($eventType, TravelNotificationService::SUPPORTED_EVENTS, true);
    }

    /**
     * @param  array<mixed>  $payload
     */
    public function handle(TravelOutboxEvent $event, array $payload): void
    {
        $this->notifications->notify((string) $event->company_id, (string) $event->event_type, $payload);
    }
}
