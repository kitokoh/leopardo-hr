<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Contracts;

/**
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
