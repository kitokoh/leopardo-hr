<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Infrastructure\Services;

use App\Core\Outbox\Contracts\OutboxPublisher;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOutboxEvent;
use DateTimeInterface;

/**
 * RESTO-404 (#6191) / #8209 — Publication d'evenements dans l'outbox
 * RestaurantManager.
 *
 * A appeler au moment de l'evenement metier : l'effet est d'abord persiste,
 * puis consomme de facon asynchrone et idempotente.
 *
 * #8209 — la mecanique (savepoint englobant, resolution de schema F-17) vit
 * desormais dans `DatabaseOutboxPublisher` ; la signature publique est
 * inchangee. Cette outbox nomme sa colonne de charge `payload_redacted` : le
 * noyau ecrit les colonnes declarees par le modele, la minimisation PII est
 * donc preservee.
 */
final class RestaurantOutboxPublisher
{
    public function __construct(
        private readonly OutboxPublisher $outbox,
    ) {}

    /**
     * @param  array<string, mixed>  $payload  payload redige (aucune PII)
     */
    public function publish(
        string $companyId,
        string $eventType,
        array $payload,
        ?string $idempotencyKey = null,
        ?DateTimeInterface $availableAt = null,
    ): RestaurantOutboxEvent {
        /** @var RestaurantOutboxEvent $event */
        $event = $this->outbox->publish(
            RestaurantOutboxEvent::class,
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
