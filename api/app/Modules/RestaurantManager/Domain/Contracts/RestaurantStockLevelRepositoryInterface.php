<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Contracts;

use App\Modules\RestaurantManager\Domain\Models\RestaurantStockLevel;

/**
 * RESTO-215, issue #6180 — Port de persistance des niveaux de stock
 * (tenant + branche + ingrédient).
 */
interface RestaurantStockLevelRepositoryInterface
{
    /**
     * Charge le niveau de stock d'un ingrédient dans une branche.
     */
    public function findForIngredient(int $ingredientId, int $branchId, string $companyId): ?RestaurantStockLevel;

    /**
     * Charge le niveau de stock d'un ingrédient sous verrou `SELECT ... FOR UPDATE`
     * (à utiliser dans une transaction au décrément de stock, RESTO-411).
     */
    public function lockForUpdateForIngredient(int $ingredientId, int $branchId, string $companyId): ?RestaurantStockLevel;
}
