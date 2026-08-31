<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\FuelStation\Domain\Models\FuelOutboxEvent;
use App\Modules\FuelStation\Domain\Models\FuelReconciliationRun;
use App\Modules\FuelStation\Domain\Models\FuelSale;
use App\Modules\FuelStation\Domain\Models\FuelStockEntry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Stocks, cuves et rapprochement FuelStation — FUEL-009 (issue #5803).
 *
 * - Entrée de stock idempotente (`idempotency_key`) ; un ajustement exige
 *   un `reason` non vide — aucun ajustement silencieux.
 * - Niveau de stock calculé : Σ entrées − Σ quantités vendues (par produit
 *   et station), borné à 0.
 * - Rapprochement par station/jour : compare le delta compteur (intervalles
 *   validés) aux ventes déclarées, puis aux variations de stock ; le
 *   `summary` explique chaque écart. UNIQUE (station, date) → rejouable.
 * - Seuil bas → événement outbox `fuel.stock.threshold.breached.v1`.
 */
final class FuelStockService
{
    /** Seuil par défaut (litres) sous lequel une alerte est émise. */
    public const DEFAULT_LOW_STOCK_THRESHOLD = 500;

    public function __construct(private readonly FuelOutboxPublisher $outbox) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function recordEntry(Employee $actor, array $data): FuelStockEntry
    {
        $companyId = (string) $actor->company_id;
        $idempotencyKey = is_string($data['idempotency_key'] ?? null) ? $data['idempotency_key'] : '';

        if ($idempotencyKey !== '') {
            /** @var FuelStockEntry|null $existing */
            $existing = FuelStockEntry::query()
                ->where('company_id', $companyId)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing instanceof FuelStockEntry) {
                return $existing;
            }
        }

        $entryType = is_string($data['entry_type'] ?? null) ? $data['entry_type'] : FuelStockEntry::ENTRY_DELIVERY;
        $reason = $data['reason'] ?? null;

        // Aucun ajustement silencieux : un ajustement sans motif est refusé.
        if ($entryType === FuelStockEntry::ENTRY_ADJUSTMENT && (! is_string($reason) || trim($reason) === '')) {
            abort(422, 'ADJUSTMENT_REASON_REQUIRED');
        }

        $entry = FuelStockEntry::query()->create([
            'company_id' => $companyId,
            'station_id' => $data['station_id'] ?? null,
            'product_code' => is_string($data['product_code'] ?? null) ? $data['product_code'] : '',
            'quantity' => is_numeric($data['quantity'] ?? null) ? (float) $data['quantity'] : 0.0,
            'unit_cost' => is_numeric($data['unit_cost'] ?? null) ? (float) $data['unit_cost'] : 0.0,
            'entry_type' => $entryType,
            'reason' => is_string($reason) ? $reason : null,
            'reference' => $data['reference'] ?? null,
            'entry_date' => $data['entry_date'] ?? now()->toDateString(),
            'idempotency_key' => $idempotencyKey !== '' ? $idempotencyKey : (string) Str::uuid(),
            'created_by' => $actor->id,
            'notes' => $data['notes'] ?? null,
        ]);

        $this->checkLowStock((string) $entry->company_id, (int) $entry->station_id, $entry->product_code);

        return $entry->refresh();
    }

    /**
     * Niveau de stock calculé pour un produit dans une station.
     */
    public function levelFor(string $companyId, ?int $stationId, string $productCode): float
    {
        if (! Schema::hasTable('fuel_stock_entries') || ! Schema::hasTable('fuel_sales')) {
            return 0.0;
        }

        $entries = (float) FuelStockEntry::query()
            ->where('company_id', $companyId)
            ->when($stationId !== null, fn ($q) => $q->where('station_id', $stationId))
            ->where('product_code', $productCode)
            ->sum('quantity');

        $sold = (float) FuelSale::query()
            ->where('company_id', $companyId)
            ->when($stationId !== null, fn ($q) => $q->where('station_id', $stationId))
            ->where('product', $productCode)
            ->sum('quantity');

        return max(0.0, round($entries - $sold, 3));
    }

    /**
     * Rapprochement idempotent par station/jour. Retourne le run (créé ou
     * déjà présent). Lève 422 si un run est déjà en cours.
     *
     * @return array{run: FuelReconciliationRun, variances: array{explained: bool, variances: array<int, array<string, mixed>>}}
     */
    public function reconcile(string $companyId, ?int $stationId, Carbon $date, ?int $actorId = null): array
    {
        $run = FuelReconciliationRun::query()
            ->where('company_id', $companyId)
            ->where('station_id', $stationId)
            ->where('run_date', $date->toDateString())
            ->first();

        if ($run instanceof FuelReconciliationRun) {
            if ($run->status === FuelReconciliationRun::STATUS_RUNNING) {
                abort(422, 'RECONCILIATION_ALREADY_RUNNING');
            }

            if ($run->status === FuelReconciliationRun::STATUS_COMPLETED) {
                // Rejouable : on recalcule et on écrase le résumé (idempotent).
            }
        }

        $run = $run ?? FuelReconciliationRun::query()->create([
            'company_id' => $companyId,
            'station_id' => $stationId,
            'run_date' => $date->toDateString(),
            'status' => FuelReconciliationRun::STATUS_RUNNING,
            'started_at' => now(),
            'created_by' => $actorId,
        ]);

        if ($run->status !== FuelReconciliationRun::STATUS_COMPLETED) {
            $run->forceFill(['status' => FuelReconciliationRun::STATUS_RUNNING, 'started_at' => now()])->save();
        }

        try {
            $variances = $this->computeVariances($companyId, $stationId, $date);

            $run->forceFill([
                'status' => FuelReconciliationRun::STATUS_COMPLETED,
                'finished_at' => now(),
                'summary' => $variances,
                'last_error' => null,
            ])->save();

            $this->outbox->publish(
                $companyId,
                FuelOutboxEvent::EVENT_STOCK_RECONCILED,
                [
                    'station_id' => $stationId,
                    'run_date' => $date->toDateString(),
                    'variance_count' => count($variances['variances']),
                    'explained' => $variances['explained'],
                ],
                'fuel_reconciliation_run',
                (string) $run->id,
                'reconcile-'.$stationId.'-'.$date->toDateString(),
            );

            return ['run' => $run, 'variances' => $variances];
        } catch (\Throwable $e) {
            $run->forceFill([
                'status' => FuelReconciliationRun::STATUS_FAILED,
                'finished_at' => now(),
                'last_error' => substr($e->getMessage(), 0, 500),
            ])->save();

            throw $e;
        }
    }

    /**
     * @return array{explained: bool, variances: list<array<string, mixed>>}
     */
    private function computeVariances(string $companyId, ?int $stationId, Carbon $date): array
    {
        // Delta compteur validé du jour (intervalles non-anomalie).
        $meterDelta = 0.0;

        if (Schema::hasTable('fuel_meter_intervals')) {
            $meterDelta = (float) DB::table('fuel_meter_intervals')
                ->join('fuel_meter_registers', function ($join) use ($companyId): void {
                    $join->on('fuel_meter_registers.id', '=', 'fuel_meter_intervals.meter_id')
                        ->where('fuel_meter_registers.company_id', '=', $companyId);
                })
                ->where('fuel_meter_intervals.company_id', $companyId)
                ->when($stationId !== null, fn ($q) => $q->where('fuel_meter_registers.station_id', $stationId))
                ->where('fuel_meter_intervals.calculated_at', '>=', $date->startOfDay())
                ->where('fuel_meter_intervals.calculated_at', '<=', $date->copy()->endOfDay())
                ->where('fuel_meter_intervals.calculation_status', 'valid')
                ->sum('fuel_meter_intervals.delta_minor');
        }

        // Ventes déclarées du jour (quantités, unités mineures approximées × 1000).
        $salesVolume = 0.0;

        if (Schema::hasTable('fuel_sales')) {
            $salesVolume = (float) FuelSale::query()
                ->where('company_id', $companyId)
                ->when($stationId !== null, fn ($q) => $q->where('station_id', $stationId))
                ->whereBetween('sale_time', [$date->startOfDay(), $date->copy()->endOfDay()])
                ->sum('quantity');
        }

        // Entrées de stock du jour.
        $stockIn = 0.0;

        if (Schema::hasTable('fuel_stock_entries')) {
            $stockIn = (float) FuelStockEntry::query()
                ->where('company_id', $companyId)
                ->when($stationId !== null, fn ($q) => $q->where('station_id', $stationId))
                ->where('entry_date', $date->toDateString())
                ->where('entry_type', '!=', FuelStockEntry::ENTRY_RETURN)
                ->sum('quantity');
        }

        // Écart expliqué si le delta compteur ≈ ventes + variations de stock.
        $deltaMinorLitres = $meterDelta / 1000; // delta_minor en unités mineures (×1000)
        $variance = round($deltaMinorLitres - $salesVolume - $stockIn, 3);

        $explained = abs($variance) <= 2.0; // tolérance pilotage (litres)

        $variances = [];
        if (! $explained) {
            $variances[] = [
                'station_id' => $stationId,
                'date' => $date->toDateString(),
                'meter_delta_litres' => round($deltaMinorLitres, 3),
                'sales_litres' => round($salesVolume, 3),
                'stock_in_litres' => round($stockIn, 3),
                'variance_litres' => $variance,
                'explanation' => 'Écart à expliquer — voir relevés et ventes du jour (aucun ajustement silencieux).',
            ];
        }

        return [
            'explained' => $explained,
            'variances' => $variances,
        ];
    }

    private function checkLowStock(string $companyId, ?int $stationId, string $productCode): void
    {
        if ($stationId === null || $productCode === '' || ! Schema::hasTable('fuel_stock_entries')) {
            return;
        }

        $level = $this->levelFor($companyId, $stationId, $productCode);

        if ($level <= self::DEFAULT_LOW_STOCK_THRESHOLD) {
            $this->outbox->publish(
                $companyId,
                FuelOutboxEvent::EVENT_STOCK_THRESHOLD_BREACHED,
                [
                    'station_id' => $stationId,
                    'product_code' => $productCode,
                    'level_litres' => $level,
                    'threshold_litres' => self::DEFAULT_LOW_STOCK_THRESHOLD,
                ],
                'fuel_stock_entry',
                $productCode,
                'threshold-'.$stationId.'-'.$productCode,
            );
        }
    }
}
