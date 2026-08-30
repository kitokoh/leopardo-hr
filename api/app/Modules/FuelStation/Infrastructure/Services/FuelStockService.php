<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\FuelStation\Domain\Events\FuelReconciliationReported;
use App\Modules\FuelStation\Domain\Models\FuelReconciliationReport;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use App\Modules\FuelStation\Domain\Models\FuelStockDelivery;
use App\Modules\FuelStation\Domain\Models\FuelTank;
use App\Modules\FuelStation\Domain\Models\FuelTankStockLevel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Stocks, cuves et rapprochement FuelStation (FUEL-009, issue #5803).
 *
 * - Niveau de cuve : idempotent par `idempotency_key` (rejeu → état
 *   existant), jamais négatif (CHECK DB).
 * - Livraison : `reference` UNIQUE par tenant → rejeu idempotent ; cycle
 *   draft → received|rejected ; la réception est idempotente et incrémente
 *   `fuel_tanks.current_level_minor` (borné par la capacité — jamais de
 *   dépassement silencieux).
 * - Rapprochement : un rapport PAR (station, jour) — `updateOrCreate` →
 *   job rejouable sans doublon. expected = ouverture + livraisons reçues −
 *   ventes (unités mineures) ; variance = clôture − attendu ; écart jamais
 *   ajusté silencieusement (revue manager avec explication obligatoire).
 * - Audit : les événements émis (FuelReconciliationReported) sont tracés
 *   dans `audit_logs` via AuditLogger (pattern canonique).
 */
final class FuelStockService
{
    private const MINOR_UNIT_SCALE = 100; // centilitres (documenté FUELSTATION_DONNEES)

    /**
     * Enregistre un niveau de stock observé — idempotent par
     * `idempotency_key`.
     *
     * @param  array<string, mixed>  $data
     */
    public function recordStockLevel(Employee $actor, array $data): FuelTankStockLevel
    {
        $key = $data['idempotency_key'] ?? null;
        if (is_string($key) && $key !== '') {
            $existing = FuelTankStockLevel::query()
                ->where('company_id', $actor->company_id)
                ->where('idempotency_key', $key)
                ->first();

            if ($existing instanceof FuelTankStockLevel) {
                return $existing;
            }
        }

        /** @var FuelTankStockLevel $level */
        $level = FuelTankStockLevel::query()->create([
            'company_id' => $actor->company_id,
            'tank_id' => (int) $data['tank_id'],
            'recorded_on' => $data['recorded_on'],
            'level_minor' => (int) $data['level_minor'],
            'source_code' => $data['source_code'] ?? FuelTankStockLevel::SOURCE_MANUAL,
            'idempotency_key' => $key,
            'notes' => $data['notes'] ?? null,
            'recorded_by' => $actor->id,
        ]);

        return $level;
    }

    /**
     * Enregistre une livraison (draft) — rejeu idempotent par `reference`.
     *
     * @param  array<string, mixed>  $data
     */
    public function recordDelivery(Employee $actor, array $data): FuelStockDelivery
    {
        /** @var string $reference */
        $reference = $data['reference'];

        $existing = FuelStockDelivery::query()
            ->where('company_id', $actor->company_id)
            ->where('reference', $reference)
            ->first();

        if ($existing instanceof FuelStockDelivery) {
            return $existing;
        }

        $tankId = isset($data['tank_id']) ? (int) $data['tank_id'] : null;

        if ($tankId !== null) {
            $tank = FuelTank::query()
                ->where('company_id', $actor->company_id)
                ->whereKey($tankId)
                ->first();

            abort_if($tank === null, 422, 'TANK_OUTSIDE_TENANT');
        }

        /** @var FuelStockDelivery $delivery */
        $delivery = FuelStockDelivery::query()->create([
            'company_id' => $actor->company_id,
            'station_id' => (int) $data['station_id'],
            'tank_id' => $tankId,
            'product_code' => $data['product_code'],
            'supplier_name' => $data['supplier_name'] ?? null,
            'quantity_minor' => (int) $data['quantity_minor'],
            'unit_code' => $data['unit_code'] ?? 'l',
            'delivered_at' => $data['delivered_at'] ?? now(),
            'reference' => $reference,
            'status' => FuelStockDelivery::STATUS_DRAFT,
            'notes' => $data['notes'] ?? null,
        ]);

        return $delivery;
    }

    /**
     * Réceptionne une livraison — idempotente (déjà reçue → état inchangé).
     * Incrémente le niveau courant de la cuve, borné par la capacité.
     */
    public function receiveDelivery(Employee $actor, FuelStockDelivery $delivery): FuelStockDelivery
    {
        if ($delivery->status === FuelStockDelivery::STATUS_RECEIVED) {
            return $delivery->refresh();
        }

        abort_if($delivery->status === FuelStockDelivery::STATUS_REJECTED, 422, 'FUEL_DELIVERY_REJECTED');

        if ($delivery->tank_id !== null) {
            DB::transaction(function () use ($delivery, $actor): void {
                $tank = FuelTank::query()
                    ->where('company_id', $delivery->company_id)
                    ->whereKey($delivery->tank_id)
                    ->lockForUpdate()
                    ->first();

                abort_if($tank === null, 422, 'TANK_OUTSIDE_TENANT');

                $newLevel = $tank->current_level_minor + $delivery->quantity_minor;

                abort_if($newLevel > $tank->capacity_minor, 422, 'FUEL_TANK_CAPACITY_EXCEEDED');

                $tank->forceFill(['current_level_minor' => $newLevel])->save();

                $delivery->update([
                    'status' => FuelStockDelivery::STATUS_RECEIVED,
                    'received_by' => $actor->id,
                    'received_at' => now(),
                ]);
            });
        } else {
            $delivery->update([
                'status' => FuelStockDelivery::STATUS_RECEIVED,
                'received_by' => $actor->id,
                'received_at' => now(),
            ]);
        }

        return $delivery->refresh();
    }

    /**
     * Lance (ou rejoue) le rapprochement d'une station pour une date.
     *
     * @param  array<string, mixed>  $data
     */
    public function reconcile(Employee $actor, array $data): FuelReconciliationReport
    {
        $report = $this->reconcileForCompany(
            (string) $actor->company_id,
            (int) $data['station_id'],
            (string) $data['report_date'],
        );

        return $report;
    }

    /**
     * Recalcul idempotent du rapport (station, jour) — utilisé par l'API et
     * par le job rejouable {@see FuelReconciliationJob} (contexte hors
     * requête : company_id explicite, pas de scope tenant global).
     */
    public function reconcileForCompany(string $companyId, int $stationId, string $reportDate): FuelReconciliationReport
    {
        $date = Carbon::parse($reportDate)->toDateString();

        $opening = $this->openingStock($companyId, $stationId, $date);
        $deliveries = $this->deliveriesVolume($companyId, $stationId, $date);
        $sales = $this->salesVolume($companyId, $stationId, $date);
        $closing = $this->closingStock($companyId, $stationId, $date);

        $expected = $opening + $deliveries - $sales;
        // Écart jamais ajusté silencieusement : sans comptage physique le
        // rapport reste pending_review avec variance non interprétable (0).
        $variance = $closing !== null ? $closing - $expected : 0;

        /** @var FuelReconciliationReport $report */
        $report = FuelReconciliationReport::query()->updateOrCreate(
            [
                'company_id' => $companyId,
                'station_id' => $stationId,
                'report_date' => $date,
            ],
            [
                'opening_stock_minor' => $opening,
                'deliveries_minor' => $deliveries,
                'sales_minor' => $sales,
                'expected_stock_minor' => max(0, $expected),
                'closing_stock_minor' => $closing,
                'variance_minor' => $variance,
                'status' => FuelReconciliationReport::STATUS_PENDING_REVIEW,
                'explanation' => null,
                'reviewed_by' => null,
                'reviewed_at' => null,
            ]
        );

        FuelReconciliationReported::dispatch($report);

        return $report;
    }

    /**
     * Revue manager du rapport — explication OBLIGATOIRE dès qu'un écart
     * existe (aucun ajustement silencieux). Rejeu sûr (re-revue autorisée).
     *
     * @param  array<string, mixed>  $data
     */
    public function review(Employee $actor, FuelReconciliationReport $report, array $data): FuelReconciliationReport
    {
        $explanation = isset($data['explanation']) ? (string) $data['explanation'] : null;

        if ($report->variance_minor !== 0) {
            abort_if($explanation === null || trim($explanation) === '', 422, 'FUEL_RECONCILIATION_EXPLANATION_REQUIRED');
        }

        $report->update([
            'status' => $data['status'] ?? FuelReconciliationReport::STATUS_REVIEWED,
            'explanation' => $explanation,
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
        ]);

        return $report->refresh();
    }

    /**
     * Stock d'ouverture : somme des derniers niveaux connus AVANT la date
     * du rapport (par cuve de la station).
     */
    private function openingStock(string $companyId, int $stationId, string $date): int
    {
        $tankIds = FuelTank::query()
            ->where('company_id', $companyId)
            ->where('station_id', $stationId)
            ->pluck('id');

        if ($tankIds->isEmpty()) {
            return 0;
        }

        $rows = DB::table('fuel_tank_stock_levels')
            ->selectRaw('DISTINCT ON (tank_id) tank_id, level_minor')
            ->where('company_id', $companyId)
            ->whereIn('tank_id', $tankIds)
            ->where('recorded_on', '<', $date)
            ->orderBy('tank_id')
            ->orderByDesc('recorded_on')
            ->get();

        return (int) $rows->sum('level_minor');
    }

    /**
     * Volume total des livraisons reçues le jour du rapport (unités mineures).
     */
    private function deliveriesVolume(string $companyId, int $stationId, string $date): int
    {
        return (int) DB::table('fuel_stock_deliveries')
            ->where('company_id', $companyId)
            ->where('station_id', $stationId)
            ->where('status', FuelStockDelivery::STATUS_RECEIVED)
            ->whereDate('delivered_at', $date)
            ->sum('quantity_minor');
    }

    /**
     * Volume vendu le jour du rapport, converti en unités mineures
     * (quantité décimale × échelle, arrondi — assomption centilitres
     * documentée dans FUELSTATION_DONNEES).
     */
    private function salesVolume(string $companyId, int $stationId, string $date): int
    {
        $liters = (float) DB::table('fuel_sales')
            ->where('company_id', $companyId)
            ->where('station_id', $stationId)
            ->whereDate('sale_time', $date)
            ->sum('quantity');

        return (int) round($liters * self::MINOR_UNIT_SCALE);
    }

    /**
     * Stock de clôture : niveaux enregistrés LE JOUR du rapport
     * (somme par cuve), ou null si aucun comptage physique.
     */
    private function closingStock(string $companyId, int $stationId, string $date): ?int
    {
        $tankIds = FuelTank::query()
            ->where('company_id', $companyId)
            ->where('station_id', $stationId)
            ->pluck('id');

        if ($tankIds->isEmpty()) {
            return null;
        }

        $rows = DB::table('fuel_tank_stock_levels')
            ->selectRaw('DISTINCT ON (tank_id) tank_id, level_minor')
            ->where('company_id', $companyId)
            ->whereIn('tank_id', $tankIds)
            ->where('recorded_on', '<=', $date)
            ->orderBy('tank_id')
            ->orderByDesc('recorded_on')
            ->get();

        if ($rows->isEmpty()) {
            return null;
        }

        return (int) $rows->sum('level_minor');
    }

    /**
     * Vérifie qu'une station appartient au tenant (helper contrôleurs).
     */
    public function assertStationOwned(FuelStation $station, string $companyId): FuelStation
    {
        abort_if($station->company_id !== $companyId, 404);

        return $station;
    }
}
