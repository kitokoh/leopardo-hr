<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Infrastructure\Services;

use App\Modules\RestaurantManager\Domain\Contracts\RestaurantOutboxConsumer;

/**
 * RESTO-808 (#6229) — Registre des consommateurs d'outbox RestaurantManager
 * (pattern CrmOutboxConsumerRegistry #5741).
 *
 * Chaque type d'événement est routé vers UN consommateur (le premier dont
 * `supports()` répond true). Un événement sans consommateur est mis en
 * dead-letter par le dispatcher (`restaurant:outbox-dispatch`).
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
