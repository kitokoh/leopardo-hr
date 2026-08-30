<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Domain\Contracts;

use App\Modules\EduManager\Domain\Models\EduOutboxEvent;

/**
 * #5832 (EDU-016) — consommateur d'événements d'outbox EduManager.
 *
 * Chaque adaptateur (Accounting, CRM client, Notification…) implémente ce
 * contrat et se déclare dans EduOutboxConsumerRegistry. `supports()` route
 * l'événement vers SON consommateur ; `handle()` est exécuté dans le
 * contexte tenant du `company_id` de l'événement (idempotent — l'outbox
 * garantit zéro doublon via (company_id, idempotency_key)).
 */
interface EduOutboxConsumer
{
    public function supports(string $eventType): bool;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(EduOutboxEvent $event, array $payload): void;
}
