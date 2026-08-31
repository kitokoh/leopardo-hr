<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Contracts;

use App\Modules\RestaurantManager\Domain\Models\RestaurantOutboxEvent;

/**
 * #6211 (RESTO-606) — Consommateur d'événement d'outbox RestaurantManager
/**
 * RESTO-808 (#6229) — Consommateur d'événement d'outbox RestaurantManager
 * (pattern CrmOutboxConsumer #5741).
 *
 * Un consommateur déclare les types d'événements qu'il sait traiter
 * (`supports`) et reçoit le payload redigé de l'événement dans le contexte
 * tenant du `company_id` porté par l'événement (résolu par le dispatcher).
 * Les consommateurs doivent être idempotents : le dispatcher peut rejouer un
 * événement (retry avec backoff) et la base déduplique les effets sensibles
 * (contrainte unique) — cf. RESTO-606 « points crédités une seule fois ».
/**
 * RESTO-806 (#6227) — Contrat de consommation d'un événement d'outbox
 * RestaurantManager (miroir CrmOutboxConsumer #5741).
 *
 * Le consommateur applique l'effet métier de façon IDEMPOTENTE (le rejeu ne
 * produit jamais de doublon) et distingue :
 *  - erreur transitoire  → Throwable générique (retry avec backoff) ;
 *  - erreur permanente   → une exception dédiée menant à la dead-letter.
 * tenant du `company_id` porté par l'événement (résolu par le dispatcher
 * `restaurant:outbox-dispatch`). Les consommateurs doivent être idempotents :
 * le dispatcher peut rejouer un événement (retry avec backoff) — les effets
 * sensibles sont dédupliqués par la base et les notifications par le
 * CommunicationService (BC-13, préférences + heures calmes + quotas).
 */
interface RestaurantOutboxConsumer
{
    public function supports(string $eventType): bool;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): void;
}
