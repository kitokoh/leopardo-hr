<?php

declare(strict_types=1);

namespace App\Modules\CRM\Infrastructure\Services;

use App\Modules\CRM\Domain\Contracts\CrmOutboxConsumer;

/**
 * #5741 — Registre des consommateurs d'événements d'outbox CRM.
 *
 * Chaque événement est routé vers UN consommateur (le premier dont
 * `supports()` répond true). Les consommateurs concrets du CRM (canaux
 * WhatsApp/email/SMS, automatisations…) arrivent avec les issues V1
 * (#5725→#5728) ; le registre est prêt à les accueillir.
 */
final class CrmOutboxConsumerRegistry
{
    /** @var list<CrmOutboxConsumer> */
    private array $consumers = [];

    public function register(CrmOutboxConsumer $consumer): void
    {
        $this->consumers[] = $consumer;
    }

    public function consumerFor(string $eventType): ?CrmOutboxConsumer
    {
        foreach ($this->consumers as $consumer) {
            if ($consumer->supports($eventType)) {
                return $consumer;
            }
        }

        return null;
    }
}
