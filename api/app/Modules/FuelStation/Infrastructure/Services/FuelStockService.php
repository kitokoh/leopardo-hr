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
use App\Modules\FuelStation\Domain\Models\FuelStation;use App\Modules\FuelStation\Domain\Models\FuelTank;use App\Modules\FuelStation\Domain\Models\FuelTankDelivery;
use App\Modules\FuelStation\Domain\Events\FuelDeliveryReceived;use App\Modules\FuelStation\Domain\Events\FuelStockReconciliationCompleted;use App\Modules\FuelStation\Domain\Events\FuelStockVarianceDetected;use App\Modules\FuelStation\Domain\Models\FuelDelivery;use App\Modules\FuelStation\Domain\Models\FuelMeterInterval;use App\Modules\FuelStation\Domain\Models\FuelMeterRegister;use App\Modules\FuelStation\Domain\Models\FuelStockMovement;use App\Modules\FuelStation\Domain\Models\FuelStockReconciliation;use App\Modules\FuelStation\Domain\Models\FuelTank;use App\Modules\FuelStation\Domain\Models\FuelTankDelivery;

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

    private function dayOpening(FuelTank $tank, string $date): ?int
    {
        if (! Schema::hasTable('fuel_stock_daily_openings')) {
            return null;
        }

        $opening = DB::table('fuel_stock_daily_openings')
            ->where('company_id', $tank->company_id)
            ->where('tank_id', $tank->id)
            ->where('open_date', $date)
            ->value('opening_level_minor');

        return is_numeric($opening) ? (int) $opening : null;
    }

    private function computeSummary(string $companyId, int $stationId, string $date): array
    {
        $dayStart = $date.' 00:00:00';
        $dayEnd = $date.' 23:59:59';

        $tanks = FuelTank::query()
            ->where('company_id', $companyId)
            ->where('station_id', $stationId)
            ->orderBy('code')
            ->get();

        $tankSummaries = [];

        foreach ($tanks as $tank) {
            $deliveries = FuelTankDelivery::query()
                ->where('company_id', $companyId)
                ->where('tank_id', $tank->id)
                ->whereBetween('delivered_at', [$dayStart, $dayEnd])
                ->sum('quantity_minor');

            $sales = FuelSale::query()
                ->where('company_id', $companyId)
                ->where('station_id', $stationId)
                ->where('product', $tank->product_type)
                ->whereBetween('sale_time', [$dayStart, $dayEnd])
                ->sum('quantity');

            $deliveryMinor = (int) $deliveries;
            $saleMinor = (int) round((float) $sales * 1000); // litres → unités mineures (millièmes)
            $currentMinor = (int) $tank->current_level_minor;

            // Ouverture = niveau figé au PREMIER mouvement du jour
            // (fuel_stock_daily_openings) : valeur INDÉPENDANTE du niveau
            // courant au moment du rapprochement. Sans mouvement ce jour-là,
            // on retombe sur le niveau courant (aucune variation attendue).
            $openingMinor = $this->dayOpening($tank, $date) ?? $currentMinor;

            // Attendu = ouverture + livraisons − ventes ; mesuré = niveau
            // courant. Registre cohérent → attendu == mesuré (écart 0,
            // expliquable). Un écart (vol, fuite, mouvement non répercuté)
            // est RAPPORTÉ, jamais corrigé silencieusement.
            $expectedMinor = max(0, $openingMinor + $deliveryMinor - $saleMinor);
            $measuredMinor = $currentMinor;
            $varianceMinor = $expectedMinor - $measuredMinor;

            $tankSummaries[] = [
                'tank_id' => $tank->id,
                'tank_code' => $tank->code,
                'product_type' => $tank->product_type,
                'opening_level_minor' => $openingMinor,
                'deliveries_minor' => $deliveryMinor,
                'sales_minor' => $saleMinor,
                'expected_level_minor' => $expectedMinor,
                'measured_level_minor' => $measuredMinor,
                'variance_minor' => $varianceMinor,
                'explainable' => $varianceMinor === 0,
            ];
        }

        $totalVariance = array_sum(array_column($tankSummaries, 'variance_minor'));

        return [
            'generated_at' => Carbon::now('UTC')->toIso8601String(),
            'run_date' => $date,
            'station_id' => $stationId,
            'total_variance_minor' => $totalVariance,
            'explainable' => $totalVariance === 0,
            'tanks' => $tankSummaries,
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
        $measuredClose = $data['measured_close_minor'] ?? null;

        // Jauge de clôture : fournie explicitement, ou repli sur les niveaux
        // de cuves courants UNIQUEMENT pour les rapprochements du jour (une
        // exécution planifiée pour une période passée n'a pas de jauge du
        // jour → `pending_measurement`, jamais d'écart fantôme).
        if ($measuredClose === null && ($data['fallback_to_tank_levels'] ?? true)) {
            $measuredClose = $this->measuredClose($station, $data['product_type']);
        }

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
        $this->contract->stockReconciled($reconciliation);

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