<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Infrastructure\Services;

use App\Modules\FuelStation\Domain\Contracts\FuelOutboxConsumer;

/**
 * Registre des consommateurs d'événements d'outbox FuelStation (FUEL-015,
 * #5809).
 *
 * Chaque événement est routé vers UN consommateur (le premier dont
 * `supports()` répond true). Les consommateurs concrets (Accounting,
 * intégrations tierces) s'enregistrent ici ; le contrat est prêt à les
 * accueillir sans modification du flux métier.
 */
final class FuelOutboxConsumerRegistry
{
    /** @var list<FuelOutboxConsumer> */
    private array $consumers = [];

    public function register(FuelOutboxConsumer $consumer): void
    {
        $this->consumers[] = $consumer;
    }

    public function consumerFor(string $eventType): ?FuelOutboxConsumer
    {
        foreach ($this->consumers as $consumer) {
            if ($consumer->supports($eventType)) {
                return $consumer;
            }
        }

        return null;
    }
}
