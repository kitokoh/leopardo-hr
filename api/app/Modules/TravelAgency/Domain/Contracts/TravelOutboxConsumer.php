<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Contracts;

use App\Modules\TravelAgency\Domain\Exceptions\PermanentOutboxException;
use App\Modules\TravelAgency\Domain\Exceptions\TransientOutboxException;
use App\Modules\TravelAgency\Domain\Exceptions\PermanentTravelOutboxException;
use App\Modules\TravelAgency\Domain\Exceptions\TransientTravelOutboxException;
use App\Modules\TravelAgency\Domain\Models\TravelOutboxEvent;

/**
 * #6066 (TRAVEL-414) — Contrat de consommation d'un événement d'outbox
 * TravelAgency.
 *
 * Miroir du pattern `CrmOutboxConsumer` (#5741) : le consommateur applique
 * l'effet métier de façon IDEMPOTENTE (il vérifie l'état déjà traité avant
 * d'appliquer — le rejeu ne produit jamais de doublon). Il distingue :
 *  - erreur transitoire  → {@see TransientOutboxException}
 *    (retry avec backoff) ;
 *  - erreur permanente   → {@see PermanentOutboxException}
 *    (dead-letter immédiate).
 */
interface TravelOutboxConsumer
{
    public function supports(string $eventType): bool;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(string $eventType, array $payload): void;






}