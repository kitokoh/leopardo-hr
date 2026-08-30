<?php

declare(strict_types=1);

namespace App\Core\Outbox\Domain\Contracts;

use App\Core\Outbox\Domain\Models\OutboxEvent;

/**
 * MAT-008 (#5866) — Consommateur d'événements d'outbox générique.
 *
 * Un consommateur déclare les types d'événements qu'il supporte et traite
 * chaque événement de façon IDEMPOTENTE (le dispatcher garantit l'exécution
 * unique par lease, mais un replay manuel peut re-exécuter un événement).
 *
 * Erreurs :
 *  - {@see TransientOutboxException} → retry avec backoff ;
 *  - {@see PermanentOutboxException} → dead-letter immédiat.
 */
interface OutboxConsumer
{
    public function supports(string $eventType): bool;

    /**
     * @throws \App\Core\Outbox\Domain\Exceptions\TransientOutboxException
     * @throws \App\Core\Outbox\Domain\Exceptions\PermanentOutboxException
     */
    public function handle(OutboxEvent $event): void;
}
