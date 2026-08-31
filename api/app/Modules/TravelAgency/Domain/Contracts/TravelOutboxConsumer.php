<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Contracts;

/**
 * #6066 (TRAVEL-414) — Contrat de consommation d'un événement d'outbox
 * TravelAgency.
 *
 * Miroir du contrat `CrmOutboxConsumer` (#5741) : le consommateur applique
 * l'effet métier de façon IDEMPOTENTE (le rejeu ne produit jamais de
 * doublon). Il distingue :
 *  - erreur transitoire → {@see \App\Modules\TravelAgency\Domain\Exceptions\TransientTravelOutboxException}
 *    (retry avec backoff) ;
 *  - erreur permanente → {@see \App\Modules\TravelAgency\Domain\Exceptions\PermanentTravelOutboxException}
 *    (dead-letter immédiate).
 *
 * La commande `travel:outbox-dispatch` exécute `handle()` DANS le contexte
 * du tenant de l'événement (`TenantManager::withinTenant`) : le consommateur
 * n'a jamais à résoudre lui-même sa compagnie — un événement cross-tenant
 * est structurellement refusé.
 * TRAVEL-414 (#6066) — Contrat de consommation d'un événement d'outbox
 * TravelAgency.
 *
 * Le consommateur applique l'effet métier de façon IDEMPOTENTE (le rejeu
 * ne produit jamais de doublon) et distingue erreur transitoire
 * (retry/backoff) d'erreur permanente (dead-letter).
 * Miroir du pattern `CrmOutboxConsumer` (#5741) : le consommateur applique
 * l'effet métier de façon IDEMPOTENTE (il vérifie l'état déjà traité avant
 * d'appliquer — le rejeu ne produit jamais de doublon). Il distingue :
 *  - erreur transitoire  → {@see \App\Modules\TravelAgency\Domain\Exceptions\TransientOutboxException}
 *    (retry avec backoff) ;
 *  - erreur permanente   → {@see \App\Modules\TravelAgency\Domain\Exceptions\PermanentOutboxException}
 *    (dead-letter immédiate).
 */
interface TravelOutboxConsumer
{
    public function supports(string $eventType): bool;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(string $eventType, array $payload): void;
    public function handle(array $payload): void;
}
