<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Application\Services;

use App\Modules\RestaurantManager\Domain\Enums\OrderItemStatus;
use App\Modules\RestaurantManager\Domain\Enums\OrderStatus;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPosSession;
use App\Modules\RestaurantManager\Domain\Models\RestaurantProductIngredient;
use App\Modules\RestaurantManager\Domain\Models\RestaurantStockLevel;

/**
 * RESTO-506 (#6205) — COGS : coût des marchandises consommées, calculé
 * SERVEUR à la clôture de caisse (spec §4.4).
 *
 *   COGS = Σ commandes soldées de la session → Σ lignes actives →
 *         Σ composition (ingrédient × quantité) × coût moyen de l'ingrédient
 *
 * Fonction PURE et déterministe : rejouer le calcul sur les mêmes données
 * donne le même résultat (critère d'acceptation « COGS recalculable = même
 * résultat »). Montant en minor units.
 */
final class CogsCalculator
{
    public function calculateForSession(RestaurantPosSession $session): int
    {
        $orders = RestaurantOrder::query()
            ->where('company_id', $session->company_id)
            ->where('pos_session_id', $session->id)
            ->whereIn('status', [OrderStatus::PAID->value, OrderStatus::CLOSED->value, OrderStatus::REFUNDED->value])
            ->get(['id', 'company_id', 'branch_id']);

        $cogs = 0;

        foreach ($orders as $order) {
            $cogs += $this->calculateForOrder($order);
        }

        return $cogs;
    }

    public function calculateForOrder(RestaurantOrder $order): int
    {
        $items = $order->items()
            ->where('status', OrderItemStatus::ACTIVE->value)
            ->get(['product_id', 'quantity']);

        $total = 0;

        foreach ($items as $item) {
            $compositions = RestaurantProductIngredient::query()
                ->where('company_id', $order->company_id)
                ->where('product_id', $item->product_id)
                ->get(['ingredient_id', 'quantity']);

            foreach ($compositions as $composition) {
                $level = RestaurantStockLevel::query()
                    ->where('company_id', $order->company_id)
                    ->where('branch_id', $order->branch_id)
                    ->where('ingredient_id', $composition->ingredient_id)
                    ->first(['avg_cost_minor']);

                $cost = (int) ($level?->avg_cost_minor ?? 0);
                $total += (int) round((float) $item->quantity * (float) $composition->quantity * $cost);
            }
        }

        return $total;
    }
}
