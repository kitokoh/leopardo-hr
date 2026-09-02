<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Infrastructure\Services;

use App\Modules\RestaurantManager\Domain\Contracts\RestaurantOutboxConsumer;

/**
 * RESTO-806 (#6227) — Registre des consommateurs d'événements d'outbox
 * RestaurantManager (miroir CrmOutboxConsumerRegistry #5741).
 *
 * Chaque événement est routé vers UN consommateur (le premier dont
 * `supports()` répond true).
 */
final class RestaurantOutboxConsumerRegistry
{
    /** @var list<RestaurantOutboxConsumer> */
    private array $consumers = [];

    public function register(RestaurantOutboxConsumer $consumer): void
    {
        $this->consumers[] = $consumer;
    }

    public function consumerFor(string $eventType): ?RestaurantOutboxConsumer
    {
        foreach ($this->consumers as $consumer) {
            if ($consumer->supports($eventType)) {
                return $consumer;
            }
        }

        return null;
    }
}
