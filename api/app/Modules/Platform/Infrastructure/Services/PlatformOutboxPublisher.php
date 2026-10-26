<?php

declare(strict_types=1);

namespace App\Modules\Platform\Infrastructure\Services;

use App\Core\Outbox\Infrastructure\DatabaseOutboxPublisher;
use App\Modules\Platform\Domain\Models\PlatformOutboxEvent;
use DateTimeInterface;

/**
 * #5866 / #8209 — Publication d'evenements dans l'outbox plateforme (MAT-008).
 *
 * A appeler au moment de l'evenement metier (listener synchrone) : l'effet
 * est d'abord persiste, puis consomme de facon asynchrone et idempotente.
 *
 * #8209 — cette classe etait la SOURCE de la mecanique desormais partagee :
 * savepoint englobant (la publication ne peut pas empoisonner la transaction
 * de l'appelant) et resolution explicite du schema (garde F-17). Les deux
 * garanties vivent maintenant dans `DatabaseOutboxPublisher` ; cette classe
 * n'en garde que la surface publique, inchangee pour les appelants.
 */
final class PlatformOutboxPublisher
{
    public function __construct(
        private readonly DatabaseOutboxPublisher $outbox = new DatabaseOutboxPublisher,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function publish(
        string $companyId,
        string $eventType,
        array $payload,
        ?string $idempotencyKey = null,
        ?string $aggregateType = null,
        ?string $aggregateId = null,
        ?DateTimeInterface $availableAt = null,
    ): PlatformOutboxEvent {
        /** @var PlatformOutboxEvent $event */
        $event = $this->outbox->publish(
            PlatformOutboxEvent::class,
            $companyId,
            $eventType,
            $payload,
            $idempotencyKey,
            $aggregateType,
            $aggregateId,
            $availableAt,
        );

        return $event;
    }
}
