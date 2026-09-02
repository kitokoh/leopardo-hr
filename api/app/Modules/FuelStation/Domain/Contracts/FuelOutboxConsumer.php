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
 */
interface FuelOutboxConsumer
{
    public function supports(string $eventType): bool;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): void;
}
