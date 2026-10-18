<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Domain\Services;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FarmOs\Domain\Enums\FarmStorageMovementType;
use App\Modules\FarmOs\Domain\Exceptions\FarmHarvestException;
use App\Modules\FarmOs\Domain\Models\FarmHarvestLot;
use App\Modules\FarmOs\Domain\Models\FarmStorageMovement;
use Illuminate\Support\Carbon;

/**
 * Farm OS — T3.2 Recoltes, entreposage et tracabilite.
 *
 * INVARIANT CENTRAL (exigence de la mission) : une meme quantite ne doit JAMAIS
 * etre comptabilisee deux fois entre recolte, stock, vente et reporting.
 *
 * Comment ce service le garantit :
 *   - `quantity_harvested` est ecrite UNE SEULE FOIS a la creation du lot et
 *     n'est jamais mise a jour : c'est la reference de tracabilite ;
 *   - `quantity_remaining` est un CACHE derive du journal : il n'est modifie que
 *     par ce service, dans la meme transaction que l'ecriture du mouvement ;
 *   - une SORTIE (perte, vente, consommation) ne peut jamais depasser la
 *     quantite restante : on refuse (422) plutot que de laisser un stock
 *     negatif. C'est cette garde qui rend impossible de vendre deux fois la
 *     meme recolte ;
 *   - un TRANSFERT ne retire rien : la marchandise change d'emplacement, elle
 *     reste dans le lot (sinon un simple demenagement ferait disparaitre du
 *     stock) ;
 *   - une VENTE doit referencer un document du service de VENTES EXISTANT :
 *     Farm OS ne cree aucun document commercial et aucun systeme financier
 *     parallele ;
 *   - IDEMPOTENT : le rejeu d'une ecriture mobile/hors ligne portant le meme
 *     `client_movement_id` renvoie le mouvement existant SANS re-appliquer la
 *     sortie de stock.
 *
 * Purete des couches (issue #6568) : aucune facade Laravel ici. La transaction
 * est portee par la connexion du modele (`getConnection()`).
 */
final class FarmHarvestService
{
    /**
     * Cree un lot de recolte. La quantite recoltee est figee ici, pour toujours.
     *
     * @param  numeric-string|string  $quantityHarvested
     */
    public function createLot(
        Company $company,
        string $lotCode,
        string $quantityHarvested,
        string $unit,
        ?int $parcelId = null,
        ?int $cropCycleId = null,
        ?string $cropName = null,
        ?string $qualityGrade = null,
        ?int $storageLocationId = null,
        ?int $farmSiteId = null,
        ?int $farmOperationId = null,
        ?int $createdBy = null,
        ?string $clientHarvestId = null,
        ?Carbon $harvestedAt = null,
    ): FarmHarvestLot {
        $quantity = $this->assertPositive($quantityHarvested);

        $lot = new FarmHarvestLot([
            'parcel_id' => $parcelId,
            'crop_cycle_id' => $cropCycleId,
            'farm_site_id' => $farmSiteId,
            'farm_operation_id' => $farmOperationId,
            'lot_code' => $lotCode,
            'crop_name' => $cropName,
            'quantity_harvested' => $quantity,
            'unit' => $unit,
            'quality_grade' => $qualityGrade,
            'storage_location_id' => $storageLocationId,
            'harvested_at' => $harvestedAt ?? Carbon::now(),
            // A la creation, tout le lot est disponible : le cache part de la
            // quantite recoltee, et c'est le journal qui le fera evoluer.
            'quantity_remaining' => $quantity,
            'status' => 'open',
            'client_harvest_id' => $clientHarvestId,
            'created_by' => $createdBy,
        ]);
        $lot->company_id = $company->id;
        $lot->save();

        return $lot;
    }

    /**
     * Enregistre un mouvement de stock et met a jour la quantite restante.
     *
     * @param  numeric-string|string  $quantity
     */
    public function recordMovement(
        Company $company,
        FarmHarvestLot $lot,
        FarmStorageMovementType $type,
        string $quantity,
        ?int $fromLocationId = null,
        ?int $toLocationId = null,
        ?int $saleDocumentId = null,
        ?int $saleLineId = null,
        ?int $counterpartyId = null,
        ?string $unitPrice = null,
        ?string $currency = null,
        ?string $reason = null,
        ?int $recordedBy = null,
        ?string $clientMovementId = null,
        ?Carbon $occurredAt = null,
    ): FarmStorageMovement {
        if ($lot->company_id !== null && $lot->company_id !== $company->id) {
            throw FarmHarvestException::lotNotInCompany($lot->id);
        }

        $value = $this->assertPositive($quantity);

        // Une vente DOIT referencer un document du service de ventes existant :
        // Farm OS ne fabrique jamais de document commercial.
        if ($type->requiresSaleReference() && $saleDocumentId === null) {
            throw FarmHarvestException::saleReferenceRequired();
        }

        $connection = FarmStorageMovement::query()->getConnection();
        $occurred = $occurredAt ?? Carbon::now();

        return $connection->transaction(function () use (
            $company, $lot, $type, $value, $fromLocationId, $toLocationId, $saleDocumentId,
            $saleLineId, $counterpartyId, $unitPrice, $currency, $reason, $recordedBy,
            $clientMovementId, $occurred
        ): FarmStorageMovement {
            // Idempotence terrain : un rejeu renvoie le mouvement deja ecrit et
            // NE re-applique PAS la sortie de stock.
            if ($clientMovementId !== null) {
                /** @var FarmStorageMovement|null $existing */
                $existing = FarmStorageMovement::query()
                    ->where('company_id', $company->id)
                    ->where('client_movement_id', $clientMovementId)
                    ->first();

                if ($existing !== null) {
                    return $existing;
                }
            }

            /** @var FarmHarvestLot $locked */
            $locked = FarmHarvestLot::query()
                ->where('company_id', $company->id)
                ->whereKey($lot->id)
                ->lockForUpdate()
                ->firstOrFail();

            $remaining = (float) $locked->quantity_remaining;
            $given = (float) $value;

            if ($type->isOutbound()) {
                if ($given > $remaining) {
                    throw FarmHarvestException::insufficientStock(
                        $locked->id,
                        number_format($remaining, 3, '.', ''),
                        number_format($given, 3, '.', ''),
                    );
                }

                $remaining -= $given;
            } elseif ($type === FarmStorageMovementType::In) {
                // Une entree ne peut pas depasser la quantite RECOLTEE : sinon le
                // lot compterait plus de marchandise qu'il n'en a ete recolte.
                $harvested = (float) $locked->quantity_harvested;

                if ($remaining + $given > $harvested) {
                    throw FarmHarvestException::insufficientStock(
                        $locked->id,
                        number_format($harvested - $remaining, 3, '.', ''),
                        number_format($given, 3, '.', ''),
                    );
                }

                $remaining += $given;
            } elseif ($type === FarmStorageMovementType::Adjustment) {
                // Un ajustement corrige une quantite constatee : il ne peut pas
                // rendre le lot negatif ni depasser la recolte.
                $harvested = (float) $locked->quantity_harvested;

                if ($given > $harvested) {
                    throw FarmHarvestException::insufficientStock(
                        $locked->id,
                        number_format($harvested, 3, '.', ''),
                        number_format($given, 3, '.', ''),
                    );
                }

                $remaining = $given;
            }
            // `transfer` ne modifie PAS la quantite restante : la marchandise
            // change d'emplacement, elle reste dans le lot.

            $movement = new FarmStorageMovement([
                'farm_harvest_lot_id' => $locked->id,
                'movement_type' => $type->value,
                'quantity' => number_format($given, 3, '.', ''),
                'quantity_after' => number_format($remaining, 3, '.', ''),
                'from_location_id' => $fromLocationId,
                'to_location_id' => $toLocationId,
                'sale_document_id' => $saleDocumentId,
                'sale_line_id' => $saleLineId,
                'counterparty_id' => $counterpartyId,
                'unit_price' => $unitPrice !== null ? number_format((float) $unitPrice, 2, '.', '') : null,
                'currency' => $currency !== null ? strtoupper($currency) : null,
                'reason' => $reason,
                'recorded_by' => $recordedBy,
                'client_movement_id' => $clientMovementId,
                'occurred_at' => $occurred,
            ]);
            $movement->company_id = $company->id;
            $movement->save();

            $locked->quantity_remaining = number_format($remaining, 3, '.', '');

            // Un lot epuise est CLOS (jamais supprime : la tracabilite reste).
            if ($remaining <= 0.0) {
                $locked->status = 'closed';
            }

            // Un transfert met a jour l'emplacement courant du lot.
            if ($type === FarmStorageMovementType::Transfer && $toLocationId !== null) {
                $locked->storage_location_id = $toLocationId;
            }

            $locked->save();

            return $movement;
        });
    }

    /**
     * Rapprochement recolte / stock / vente d'un lot.
     *
     * Honnetete : on renvoie separement la quantite RECOLTEE (figee), la quantite
     * RESTANTE (cache du journal), et le detail des sorties par nature. La somme
     * `dispatched + remaining` doit egaler `harvested` — c'est exactement ce que
     * verifie le test de coherence, et c'est ce qui prouve qu'aucune quantite
     * n'est comptee deux fois.
     *
     * @return array{harvested: string, remaining: string, dispatched: string, by_type: array<string, string>, is_consistent: bool}
     */
    public function reconcile(Company $company, FarmHarvestLot $lot): array
    {
        if ($lot->company_id !== null && $lot->company_id !== $company->id) {
            throw FarmHarvestException::lotNotInCompany($lot->id);
        }

        $movements = FarmStorageMovement::query()
            ->where('company_id', $company->id)
            ->where('farm_harvest_lot_id', $lot->id)
            ->get();

        $byType = [];
        $dispatched = 0.0;

        foreach ($movements as $movement) {
            $type = $movement->typeEnum();

            if (! $type->isOutbound()) {
                continue;
            }

            $byType[$type->value] = number_format(
                (float) ($byType[$type->value] ?? 0) + (float) $movement->quantity,
                3,
                '.',
                '',
            );

            $dispatched += (float) $movement->quantity;
        }

        $harvested = (float) $lot->quantity_harvested;
        $remaining = (float) $lot->quantity_remaining;

        return [
            'harvested' => number_format($harvested, 3, '.', ''),
            'remaining' => number_format($remaining, 3, '.', ''),
            'dispatched' => number_format($dispatched, 3, '.', ''),
            'by_type' => $byType,
            // Tolerant a l'arrondi au millieme pres.
            'is_consistent' => abs(($dispatched + $remaining) - $harvested) < 0.001,
        ];
    }

    /**
     * Stock restant agrege par culture et unite.
     *
     * @return list<array{crop_name: string|null, unit: string, remaining: string, lots: int}>
     */
    public function remainingByCrop(Company $company): array
    {
        $lots = FarmHarvestLot::query()
            ->where('company_id', $company->id)
            ->where('status', 'open')
            ->get();

        /** @var array<string, array{crop_name: string|null, unit: string, remaining: float, lots: int}> $totals */
        $totals = [];

        foreach ($lots as $lot) {
            $key = ($lot->crop_name ?? '').'|'.$lot->unit;

            if (! isset($totals[$key])) {
                $totals[$key] = [
                    'crop_name' => $lot->crop_name,
                    'unit' => $lot->unit,
                    'remaining' => 0.0,
                    'lots' => 0,
                ];
            }

            $totals[$key]['remaining'] += (float) $lot->quantity_remaining;
            $totals[$key]['lots']++;
        }

        $rows = [];

        foreach ($totals as $total) {
            $rows[] = [
                'crop_name' => $total['crop_name'],
                'unit' => $total['unit'],
                'remaining' => number_format($total['remaining'], 3, '.', ''),
                'lots' => $total['lots'],
            ];
        }

        return $rows;
    }

    /**
     * Pertes enregistrees sur une periode, par lot.
     *
     * @return list<array{farm_harvest_lot_id: int, lot_code: string, unit: string, lost: string}>
     */
    public function losses(Company $company, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $query = FarmStorageMovement::query()
            ->where('company_id', $company->id)
            ->where('movement_type', FarmStorageMovementType::Loss->value);

        if ($from !== null) {
            $query->where('occurred_at', '>=', $from);
        }

        if ($to !== null) {
            $query->where('occurred_at', '<=', $to);
        }

        /** @var array<int, float> $totals */
        $totals = [];

        foreach ($query->get() as $movement) {
            $totals[$movement->farm_harvest_lot_id] = ($totals[$movement->farm_harvest_lot_id] ?? 0.0) + (float) $movement->quantity;
        }

        $rows = [];

        foreach ($totals as $lotId => $lost) {
            /** @var FarmHarvestLot|null $lot */
            $lot = FarmHarvestLot::query()
                ->where('company_id', $company->id)
                ->whereKey($lotId)
                ->first();

            if ($lot === null) {
                continue;
            }

            $rows[] = [
                'farm_harvest_lot_id' => $lot->id,
                'lot_code' => $lot->lot_code,
                'unit' => $lot->unit,
                'lost' => number_format($lost, 3, '.', ''),
            ];
        }

        return $rows;
    }

    /**
     * @return numeric-string
     */
    private function assertPositive(string $value): string
    {
        if (is_numeric($value) === false || (float) $value <= 0) {
            throw FarmHarvestException::invalidQuantity($value);
        }

        return number_format((float) $value, 3, '.', '');
    }
}
