<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Infrastructure\Services;

use App\Modules\EduManager\Domain\Contracts\EduOutboxConsumer;

/**
 * #5832 (EDU-016) — registre des consommateurs d'événements d'outbox
 * EduManager. Chaque événement est routé vers UN consommateur (le premier
 * dont `supports()` répond true). Les adaptateurs concrets (Accounting,
 * CRM client, Notification) arrivent avec les issues de consommation ; le
 * registre est prêt à les accueillir.
 */
final class EduOutboxConsumerRegistry
{
    /** @var list<EduOutboxConsumer> */
    private array $consumers = [];

    public function register(EduOutboxConsumer $consumer): void
    {
        $this->consumers[] = $consumer;
    }

    public function consumerFor(string $eventType): ?EduOutboxConsumer
    {
        foreach ($this->consumers as $consumer) {
            if ($consumer->supports($eventType)) {
                return $consumer;
            }
        }

        return null;
    }
}
