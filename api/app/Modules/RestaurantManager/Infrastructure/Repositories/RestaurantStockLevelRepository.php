<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Infrastructure\Repositories;

use App\Modules\RestaurantManager\Domain\Contracts\RestaurantStockLevelRepositoryInterface;
use App\Modules\RestaurantManager\Domain\Models\RestaurantStockLevel;
use Illuminate\Database\Eloquent\Builder;

/**
 * RESTO-215, issue #6180 — Implémentation Eloquent du port de persistance
 * des niveaux de stock (tenant + branche + ingrédient).
 */
final class RestaurantStockLevelRepository implements RestaurantStockLevelRepositoryInterface
{
    public function findForIngredient(int $ingredientId, int $branchId, string $companyId): ?RestaurantStockLevel
    {
        return $this->scope($ingredientId, $branchId, $companyId)->first();
    }

    public function lockForUpdateForIngredient(int $ingredientId, int $branchId, string $companyId): ?RestaurantStockLevel
    {
        // Verrou SELECT ... FOR UPDATE : à utiliser dans une transaction au
        // décrément de stock (RESTO-411) pour éviter les double-décréments.
        return $this->scope($ingredientId, $branchId, $companyId)
            ->lockForUpdate()
            ->first();
    }

    /**
     * Requête de base scopée tenant + branche + ingrédient.
     *
     * @return Builder<RestaurantStockLevel>
     */
    private function scope(int $ingredientId, int $branchId, string $companyId): Builder
    {
        return RestaurantStockLevel::query()
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->where('ingredient_id', $ingredientId);
    }
}
