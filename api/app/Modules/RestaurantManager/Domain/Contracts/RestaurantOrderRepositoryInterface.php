<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Contracts;

use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;

/**
 * RESTO-215, issue #6180 — Port de persistance des commandes restaurant
 * (tenant-scoped).
 */
interface RestaurantOrderRepositoryInterface
{
    /**
     * Charge une commande scopée au tenant. null si absente OU hors tenant (404 sûr).
     */
    public function findForCompany(int $id, string $companyId): ?RestaurantOrder;

    /**
     * Charge une commande par référence (RST-XXXXXXXX), scopée au tenant.
     */
    public function findByReference(string $reference, string $companyId): ?RestaurantOrder;
}
