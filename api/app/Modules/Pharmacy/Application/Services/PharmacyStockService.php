<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Application\Services;

use App\Modules\Pharmacy\Domain\Exceptions\PharmacyInsufficientStockException;
use App\Modules\Pharmacy\Domain\Models\PharmacyBatch;
use App\Modules\Pharmacy\Domain\Models\PharmacyProduct;
use App\Modules\Pharmacy\Domain\Models\PharmacyStockMovement;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * PHARMA-003 (#7800) — point d'écriture UNIQUE des quantités de stock
 * d'officine.
 *
 * Chaque opération est TRANSACTIONNELLE avec verrou pessimiste
 * `SELECT ... FOR UPDATE` sur les lots (les opérations concurrentes sont
 * sérialisées — pattern RetailStockService #7673, pas de survente possible).
 * Invariants :
 *   - un lot n'est JAMAIS négatif ;
 *   - tout changement de quantité journalise un PharmacyStockMovement
 *     (append-only) — aucun UPDATE direct des quantités sans mouvement ;
 *   - la délivrance est FEFO (First-Expired-First-Out) et EXCLUT les lots
 *     périmés ; stock insuffisant → PharmacyInsufficientStockException
 *     (422 PHARMACY_INSUFFICIENT_STOCK) et AUCUN effet partiel.
 *
 * Pas de facade Laravel ici (pureté de couche Application, garde #6568) :
 * la connexion est injectée via ConnectionInterface.
 */
final class PharmacyStockService
{
    public function __construct(private readonly ConnectionInterface $connection) {}

    /**
     * Réceptionne une quantité sur un lot (créé s'il n'existe pas encore
     * pour ce (produit, n° de lot)) et journalise un mouvement `receipt`.
     *
     * @throws ValidationException 422 si la quantité n'est pas strictement positive ou si la péremption diverge du lot existant.
     */
    public function receive(
        PharmacyProduct $product,
        string $batchNumber,
        Carbon $expiryDate,
        int $quantity,
        ?string $unitCost = null,
        ?int $supplierId = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?int $employeeId = null,
    ): PharmacyBatch {
        $this->assertPositiveQuantity($quantity);
        $companyId = (string) $product->company_id;

        /** @var PharmacyBatch $batch */
        $batch = $this->connection->transaction(
            function () use ($product, $companyId, $batchNumber, $expiryDate, $quantity, $unitCost, $supplierId, $referenceType, $referenceId, $employeeId): PharmacyBatch {
                /** @var PharmacyBatch|null $existing */
                $existing = PharmacyBatch::query()
                    ->where('company_id', $companyId)
                    ->where('product_id', (int) $product->id)
                    ->where('batch_number', $batchNumber)
                    ->lockForUpdate()
                    ->first();

                if ($existing instanceof PharmacyBatch) {
                    if (! $existing->expiry_date->isSameDay($expiryDate)) {
                        throw ValidationException::withMessages([
                            'expiry_date' => 'Le lot existe deja avec une autre date de peremption.',
                        ]);
                    }

                    $existing->forceFill([
                        'quantity' => $existing->quantity + $quantity,
                        'unit_cost' => $unitCost ?? $existing->unit_cost,
                        'supplier_id' => $supplierId ?? $existing->supplier_id,
                        'received_at' => Carbon::now(),
                    ])->save();

                    $batch = $existing;
                } else {
                    $batch = PharmacyBatch::query()->create([
                        'company_id' => $companyId,
                        'product_id' => (int) $product->id,
                        'supplier_id' => $supplierId,
                        'batch_number' => $batchNumber,
                        'expiry_date' => $expiryDate->toDateString(),
                        'quantity' => $quantity,
                        'unit_cost' => $unitCost,
                        'received_at' => Carbon::now(),
                    ]);
                }

                $this->recordMovement($batch, PharmacyStockMovement::TYPE_RECEIPT, $quantity, null, $referenceType, $referenceId, $employeeId);

                return $batch;
            }
        );

        return $batch;
    }

    /**
     * Ajustement d'inventaire sur un lot (delta signé, raison OBLIGATOIRE).
     * Journalise un mouvement `adjustment` (ou `expiry_writeoff` pour le
     * retrait d'un lot périmé). Un lot ne devient jamais négatif.
     *
     * @throws ValidationException 422 si le delta est nul ou rendrait le lot négatif.
     */
    public function adjust(
        PharmacyBatch $batch,
        int $quantityDelta,
        string $reason,
        ?int $employeeId = null,
        string $type = PharmacyStockMovement::TYPE_ADJUSTMENT,
    ): PharmacyBatch {
        if ($quantityDelta === 0) {
            throw ValidationException::withMessages([
                'quantity_delta' => 'Un ajustement de zero est sans objet.',
            ]);
        }

        /** @var PharmacyBatch $adjusted */
        $adjusted = $this->connection->transaction(
            function () use ($batch, $quantityDelta, $reason, $employeeId, $type): PharmacyBatch {
                /** @var PharmacyBatch $locked */
                $locked = PharmacyBatch::query()
                    ->where('company_id', (string) $batch->company_id)
                    ->lockForUpdate()
                    ->findOrFail((int) $batch->id);

                $newQuantity = $locked->quantity + $quantityDelta;

                if ($newQuantity < 0) {
                    throw ValidationException::withMessages([
                        'quantity_delta' => 'Stock insuffisant : le mouvement rendrait le lot negatif.',
                    ]);
                }

                $locked->forceFill(['quantity' => $newQuantity])->save();

                $this->recordMovement($locked, $type, $quantityDelta, $reason, null, null, $employeeId);

                return $locked;
            }
        );

        return $adjusted;
    }

    /**
     * Délivrance FEFO (First-Expired-First-Out) : consomme les lots NON
     * périmés dans l'ordre des péremptions les plus proches, en transaction
     * avec verrou pessimiste. Refus EN BLOC si le stock non périmé ne couvre
     * pas la quantité (aucun effet partiel).
     *
     * @return list<array{batch_id: int, quantity: int}> allocations consommées (pour ré-crédit lors d'une annulation)
     *
     * @throws PharmacyInsufficientStockException 422 PHARMACY_INSUFFICIENT_STOCK
     */
    public function dispenseFefo(
        PharmacyProduct $product,
        int $quantity,
        string $type = PharmacyStockMovement::TYPE_SALE,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?int $employeeId = null,
    ): array {
        $this->assertPositiveQuantity($quantity);
        $companyId = (string) $product->company_id;

        /** @var list<array{batch_id: int, quantity: int}> $allocations */
        $allocations = $this->connection->transaction(
            function () use ($product, $companyId, $quantity, $type, $referenceType, $referenceId, $employeeId): array {
                /** @var \Illuminate\Database\Eloquent\Collection<int, PharmacyBatch> $batches */
                $batches = PharmacyBatch::query()
                    ->where('company_id', $companyId)
                    ->where('product_id', (int) $product->id)
                    ->where('quantity', '>', 0)
                    ->whereDate('expiry_date', '>=', Carbon::today())
                    ->orderBy('expiry_date')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                $available = (int) $batches->sum('quantity');

                if ($available < $quantity) {
                    throw new PharmacyInsufficientStockException;
                }

                $remaining = $quantity;
                $allocations = [];

                foreach ($batches as $batch) {
                    if ($remaining === 0) {
                        break;
                    }

                    $taken = min($batch->quantity, $remaining);
                    $batch->forceFill(['quantity' => $batch->quantity - $taken])->save();

                    $this->recordMovement($batch, $type, -$taken, null, $referenceType, $referenceId, $employeeId);

                    $allocations[] = ['batch_id' => (int) $batch->id, 'quantity' => $taken];
                    $remaining -= $taken;
                }

                return $allocations;
            }
        );

        return $allocations;
    }

    /**
     * Ré-crédite un lot précis (annulation de vente : mouvement `return`
     * sur les lots d'origine de la délivrance).
     */
    public function credit(
        PharmacyBatch $batch,
        int $quantity,
        string $type = PharmacyStockMovement::TYPE_RETURN,
        ?string $reason = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?int $employeeId = null,
    ): PharmacyBatch {
        $this->assertPositiveQuantity($quantity);

        /** @var PharmacyBatch $credited */
        $credited = $this->connection->transaction(
            function () use ($batch, $quantity, $type, $reason, $referenceType, $referenceId, $employeeId): PharmacyBatch {
                /** @var PharmacyBatch $locked */
                $locked = PharmacyBatch::query()
                    ->where('company_id', (string) $batch->company_id)
                    ->lockForUpdate()
                    ->findOrFail((int) $batch->id);

                $locked->forceFill(['quantity' => $locked->quantity + $quantity])->save();

                $this->recordMovement($locked, $type, $quantity, $reason, $referenceType, $referenceId, $employeeId);

                return $locked;
            }
        );

        return $credited;
    }

    /**
     * Stock disponible d'un produit : somme des lots NON périmés.
     */
    public function availableQuantity(PharmacyProduct $product): int
    {
        return (int) PharmacyBatch::query()
            ->where('company_id', (string) $product->company_id)
            ->where('product_id', (int) $product->id)
            ->whereDate('expiry_date', '>=', Carbon::today())
            ->sum('quantity');
    }

    private function recordMovement(
        PharmacyBatch $batch,
        string $type,
        int $quantityDelta,
        ?string $reason,
        ?string $referenceType,
        ?int $referenceId,
        ?int $employeeId,
    ): void {
        PharmacyStockMovement::query()->create([
            'company_id' => (string) $batch->company_id,
            'product_id' => $batch->product_id,
            'batch_id' => (int) $batch->id,
            'type' => $type,
            'quantity_delta' => $quantityDelta,
            'reason' => $reason,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'created_by_employee_id' => $employeeId,
        ]);
    }

    /**
     * @throws ValidationException
     */
    private function assertPositiveQuantity(int $quantity): void
    {
        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => 'La quantite doit etre strictement positive.',
            ]);
        }
    }
}
