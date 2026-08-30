<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Contracts;

use App\Modules\TravelAgency\Domain\Models\TravelOutboxEvent;

/**
 * TRAVEL-414 (#6066) — consommateur d'événements d'outbox TravelAgency.
 *
 * Chaque adaptateur (Notifications BC-13, CRM client, Accounting…) implémente
 * ce contrat et se déclare dans TravelOutboxConsumerRegistry. `supports()`
 * route l'événement vers SON consommateur ; `handle()` est exécuté dans le
 * contexte tenant du `company_id` de l'événement (idempotent — l'outbox
 * garantit zéro doublon via (company_id, idempotency_key)).
 */
interface TravelOutboxConsumer
{
    public function supports(string $eventType): bool;

    /**
     * @param  array<mixed>  $payload  payload redigé (aucune PII brute)
     */
    public function handle(TravelOutboxEvent $event, array $payload): void;
}
