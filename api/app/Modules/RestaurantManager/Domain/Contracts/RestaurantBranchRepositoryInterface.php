<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Contracts;

use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;

/**
 * RESTO-215, issue #6180 — Port de persistance des branches restaurant
 * (tenant-scoped).
 */
interface RestaurantBranchRepositoryInterface
{
    /**
     * Charge une branche scopée au tenant. null si absente OU hors tenant (404 sûr).
     */
    public function findForCompany(int $id, string $companyId): ?RestaurantBranch;

    /**
     * Retourne la branche par défaut du tenant : première branche active
     * ordonnée par date de création.
     */
    public function findDefaultForCompany(string $companyId): ?RestaurantBranch;
}
