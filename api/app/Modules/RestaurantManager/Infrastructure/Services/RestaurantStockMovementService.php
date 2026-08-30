<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Infrastructure\Services;

use App\Modules\RestaurantManager\Domain\Contracts\RestaurantStockLevelRepositoryInterface;
use App\Modules\RestaurantManager\Domain\Enums\StockMovementReason;
use App\Modules\RestaurantManager\Domain\Exceptions\RestaurantStockException;
use App\Modules\RestaurantManager\Domain\Models\RestaurantInventoryMovement;
use App\Modules\RestaurantManager\Domain\Models\RestaurantStockLevel;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * RESTO-501 (#6200) / RESTO-411 (#6198) — Service des mouvements de stock.
 *
 * Source de vérité de toute mutation du stock : les contrôleurs du module
 * (mouvements manuels, réceptions, inventaires, décrément à la vente) passent
 * par `apply()` qui :
 *   - verrouille la ligne de stock (`SELECT ... FOR UPDATE`, repository #6180) ;
 *   - refuse un stock négatif (invariant métier spec §1.3 — « jamais négatif ») ;
 *   - recalcule le coût moyen pondéré quand un prix unitaire est fourni
 *     (réceptions, RESTO-503) ;
 *   - journalise un mouvement tracé (raison, référence polymorphe, auteur).
 *
 * Toute mutation est transactionnelle et idempotente côté consommateur :
 * les références métier (bon de commande, réception, inventaire) portent des
 * clés d'unicité qui empêchent le double-apply.
 */
final class RestaurantStockMovementService
{
    public function __construct(
        private readonly RestaurantStockLevelRepositoryInterface $stockLevels,
    ) {
    }

    /**
     * Applique un mouvement de stock et renvoie le mouvement journalisé.
     *
     * @param  string  $quantityDelta  delta signé (ex. "12.500", "-3.000")
     * @param  int|null  $unitPriceMinor  prix unitaire en minor units — recalcul du coût moyen (delta > 0 uniquement)
     */
    public function apply(
        string $companyId,
        int $branchId,
        int $ingredientId,
        string $quantityDelta,
        StockMovementReason $reason,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $noteRedacted = null,
        ?int $userId = null,
        ?int $unitPriceMinor = null,
    ): RestaurantInventoryMovement {
        if (bccomp($quantityDelta, '0', 3) === 0) {
            throw new RestaurantStockException('Le mouvement doit avoir un delta non nul.');
        }

        /** @var RestaurantInventoryMovement $movement */
        $movement = DB::transaction(function () use (
            $companyId,
            $branchId,
            $ingredientId,
            $quantityDelta,
            $reason,
            $referenceType,
            $referenceId,
            $noteRedacted,
            $userId,
            $unitPriceMinor,
        ): RestaurantInventoryMovement {
            $level = $this->stockLevels->lockForUpdateForIngredient($ingredientId, $branchId, $companyId);

            if ($level === null) {
                $level = RestaurantStockLevel::query()->create([
                    'company_id' => $companyId,
                    'branch_id' => $branchId,
                    'ingredient_id' => $ingredientId,
                    'quantity' => 0,
                ]);
            }

            $newQuantity = bcadd((string) $level->quantity, $quantityDelta, 3);

            if (bccomp($newQuantity, '0', 3) < 0) {
                throw new RestaurantStockException(
                    'Stock insuffisant pour l\'ingrédient #'.$ingredientId.' (stock demandé : '.$newQuantity.').',
                );
            }

            if ($unitPriceMinor !== null && bccomp($quantityDelta, '0', 3) > 0) {
                $level->avg_cost_minor = $this->recomputeWeightedAverageCost(
                    (string) $level->quantity,
                    $level->avg_cost_minor,
                    $quantityDelta,
                    $unitPriceMinor,
                );
            }

            $level->quantity = $newQuantity;
            $level->save();

            return RestaurantInventoryMovement::query()->create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'ingredient_id' => $ingredientId,
                'stock_level_id' => $level->id,
                'quantity_delta' => $quantityDelta,
                'reason_code' => $reason->value,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'note_redacted' => $noteRedacted,
                'user_id' => $userId,
            ]);
        });

        return $movement;
    }

    /**
     * Coût moyen pondéré : (stock × coût moyen + quantité × prix) / (stock + quantité).
     * Arrondi à l'unité mineure la plus proche (les montants restent entiers).
     */
    private function recomputeWeightedAverageCost(
        string $currentQuantity,
        ?int $currentAvgCostMinor,
        string $deltaQuantity,
        int $unitPriceMinor,
    ): int {
        $currentQty = $currentQuantity === '' ? '0' : $currentQuantity;
        $currentCost = (string) ($currentAvgCostMinor ?? 0);
        $numerator = bcadd(
            bcmul($currentQty, $currentCost, 6),
            bcmul($deltaQuantity, (string) $unitPriceMinor, 6),
            6,
        );
        $denominator = bcadd($currentQty, $deltaQuantity, 6);

        if (bccomp($denominator, '0', 6) === 0) {
            throw new RuntimeException('Impossible de calculer un coût moyen avec un stock nul.');
        }

        return (int) round((float) bcdiv($numerator, $denominator, 6));
    }
}
