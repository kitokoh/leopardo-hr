<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\FuelStation\Domain\Models\FuelOutboxEvent;
use App\Modules\FuelStation\Domain\Models\FuelReconciliationRun;
use App\Modules\FuelStation\Domain\Models\FuelSale;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use App\Modules\FuelStation\Domain\Models\FuelStockEntry;
use App\Modules\FuelStation\Domain\Models\FuelTank;
use App\Modules\FuelStation\Domain\Models\FuelTankDelivery;
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

    /**
     * Tolerance de jauge : en deca de ce nombre d'unites mineures (0,5 L),
     * l'ecart entre registre et mesure physique est considere comme absorbe
     * par les ajustements enregistres du jour.
     */
    private const TANK_VARIANCE_TOLERANCE_MINOR = 500;

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
            // Une ligne par cuve : niveau theorique (registre) contre niveau
            // mesure (jauge saisie ou importee). Le niveau physique d'une cuve
            // n'est JAMAIS suppose -- il vient de `current_level_minor`.
            'tanks' => $this->tankLines($companyId, $stationId, $date),
        ];
    }

    /**
     * Rapprochement par cuve : compare le registre (ouverture + livraisons
     * - ventes) au niveau physique mesure, puis qualifie l'ecart.
     *
     * `explainable` repond a la seule question utile a l'exploitant :
     * "l'ecart entre registre et jauge est-il absorbe par un ajustement
     * enregistre du jour ?" -- sinon l'ecart reste a expliquer (perte, vol,
     * erreur de saisie). Aucun ajustement silencieux.
     *
     * @return list<array<string, mixed>>
     */
    private function tankLines(string $companyId, ?int $stationId, Carbon $date): array
    {
        if (! Schema::hasTable('fuel_tanks')) {
            return [];
        }

        $dayStart = $date->copy()->startOfDay();
        $dayEnd = $date->copy()->endOfDay();
        $day = $date->toDateString();

        $tanks = FuelTank::query()
            ->where('company_id', $companyId)
            ->when($stationId !== null, fn ($q) => $q->where('station_id', $stationId))
            ->orderBy('id')
            ->get();

        $lines = [];

        foreach ($tanks as $tank) {
            $salesLitres = (float) FuelSale::query()
                ->where('company_id', $companyId)
                ->where('station_id', $tank->station_id)
                ->where('product', $tank->product_type)
                ->whereBetween('sale_time', [$dayStart, $dayEnd])
                ->sum('quantity');

            $salesMinor = (int) round($salesLitres * 1000);

            $deliveriesMinor = Schema::hasTable('fuel_tank_deliveries')
                ? (int) FuelTankDelivery::query()
                    ->where('company_id', $companyId)
                    ->where('tank_id', $tank->id)
                    ->whereBetween('delivered_at', [$dayStart, $dayEnd])
                    ->sum('quantity_minor')
                : 0;

            $measuredMinor = (int) $tank->current_level_minor;

            $openingMinor = $this->openingLevelFor($companyId, (int) $tank->id, $day);

            if ($openingMinor === null) {
                // Ouverture non figee : derivee des seuls mouvements connus.
                $openingMinor = $measuredMinor + $salesMinor - $deliveriesMinor;
            }

            $expectedMinor = $openingMinor + $deliveriesMinor - $salesMinor;
            // Convention d'ecart figee par la suite : variance = attendu - mesure.
            // Une valeur POSITIVE signale un manque dans la cuve (perte, vol,
            // erreur de saisie) -- c'est l'ecart "a expliquer".
            $varianceMinor = $expectedMinor - $measuredMinor;

            $adjustments = $this->adjustmentsFor($companyId, $tank, $dayStart, $dayEnd);

            $lines[] = [
                'tank_id' => (int) $tank->id,
                'station_id' => (int) $tank->station_id,
                'product_type' => $tank->product_type,
                'opening_level_minor' => $openingMinor,
                'deliveries_minor' => $deliveriesMinor,
                'sales_minor' => $salesMinor,
                'expected_level_minor' => $expectedMinor,
                'measured_level_minor' => $measuredMinor,
                'variance_minor' => $varianceMinor,
                'adjustments_minor' => $adjustments['minor'],
                'explainable' => abs($varianceMinor - $adjustments['minor']) <= self::TANK_VARIANCE_TOLERANCE_MINOR,
                'explained_by' => $adjustments['ids'],
            ];
        }

        return $lines;
    }

    /** Niveau d'ouverture fige du jour (premier mouvement), si disponible. */
    private function openingLevelFor(string $companyId, int $tankId, string $day): ?int
    {
        if (! Schema::hasTable('fuel_stock_daily_openings')) {
            return null;
        }

        $value = DB::table('fuel_stock_daily_openings')
            ->where('company_id', $companyId)
            ->where('tank_id', $tankId)
            ->where('open_date', $day)
            ->value('opening_level_minor');

        return $value === null ? null : (int) $value;
    }

    /**
     * Ajustements de stock enregistres du jour pour le produit de la cuve.
     *
     * @return array{minor: int, ids: list<int>}
     */
    private function adjustmentsFor(string $companyId, FuelTank $tank, Carbon $dayStart, Carbon $dayEnd): array
    {
        if (! Schema::hasTable('fuel_stock_entries')) {
            return ['minor' => 0, 'ids' => []];
        }

        $rows = FuelStockEntry::query()
            ->where('company_id', $companyId)
            ->where('station_id', $tank->station_id)
            ->where('product_code', $tank->product_type)
            ->where('entry_type', FuelStockEntry::ENTRY_ADJUSTMENT)
            ->whereBetween('entry_date', [$dayStart->toDateString(), $dayEnd->toDateString()])
            ->get(['id', 'quantity']);

        $ids = [];
        foreach ($rows as $row) {
            $ids[] = (int) $row->id;
        }

        return [
            'minor' => (int) round(((float) $rows->sum('quantity')) * 1000),
            'ids' => $ids,
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

    public function recordDelivery(FuelTank $tank, Employee $actor, array $data): FuelTankDelivery
    {
        $companyId = $tank->company_id;

        $externalId = $data['external_id'] ?? null;
        $externalId = is_string($externalId) ? $externalId : null;
        if ($externalId !== null && $externalId !== '') {
            $existing = FuelTankDelivery::query()
                ->where('company_id', $companyId)
                ->where('external_id', $externalId)
                ->first();

            if ($existing instanceof FuelTankDelivery) {
                return $existing;
            }
        }

        $quantityRaw = $data['quantity_minor'] ?? null;
        $quantityMinor = is_numeric($quantityRaw) ? (int) $quantityRaw : 0;
        if ($quantityMinor <= 0) {
            abort(422, 'DELIVERY_QUANTITY_MUST_BE_POSITIVE');
        }

        $deliveredAtRaw = $data['delivered_at'] ?? null;
        $deliveredAt = is_string($deliveredAtRaw) && $deliveredAtRaw !== ''
            ? Carbon::parse($deliveredAtRaw)->utc()
            : Carbon::now('UTC');

        return DB::transaction(function () use ($tank, $actor, $companyId, $quantityMinor, $data, $deliveredAt, $externalId): FuelTankDelivery {
            $delivery = FuelTankDelivery::query()->create([
                'company_id' => $companyId,
                'tank_id' => $tank->id,
                'quantity_minor' => $quantityMinor,
                'unit_price_minor' => isset($data['unit_price_minor']) && is_numeric($data['unit_price_minor'])
                    ? (int) $data['unit_price_minor']
                    : null,
                'delivered_at' => $deliveredAt,
                'external_id' => is_string($externalId) ? $externalId : null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
            ]);

            // Mise à jour du niveau courant (aucun ajustement silencieux :
            // la livraison EST un mouvement de stock légitime).
            // Fige d'abord l'ouverture du jour au premier mouvement.
            $this->captureDayOpening($tank, $quantityMinor);
            $tank->increment('current_level_minor', $quantityMinor);

            return $delivery;
        });
    }

    private function captureDayOpening(FuelTank $tank, int $quantityMinor): void
    {
        if (! Schema::hasTable('fuel_stock_daily_openings')) {
            return;
        }

        $companyId = $tank->company_id;
        $today = now()->toDateString();

        $exists = DB::table('fuel_stock_daily_openings')
            ->where('company_id', $companyId)
            ->where('tank_id', $tank->id)
            ->where('open_date', $today)
            ->exists();

        if ($exists) {
            return;
        }

        // Le premier mouvement du jour fixe l'ouverture au niveau courant
        // AVANT le mouvement (livraison : on fige avant l'incrément).
        DB::table('fuel_stock_daily_openings')->insert([
            'company_id' => $companyId,
            'tank_id' => $tank->id,
            'open_date' => $today,
            'opening_level_minor' => (int) $tank->current_level_minor,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
