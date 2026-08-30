<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Events;

use App\Core\Tenant\Domain\Contracts\TenantScopedEvent;

/**
 * #6066 (TRAVEL-414) — Publication d'un événement de domaine TravelAgency
 * sur le bus d'événements tenant-scopé de la plateforme.
 *
 * Consommé par le worker `travel:outbox-dispatch` APRÈS commit de l'état
 * métier : chaque événement `travel.*.v1` persiste d'abord dans
 * `travel_outbox_events` (idempotence par tenant), puis est dispatché ici
 * dans le contexte de sa compagnie — les BC consommateurs (BC-13 COMMS,
 * Accounting, CRM, Documents — spec §8.4/§8.5) s'abonnent SANS import
 * inter-modules (règle #5584). `payload_redacted` : jamais de secret/PII.
 */
final class TravelEventPublished implements TenantScopedEvent
{
    /**
     * @param  array<string, mixed>  $payloadRedacted
     */
    public function __construct(
        private readonly string $companyId,
        public readonly string $eventType,
        public readonly array $payloadRedacted,
    ) {}

    public function tenantCompanyId(): ?string
    {
        return $this->companyId;
    }
}
