<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Contracts;

use App\Modules\RestaurantManager\Domain\Models\RestaurantOutboxEvent;

/**
 * #6211 (RESTO-606) — Consommateur d'événement d'outbox RestaurantManager
 * (pattern CrmOutboxConsumer #5741).
 *
 * Un consommateur déclare les types d'événements qu'il sait traiter
 * (`supports`) et reçoit le payload redigé de l'événement dans le contexte
 * tenant du `company_id` porté par l'événement (résolu par le dispatcher).
 * Les consommateurs doivent être idempotents : le dispatcher peut rejouer un
 * événement (retry avec backoff) et la base déduplique les effets sensibles
 * (contrainte unique) — cf. RESTO-606 « points crédités une seule fois ».
 */
interface RestaurantOutboxConsumer
{
    public function supports(string $eventType): bool;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): void;
}
