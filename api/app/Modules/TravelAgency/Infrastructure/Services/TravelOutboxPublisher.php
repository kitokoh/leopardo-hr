<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Infrastructure\Services;

use App\Core\Outbox\Contracts\OutboxPublisher;
use App\Modules\TravelAgency\Domain\Models\TravelOutboxEvent;
use DateTimeInterface;

/**
 * TRAVEL-310 (#6040) / #8209 — Publication d'evenements dans l'outbox
 * TravelAgency.
 *
 * A appeler au moment de l'evenement metier : l'effet est d'abord persiste,
 * puis consomme de facon asynchrone et idempotente.
 *
 * #8209 — la mecanique vit desormais dans `DatabaseOutboxPublisher` ; la
 * signature publique est inchangee. Particularite conservee : cette outbox
 * nomme sa colonne de charge `payload_redacted` (minimisation PII) et ne
 * porte pas d'agregats — le noyau ecrit les colonnes declarees par le modele,
 * il n'impose donc pas `payload` et ne perd pas la garantie de minimisation.
 */
final class TravelOutboxPublisher
{
    public function __construct(
        private readonly OutboxPublisher $outbox,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function publish(
        string $companyId,
        string $eventType,
        array $payload,
        ?string $idempotencyKey = null,
        ?DateTimeInterface $availableAt = null,
    ): TravelOutboxEvent {
        /** @var TravelOutboxEvent $event */
        $event = $this->outbox->publish(
            TravelOutboxEvent::class,
            $companyId,
            $eventType,
            $payload,
            $idempotencyKey,
            null,
            null,
            $availableAt,
        );

        return $event;
    }
}
