<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Infrastructure\Repositories;

use App\Modules\RestaurantManager\Domain\Contracts\RestaurantBranchRepositoryInterface;
use App\Modules\RestaurantManager\Domain\Enums\RestaurantRecordStatus;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;

/**
 * RESTO-215, issue #6180 — Implémentation Eloquent du port de persistance
 * des branches restaurant (pattern CrmLeadRepository : scoping tenant).
 */
final class RestaurantBranchRepository implements RestaurantBranchRepositoryInterface
{
    public function findForCompany(int $id, string $companyId): ?RestaurantBranch
    {
        return RestaurantBranch::query()
            ->where('company_id', $companyId)
            ->find($id);
    }

    public function findDefaultForCompany(string $companyId): ?RestaurantBranch
    {
        return RestaurantBranch::query()
            ->where('company_id', $companyId)
            ->where('status', RestaurantRecordStatus::ACTIVE->value)
            ->orderBy('created_at')
            ->first();
    }
}
