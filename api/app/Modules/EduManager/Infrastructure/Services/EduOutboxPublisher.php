<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Infrastructure\Services;

use App\Core\Outbox\Infrastructure\DatabaseOutboxPublisher;
use App\Modules\EduManager\Domain\Models\EduOutboxEvent;
use DateTimeInterface;

/**
 * Issue #5832 (EDU-016) / #8209 — Publication d'evenements dans l'outbox
 * EduManager.
 *
 * A appeler au moment de l'evenement metier : l'effet est d'abord persiste,
 * puis consomme de facon asynchrone et idempotente par `edu:outbox-dispatch`.
 *
 * #8209 — la mecanique (savepoint englobant, resolution de schema F-17) vit
 * desormais dans `DatabaseOutboxPublisher` ; la signature publique est
 * inchangee. Cette verticale n'emet pas d'agregats : le noyau n'ecrit que les
 * colonnes que `EduOutboxEvent` declare.
 */
final class EduOutboxPublisher
{
    public function __construct(
        private readonly DatabaseOutboxPublisher $outbox = new DatabaseOutboxPublisher,
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
    ): EduOutboxEvent {
        /** @var EduOutboxEvent $event */
        $event = $this->outbox->publish(
            EduOutboxEvent::class,
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
