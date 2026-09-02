<<<<<<< HEAD
<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Infrastructure\Services;

use App\Modules\FuelStation\Domain\Contracts\FuelOutboxConsumer;

/**
 * Registre des consommateurs d'événements d'outbox FuelStation.
 *
 * Chaque événement est routé vers UN consommateur (le premier dont
 * `supports()` répond true) — même pattern que le registre CRM (#5741).
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
||||||| 5d41e8337
=======
<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Infrastructure\Services;

use App\Modules\FuelStation\Domain\Contracts\FuelOutboxConsumer;

/**
 * FUEL-015 (#5809) — Registre des consommateurs d'événements d'outbox
 * FuelStation.
 *
 * Chaque événement est routé vers UN consommateur (le premier dont
 * `supports()` répond true). Les consommateurs concrets du contrat
 * Accounting (écritures de synthèse, rapprochement) et des notifications
 * (FUEL-019) s'enregistrent ici via le FuelStationServiceProvider.
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
>>>>>>> origin/bc/bc15-fuel-operations
