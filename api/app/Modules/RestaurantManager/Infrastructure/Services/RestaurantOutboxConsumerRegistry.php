<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Infrastructure\Services;

use App\Modules\RestaurantManager\Domain\Contracts\RestaurantOutboxConsumer;

/**
 * #6211 (RESTO-606) — Registre des consommateurs d'outbox RestaurantManager
 * (pattern CrmOutboxConsumerRegistry #5741).
 *
 * Chaque type d'événement est routé vers UN consommateur (le premier dont
 * `supports()` répond true). Le registre est alimenté par le service provider
 * du module ; un événement sans consommateur est mis en dead-letter par le
 * dispatcher (`restaurant:outbox-dispatch`).
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
