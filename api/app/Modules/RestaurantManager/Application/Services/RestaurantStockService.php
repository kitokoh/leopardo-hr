<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Application\Services;

use App\Modules\RestaurantManager\Domain\Enums\OrderItemStatus;
use App\Modules\RestaurantManager\Domain\Enums\StockMovementReason;
use App\Modules\RestaurantManager\Domain\Models\RestaurantInventoryMovement;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use App\Modules\RestaurantManager\Domain\Models\RestaurantStockLevel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * RESTO-411 (#6198) — Décrément de stock à la confirmation d'une commande.
 *
 * Invariant stock (spec §4.4 / D4) : le stock des ingrédients composant les
 * lignes actives de la commande est décrémenté EN TRANSACTION avec verrou
 * `SELECT ... FOR UPDATE` (une seule confirmation consomme le dernier stock —
 * les confirmations concurrentes sont sérialisées par le verrou de ligne).
 *
 * Politique de stock insuffisant configurable
 * (`config('restaurantmanager.stock.insufficient_policy')`) :
 *   - 'block' (défaut) : la confirmation est refusée (422), le stock n'est
 *     JAMAIS négatif ;
 *   - 'warn'           : la confirmation passe, le stock est plafonné à 0 et
 *     la consommation excédentaire est tracée dans le mouvement.
 *
 * Chaque consommation est journalisée dans `restaurant_inventory_movements`
 * (reason_code `sale`, référence commande) pour la traçabilité et le COGS.
 */
final class RestaurantStockService
{
    /** Politique par défaut : blocage (stock jamais négatif). */
    public const POLICY_BLOCK = 'block';

    /** Politique permissive : plafonnement à zéro + trace. */
    public const POLICY_WARN = 'warn';

    /**
     * Décrémente le stock des ingrédients composant les lignes actives de la
     * commande. À appeler DANS la transaction de confirmation de la commande
     * (la méthode ouvre une sous-transaction/savepoint si appelée hors
     * transaction — même pattern que RestaurantOutboxPublisher).
     */
    public function decrementForConfirmedOrder(RestaurantOrder $order): void
    {
        DB::transaction(function () use ($order): void {
            $items = $order->items()
                ->with(['product.ingredients.ingredient'])
                ->where('status', OrderItemStatus::ACTIVE->value)
                ->get();

            foreach ($items as $item) {
                $product = $item->product;

                if ($product === null) {
                    continue;
                }

                foreach ($product->ingredients as $composition) {
                    $this->decrementIngredient(
                        ingredientId: (int) $composition->ingredient_id,
                        branchId: (int) $order->branch_id,
                        companyId: (string) $order->company_id,
                        requiredQuantity: (float) $composition->quantity * (float) $item->quantity,
                        referenceId: (int) $order->id,
                    );
                }
            }
        });
    }

    /**
     * Décrémente un ingrédient avec verrou de ligne (SELECT ... FOR UPDATE).
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException 422 en
     *         politique 'block' quand le stock est insuffisant.
     */
    public function decrementIngredient(
        int $ingredientId,
        int $branchId,
        string $companyId,
        float $requiredQuantity,
        int $referenceId,
        ?int $userId = null,
        ?string $noteRedacted = null,
    ): void {
        if ($requiredQuantity <= 0.0) {
            return;
        }

        /** @var RestaurantStockLevel|null $stock */
        $stock = RestaurantStockLevel::query()
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->where('ingredient_id', $ingredientId)
            ->lockForUpdate()
            ->first();

        if (! $stock instanceof RestaurantStockLevel) {
            $this->handleInsufficient(
                'Ingredient stock level is not configured for this branch.',
                $companyId,
                $branchId,
                $ingredientId,
                $referenceId,
            );

            return;
        }

        $available = (float) $stock->quantity;
        $remaining = max(0.0, $available - $requiredQuantity);

        if ($requiredQuantity > $available) {
            $this->handleInsufficient(
                sprintf(
                    'Insufficient stock for ingredient #%d: required %s, available %s.',
                    $ingredientId,
                    $this->formatQuantity($requiredQuantity),
                    $this->formatQuantity($available),
                ),
                $companyId,
                $branchId,
                $ingredientId,
                $referenceId,
                ['required' => $requiredQuantity, 'available' => $available],
            );
        }

        // En politique 'block', handleInsufficient() a déjà abort → jamais
        // atteint avec un stock négatif. En 'warn', on plafonne à 0.
        $stock->forceFill(['quantity' => $remaining])->save();

        RestaurantInventoryMovement::query()->create([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'ingredient_id' => $ingredientId,
            'stock_level_id' => $stock->id,
            'quantity_delta' => -1 * $requiredQuantity,
            'reason_code' => StockMovementReason::SALE->value,
            'reference_type' => 'order',
            'reference_id' => $referenceId,
            'note_redacted' => $noteRedacted,
            'user_id' => $userId,
        ]);
    }

    /**
     * Applique la politique de stock insuffisant : abort 422 en 'block',
     * warning loggé en 'warn' (consommation partielle tracée).
     *
     * @param  array<string, float>  $context
     */
    private function handleInsufficient(
        string $message,
        string $companyId,
        int $branchId,
        int $ingredientId,
        int $referenceId,
        array $context = [],
    ): void {
        $policy = (string) config('restaurantmanager.stock.insufficient_policy', self::POLICY_BLOCK);

        if ($policy === self::POLICY_BLOCK) {
            abort(422, $message);
        }

        Log::warning('restaurant.stock.insufficient', [
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'ingredient_id' => $ingredientId,
            'order_id' => $referenceId,
            'required' => $context['required'] ?? null,
            'available' => $context['available'] ?? null,
        ]);
    }

    /**
     * Normalise une quantité décimal (3 décimales, sans zéros superflus).
     */
    private function formatQuantity(float $quantity): string
    {
        return rtrim(rtrim(number_format($quantity, 3, '.', ''), '0'), '.');
    }
}
