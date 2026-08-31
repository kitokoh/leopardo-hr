<?php

declare(strict_types=1);

namespace App\Modules\Platform\Infrastructure\Services;

use App\Modules\Platform\Domain\Contracts\PlatformOutboxConsumer;

/**
 * #5866 — Registre des consommateurs d'événements d'outbox plateforme (MAT-008).
 *
 * Chaque événement est routé vers UN consommateur (le premier dont
 * `supports()` répond true). Les consommateurs de production
 * (audit `platform_outbox.*`) sont enregistrés dans
 * {@see \App\Modules\Platform\Providers\PlatformServiceProvider}.
 */
final class PlatformOutboxConsumerRegistry
{
    /** @var list<PlatformOutboxConsumer> */
    private array $consumers = [];

    public function register(PlatformOutboxConsumer $consumer): void
    {
        $this->consumers[] = $consumer;
    }

    public function consumerFor(string $eventType): ?PlatformOutboxConsumer
    {
        foreach ($this->consumers as $consumer) {
            if ($consumer->supports($eventType)) {
                return $consumer;
            }
        }

        return null;
    }
}
