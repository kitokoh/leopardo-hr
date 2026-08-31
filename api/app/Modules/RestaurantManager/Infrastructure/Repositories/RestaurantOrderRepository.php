<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Infrastructure\Repositories;

use App\Modules\RestaurantManager\Domain\Contracts\RestaurantOrderRepositoryInterface;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;

/**
 * RESTO-215, issue #6180 — Implémentation Eloquent du port de persistance
 * des commandes restaurant (pattern CrmLeadRepository : scoping tenant).
 */
final class RestaurantOrderRepository implements RestaurantOrderRepositoryInterface
{
    public function findForCompany(int $id, string $companyId): ?RestaurantOrder
    {
        return RestaurantOrder::query()
            ->where('company_id', $companyId)
            ->find($id);
    }

    public function findByReference(string $reference, string $companyId): ?RestaurantOrder
    {
        return RestaurantOrder::query()
            ->where('company_id', $companyId)
            ->where('reference', $reference)
            ->first();
    }
}
