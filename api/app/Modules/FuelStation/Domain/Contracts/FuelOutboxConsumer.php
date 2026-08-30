<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Contracts;

/**
 * Contrat des consommateurs d'événements d'outbox FuelStation
 * (FUEL-015, #5809).
 *
 * Un consommateur traite UN type d'événement de façon IDEMPOTENTE : rejouer
 * le même événement ne doit produire aucun effet dupliqué. Erreurs :
 * `TransientOutboxException` → retry avec backoff ; `PermanentOutboxException`
 * → dead-letter immédiat. Le flux opérationnel FuelStation n'est jamais
 * bloqué par un consommateur (isolation de l'échec).
 */
interface FuelOutboxConsumer
{
    public function supports(string $eventType): bool;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): void;
}
