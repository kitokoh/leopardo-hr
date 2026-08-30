<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\FuelStation\Domain\Events\FuelDeliveryReceived;
use App\Modules\FuelStation\Domain\Events\FuelStockReconciliationCompleted;
use App\Modules\FuelStation\Domain\Events\FuelStockVarianceDetected;
use App\Modules\FuelStation\Domain\Models\FuelDelivery;
use App\Modules\FuelStation\Domain\Models\FuelMeterInterval;
use App\Modules\FuelStation\Domain\Models\FuelMeterRegister;
use App\Modules\FuelStation\Domain\Models\FuelSale;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use App\Modules\FuelStation\Domain\Models\FuelStockMovement;
use App\Modules\FuelStation\Domain\Models\FuelStockReconciliation;
use App\Modules\FuelStation\Domain\Models\FuelTank;
use Illuminate\Support\Carbon;

/**
 * Règles métier des stocks, cuves et rapprochements FuelStation
 * (FUEL-009, issue #5803).
 *
 * - Volumes en unités mineures entières, converties via l'échelle du
 *   compteur (`precision_scale`, défaut 2) — jamais de flottants métier
 *   dans les agrégats de stock.
 * - Livraison rejouable : `idempotency_key` unique par tenant ; un rejeu
 *   retourne la livraison existante (zéro doublon, zéro écriture).
 * - Rapprochement rejouable : un même couple (station, produit, période,
 *   clé) retourne le rapport existant.
 * - Aucun ajustement silencieux : un écart > tolérance clôt le rapport en
 *   `exception` ; la correction passe par un mouvement `adjustment`
 *   explicite et audité (manager).
 */
final class FuelStockService
{
    private const DEFAULT_PRECISION_SCALE = 2;

    /**
     * Tolérance d'écart par défaut : max(50 unités mineures, 0,5 % du
     * stock théorique). Borne basse pour les petits volumes.
     */
    public const DEFAULT_TOLERANCE_RATIO = 0.005;

    public const MIN_TOLERANCE_MINOR = 50;

    /**
     * @param  array{station_id: int, tank_id?: int|null, product_type: string, quantity_minor: int, delivered_at: string, idempotency_key: string, supplier?: string|null, reference_number?: string|null, notes?: string|null}  $data
     *
     * @return array{delivery: FuelDelivery, movement: FuelStockMovement, replayed: bool}
     */
    public function recordDelivery(?Employee $actor, FuelStation $station, array $data): array
    {
        $existing = FuelDelivery::query()
            ->where('company_id', $station->company_id)
            ->where('idempotency_key', $data['idempotency_key'])
            ->first();

        if ($existing instanceof FuelDelivery) {
            $movement = FuelStockMovement::query()
                ->where('company_id', $station->company_id)
                ->where('reference_type', FuelStockMovement::REFERENCE_DELIVERY)
                ->where('reference_id', $existing->id)
                ->first();

            return [
                'delivery' => $existing,
                'movement' => $movement ?? new FuelStockMovement,
                'replayed' => true,
            ];
        }

        /** @var FuelDelivery $delivery */
        $delivery = FuelDelivery::query()->create([
            'company_id' => $station->company_id,
            'station_id' => $station->id,
            'tank_id' => $data['tank_id'] ?? null,
            'product_type' => $data['product_type'],
            'quantity_minor' => $data['quantity_minor'],
            'supplier' => $data['supplier'] ?? null,
            'reference_number' => $data['reference_number'] ?? null,
            'status' => FuelDelivery::STATUS_RECEIVED,
            'delivered_at' => $data['delivered_at'],
            'idempotency_key' => $data['idempotency_key'],
            'received_by' => $actor?->id,
            'created_by' => $actor?->id,
            'notes' => $data['notes'] ?? null,
        ]);

        /** @var FuelStockMovement $movement */
        $movement = FuelStockMovement::query()->create([
            'company_id' => $station->company_id,
            'station_id' => $station->id,
            'tank_id' => $data['tank_id'] ?? null,
            'product_type' => $data['product_type'],
            'quantity_minor' => $data['quantity_minor'],
            'direction' => FuelStockMovement::DIRECTION_IN,
            'reason' => FuelStockMovement::REASON_DELIVERY,
            'reference_type' => FuelStockMovement::REFERENCE_DELIVERY,
            'reference_id' => $delivery->id,
            'movement_at' => $data['delivered_at'],
            'idempotency_key' => $data['idempotency_key'],
            'created_by' => $actor?->id,
        ]);

        FuelDeliveryReceived::dispatch($delivery);

        return [
            'delivery' => $delivery,
            'movement' => $movement,
            'replayed' => false,
        ];
    }

    public function verifyDelivery(?Employee $actor, FuelDelivery $delivery): FuelDelivery
    {
        if ($delivery->status === FuelDelivery::STATUS_VERIFIED) {
            return $delivery;
        }

        $delivery->update([
            'status' => FuelDelivery::STATUS_VERIFIED,
            'verified_by' => $actor?->id,
            'verified_at' => now(),
        ]);

        return $delivery->refresh();
    }

    /**
     * Ajustement explicite et audité (jamais silencieux) : mouvement
     * `out|adjustment` (ou `in|adjustment`) avec motif obligatoire.
     *
     * @param  array{product_type: string, quantity_minor: int, direction: string, reason: string, movement_at: string, idempotency_key: string, tank_id?: int|null, notes?: string|null}  $data
     *
     * @return array{movement: FuelStockMovement, replayed: bool}
     */
    public function recordAdjustment(?Employee $actor, FuelStation $station, array $data): array
    {
        $existing = FuelStockMovement::query()
            ->where('company_id', $station->company_id)
            ->where('idempotency_key', $data['idempotency_key'])
            ->first();

        if ($existing instanceof FuelStockMovement) {
            return ['movement' => $existing, 'replayed' => true];
        }

        /** @var FuelStockMovement $movement */
        $movement = FuelStockMovement::query()->create([
            'company_id' => $station->company_id,
            'station_id' => $station->id,
            'tank_id' => $data['tank_id'] ?? null,
            'product_type' => $data['product_type'],
            'quantity_minor' => $data['quantity_minor'],
            'direction' => $data['direction'],
            'reason' => FuelStockMovement::REASON_ADJUSTMENT,
            'reference_type' => FuelStockMovement::REFERENCE_RECONCILIATION,
            'movement_at' => $data['movement_at'],
            'idempotency_key' => $data['idempotency_key'],
            'created_by' => $actor?->id,
            'notes' => $data['notes'] ?? null,
        ]);

        return ['movement' => $movement, 'replayed' => false];
    }

    /**
     * Exécute (ou rejoue) le rapprochement compteurs ↔ ventes ↔ stock d'une
     * station pour un produit et une période.
     *
     * @param  array{product_type: string, period_start: string, period_end: string, idempotency_key: string, measured_close_minor?: int|null, tolerance_minor?: int|null}  $data
     */
    public function runReconciliation(
        ?Employee $actor,
        FuelStation $station,
        array $data,
    ): FuelStockReconciliation {
        $existing = FuelStockReconciliation::query()
            ->where('company_id', $station->company_id)
            ->where('station_id', $station->id)
            ->where('product_type', $data['product_type'])
            ->where('period_start', $data['period_start'])
            ->where('period_end', $data['period_end'])
            ->where('idempotency_key', $data['idempotency_key'])
            ->first();

        if ($existing instanceof FuelStockReconciliation) {
            return $existing;
        }

        $periodStart = Carbon::parse($data['period_start'])->startOfDay();
        $periodEnd = Carbon::parse($data['period_end'])->endOfDay();

        $opening = $this->openingBalance($station, $data['product_type'], $periodStart);
        $delivered = $this->deliveredVolume($station, $data['product_type'], $periodStart, $periodEnd);
        $sold = $this->soldVolume($station, $data['product_type'], $periodStart, $periodEnd);
        $metered = $this->meteredDelta($station, $data['product_type'], $periodStart, $periodEnd);

        $theoreticalClose = $opening + $delivered - $sold;
        $measuredClose = $data['measured_close_minor'] ?? $this->measuredClose($station, $data['product_type']);
        $variance = $measuredClose !== null ? $measuredClose - $theoreticalClose : 0;

        $tolerance = $data['tolerance_minor'] ?? max(
            self::MIN_TOLERANCE_MINOR,
            (int) round(abs($theoreticalClose) * self::DEFAULT_TOLERANCE_RATIO),
        );

        $status = match (true) {
            $measuredClose === null => FuelStockReconciliation::STATUS_PENDING_MEASUREMENT,
            abs($variance) <= $tolerance => FuelStockReconciliation::STATUS_COMPLETED,
            default => FuelStockReconciliation::STATUS_EXCEPTION,
        };

        /** @var FuelStockReconciliation $reconciliation */
        $reconciliation = FuelStockReconciliation::query()->create([
            'company_id' => $station->company_id,
            'station_id' => $station->id,
            'product_type' => $data['product_type'],
            'period_start' => $data['period_start'],
            'period_end' => $data['period_end'],
            'status' => $status,
            'opening_minor' => $opening,
            'delivered_minor' => $delivered,
            'sold_minor' => $sold,
            'metered_delta_minor' => $metered,
            'measured_close_minor' => $measuredClose,
            'theoretical_close_minor' => $theoreticalClose,
            'variance_minor' => $variance,
            'variance_tolerance_minor' => $tolerance,
            'explanation' => [
                'opening_basis' => 'movements_before_period_or_previous_reconciliation',
                'metered_vs_sold_delta_minor' => $metered - $sold,
                'variance_explained' => $variance === 0 ? 'measured_close_matches_theoretical' : (
                    abs($variance) <= $tolerance ? 'within_tolerance' : 'outside_tolerance_requires_adjustment'
                ),
                'status' => $status,
            ],
            'idempotency_key' => $data['idempotency_key'],
            'started_by' => $actor?->id,
            'completed_at' => now(),
        ]);

        FuelStockReconciliationCompleted::dispatch($reconciliation);

        if ($status === FuelStockReconciliation::STATUS_EXCEPTION) {
            FuelStockVarianceDetected::dispatch($reconciliation);
        }

        return $reconciliation;
    }

    private function openingBalance(FuelStation $station, string $productType, Carbon $periodStart): int
    {
        // Préférer la clôture mesurée du rapprochement précédent (même
        // produit, période antérieure) — sinon la somme des mouvements
        // antérieurs à la période.
        $previous = FuelStockReconciliation::query()
            ->where('company_id', $station->company_id)
            ->where('station_id', $station->id)
            ->where('product_type', $productType)
            ->where('period_end', '<', $periodStart->toDateString())
            ->whereIn('status', [
                FuelStockReconciliation::STATUS_COMPLETED,
                FuelStockReconciliation::STATUS_EXCEPTION,
            ])
            ->orderByDesc('period_end')
            ->first();

        if ($previous instanceof FuelStockReconciliation && $previous->measured_close_minor !== null) {
            return $previous->measured_close_minor;
        }

        $in = (int) FuelStockMovement::query()
            ->where('company_id', $station->company_id)
            ->where('station_id', $station->id)
            ->where('product_type', $productType)
            ->where('direction', FuelStockMovement::DIRECTION_IN)
            ->where('movement_at', '<', $periodStart)
            ->sum('quantity_minor');

        $out = (int) FuelStockMovement::query()
            ->where('company_id', $station->company_id)
            ->where('station_id', $station->id)
            ->where('product_type', $productType)
            ->where('direction', FuelStockMovement::DIRECTION_OUT)
            ->where('movement_at', '<', $periodStart)
            ->sum('quantity_minor');

        return $in - $out;
    }

    private function deliveredVolume(FuelStation $station, string $productType, Carbon $periodStart, Carbon $periodEnd): int
    {
        return (int) FuelStockMovement::query()
            ->where('company_id', $station->company_id)
            ->where('station_id', $station->id)
            ->where('product_type', $productType)
            ->where('reason', FuelStockMovement::REASON_DELIVERY)
            ->whereBetween('movement_at', [$periodStart, $periodEnd])
            ->sum('quantity_minor');
    }

    /**
     * Ventes converties en unités mineures via l'échelle du compteur du
     * produit (défaut 2) — comparables aux deltas de compteurs.
     */
    private function soldVolume(FuelStation $station, string $productType, Carbon $periodStart, Carbon $periodEnd): int
    {
        $scale = $this->precisionScaleFor($station, $productType);
        $quantity = (float) FuelSale::query()
            ->where('company_id', $station->company_id)
            ->where('station_id', $station->id)
            ->where('product', $productType)
            ->whereBetween('sale_time', [$periodStart, $periodEnd])
            ->sum('quantity');

        return (int) round($quantity * (10 ** $scale));
    }

    /**
     * Somme des deltas de compteurs (intervalles validés/rollover) des
     * pompes de la station pour la période — indépendante des ventes.
     */
    private function meteredDelta(FuelStation $station, string $productType, Carbon $periodStart, Carbon $periodEnd): int
    {
        $meterIds = FuelMeterRegister::query()
            ->where('company_id', $station->company_id)
            ->where('station_id', $station->id)
            ->where('product_code', $productType)
            ->pluck('id');

        if ($meterIds->isEmpty()) {
            return 0;
        }

        return (int) FuelMeterInterval::query()
            ->where('company_id', $station->company_id)
            ->whereIn('meter_id', $meterIds)
            ->whereIn('calculation_status', [
                FuelMeterInterval::STATUS_VALID,
                FuelMeterInterval::STATUS_ROLLOVER,
            ])
            ->whereBetween('calculated_at', [$periodStart, $periodEnd])
            ->sum('delta_minor');
    }

    private function measuredClose(FuelStation $station, string $productType): ?int
    {
        $tanks = FuelTank::query()
            ->where('company_id', $station->company_id)
            ->where('station_id', $station->id)
            ->where('product_type', $productType)
            ->get();

        if ($tanks->isEmpty()) {
            return null;
        }

        $sum = 0;
        $anyKnown = false;

        foreach ($tanks as $tank) {
            $level = $tank->getAttribute('current_level_minor');

            if (is_int($level) && $level >= 0) {
                $sum += $level;
                $anyKnown = true;
            }
        }

        return $anyKnown ? $sum : null;
    }

    private function precisionScaleFor(FuelStation $station, string $productType): int
    {
        $register = FuelMeterRegister::query()
            ->where('company_id', $station->company_id)
            ->where('station_id', $station->id)
            ->where('product_code', $productType)
            ->where('status', FuelMeterRegister::STATUS_ACTIVE)
            ->first();

        $scale = $register?->getAttribute('precision_scale');

        return is_int($scale) && $scale > 0 ? $scale : self::DEFAULT_PRECISION_SCALE;
    }
}
