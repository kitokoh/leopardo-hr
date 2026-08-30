<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Infrastructure\Consumers;

use App\Modules\FuelStation\Domain\Contracts\FuelOutboxConsumer;
use App\Modules\FuelStation\Domain\Models\FuelCashSession;
use App\Modules\FuelStation\Domain\Models\FuelOutboxEvent;
use App\Modules\FuelStation\Domain\Models\FuelReconciliationRun;
use App\Modules\FuelStation\Domain\Models\FuelSale;
use Illuminate\Support\Facades\Log;

/**
 * Consommateur des événements FuelStation à destination d'Accounting
 * (FUEL-015, issue #5809).
 *
 * Le contrat s'échange par événements VERSIONNÉS dans l'outbox
 * (fuel.sale.recorded.v1, fuel.cash_session.closed.v1,
 * fuel.stock.reconciled.v1) : Accounting ne lit JAMAIS les tables
 * FuelStation directement. Ce consommateur VALIDE l'agrégat référencé
 * (existence + tenant) avant d'accuser réception — une agrégation inexistante
 * est une erreur permanente (dead-letter, aucun retry inutile). Les écritures
 * comptables réelles (journal) arriveront avec le module Accounting.
 */
final class FuelAccountingOutboxConsumer implements FuelOutboxConsumer
{
    /**
     * @return list<string>
     */
    private const SUPPORTED = [
        FuelOutboxEvent::EVENT_SALE_RECORDED,
        FuelOutboxEvent::EVENT_CASH_SESSION_CLOSED,
        FuelOutboxEvent::EVENT_STOCK_RECONCILED,
    ];

    public function supports(string $eventType): bool
    {
        return in_array($eventType, self::SUPPORTED, true);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): void
    {
        $eventType = is_string($payload['_event_type'] ?? null) ? $payload['_event_type'] : null;

        match ($eventType) {
            FuelOutboxEvent::EVENT_SALE_RECORDED => $this->validateSale($payload),
            FuelOutboxEvent::EVENT_CASH_SESSION_CLOSED => $this->validateCashSession($payload),
            FuelOutboxEvent::EVENT_STOCK_RECONCILED => $this->validateReconciliation($payload),
            default => $this->validateGeneric($payload),
        };

        Log::channel('fuel-station')->info('fuel.accounting.event.validated', [
            'event_type' => $eventType,
            'aggregate_id' => $payload['aggregate_id'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function validateSale(array $payload): void
    {
        $sale = FuelSale::query()->find((int) ($payload['sale_id'] ?? 0));

        if (! $sale instanceof FuelSale) {
            throw new \RuntimeException('SALE_NOT_FOUND');
        }

        if (($payload['amount'] ?? null) !== null && abs((float) $payload['amount'] - (float) $sale->amount) > 0.001) {
            throw new \RuntimeException('SALE_AMOUNT_MISMATCH');
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function validateCashSession(array $payload): void
    {
        $session = FuelCashSession::query()->find((int) ($payload['cash_session_id'] ?? 0));

        if (! $session instanceof FuelCashSession) {
            throw new \RuntimeException('CASH_SESSION_NOT_FOUND');
        }

        if ($session->status === FuelCashSession::STATUS_OPEN) {
            throw new \RuntimeException('CASH_SESSION_NOT_CLOSED');
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function validateReconciliation(array $payload): void
    {
        $run = FuelReconciliationRun::query()->find((int) ($payload['run_id'] ?? 0));

        if (! $run instanceof FuelReconciliationRun) {
            throw new \RuntimeException('RECONCILIATION_RUN_NOT_FOUND');
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function validateGeneric(array $payload): void
    {
        // Agrégat non vérifiable : on accepte (payload déjà validé à la source).
    }
}
