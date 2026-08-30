<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Infrastructure\Services;

use App\Modules\TravelAgency\Domain\Contracts\TravelOutboxConsumer;

/**
 * #6066 (TRAVEL-414) — Registre des consommateurs d'événements d'outbox
 * TravelAgency.
 *
 * Miroir du pattern `CrmOutboxConsumerRegistry` (#5741) : chaque événement
 * est routé vers UN consommateur (le premier dont `supports()` répond true).
 * Les consommateurs concrets (notifications BC-13, synthèse Accounting,
 * lead CRM…) sont enregistrés par leur propre issue (#6067/#6069/#6068).
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
