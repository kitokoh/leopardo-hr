<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\FuelStation\Domain\Models\FuelDelivery;
use App\Modules\FuelStation\Domain\Models\FuelMeterInterval;
use App\Modules\FuelStation\Domain\Models\FuelPump;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use App\Modules\FuelStation\Domain\Models\FuelStockMovement;
use App\Modules\FuelStation\Domain\Models\FuelStockReconciliation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Règles métier des stocks FuelStation (FUEL-009, issue #5803).
 *
 * - Journal append-only : livraison +, vente −, ajustement ± (unités mineures
 *   entières signées) ; aucun ajustement silencieux (raison obligatoire pour
 *   un ajustement).
 * - Idempotence : `idempotency_key` UNIQUE (company_id, idempotency_key) sur
 *   les mouvements, `external_id` UNIQUE sur les livraisons — un rejeu ne
 *   double jamais l'effet.
 * - Rapprochement : snapshot par (station, produit, jour), upsert par clé
 *   unique → job rejouable ; un écart n'est jamais silencieux
 *   (status `variance` + notes explicatives).
 */
final class FuelStockService
{
    /**
     * Niveau de stock courant d'un produit (ou total station si produit nul).
     */
    public function currentLevel(string $companyId, int $stationId, ?string $productType = null): int
    {
        $query = FuelStockMovement::query()
            ->where('company_id', $companyId)
            ->where('station_id', $stationId);

        if ($productType !== null) {
            $query->where('product_type', $productType);
        }

        return (int) $query->sum('quantity_minor');
    }

    /**
     * Enregistre une livraison reçue : ligne `fuel_deliveries` + mouvement de
     * stock `delivery` (+quantité). Idempotent par `external_id`/`idempotency_key`.
     *
     * @param  array<string, mixed>  $data
     */
    public function receiveDelivery(Employee $actor, FuelStation $station, array $data): FuelDelivery
    {
        $externalId = isset($data['external_id']) ? (string) $data['external_id'] : null;

        if ($externalId !== null) {
            $existing = FuelDelivery::query()
                ->where('company_id', $actor->company_id)
                ->where('external_id', $externalId)
                ->first();

            if ($existing instanceof FuelDelivery) {
                return $existing;
            }
        }

        /** @var FuelDelivery $delivery */
        $delivery = FuelDelivery::query()->create([
            'company_id' => $actor->company_id,
            'station_id' => $station->id,
            'tank_id' => $data['tank_id'] ?? null,
            'product_type' => $data['product_type'],
            'quantity_minor' => (int) $data['quantity_minor'],
            'delivered_at' => isset($data['delivered_at'])
                ? Carbon::parse((string) $data['delivered_at'])
                : Carbon::now(),
            'source' => $data['source'] ?? 'manual',
            'status' => $data['status'] ?? FuelDelivery::STATUS_RECEIVED,
            'external_id' => $externalId,
            'received_by' => $actor->id,
            'received_at' => Carbon::now(),
            'notes' => $data['notes'] ?? null,
        ]);

        if ($delivery->status === FuelDelivery::STATUS_RECEIVED) {
            $this->recordMovement($actor, $station, [
                'product_type' => $delivery->product_type,
                'type' => FuelStockMovement::TYPE_DELIVERY,
                'quantity_minor' => $delivery->quantity_minor,
                'reason' => 'Livraison #'.$delivery->id,
                'reference' => $externalId,
                'idempotency_key' => $externalId !== null ? 'delivery:'.$externalId : null,
            ]);
        }

        return $delivery;
    }

    /**
     * Ajustement de stock (écart physique constaté). Raison obligatoire —
     * jamais d'ajustement silencieux. Quantité signée.
     *
     * @param  array<string, mixed>  $data
     */
    public function recordAdjustment(Employee $actor, FuelStation $station, array $data): FuelStockMovement
    {
        return $this->recordMovement($actor, $station, [
            'product_type' => $data['product_type'],
            'type' => FuelStockMovement::TYPE_ADJUSTMENT,
            'quantity_minor' => (int) $data['quantity_minor'],
            'reason' => (string) $data['reason'],
            'reference' => $data['reference'] ?? null,
            'idempotency_key' => $data['idempotency_key'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function recordSale(Employee $actor, FuelStation $station, array $data): FuelStockMovement
    {
        return $this->recordMovement($actor, $station, [
            'product_type' => $data['product_type'],
            'type' => FuelStockMovement::TYPE_SALE,
            'quantity_minor' => -1 * (int) $data['quantity_minor'],
            'reason' => $data['reason'] ?? 'Vente',
            'reference' => $data['reference'] ?? null,
            'idempotency_key' => $data['idempotency_key'] ?? null,
        ]);
    }

    /**
     * Rapprochement (station, jour) : pour chaque produit ayant des mouvements,
     * calcule ouverture, entrées/sorties du jour, clôture attendue, écart
     * éventuel vs compteurs (mono-produit uniquement) et upsert le snapshot.
     * Rejouable : même jour → même résultat (clé unique).
     *
     * @return list<FuelStockReconciliation>
     */
    public function reconcile(string $companyId, int $stationId, string $day): array
    {
        $dayStart = Carbon::parse($day)->startOfDay();
        $dayEnd = (clone $dayStart)->addDay();

        $products = FuelStockMovement::query()
            ->where('company_id', $companyId)
            ->where('station_id', $stationId)
            ->distinct()
            ->pluck('product_type');

        $snapshots = [];

        foreach ($products as $productType) {
            $opening = (int) FuelStockMovement::query()
                ->where('company_id', $companyId)
                ->where('station_id', $stationId)
                ->where('product_type', $productType)
                ->where('recorded_at', '<', $dayStart)
                ->sum('quantity_minor');

            $deliveries = (int) FuelStockMovement::query()
                ->where('company_id', $companyId)
                ->where('station_id', $stationId)
                ->where('product_type', $productType)
                ->where('type', FuelStockMovement::TYPE_DELIVERY)
                ->whereBetween('recorded_at', [$dayStart, $dayEnd])
                ->sum('quantity_minor');

            $sales = (int) FuelStockMovement::query()
                ->where('company_id', $companyId)
                ->where('station_id', $stationId)
                ->where('product_type', $productType)
                ->where('type', FuelStockMovement::TYPE_SALE)
                ->whereBetween('recorded_at', [$dayStart, $dayEnd])
                ->sum('quantity_minor');

            $adjustments = (int) FuelStockMovement::query()
                ->where('company_id', $companyId)
                ->where('station_id', $stationId)
                ->where('product_type', $productType)
                ->where('type', FuelStockMovement::TYPE_ADJUSTMENT)
                ->whereBetween('recorded_at', [$dayStart, $dayEnd])
                ->sum('quantity_minor');

            $expectedClosing = $opening + $deliveries + $sales + $adjustments;

            $meteredDelta = $this->meteredDeltaForProduct($companyId, $stationId, (string) $productType, $dayStart, $dayEnd);

            $variance = null;
            $status = FuelStockReconciliation::STATUS_BALANCED;

            if ($meteredDelta !== null) {
                $variance = $expectedClosing - $meteredDelta;
                $status = $variance === 0 ? FuelStockReconciliation::STATUS_BALANCED : FuelStockReconciliation::STATUS_VARIANCE;
            }

            /** @var FuelStockReconciliation $snapshot */
            $snapshot = FuelStockReconciliation::query()->updateOrCreate(
                [
                    'company_id' => $companyId,
                    'station_id' => $stationId,
                    'product_type' => $productType,
                    'day' => $dayStart->toDateString(),
                ],
                [
                    'opening_minor' => $opening,
                    'deliveries_minor' => $deliveries,
                    'sales_minor' => $sales,
                    'adjustments_minor' => $adjustments,
                    'expected_closing_minor' => $expectedClosing,
                    'metered_delta_minor' => $meteredDelta,
                    'variance_minor' => $variance,
                    'status' => $status,
                    'notes' => $meteredDelta === null
                        ? 'Écart compteurs non calculé (station multi-produits ou sans compteurs)'
                        : null,
                    'computed_at' => Carbon::now(),
                ]
            );

            $snapshots[] = $snapshot;
        }

        return $snapshots;
    }

    /**
     * Delta total mesuré par les compteurs de la station pour ce produit sur
     * la période. Uniquement si la station sert un seul type de produit
     * (sinon null : l'affectation compteur → produit est ambiguë via les
     * `product_types` des pompes, documenté FUEL-009).
     */
    private function meteredDeltaForProduct(
        string $companyId,
        int $stationId,
        string $productType,
        Carbon $dayStart,
        Carbon $dayEnd
    ): ?int {
        if ($this->stationProductCount($companyId, $stationId) !== 1) {
            return null;
        }

        $delta = FuelMeterInterval::query()
            ->where('company_id', $companyId)
            ->where('calculated_at', '>=', $dayStart)
            ->where('calculated_at', '<', $dayEnd)
            ->whereHas('meter', function (Builder $query) use ($companyId, $stationId, $productType): void {
                $query->where('company_id', $companyId)
                    ->where('station_id', $stationId)
                    ->where('product_code', $productType);
            })
            ->sum('delta_minor');

        return (int) $delta;
    }

    /**
     * Nombre de types de produits servis par les pompes de la station
     * (union des `product_types` des pompes non retirées).
     */
    private function stationProductCount(string $companyId, int $stationId): int
    {
        $productTypes = FuelPump::query()
            ->where('company_id', $companyId)
            ->where('station_id', $stationId)
            ->where('status', '!=', 'retired')
            ->pluck('product_types')
            ->flatten()
            ->filter()
            ->unique();

        return $productTypes->count();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function recordMovement(Employee $actor, FuelStation $station, array $data): FuelStockMovement
    {
        $idempotencyKey = $data['idempotency_key'] ?? null;

        if ($idempotencyKey !== null) {
            $existing = FuelStockMovement::query()
                ->where('company_id', $actor->company_id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing instanceof FuelStockMovement) {
                return $existing;
            }
        }

        /** @var FuelStockMovement $movement */
        $movement = FuelStockMovement::query()->create([
            'company_id' => $actor->company_id,
            'station_id' => $station->id,
            'tank_id' => $data['tank_id'] ?? null,
            'product_type' => $data['product_type'],
            'type' => $data['type'],
            'quantity_minor' => (int) $data['quantity_minor'],
            'reason' => $data['reason'] ?? null,
            'reference' => $data['reference'] ?? null,
            'idempotency_key' => $idempotencyKey,
            'recorded_by' => $actor->id,
            'recorded_at' => Carbon::now(),
        ]);

        return $movement;
    }
}
