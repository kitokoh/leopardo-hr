<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Contracts;

/**
 * TRAVEL-414 (#6066) — Contrat de consommation d'un événement d'outbox
 * TravelAgency.
 *
 * Le consommateur applique l'effet métier de façon IDEMPOTENTE (le rejeu
 * ne produit jamais de doublon) et distingue erreur transitoire
 * (retry/backoff) d'erreur permanente (dead-letter).
 */
interface TravelOutboxConsumer
{
    public function supports(string $eventType): bool;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): void;
}
