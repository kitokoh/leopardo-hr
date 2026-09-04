<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Infrastructure\Services;

use App\Modules\FuelStation\Domain\Models\FuelCashSession;
use App\Modules\FuelStation\Domain\Models\FuelDelivery;
use App\Modules\FuelStation\Domain\Models\FuelIncident;
use App\Modules\FuelStation\Domain\Models\FuelOutboxEvent;
use App\Modules\FuelStation\Domain\Models\FuelSale;
use App\Modules\FuelStation\Domain\Models\FuelStockReconciliation;

/**
 * Contrat Accounting FuelStation (FUEL-015, #5809).
 *
 * Publie les événements versionnés (agrégats validés, état figé) dans
 * l'outbox — consommés de façon asynchrone et idempotente par le module
 * Accounting (ou toute intégration) SANS accès direct aux tables.
 *
 * Contrat des événements :
 * - `fuel.sale.recorded.v1` : vente enregistrée (montant calculé serveur) ;
 * - `fuel.cash_session.closed.v1` : session de caisse clôturée (attendu,
 *   écart) ;
 * - `fuel.delivery.received.v1` : livraison reçue (quantité mineure) ;
 * - `fuel.stock.reconciled.v1` : rapprochement de stock (variance) ;
 * - `fuel.incident.resolved.v1` : incident résolu.
 *
 * La publication est post-commit et isolée : une erreur d'outbox ne fait
 * jamais échouer le flux opérationnel.
 */
final class FuelAccountingContractPublisher
{
    public function __construct(
        private readonly FuelOutboxPublisher $outbox,
    ) {}

    public function saleRecorded(FuelSale $sale): FuelOutboxEvent
    {
        return $this->outbox->publish(
            companyId: $sale->company_id,
            eventType: FuelOutboxEvent::TYPE_SALE_RECORDED,
            payload: [
                'sale_id' => $sale->id,
                'company_id' => $sale->company_id,
                'station_id' => $sale->station_id,
                'pump_id' => $sale->pump_id,
                'cash_session_id' => $sale->cash_session_id,
                'product' => $sale->product,
                'quantity' => $sale->quantity,
                'unit_price' => $sale->unit_price,
                'amount' => $sale->amount,
                'sale_time' => $sale->sale_time?->toISOString(),
                'source' => $sale->source,
                'external_id' => $sale->external_id,
            ],
            aggregateType: 'fuel_sale',
            aggregateId: $sale->id,
        );
    }

    public function cashSessionClosed(FuelCashSession $session): FuelOutboxEvent
    {
        return $this->outbox->publish(
            companyId: $session->company_id,
            eventType: FuelOutboxEvent::TYPE_CASH_SESSION_CLOSED,
            payload: [
                'session_id' => $session->id,
                'company_id' => $session->company_id,
                'station_id' => $session->station_id,
                'opened_by' => $session->opened_by,
                'opening_balance' => $session->opening_balance,
                'expected_balance' => $session->expected_balance,
                'closing_balance' => $session->closing_balance,
                'variance' => $session->variance,
                'closed_by' => $session->closed_by,
                'closed_at' => $session->closed_at?->toISOString(),
            ],
            aggregateType: 'fuel_cash_session',
            aggregateId: $session->id,
        );
    }

    public function deliveryReceived(FuelDelivery $delivery): FuelOutboxEvent
    {
        return $this->outbox->publish(
            companyId: $delivery->company_id,
            eventType: FuelOutboxEvent::TYPE_DELIVERY_RECEIVED,
            payload: [
                'delivery_id' => $delivery->id,
                'company_id' => $delivery->company_id,
                'station_id' => $delivery->station_id,
                'tank_id' => $delivery->tank_id,
                'product_type' => $delivery->product_type,
                'quantity_minor' => $delivery->quantity_minor,
                'supplier' => $delivery->supplier,
                'reference_number' => $delivery->reference_number,
                'delivered_at' => $delivery->delivered_at?->toISOString(),
            ],
            aggregateType: 'fuel_delivery',
            aggregateId: $delivery->id,
        );
    }

    public function stockReconciled(FuelStockReconciliation $reconciliation): FuelOutboxEvent
    {
        return $this->outbox->publish(
            companyId: $reconciliation->company_id,
            eventType: FuelOutboxEvent::TYPE_STOCK_RECONCILED,
            payload: [
                'reconciliation_id' => $reconciliation->id,
                'company_id' => $reconciliation->company_id,
                'station_id' => $reconciliation->station_id,
                'product_type' => $reconciliation->product_type,
                'period_start' => $reconciliation->period_start?->toDateString(),
                'period_end' => $reconciliation->period_end?->toDateString(),
                'status' => $reconciliation->status,
                'opening_minor' => $reconciliation->opening_minor,
                'delivered_minor' => $reconciliation->delivered_minor,
                'sold_minor' => $reconciliation->sold_minor,
                'metered_delta_minor' => $reconciliation->metered_delta_minor,
                'variance_minor' => $reconciliation->variance_minor,
                'variance_tolerance_minor' => $reconciliation->variance_tolerance_minor,
                'completed_at' => $reconciliation->completed_at?->toISOString(),
            ],
            aggregateType: 'fuel_stock_reconciliation',
            aggregateId: $reconciliation->id,
        );
    }

    public function incidentResolved(FuelIncident $incident): FuelOutboxEvent
    {
        return $this->outbox->publish(
            companyId: $incident->company_id,
            eventType: FuelOutboxEvent::TYPE_INCIDENT_RESOLVED,
            payload: [
                'incident_id' => $incident->id,
                'company_id' => $incident->company_id,
                'station_id' => $incident->station_id,
                'equipment_type' => $incident->equipment_type,
                'equipment_id' => $incident->equipment_id,
                'severity' => $incident->severity,
                'title' => $incident->title,
                'resolution_notes' => $incident->resolution_notes,
                'resolved_at' => $incident->resolved_at?->toISOString(),
            ],
            aggregateType: 'fuel_incident',
            aggregateId: $incident->id,
        );
    }
}
