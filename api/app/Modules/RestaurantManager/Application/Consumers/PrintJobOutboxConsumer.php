<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Application\Consumers;

use App\Modules\RestaurantManager\Application\Actions\AckPrintJobAction;
use App\Modules\RestaurantManager\Application\Actions\EnqueueKitchenTicketPrintAction;
use App\Modules\RestaurantManager\Domain\Contracts\RestaurantOutboxConsumer;
use Psr\Log\LoggerInterface;

/**
 * REST-OS-5 (Mission Leopardo Restaurant OS) — Consommateur d'observabilité
 * des événements d'impression thermique.
 *
 * Les événements `restaurant.print.job.*` matérialisent la traçabilité du
 * cycle de vie d'un ticket (mis en file / imprimé / échoué). Sans consommateur
 * enregistré, l'outbox les placerait en dead-letter (`no_consumer`) : ce
 * consommateur les ACQUITTE en journalisant une ligne structurée, sans PII et
 * sans nouveau stockage (l'état de vérité reste `restaurant_print_jobs`).
 *
 * Idempotent par nature : un rejeu réécrit la même ligne de journal.
 */
final class PrintJobOutboxConsumer implements RestaurantOutboxConsumer
{
    public function __construct(
        private readonly LoggerInterface $log,
    ) {}

    public function supports(string $eventType): bool
    {
        return in_array($eventType, [
            EnqueueKitchenTicketPrintAction::EVENT_JOB_ENQUEUED,
            AckPrintJobAction::EVENT_JOB_PRINTED,
            AckPrintJobAction::EVENT_JOB_FAILED,
        ], true);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): void
    {
        $this->log->info('restaurant.print.job', [
            'print_job_id' => $payload['print_job_id'] ?? null,
            'order_id' => $payload['order_id'] ?? null,
            'ticket_kind' => $payload['ticket_kind'] ?? null,
            'revision' => $payload['revision'] ?? null,
            'attempts' => $payload['attempts'] ?? null,
        ]);
    }
}
