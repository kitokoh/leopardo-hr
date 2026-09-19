<?php

declare(strict_types=1);

namespace App\Modules\Retail\Application\Services;

use App\Modules\Retail\Domain\Enums\RetailStockReasonCode;
use App\Modules\Retail\Domain\Models\RetailInventoryMovement;
use App\Modules\Retail\Domain\Models\RetailLocation;
use App\Modules\Retail\Domain\Models\RetailProduct;
use App\Modules\Retail\Domain\Models\RetailStockLevel;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Validation\ValidationException;

/**
 * BC-17 RETAIL (#7673) — Point d'ecriture UNIQUE des quantites de stock.
 *
 * Chaque mouvement est applique EN TRANSACTION avec verrou de ligne
 * `SELECT ... FOR UPDATE` sur le niveau de stock (les mouvements concurrents
 * sont serialises par le verrou — pattern RestaurantStockService #6198).
 * Invariant : le stock n'est JAMAIS negatif — un delta qui rendrait la
 * quantite negative est refuse (ValidationException → 422) et rien n'est
 * persiste. Chaque application journalise un RetailInventoryMovement
 * (delta signe, reason_code controle, reference polymorphe) pour la
 * tracabilite.
 *
 * Pas de facade Laravel ici (purete de couche Application, garde #6568) :
 * la connexion est injectee via ConnectionInterface.
 */
final class RetailStockService
{
    public function __construct(private readonly ConnectionInterface $connection) {}

    /**
     * Applique un mouvement de stock signe sur (emplacement, produit) du
     * tenant de l'emplacement, et retourne le mouvement cree (le niveau de
     * stock a jour est accessible via $movement->stockLevel).
     *
     * @throws ValidationException 422 si le delta rendrait la quantite negative.
     */
    public function applyMovement(
        RetailLocation $location,
        RetailProduct $product,
        float $quantityDelta,
        RetailStockReasonCode $reasonCode,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $note = null,
        ?int $userId = null,
    ): RetailInventoryMovement {
        $companyId = (string) $location->company_id;

        /** @var RetailInventoryMovement $movement */
        $movement = $this->connection->transaction(
            function () use ($location, $product, $companyId, $quantityDelta, $reasonCode, $referenceType, $referenceId, $note, $userId): RetailInventoryMovement {
                $level = $this->lockedLevel($companyId, (int) $location->id, (int) $product->id);

                $newQuantity = round((float) $level->quantity + $quantityDelta, 3);

                if ($newQuantity < 0.0) {
                    throw ValidationException::withMessages([
                        'quantity_delta' => 'Stock insuffisant : le mouvement rendrait la quantite negative.',
                    ]);
                }

                $level->forceFill(['quantity' => number_format($newQuantity, 3, '.', '')])->save();

                return RetailInventoryMovement::query()->create([
                    'company_id' => $companyId,
                    'location_id' => (int) $location->id,
                    'product_id' => (int) $product->id,
                    'stock_level_id' => (int) $level->id,
                    'quantity_delta' => number_format($quantityDelta, 3, '.', ''),
                    'reason_code' => $reasonCode->value,
                    'reference_type' => $referenceType,
                    'reference_id' => $referenceId,
                    'note' => $note,
                    'user_id' => $userId,
                ]);
            }
        );

        return $movement;
    }

    /**
     * Charge (ou cree) le niveau de stock avec verrou de ligne
     * `SELECT ... FOR UPDATE` — a appeler DANS une transaction.
     */
    private function lockedLevel(string $companyId, int $locationId, int $productId): RetailStockLevel
    {
        /** @var RetailStockLevel|null $level */
        $level = RetailStockLevel::query()
            ->where('company_id', $companyId)
            ->where('location_id', $locationId)
            ->where('product_id', $productId)
            ->lockForUpdate()
            ->first();

        if ($level instanceof RetailStockLevel) {
            return $level;
        }

        // Ligne absente : creation dans la transaction courante (la ligne
        // inseree est verrouillee par l'INSERT jusqu'au commit).
        /** @var RetailStockLevel $created */
        $created = RetailStockLevel::query()->firstOrCreate([
            'company_id' => $companyId,
            'location_id' => $locationId,
            'product_id' => $productId,
        ], [
            'quantity' => '0.000',
        ]);

        return $created;
    }
}
