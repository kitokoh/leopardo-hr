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
 */
interface TravelOutboxConsumer
{
    public function supports(string $eventType): bool;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(string $eventType, array $payload): void;
}
