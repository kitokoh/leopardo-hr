<?php

declare(strict_types=1);

namespace App\Modules\CRM\Infrastructure\Services;

use App\Core\Outbox\Infrastructure\DatabaseOutboxPublisher;
use App\Modules\CRM\Domain\Models\CrmOutboxEvent;
use DateTimeInterface;

/**
 * #5741 / #8209 — Publication d'evenements dans l'outbox CRM.
 *
 * A appeler au moment de l'evenement metier : l'effet est d'abord persiste,
 * puis consomme de facon asynchrone et idempotente.
 *
 * #8209 — la mecanique (savepoint englobant, resolution de schema F-17,
 * dedoublonnage puis INSERT en savepoint) vit desormais dans
 * `DatabaseOutboxPublisher` : cette classe n'en garde que la surface publique
 * propre a la verticale. La signature est inchangee, donc aucun appelant ne
 * bouge ; ce qui change est le comportement en cas d'echec — un echec SQL
 * d'outbox n'avorte plus la transaction de l'appelant (25P02, #6958).
 */
final class CrmOutboxPublisher
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
    ): CrmOutboxEvent {
        /** @var CrmOutboxEvent $event */
        $event = $this->outbox->publish(
            CrmOutboxEvent::class,
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
