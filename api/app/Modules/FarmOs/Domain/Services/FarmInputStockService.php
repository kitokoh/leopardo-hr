<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Domain\Services;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FarmOs\Domain\Enums\FarmStockMovementType;
use App\Modules\FarmOs\Domain\Exceptions\FarmStockException;
use App\Modules\FarmOs\Domain\Models\FarmInput;
use App\Modules\FarmOs\Domain\Models\FarmInputLot;
use App\Modules\FarmOs\Domain\Models\FarmInputStockMovement;
use Illuminate\Support\Carbon;

/**
 * Farm OS — T2.1 Journal de stock d'intrant.
 *
 * Invariants (exigences de la mission) :
 *   - TOUT changement de quantité d'un lot trace EXACTEMENT un mouvement dans
 *     le journal immuable `farm_input_stock_movements` ;
 *   - aucun lot ne devient NÉGATIF (exception + contrôle applicatif) ;
 *   - la quantité d'un mouvement est toujours POSITIVE : le sens est porté par
 *     `movement_type`, jamais par le signe ;
 *   - IDEMPOTENT : le rejeu d'une écriture mobile/hors ligne portant le même
 *     `client_movement_id` renvoie le mouvement existant au lieu d'en créer un
 *     second (contrainte unique `company_id + client_movement_id`) ;
 *   - TRANSACTIONNEL + verrouillage pessimiste (`lockForUpdate`) : pas de
 *     surconsommation concurrente.
 *
 * Pureté des couches (issue #6568) : aucune façade Laravel ici. La transaction
 * est portée par la connexion du modèle (`getConnection()`) et les noms de
 * tables viennent des modèles (`getTable()`) — `DB` et `Schema` sont réservés
 * aux couches Interfaces/Infrastructure.
 */
final class FarmInputStockService
{
    /**
     * Réception d'une quantité sur un lot (créé ou incrémenté) + mouvement
     * `receipt`.
     *
     * @param  numeric-string|string  $quantity
     */
    public function receive(
        Company $company,
        FarmInput $input,
        string $lotNumber,
        string $quantity,
        ?string $expiryDate = null,
        ?int $supplierId = null,
        ?int $recordedBy = null,
        ?string $clientMovementId = null,
        ?Carbon $occurredAt = null,
    ): FarmInputStockMovement {
        $this->assertInputBelongsToCompany($company, $input);
        $amount = $this->assertPositive($quantity);

        return $this->record(
            $company,
            $input,
            FarmStockMovementType::Receipt,
            $amount,
            $lotNumber,
            $expiryDate,
            $supplierId,
            $recordedBy,
            $clientMovementId,
            $occurredAt,
        );
    }

    /**
     * Sortie d'une quantité d'un lot (consommation, vente, perte) + mouvement
     * `issue`. Refuse un stock insuffisant : aucun lot ne devient négatif.
     *
     * @param  numeric-string|string  $quantity
     */
    public function issue(
        Company $company,
        FarmInput $input,
        string $lotNumber,
        string $quantity,
        ?int $farmOperationId = null,
        ?int $parcelId = null,
        ?string $reason = null,
        ?int $recordedBy = null,
        ?string $clientMovementId = null,
        ?Carbon $occurredAt = null,
    ): FarmInputStockMovement {
        $this->assertInputBelongsToCompany($company, $input);
        $amount = $this->assertPositive($quantity);

        return $this->record(
            $company,
            $input,
            FarmStockMovementType::Issue,
            $amount,
            $lotNumber,
            null,
            null,
            $recordedBy,
            $clientMovementId,
            $occurredAt,
            $farmOperationId,
            $parcelId,
            $reason,
        );
    }

    /**
     * Ajustement d'inventaire : la quantité cible est ABSOLUE (pas un delta).
     * Le mouvement enregistré porte la valeur absolue de l'écart, et
     * `quantity_after` porte la cible — c'est lui qui dit si le stock monte ou
     * descend (un ajustement est ambigu par nature).
     *
     * @param  numeric-string|string  $targetQuantity
     */
    public function adjust(
        Company $company,
        FarmInput $input,
        string $lotNumber,
        string $targetQuantity,
        ?string $reason = null,
        ?int $recordedBy = null,
        ?string $clientMovementId = null,
        ?Carbon $occurredAt = null,
    ): FarmInputStockMovement {
        $this->assertInputBelongsToCompany($company, $input);
        $target = $this->assertNotNegative($targetQuantity);

        return $this->record(
            $company,
            $input,
            FarmStockMovementType::Adjustment,
            $target,
            $lotNumber,
            null,
            null,
            $recordedBy,
            $clientMovementId,
            $occurredAt,
            null,
            null,
            $reason,
            true,
        );
    }

    /**
     * Cœur transactionnel : verrouille le lot, calcule la quantité résultante,
     * refuse un stock négatif, écrit le lot puis le mouvement.
     *
     * @param  numeric-string|string  $amount
     */
    private function record(
        Company $company,
        FarmInput $input,
        FarmStockMovementType $type,
        string $amount,
        string $lotNumber,
        ?string $expiryDate,
        ?int $supplierId,
        ?int $recordedBy,
        ?string $clientMovementId,
        ?Carbon $occurredAt,
        ?int $farmOperationId = null,
        ?int $parcelId = null,
        ?string $reason = null,
        bool $isAbsoluteTarget = false,
    ): FarmInputStockMovement {
        $connection = FarmInputStockMovement::query()->getConnection();
        $now = Carbon::now();
        $occurred = $occurredAt ?? $now;

        return $connection->transaction(function () use (
            $company, $input, $type, $amount, $lotNumber, $expiryDate, $supplierId,
            $recordedBy, $clientMovementId, $occurred, $farmOperationId, $parcelId,
            $reason, $isAbsoluteTarget, $now
        ): FarmInputStockMovement {
            // Idempotence terrain : un rejeu renvoie le mouvement déjà écrit.
            if ($clientMovementId !== null) {
                /** @var FarmInputStockMovement|null $existing */
                $existing = FarmInputStockMovement::query()
                    ->where('company_id', $company->id)
                    ->where('client_movement_id', $clientMovementId)
                    ->first();

                if ($existing !== null) {
                    return $existing;
                }
            }

            /** @var FarmInputLot|null $lot */
            $lot = FarmInputLot::query()
                ->where('company_id', $company->id)
                ->where('farm_input_id', $input->id)
                ->where('lot_number', $lotNumber)
                ->lockForUpdate()
                ->first();

            $current = $lot !== null ? (float) $lot->quantity : 0.0;
            $delta = (float) $amount;

            if ($isAbsoluteTarget) {
                $after = $delta;
                $movementQuantity = abs($after - $current);
            } else {
                $after = $type === FarmStockMovementType::Receipt
                    ? $current + $delta
                    : $current - $delta;
                $movementQuantity = $delta;
            }

            if ($after < 0) {
                // `??` supprime déjà le déréférencement sur null : le nullsafe
                // `?->` serait redondant (PHPStan strict level 8).
                throw FarmStockException::insufficientStock(
                    $lot->id ?? 0,
                    number_format($current, 3, '.', ''),
                    number_format($delta, 3, '.', ''),
                );
            }

            if ($lot === null) {
                // Une sortie ou un ajustement sur un lot inexistant n'a pas de
                // sens : seul un `receipt` peut créer le lot.
                if ($type !== FarmStockMovementType::Receipt) {
                    throw FarmStockException::lotRequiredForIssue();
                }

                $lot = new FarmInputLot([
                    'farm_input_id' => $input->id,
                    'lot_number' => $lotNumber,
                    'expiry_date' => $expiryDate,
                    'quantity' => 0,
                    'supplier_id' => $supplierId,
                    'received_at' => $now,
                ]);
                $lot->company_id = $company->id;
            }

            $lot->quantity = number_format($after, 3, '.', '');
            if ($expiryDate !== null) {
                $lot->expiry_date = Carbon::parse($expiryDate);
            }
            $lot->save();

            $movement = new FarmInputStockMovement([
                'farm_input_id' => $input->id,
                'farm_input_lot_id' => $lot->id,
                'movement_type' => $type->value,
                'quantity' => number_format($movementQuantity, 3, '.', ''),
                'quantity_after' => number_format($after, 3, '.', ''),
                'farm_operation_id' => $farmOperationId,
                'parcel_id' => $parcelId,
                'reason' => $reason,
                'recorded_by' => $recordedBy,
                'client_movement_id' => $clientMovementId,
                'occurred_at' => $occurred,
            ]);
            $movement->company_id = $company->id;
            $movement->save();

            return $movement;
        });
    }

    private function assertInputBelongsToCompany(Company $company, FarmInput $input): void
    {
        if ($input->company_id !== null && $input->company_id !== $company->id) {
            throw FarmStockException::inputNotInCompany($input->id);
        }
    }

    /**
     * @return numeric-string
     */
    private function assertPositive(string $quantity): string
    {
        if (is_numeric($quantity) === false || (float) $quantity <= 0) {
            throw FarmStockException::quantityMustBePositive($quantity);
        }

        return number_format((float) $quantity, 3, '.', '');
    }

    /**
     * @return numeric-string
     */
    private function assertNotNegative(string $quantity): string
    {
        if (is_numeric($quantity) === false || (float) $quantity < 0) {
            throw FarmStockException::quantityMustBePositive($quantity);
        }

        return number_format((float) $quantity, 3, '.', '');
    }
}
