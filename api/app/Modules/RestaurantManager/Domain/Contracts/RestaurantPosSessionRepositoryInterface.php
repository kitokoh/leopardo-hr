<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Contracts;

use App\Modules\RestaurantManager\Domain\Models\RestaurantPosSession;

/**
 * RESTO-215, issue #6180 — Port de persistance des sessions de caisse (POS).
 */
interface RestaurantPosSessionRepositoryInterface
{
    /**
     * Retourne la session de caisse actuellement ouverte pour une branche
     * (statut open, la plus récente par opened_at). null si aucune.
     */
    public function currentOpenForBranch(int $branchId, string $companyId): ?RestaurantPosSession;
}
