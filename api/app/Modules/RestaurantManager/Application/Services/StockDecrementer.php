<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Application\Services;

use App\Modules\RestaurantManager\Domain\Enums\OrderItemStatus;
use App\Modules\RestaurantManager\Domain\Enums\StockMovementReason;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrderItem;
use App\Modules\RestaurantManager\Domain\Models\RestaurantProductIngredient;
use App\Modules\RestaurantManager\Infrastructure\Services\StockMovementService;

/**
 * RESTO-411 (#6198) — Décrément de stock à la confirmation d'une commande.
 *
 * À la confirmation (open → in_preparation), les ingrédients composant les
 * lignes actives sont décrémentés en transaction : le mouvement passe par
 * StockMovementService (verrou `SELECT FOR UPDATE` — jamais de course sur le
 * dernier stock, jamais de stock négatif). Comportement en cas de stock
 * insuffisant : blocage (422) par défaut, ou passage en négatif si
 * `restaurantmanager.stock.block_on_insufficient=false` (avertissement
 * configurable, spec §4.4).
 */
final class StockDecrementer
{
    public function __construct(
        private readonly StockMovementService $movements,
        private readonly bool $blockOnInsufficient,
    ) {
    }

    public function decrementForOrder(RestaurantOrder $order): void
    {
        /** @var array<int, RestaurantOrderItem> $items */
        $items = $order->items()
            ->where('status', OrderItemStatus::ACTIVE->value)
            ->get();

        foreach ($items as $item) {
            // Composition du produit vendu (recette) : ingrédient × quantité.
            $compositions = RestaurantProductIngredient::query()
                ->where('company_id', $order->company_id)
                ->where('product_id', $item->product_id)
                ->get();

            foreach ($compositions as $composition) {
                $delta = -1 * ((float) $item->quantity * (float) $composition->quantity);

                $this->movements->apply(
                    companyId: $order->company_id,
                    branchId: $order->branch_id,
                    ingredientId: $composition->ingredient_id,
                    quantityDelta: $delta,
                    reason: StockMovementReason::SALE,
                    referenceType: RestaurantOrder::class,
                    referenceId: $order->id,
                    allowNegative: ! $this->blockOnInsufficient,
                );
            }
        }
    }
}
