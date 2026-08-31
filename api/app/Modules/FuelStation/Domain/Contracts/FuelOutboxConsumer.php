<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Contracts;

/**
 * Contrat de consommation d'un événement d'outbox FuelStation
 * (FUEL-015/019).
 *
 * Le consommateur applique l'effet métier de façon IDEMPOTENTE (le rejeu ne
 * produit jamais de doublon). Une erreur transitoire (Throwable) déclenche
 * un retry avec backoff borné ; une erreur permanente doit lever
 * PermanentFuelOutboxException (dead-letter immédiate).
 * Contrat des consommateurs d'événements d'outbox FuelStation
 * (FUEL-015, #5809).
 *
 * Un consommateur traite UN type d'événement de façon IDEMPOTENTE : rejouer
 * le même événement ne doit produire aucun effet dupliqué. Erreurs :
 * `TransientOutboxException` → retry avec backoff ; `PermanentOutboxException`
 * → dead-letter immédiat. Le flux opérationnel FuelStation n'est jamais
 * bloqué par un consommateur (isolation de l'échec).
 * Contrat de consommation d'un événement d'outbox FuelStation (FUEL-015,
 * #5809).
 *
 * Le consommateur applique l'effet métier de façon IDEMPOTENTE (rejeu sans
 * doublon) et distingue :
 *  - erreur transitoire → {@see \App\Modules\FuelStation\Domain\Exceptions\TransientFuelOutboxException}
 *    (retry avec backoff) ;
 *  - erreur permanente → {@see \App\Modules\FuelStation\Domain\Exceptions\PermanentFuelOutboxException}
 *    (dead-letter immédiate).
 */
interface FuelOutboxConsumer
{
    public function supports(string $eventType): bool;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): void;
}
