<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Application\Actions;

use App\Core\Solutions\Contracts\SolutionPermissionInstaller;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\TravelAgency\Domain\Exceptions\TravelActivationFailedException;
use App\Modules\TravelAgency\Domain\Manifests\TravelAgencyManifest;
use App\Modules\TravelAgency\Infrastructure\Services\TravelGeoSeederService;

/**
 * Activation de la verticale TravelAgency pour un tenant (TRAVEL-105, #6010).
 *
 * 1. Active le feature flag `travelagency` (companies.features.travelagency) —
 *    kill switch opérationnel : désactiver le flag → 403 immédiat (middleware
 *    module.travelagency), aucune donnée touchée.
 * 2. Seed le référentiel géographique tenant-scoped (pays + villes), idempotent.
 *
 * #7393 — `Company::setFeature()` ne fait qu'une mutation EN MÉMOIRE (contrat
 * partagé avec `activateHorizontalTool()` : « n'appelle PAS save(), l'appelant
 * persiste »). L'Action est ici l'appelante : elle DOIT donc persister, sinon
 * la commande annonçait « activée » alors que le flag restait absent de
 * `public.companies` (activation à moitié faite : le référentiel géo, lui,
 * était bien seedé). Après écriture, le flag est RELU depuis la base — et
 * l'échec est explicite (exception) plutôt que silencieux.
 *
 * Le branchement sur l'orchestrateur de provisioning (PLAT-001, étape
 * `install_solution`) est documenté dans la spec (§10.3) et sera câblé côté
 * plateforme quand PLAT-001 sera livré.
 */
final class ActivateTravelAgencyAction
{
    public function __construct(
        private readonly TravelGeoSeederService $geoSeeder,
        private readonly SolutionPermissionInstaller $permissionInstaller,
    ) {}

    public function execute(Company $company): void
    {
        $company->setFeature('travelagency', true);
        $company->save();

        // Relecture depuis la BASE (jamais l'instance en mémoire) : preuve que
        // l'écriture a bien atteint `public.companies` et n'a pas été perdue
        // par le search_path multi-tenant.
        $persisted = Company::query()->find($company->id);

        if (! $persisted instanceof Company || ! $persisted->hasFeature('travelagency')) {
            throw TravelActivationFailedException::flagNotPersisted($company->id);
        }

        // #EVAL — BOS-013 (#8200) : `SolutionPermissionInstaller` installe les
        // permissions déclarées par le manifest pour le(s) `principal` du
        // tenant. Le chemin d'activation TravelAgency (`leopardo:travel:activate`
        // → cette Action) ne l'appelait PAS : les permissions `travel.manage`,
        // `travel.agent`, `travel.checkin`, `travel.reports` n'étaient jamais
        // posées en base, alors que la matrice RBAC et la spec §10.2 les
        // annoncent comme installées à l'activation. On aligne le chemin
        // TravelAgency sur `SolutionActivator::activate()` (idempotent).
        $this->permissionInstaller->install($company, new TravelAgencyManifest);

        $this->geoSeeder->seed($company);
    }
}
