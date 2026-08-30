<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Infrastructure\Services;

use App\Modules\TravelAgency\Domain\Contracts\TravelOutboxConsumer;

/**
 * TRAVEL-414 (#6066) — Registre des consommateurs d'événements d'outbox
 * TravelAgency.
 *
 * Chaque événement est routé vers UN consommateur (le premier dont
 * `supports()` répond true). Les consommateurs concrets arrivent avec les
 * lots suivants (notifications TRAVEL-415, CRM TRAVEL-416, Accounting
 * TRAVEL-417) ; le registre est prêt à les accueillir.
 */
final class TravelOutboxConsumerRegistry
{
    /** @var list<TravelOutboxConsumer> */
    private array $consumers = [];

    public function register(TravelOutboxConsumer $consumer): void
    {
        $this->consumers[] = $consumer;
    }

    public function consumerFor(string $eventType): ?TravelOutboxConsumer
    {
        foreach ($this->consumers as $consumer) {
            if ($consumer->supports($eventType)) {
                return $consumer;
            }
        }

        return null;
    }
}
