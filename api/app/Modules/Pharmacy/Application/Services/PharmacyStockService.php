<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Application\Services;

use App\Exceptions\DomainException;
use App\Modules\Pharmacy\Domain\Exceptions\PharmacyInsufficientStockException;
use App\Modules\Pharmacy\Domain\Models\PharmacyBatch;
use App\Modules\Pharmacy\Domain\Models\PharmacyStockMovement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Service de stock d'officine — PHARMA-003 (#7800).
 *
 * Invariants :
 *   - TOUT changement de quantité d'un lot trace exactement un mouvement
 *     dans le journal immuable `pharmacy_stock_movements` ;
 *   - aucun lot ne devient négatif (exception + CHECK SQL) ;
 *   - la délivrance est FEFO (First-Expired-First-Out), multi-lots, refuse
 *     les lots périmés et le stock insuffisant (PHARMACY_INSUFFICIENT_STOCK) ;
 *   - transactionnel + verrouillage pessimiste (`lockForUpdate`) : pas de
 *     survente concurrente.
 */
class PharmacyStockService
{
    /**
     * Réception d'une quantité sur un lot (créé ou incrémenté) + mouvement
     * `receipt`. Utilisé par les réceptions de commandes (PHARMA-004).
     *
     * @param  numeric-string|string  $unitCost
     */
    public function receive(
        string $companyId,
        int $productId,
        string $batchNumber,
        string $expiryDate,
        int $quantity,
        string $unitCost = '0',
        ?int $supplierId = null,
        ?int $employeeId = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
    ): PharmacyBatch {
        if ($quantity <= 0) {
            throw new DomainException((string) __('pharmacy.quantity_received_positive'), 422, 'PHARMACY_INVALID_QUANTITY');
        }

        return DB::transaction(function () use ($companyId, $productId, $batchNumber, $expiryDate, $quantity, $unitCost, $supplierId, $employeeId, $referenceType, $referenceId): PharmacyBatch {
            /** @var PharmacyBatch|null $batch */
            $batch = PharmacyBatch::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('product_id', $productId)
                ->where('batch_number', $batchNumber)
                ->lockForUpdate()
                ->first();

            if ($batch === null) {
                $batch = new PharmacyBatch([
                    'product_id' => $productId,
                    'batch_number' => $batchNumber,
                    'expiry_date' => $expiryDate,
                    'quantity' => 0,
                    'unit_cost' => $unitCost,
                    'supplier_id' => $supplierId,
                    'received_at' => Carbon::now(),
                ]);
                $batch->company_id = $companyId;
            }

            $batch->quantity += $quantity;
            $batch->unit_cost = $unitCost;
            $batch->received_at = Carbon::now();
            if ($supplierId !== null) {
                $batch->supplier_id = $supplierId;
            }
            $batch->save();

            $this->logMovement($companyId, $productId, (int) $batch->id, 'receipt', $quantity, null, $referenceType, $referenceId, $employeeId);

            return $batch;
        });
    }

    /**
     * Ajustement d'inventaire (raison OBLIGATOIRE, delta signé). Refuse de
     * rendre un lot négatif. `expiry_writeoff` pour retirer un lot périmé.
     */
    public function adjust(
        string $companyId,
        int $batchId,
        int $quantityDelta,
        string $reason,
        ?int $employeeId = null,
        string $type = 'adjustment',
    ): PharmacyBatch {
        if ($quantityDelta === 0) {
            throw new DomainException((string) __('pharmacy.adjustment_delta_nonzero'), 422, 'PHARMACY_INVALID_QUANTITY');
        }

        if (trim($reason) === '') {
            throw new DomainException((string) __('pharmacy.adjustment_reason_required'), 422, 'PHARMACY_REASON_REQUIRED');
        }

        if (! in_array($type, ['adjustment', 'expiry_writeoff'], true)) {
            throw new DomainException((string) __('pharmacy.invalid_adjustment_type'), 422, 'PHARMACY_INVALID_MOVEMENT_TYPE');
        }

        return DB::transaction(function () use ($companyId, $batchId, $quantityDelta, $reason, $employeeId, $type): PharmacyBatch {
            /** @var PharmacyBatch|null $batch */
            $batch = PharmacyBatch::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->whereKey($batchId)
                ->lockForUpdate()
                ->first();

            if ($batch === null) {
                throw new DomainException((string) __('pharmacy.batch_not_found'), 404, 'PHARMACY_BATCH_NOT_FOUND');
            }

            $newQuantity = $batch->quantity + $quantityDelta;

            if ($newQuantity < 0) {
                throw new PharmacyInsufficientStockException($batch->product_id, abs($quantityDelta), $batch->quantity);
            }

            $batch->quantity = $newQuantity;
            $batch->save();

            $this->logMovement($companyId, $batch->product_id, (int) $batch->id, $type, $quantityDelta, $reason, null, null, $employeeId);

            return $batch;
        });
    }

    /**
     * Délivrance FEFO (First-Expired-First-Out) multi-lots : consomme les
     * lots NON périmés dans l'ordre des péremptions les plus proches.
     * Stock insuffisant → PHARMACY_INSUFFICIENT_STOCK (transaction annulée,
     * aucun effet partiel).
     *
     * @return list<array{batch_id: int, quantity: int, unit_cost: string}>
     */
    public function dispenseFefo(
        string $companyId,
        int $productId,
        int $quantity,
        ?int $employeeId = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        string $type = 'sale',
    ): array {
        if ($quantity <= 0) {
            throw new DomainException((string) __('pharmacy.quantity_dispensed_positive'), 422, 'PHARMACY_INVALID_QUANTITY');
        }

        if (! in_array($type, ['sale', 'adjustment'], true)) {
            throw new DomainException((string) __('pharmacy.invalid_dispense_type'), 422, 'PHARMACY_INVALID_MOVEMENT_TYPE');
        }

        return DB::transaction(function () use ($companyId, $productId, $quantity, $employeeId, $referenceType, $referenceId, $type): array {
            /** @var \Illuminate\Database\Eloquent\Collection<int, PharmacyBatch> $batches */
            $batches = PharmacyBatch::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('product_id', $productId)
                ->where('quantity', '>', 0)
                ->whereDate('expiry_date', '>=', Carbon::today())
                ->orderBy('expiry_date')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $available = (int) $batches->sum('quantity');

            if ($available < $quantity) {
                throw new PharmacyInsufficientStockException($productId, $quantity, $available);
            }

            $remaining = $quantity;
            $dispensed = [];

            foreach ($batches as $batch) {
                if ($remaining <= 0) {
                    break;
                }

                $take = min($batch->quantity, $remaining);
                $batch->quantity -= $take;
                $batch->save();

                $this->logMovement($companyId, $productId, (int) $batch->id, $type, -$take, null, $referenceType, $referenceId, $employeeId);

                $dispensed[] = [
                    'batch_id' => (int) $batch->id,
                    'quantity' => $take,
                    'unit_cost' => (string) $batch->unit_cost,
                ];
                $remaining -= $take;
            }

            return $dispensed;
        });
    }

    /**
     * Ré-crédite des lots d'origine (annulation de vente PHARMA-005) via des
     * mouvements `return` — jamais d'effacement des mouvements d'origine.
     *
     * @param  list<array{batch_id: int, quantity: int}>  $lines
     */
    public function returnToBatches(
        string $companyId,
        array $lines,
        string $reason,
        ?int $employeeId = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
    ): void {
        DB::transaction(function () use ($companyId, $lines, $reason, $employeeId, $referenceType, $referenceId): void {
            foreach ($lines as $line) {
                if ($line['quantity'] <= 0) {
                    continue;
                }

                /** @var PharmacyBatch|null $batch */
                $batch = PharmacyBatch::withoutGlobalScopes()
                    ->where('company_id', $companyId)
                    ->whereKey($line['batch_id'])
                    ->lockForUpdate()
                    ->first();

                if ($batch === null) {
                    throw new DomainException((string) __('pharmacy.batch_not_found_return'), 404, 'PHARMACY_BATCH_NOT_FOUND');
                }

                $batch->quantity += $line['quantity'];
                $batch->save();

                $this->logMovement($companyId, $batch->product_id, (int) $batch->id, 'return', $line['quantity'], $reason, $referenceType, $referenceId, $employeeId);
            }
        });
    }

    /**
     * Stock disponible d'un produit = somme des lots NON périmés.
     */
    public function availableQuantity(string $companyId, int $productId): int
    {
        return (int) PharmacyBatch::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('product_id', $productId)
            ->whereDate('expiry_date', '>=', Carbon::today())
            ->sum('quantity');
    }

    private function logMovement(
        string $companyId,
        int $productId,
        int $batchId,
        string $type,
        int $quantityDelta,
        ?string $reason,
        ?string $referenceType,
        ?int $referenceId,
        ?int $employeeId,
    ): void {
        $movement = new PharmacyStockMovement([
            'product_id' => $productId,
            'batch_id' => $batchId,
            'type' => $type,
            'quantity_delta' => $quantityDelta,
            'reason' => $reason,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'created_by_employee_id' => $employeeId,
        ]);
        $movement->company_id = $companyId;
        $movement->save();
    }
}
