<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Infrastructure\Services;

use App\Modules\TravelAgency\Domain\Contracts\TravelOutboxConsumer;

/**
 * TRAVEL-414 (#6066) — registre des consommateurs d'outbox TravelAgency.
 *
 * Chaque événement est routé vers UN consommateur (le premier dont
 * `supports()` répond true). Les adaptateurs concrets (Notifications BC-13
 * TRAVEL-415, CRM client TRAVEL-416, Accounting TRAVEL-417) s'enregistrent
 * ici ; le registre est prêt à les accueillir au fil des lots.
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
