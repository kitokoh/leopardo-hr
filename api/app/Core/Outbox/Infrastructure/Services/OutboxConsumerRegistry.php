<?php

declare(strict_types=1);

namespace App\Core\Outbox\Infrastructure\Services;

use App\Core\Outbox\Domain\Contracts\OutboxConsumer;

/**
 * MAT-008 (#5866) — Registre des consommateurs d'outbox générique.
 *
 * Chaque événement est routé vers le PREMIER consommateur dont `supports()`
 * répond true. Un événement sans consommateur est mis en dead-letter
 * (erreur permanente) : jamais de perte silencieuse.
 */
final class OutboxConsumerRegistry
{
    /** @var list<OutboxConsumer> */
    private array $consumers = [];

    public function register(OutboxConsumer $consumer): void
    {
        $this->consumers[] = $consumer;
    }

    public function consumerFor(string $eventType): ?OutboxConsumer
    {
        foreach ($this->consumers as $consumer) {
            if ($consumer->supports($eventType)) {
                return $consumer;
            }
        }

        return null;
    }

    /**
     * @return list<OutboxConsumer>
     */
    public function all(): array
    {
        return $this->consumers;
    }
}
