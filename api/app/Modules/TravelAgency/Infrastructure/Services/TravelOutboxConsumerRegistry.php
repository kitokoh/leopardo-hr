<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Infrastructure\Services;

use App\Modules\TravelAgency\Domain\Contracts\TravelOutboxConsumer;

/**
 * #6066 (TRAVEL-414) — Registre des consommateurs d'événements d'outbox
 * TravelAgency.
 *
 * Chaque événement est routé vers UN consommateur (le premier dont
 * `supports()` répond true). Les consommateurs métier (BC-13 COMMS,
 * Accounting, CRM, Documents) arrivent avec les issues TRAVEL-415..418 ;
 * le registre est prêt à les accueillir.
 * TRAVEL-414 (#6066) — Registre des consommateurs d'événements d'outbox
 * TravelAgency.
 *
 * Chaque événement est routé vers TOUS les consommateurs dont `supports()`
 * répond true (multi-consommation : webhooks TRAVEL-806, notifications
 * TRAVEL-415, Accounting TRAVEL-417…). Chaque consommateur applique son
 * effet de façon idempotente (rejeu sûr).
 * Miroir du pattern `CrmOutboxConsumerRegistry` (#5741) : chaque événement
 * est routé vers UN consommateur (le premier dont `supports()` répond true).
 * Les consommateurs concrets (notifications BC-13, synthèse Accounting,
 * lead CRM…) sont enregistrés par leur propre issue (#6067/#6069/#6068).
 * TRAVEL-414 (#6066) — registre des consommateurs d'outbox TravelAgency.
 *
 * Chaque événement est routé vers UN consommateur (le premier dont
 * `supports()` répond true). Les adaptateurs concrets (Notifications BC-13
 * TRAVEL-415, CRM client TRAVEL-416, Accounting TRAVEL-417) s'enregistrent
 * ici ; le registre est prêt à les accueillir au fil des lots.
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
