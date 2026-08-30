<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Infrastructure\Services;

use App\Modules\TravelAgency\Domain\Contracts\TravelOutboxConsumer;

/**
 * TRAVEL-414 (#6066) — Registre des consommateurs d'événements d'outbox
 * TravelAgency.
 *
 * Chaque événement est routé vers TOUS les consommateurs dont `supports()`
 * répond true (multi-consommation : webhooks TRAVEL-806, notifications
 * TRAVEL-415, Accounting TRAVEL-417…). Chaque consommateur applique son
 * effet de façon idempotente (rejeu sûr).
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
        return $this->consumersFor($eventType)[0] ?? null;
    }

    /**
     * Tous les consommateurs enregistrés qui supportent l'événement
     * (multi-consommation : webhooks + notifications + Accounting…).
     *
     * @return list<TravelOutboxConsumer>
     */
    public function consumersFor(string $eventType): array
    {
        $matched = [];

        foreach ($this->consumers as $consumer) {
            if ($consumer->supports($eventType)) {
                $matched[] = $consumer;
            }
        }

        return $matched;
    }
}
