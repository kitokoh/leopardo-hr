<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Infrastructure\Services;

use App\Modules\RestaurantManager\Domain\Enums\StockMovementReason;
use App\Modules\RestaurantManager\Domain\Models\RestaurantReceiving;
use App\Modules\RestaurantManager\Domain\Models\RestaurantStockLevel;
use Illuminate\Support\Facades\DB;

/**
 * RESTO-502 (#6201) / RESTO-503 (#6202) — Réception de marchandises.
 *
 * Crée la réception (référence unique par tenant — rejeu idempotent), applique
 * les entrées de stock (mouvements `receiving` verrouillés, jamais négatifs)
 * puis recalcule le coût moyen pondéré de chaque ingrédient :
 *   new_avg = (qty_avant × avg_avant + qty_reçue × prix_unitaire) / qty_après
 */
final class ReceivingService
{
    public function __construct(private readonly StockMovementService $movements)
    {
    }

    /**
     * @param  array<int, array{ingredient_id: int, quantity: float, unit_price_minor: int}>  $lines
     */
    public function receive(
        string $companyId,
        int $branchId,
        array $lines,
        ?int $purchaseOrderId = null,
        ?int $supplierId = null,
        ?string $note = null,
        ?string $reference = null,
        ?int $userId = null,
    ): RestaurantReceiving {
        return DB::transaction(function () use ($companyId, $branchId, $lines, $purchaseOrderId, $supplierId, $note, $reference, $userId): RestaurantReceiving {
            $receiving = RestaurantReceiving::query()->create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'purchase_order_id' => $purchaseOrderId,
                'supplier_id' => $supplierId,
                'reference' => $reference, // null → généré (RCV-…)
                'received_at' => now(),
                'note_redacted' => $note,
            ]);

            foreach ($lines as $line) {
                $quantity = (float) $line['quantity'];

                if ($quantity <= 0) {
                    abort(422, 'Receiving line quantities must be strictly positive.');
                }

                $level = $this->movements->apply(
                    companyId: $companyId,
                    branchId: $branchId,
                    ingredientId: (int) $line['ingredient_id'],
                    quantityDelta: $quantity,
                    reason: StockMovementReason::RECEIVING,
                    referenceType: RestaurantReceiving::class,
                    referenceId: $receiving->id,
                    note: $note,
                    userId: $userId,
                );

                $this->updateAverageCost($level, $quantity, (int) $line['unit_price_minor']);
            }

            return $receiving;
        });
    }

    /**
     * Coût moyen pondéré après réception (minor units).
     */
    private function updateAverageCost(RestaurantStockLevel $level, float $receivedQty, int $unitPriceMinor): void
    {
        $currentQty = (float) $level->quantity; // inclut déjà la quantité reçue
        $previousQty = $currentQty - $receivedQty;

        if ($previousQty <= 0) {
            $newAverage = $unitPriceMinor;
        } else {
            $previousCost = (int) ($level->avg_cost_minor ?? 0);
            $newAverage = (int) round((($previousQty * $previousCost) + ($receivedQty * $unitPriceMinor)) / $currentQty);
        }

        $level->forceFill(['avg_cost_minor' => $newAverage])->save();
    }
}
