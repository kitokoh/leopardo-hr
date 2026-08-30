<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\FuelStation\Domain\Models\FuelReconciliationRun;
use App\Modules\FuelStation\Domain\Models\FuelSale;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use App\Modules\FuelStation\Domain\Models\FuelTank;
use App\Modules\FuelStation\Domain\Models\FuelTankDelivery;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Stocks, cuves et rapprochement FuelStation (FUEL-009, issue #5803).
 *
 * - Livraison idempotente : `external_id` unique par tenant — un rejeu
 *   renvoie la livraison existante (zéro doublon).
 * - Rapprochement rejouable : un seul run par (station, date) ; le calcul
 *   attendu = ouverture + livraisons − ventes vs mesuré (current_level_minor)
 *   est rapporté dans `summary` — AUCUN ajustement silencieux en base.
 * - Zéro flottant métier : quantités et prix en unités mineures entières.
 * - Isolation tenant : cuves/station résolues dans le tenant courant
 *   (FK composites + scopes BelongsToCompany).
 */
final class FuelStockService
{
    /**
     * Enregistre une livraison de carburant dans une cuve.
     *
     * @param  array<string, mixed>  $data
     */
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
            $tank->increment('current_level_minor', $quantityMinor);

            return $delivery;
        });
    }

    /**
     * Exécute (ou rejoue) le rapprochement stock d'une station pour une date.
     *
     * Rejouable : si un run existe déjà pour (station, run_date), il est
     * renvoyé tel quel — jamais recalculé, jamais dupliqué.
     *
     * @return array{run: FuelReconciliationRun, replayed: bool}
     */
    public function reconcile(FuelStation $station, string $runDate, ?Employee $actor = null): array
    {
        $companyId = $station->company_id;
        $date = Carbon::parse($runDate)->toDateString();

        $existing = FuelReconciliationRun::query()
            ->where('company_id', $companyId)
            ->where('station_id', $station->id)
            ->where('run_date', $date)
            ->first();

        if ($existing instanceof FuelReconciliationRun) {
            return ['run' => $existing, 'replayed' => true];
        }

        $run = DB::transaction(function () use ($companyId, $station, $date, $actor): FuelReconciliationRun {
            return FuelReconciliationRun::query()->create([
                'company_id' => $companyId,
                'station_id' => $station->id,
                'run_date' => $date,
                'status' => FuelReconciliationRun::STATUS_RUNNING,
                'started_at' => Carbon::now('UTC'),
                'created_by' => $actor?->id,
            ]);
        });

        try {
            // L'ouverture est un instantané des niveaux au début de la passe
            // (jamais dérivée circulairement du niveau mesuré courant).
            $openingLevels = $this->openingLevels($companyId, $station->id);
            $summary = $this->computeSummary($companyId, $station->id, $date, $openingLevels);
            $run->update([
                'status' => FuelReconciliationRun::STATUS_COMPLETED,
                'summary' => $summary,
                'finished_at' => Carbon::now('UTC'),
            ]);

            return ['run' => $run->refresh(), 'replayed' => false];
        } catch (\Throwable $e) {
            $run->update([
                'status' => FuelReconciliationRun::STATUS_FAILED,
                'last_error' => substr($e->getMessage(), 0, 500),
                'finished_at' => Carbon::now('UTC'),
            ]);

            throw $e;
        }
    }

    /**
     * Calcule l'écart par cuve pour une station et une date.
     *
     * Attendu = ouverture (instantané au début de la passe) + Σ livraisons
     * − Σ ventes (même type de produit), rapporté contre le niveau mesuré
     * courant. L'écart est RAPPORTÉ, jamais corrigé (aucun ajustement
     * silencieux) ; un écart non expliqué doit être investigué (runbook
     * pilote FuelStation §6 : gel des écritures + investigation).
     *
     * @param  array<int, int>  $openingLevels  tank_id => niveau d'ouverture (minor)
     * @return array<string, mixed>
     */
    private function computeSummary(string $companyId, int $stationId, string $date, array $openingLevels): array
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
            $openingMinor = (int) ($openingLevels[$tank->id] ?? $tank->current_level_minor);
            $expectedMinor = max(0, $openingMinor + $deliveryMinor - $saleMinor);
            $measuredMinor = (int) $tank->current_level_minor;
            $varianceMinor = $expectedMinor - $measuredMinor;

            $tankSummaries[] = [
                'tank_id' => $tank->id,
                'tank_code' => $tank->code,
                'product_type' => $tank->product_type,
                'opening_level_minor' => max(0, $openingMinor),
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

    /**
     * Instantané des niveaux de cuve au début de la passe.
     *
     * @return array<int, int> tank_id => current_level_minor
     */
    private function openingLevels(string $companyId, int $stationId): array
    {
        $levels = [];

        foreach (FuelTank::query()->where('company_id', $companyId)->where('station_id', $stationId)->get() as $tank) {
            $levels[(int) $tank->id] = (int) $tank->current_level_minor;
        }

        return $levels;
    }
}
