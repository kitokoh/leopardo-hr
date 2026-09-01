<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Infrastructure\Services;

use App\Modules\RestaurantManager\Application\Services\StockAlertService;
use App\Modules\RestaurantManager\Domain\Enums\StockMovementReason;
use App\Modules\RestaurantManager\Domain\Models\RestaurantInventoryMovement;
use App\Modules\RestaurantManager\Domain\Models\RestaurantStockLevel;
use Illuminate\Support\Facades\DB;

/**
 * RESTO-501 (#6200) / RESTO-411 (#6198) — Service applicatif des mouvements
 * de stock.
 *
 * Applique un mouvement de façon atomique : verrou `SELECT ... FOR UPDATE`
 * sur la ligne de stock (jamais négatif sauf `allowNegative`), écriture du
 * journal `restaurant_inventory_movements` (raison + référence polymorphe),
 * puis contrôle du seuil d'alerte (RESTO-505 : passage sous le seuil →
 * événement `restaurant.stock.alert.v1`, idempotent par jour).
 * C'est la SEULE porte d'entrée de modification des niveaux de stock de la
 * verticale (ventes, réceptions, inventaires, ajustements, pertes, transferts).
 */
final class StockMovementService
{
    public function __construct(private readonly StockAlertService $alerts)
    {
    }

    /**
     * @param  StockMovementReason  $reason  motif (sale/receiving/count/adjustment/waste/transfer)
     */
    public function apply(
        string $companyId,
        int $branchId,
        int $ingredientId,
        float $quantityDelta,
        StockMovementReason $reason,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $note = null,
        ?int $userId = null,
        bool $allowNegative = false,
    ): RestaurantStockLevel {
        return DB::transaction(function () use ($companyId, $branchId, $ingredientId, $quantityDelta, $reason, $referenceType, $referenceId, $note, $userId, $allowNegative): RestaurantStockLevel {
            /** @var RestaurantStockLevel|null $level */
            $level = RestaurantStockLevel::query()
                ->where('company_id', $companyId)
                ->where('branch_id', $branchId)
                ->where('ingredient_id', $ingredientId)
                ->lockForUpdate()
                ->first();

            if (! $level instanceof RestaurantStockLevel) {
                $level = RestaurantStockLevel::query()->create([
                    'company_id' => $companyId,
                    'branch_id' => $branchId,
                    'ingredient_id' => $ingredientId,
                    'quantity' => 0,
                ]);
            }

            $current = (float) $level->quantity;
            $newQuantity = $current + $quantityDelta;

            if ($newQuantity < 0 && ! $allowNegative) {
                abort(422, sprintf('Insufficient stock for ingredient %d (branch %d): available %.3f, requested %.3f.', $ingredientId, $branchId, $current, abs($quantityDelta)));
            }

            $finalQuantity = $newQuantity < 0 ? 0 : $newQuantity;

            $level->forceFill(['quantity' => $finalQuantity])->save();

            RestaurantInventoryMovement::query()->create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'ingredient_id' => $ingredientId,
                'stock_level_id' => $level->id,
                'quantity_delta' => $quantityDelta,
                'reason_code' => $reason->value,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'note_redacted' => $note,
                'user_id' => $userId,
            ]);

            // RESTO-505 : passage sous le seuil → alerte outbox (idempotente
            // par jour, une seule par branche/ingrédient/période).
            $this->alerts->checkLevel($level);

            return $level;
        });
    }
}
