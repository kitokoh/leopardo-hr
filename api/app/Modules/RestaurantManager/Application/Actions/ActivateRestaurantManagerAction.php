<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Application\Actions;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\RestaurantManager\Infrastructure\Services\RestaurantReferentialSeederService;

/**
 * Activation de la verticale RestaurantManager pour un tenant (RESTO-105,
 * issue #6162).
 *
 * 1. Active le feature flag `restaurantmanager` (companies.features.
 *    restaurantmanager) — kill switch opérationnel : désactiver le flag →
 *    403 immédiat (middleware module.restaurantmanager), aucune donnée
 *    touchée.
 * 2. Seed le référentiel tenant-scoped (branche par défaut, unités, TVA,
 *    catégories), idempotent.
 *
 * Le branchement sur l'orchestrateur de provisioning (PLAT-001, étape
 * `install_solution`) est documenté dans la spec (§8) et sera câblé côté
 * plateforme quand PLAT-001 sera livré.
 */
final class ActivateRestaurantManagerAction
{
    public function __construct(private readonly RestaurantReferentialSeederService $referentialSeeder) {}

    public function execute(Company $company): void
    {
        $company->setFeature('restaurantmanager', true);
        $company->save();

        $this->referentialSeeder->seed($company);
    }
}
