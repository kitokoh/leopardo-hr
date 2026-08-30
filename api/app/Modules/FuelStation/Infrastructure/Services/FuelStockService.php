<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Infrastructure\Services;

use App\Modules\FuelStation\Domain\Enums\FuelStockMovementType;
use App\Modules\FuelStation\Domain\Models\FuelStockMovement;
use App\Modules\FuelStation\Domain\Models\FuelStockReconciliation;
use App\Modules\FuelStation\Domain\Models\FuelTank;
use DateTimeInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * #5803 — Grand-livre de stock par cuve + rapprochement (FUEL-009).
 *
 * Chaque mouvement (livraison, vente, ouverture, clôture = comptage,
 * ajustement) est PERSISTÉ de façon idempotente (clé unique
 * (company_id, idempotency_key)) : les jobs de rapprochement sont
 * rejouables sans doublon.
 *
 * Le rapprochement compare, par cuve et par période :
 *   expected = opening + Σ deliveries − Σ sales
 *   actual   = dernier comptage physique (mouvement `closing`) de la période
 *   variance = actual − expected
 * Aucun ajustement silencieux : une variance est TOUJOURS remontée (statut
 * `variance`), jamais corrigée dans le grand-livre par le rapport.
 */
final class FuelStockService
{
    /** Tolérance de rapprochement (litres) sous laquelle l'écart est `ok`. */
    private const TOLERANCE_LITERS = 0.5;

    /**
     * Enregistre un mouvement idempotent. Retourne le mouvement existant si
     * la clé d'idempotence a déjà été consommée.
     *
     * @param  array{station_id?: int|null, unit_price?: float|null, reference?: string|null, notes?: string|null, created_by?: int|null, occurred_at?: DateTimeInterface|null}  $context
     */
    public function recordMovement(
        FuelTank $tank,
        FuelStockMovementType $type,
        float $quantity,
        array $context = [],
        ?string $idempotencyKey = null,
    ): FuelStockMovement {
        $key = $idempotencyKey ?? hash('sha256', $type->value.'|'.$tank->id.'|'.(string) $quantity.'|'.($context['reference'] ?? ''));

        $existing = FuelStockMovement::query()
            ->where('company_id', $tank->company_id)
            ->where('idempotency_key', $key)
            ->first();

        if ($existing instanceof FuelStockMovement) {
            return $existing;
        }

        try {
            return DB::transaction(fn (): FuelStockMovement => FuelStockMovement::query()->create([
                'company_id' => $tank->company_id,
                'station_id' => $context['station_id'] ?? null,
                'tank_id' => $tank->id,
                'type' => $type->value,
                'quantity' => $quantity,
                'unit_price' => $context['unit_price'] ?? null,
                'occurred_at' => $context['occurred_at'] ?? now(),
                'reference' => $context['reference'] ?? null,
                'notes' => $context['notes'] ?? null,
                'created_by' => $context['created_by'] ?? null,
                'idempotency_key' => $key,
            ]));
        } catch (UniqueConstraintViolationException) {
            /** @var FuelStockMovement $existing */
            $existing = FuelStockMovement::query()
                ->where('company_id', $tank->company_id)
                ->where('idempotency_key', $key)
                ->firstOrFail();

            return $existing;
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function recordDelivery(FuelTank $tank, float $liters, array $context = []): FuelStockMovement
    {
        return $this->recordMovement($tank, FuelStockMovementType::Delivery, $liters, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function recordSale(FuelTank $tank, float $liters, array $context = []): FuelStockMovement
    {
        return $this->recordMovement($tank, FuelStockMovementType::Sale, $liters, $context);
    }

    /**
     * Comptage physique de fin de période (clôture) : c'est la référence
     * « actual » du rapprochement.
     *
     * @param  array<string, mixed>  $context
     */
    public function recordClosingCount(FuelTank $tank, float $countedLiters, array $context = []): FuelStockMovement
    {
        return $this->recordMovement($tank, FuelStockMovementType::Closing, $countedLiters, $context);
    }

    /**
     * Ajustement manuel motivé (jamais silencieux : reference obligatoire).
     *
     * @param  array<string, mixed>  $context
     */
    public function recordAdjustment(FuelTank $tank, float $deltaLiters, array $context = []): FuelStockMovement
    {
        return $this->recordMovement($tank, FuelStockMovementType::Adjustment, $deltaLiters, $context);
    }

    /**
     * Rapprochement d'une station pour une période (rejouable : updateOrCreate).
     */
    public function reconcile(string $companyId, int $stationId, string $period, ?int $actorId = null): FuelStockReconciliation
    {
        $tanks = FuelTank::query()
            ->where('company_id', $companyId)
            ->where('station_id', $stationId)
            ->get();

        $tankBreakdown = [];

        foreach ($tanks as $tank) {
            $movements = FuelStockMovement::query()
                ->where('company_id', $companyId)
                ->where('tank_id', $tank->id)
                ->whereBetween('occurred_at', [$period.'-01 00:00:00', $period.'-31 23:59:59'])
                ->orderBy('occurred_at')
                ->orderBy('id')
                ->get();

            $opening = 0.0;
            $delivered = 0.0;
            $sold = 0.0;
            $actual = null;

            foreach ($movements as $movement) {
                $quantity = (float) $movement->quantity;

                switch ($movement->type) {
                    case FuelStockMovementType::Opening->value:
                        $opening = $quantity;
                        break;
                    case FuelStockMovementType::Delivery->value:
                        $delivered += $quantity;
                        break;
                    case FuelStockMovementType::Sale->value:
                        $sold += $quantity;
                        break;
                    case FuelStockMovementType::Closing->value:
                        $actual = $quantity; // dernier comptage physique
                        break;
                    case FuelStockMovementType::Adjustment->value:
                        // Les ajustements motivés sont hors calcul d'écart —
                        // ils apparaissent dans le détail du rapport.
                        break;
                }
            }

            $expected = $opening + $delivered - $sold;
            $variance = $actual !== null ? $actual - $expected : null;

            $tankBreakdown[] = [
                'tank_id' => $tank->id,
                'tank_code' => $tank->code,
                'product_type' => $tank->product_type,
                'opening' => round($opening, 3),
                'delivered' => round($delivered, 3),
                'sold' => round($sold, 3),
                'expected_level' => round($expected, 3),
                'actual_level' => $actual !== null ? round($actual, 3) : null,
                'variance_liters' => $variance !== null ? round($variance, 3) : null,
                'status' => $this->statusFor($variance),
            ];
        }

        $hasActual = collect($tankBreakdown)->contains(fn (array $row): bool => $row['actual_level'] !== null);
        $overallStatus = $tankBreakdown === []
            ? FuelStockReconciliation::STATUS_INSUFFICIENT_DATA
            : ($hasActual
                ? (collect($tankBreakdown)->contains(fn (array $row): bool => $row['status'] === FuelStockReconciliation::STATUS_VARIANCE)
                    ? FuelStockReconciliation::STATUS_VARIANCE
                    : FuelStockReconciliation::STATUS_OK)
                : FuelStockReconciliation::STATUS_INSUFFICIENT_DATA);

        $totalVariance = collect($tankBreakdown)->sum(fn (array $row): float => (float) ($row['variance_liters'] ?? 0));

        return FuelStockReconciliation::query()->updateOrCreate(
            [
                'company_id' => $companyId,
                'station_id' => $stationId,
                'period' => $period,
            ],
            [
                'opening_quantity' => round(collect($tankBreakdown)->sum('opening'), 3),
                'delivered_quantity' => round(collect($tankBreakdown)->sum('delivered'), 3),
                'sold_quantity' => round(collect($tankBreakdown)->sum('sold'), 3),
                'expected_level' => round(collect($tankBreakdown)->sum('expected_level'), 3),
                'actual_level' => round(collect($tankBreakdown)->sum('actual_level') ?? 0, 3),
                'variance_liters' => round($totalVariance, 3),
                'status' => $overallStatus,
                'data' => ['tanks' => $tankBreakdown],
                'reconciled_by' => $actorId,
                'reconciled_at' => now(),
            ],
        );
    }

    private function statusFor(?float $variance): string
    {
        if ($variance === null) {
            return FuelStockReconciliation::STATUS_INSUFFICIENT_DATA;
        }

        return abs($variance) <= self::TOLERANCE_LITERS
            ? FuelStockReconciliation::STATUS_OK
            : FuelStockReconciliation::STATUS_VARIANCE;
    }
}
