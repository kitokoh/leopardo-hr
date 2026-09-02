<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Infrastructure\Repositories;

use App\Modules\RestaurantManager\Domain\Contracts\RestaurantPosSessionRepositoryInterface;
use App\Modules\RestaurantManager\Domain\Enums\PosSessionStatus;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPosSession;

/**
 * RESTO-215, issue #6180 — Implémentation Eloquent du port de persistance
 * des sessions de caisse (POS).
 */
final class RestaurantPosSessionRepository implements RestaurantPosSessionRepositoryInterface
{
    public function currentOpenForBranch(int $branchId, string $companyId): ?RestaurantPosSession
    {
        return RestaurantPosSession::query()
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->where('status', PosSessionStatus::OPEN->value)
            ->orderByDesc('opened_at')
            ->first();
    }
}
