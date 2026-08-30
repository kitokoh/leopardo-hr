<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Contracts;

/**
 * RESTO-806 (#6227) — Contrat de consommation d'un événement d'outbox
 * RestaurantManager (miroir CrmOutboxConsumer #5741).
 *
 * Le consommateur applique l'effet métier de façon IDEMPOTENTE (le rejeu ne
 * produit jamais de doublon) et distingue :
 *  - erreur transitoire  → Throwable générique (retry avec backoff) ;
 *  - erreur permanente   → une exception dédiée menant à la dead-letter.
 */
interface RestaurantOutboxConsumer
{
    public function supports(string $eventType): bool;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): void;
}
