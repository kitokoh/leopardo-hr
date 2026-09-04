<?php

declare(strict_types=1);

namespace App\Modules\Platform\Domain\Contracts;

/**
 * #5866 — Contrat de consommation d'un événement d'outbox plateforme (MAT-008).
 *
 * Le consommateur applique l'effet métier de façon IDEMPOTENTE (il vérifie
 * l'état déjà traité avant d'appliquer — le rejeu ne produit jamais de
 * doublon). Il distingue :
 *  - erreur transitoire → {@see \App\Modules\Platform\Domain\Exceptions\TransientOutboxException}
 *    (retry avec backoff) ;
 *  - erreur permanente → {@see \App\Modules\Platform\Domain\Exceptions\PermanentOutboxException}
 *    (dead-letter immédiate).
 */
interface PlatformOutboxConsumer
{
    public function supports(string $eventType): bool;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): void;
}
