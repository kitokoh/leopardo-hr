<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Application\Actions;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\TravelAgency\Infrastructure\Services\TravelGeoSeederService;

/**
 * Activation de la verticale TravelAgency pour un tenant (TRAVEL-105, #6010).
 *
 * 1. Active le feature flag `travelagency` (companies.features.travelagency) —
 *    kill switch opérationnel : désactiver le flag → 403 immédiat (middleware
 *    module.travelagency), aucune donnée touchée.
 * 2. Seed le référentiel géographique tenant-scoped (pays + villes), idempotent.
 *
 * Le branchement sur l'orchestrateur de provisioning (PLAT-001, étape
 * `install_solution`) est documenté dans la spec (§10.3) et sera câblé côté
 * plateforme quand PLAT-001 sera livré.
 */
final class ActivateTravelAgencyAction
{
    public function __construct(private readonly TravelGeoSeederService $geoSeeder)
    {
    }

    public function execute(Company $company): void
    {
        $company->setFeature('travelagency', true);

        $this->geoSeeder->seed($company);
    }
}
