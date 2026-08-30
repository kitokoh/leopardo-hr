<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Infrastructure\Services;

use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPosSession;
use App\Modules\RestaurantManager\Domain\Models\RestaurantProduct;
use App\Modules\RestaurantManager\Domain\Models\RestaurantStockLevel;

/**
 * RESTO-506 (#6205) — COGS : calcul serveur à la clôture de caisse.
 *
 * Coût des marchandises vendues = Σ sur les commandes confirmées de la
 * session (served/paid/closed) de :
 *   Σ articles actifs (quantité × Σ composition du produit (quantité
 *   d'ingrédient) × coût moyen pondéré de l'ingrédient au moment du calcul).
 *
 * Calcul **pur et idempotent** (critère d'acceptation : recalculable = même
 * résultat) — aucun effet de bord, les montants restent en minor units.
 */
final class RestaurantCogsService
{
    /** Statuts de commande pris en compte dans le COGS. */
    private const COGS_ORDER_STATUSES = ['served', 'paid', 'closed'];
    /**
     * @return array{
     *   pos_session_id: int,
     *   currency: string,
     *   cogs_minor: int,
     *   computed_at: string,
     *   orders_count: int,
     *   lines: array<int, array{product_id: int, quantity: string, cogs_minor: int}>
     * }
     */
    public function calculateForPosSession(RestaurantPosSession $session): array
    {
        $orders = RestaurantOrder::query()
            ->where('company_id', $session->company_id)
            ->where('pos_session_id', $session->id)
            ->whereIn('status', self::COGS_ORDER_STATUSES)
            ->with(['items' => fn ($q) => $q->where('status', 'active'), 'items.product'])
            ->get();

        // Coût moyen pondéré par ingrédient (branche de la session).
        $avgCosts = RestaurantStockLevel::query()
            ->where('company_id', $session->company_id)
            ->where('branch_id', $session->branch_id)
            ->pluck('avg_cost_minor', 'ingredient_id');

        $lines = [];
        $cogsTotal = 0;

        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                /** @var RestaurantProduct|null $product */
                $product = $item->product;

                if ($product === null) {
                    continue;
                }

                $productCogs = $this->productCogsMinor($product, $avgCosts->all());
                $lineCogs = (int) round((float) $item->quantity * $productCogs);

                if ($lineCogs <= 0) {
                    continue;
                }

                $lines[] = [
                    'product_id' => $product->id,
                    'quantity' => (string) $item->quantity,
                    'cogs_minor' => $lineCogs,
                ];
                $cogsTotal += $lineCogs;
            }
        }

        return [
            'pos_session_id' => (int) $session->id,
            'currency' => (string) ($session->branch->currency ?? 'DZD'),
            'cogs_minor' => $cogsTotal,
            'computed_at' => now()->toIso8601String(),
            'orders_count' => $orders->count(),
            'lines' => $lines,
        ];
    }

    /**
     * COGS d'un produit vendu = Σ (quantité d'ingrédient × coût moyen).
     *
     * @param  array<int, int|null>  $avgCosts  ingrédient_id => avg_cost_minor
     */
    private function productCogsMinor(RestaurantProduct $product, array $avgCosts): int
    {
        $cogs = 0;

        foreach ($product->ingredients as $ingredient) {
            $avgCost = $avgCosts[$ingredient->ingredient_id] ?? 0;

            if ($avgCost === null) {
                continue;
            }

            $cogs += (int) round((float) $ingredient->quantity * (int) $avgCost);
        }

        return $cogs;
    }
}
