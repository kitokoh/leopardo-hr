<?php

declare(strict_types=1);

namespace App\Core\Solutions\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Solutions\Contracts\SolutionManifest;
use App\Core\Solutions\Contracts\SolutionPermissionInstaller as SolutionPermissionInstallerContract;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\EmployeeModuleGrant;

/**
 * Implémentation du contrat {@see SolutionPermissionInstallerContract} —
 * BOS-013 (#8200).
 *
 * Voir le contrat pour les propriétés garanties (idempotence, fail-closed,
 * `module_key` portant le code de permission du manifest sans étendre le
 * registre `ModuleKey`).
 *
 * Déplacée de `Core/Solutions` vers `Core/Solutions/Infrastructure/Services`
 * lors de l'extraction du contrat : les modules importaient la classe
 * concrète, ce que refuse `IdentityLayerBoundariesArchitectureTest`
 * (#8717, écart I-3).
 */
final class ManifestSolutionPermissionInstaller implements SolutionPermissionInstallerContract
{
    /**
     * Installe les permissions du manifest pour le(s) `principal` du tenant.
     *
     * @return list<string> codes de permissions nouvellement installés
     *                      (les déjà présentes ne sont pas retournées)
     */
    public function install(Company $company, SolutionManifest $manifest): array
    {
        $codes = array_keys($manifest->permissions());

        if ($codes === []) {
            return [];
        }

        $principals = Employee::query()
            ->where('company_id', $company->id)
            ->where('manager_role', 'principal')
            ->get();

        $installed = [];

        foreach ($principals as $principal) {
            foreach ($codes as $code) {
                $exists = EmployeeModuleGrant::forCompany($company)
                    ->where('employee_id', $principal->id)
                    ->where('module_key', $code)
                    ->exists();

                if ($exists) {
                    continue;
                }

                $grant = new EmployeeModuleGrant([
                    'employee_id' => $principal->id,
                    'module_key' => $code,
                ]);
                // Posé explicitement depuis le contexte d'activation (jamais
                // mass-assignable — même garde qu'#3597/#7761).
                $grant->company_id = $company->id;
                $grant->granted_by_employee_id = $principal->id; // installation système au nom du principal
                $grant->save();

                $installed[] = $code;
            }
        }

        return array_values(array_unique($installed));
    }

    /**
     * Retire les grants installés pour les permissions du manifest, sur tous
     * les collaborateurs du tenant. Les clés AUSSI déclarées par une autre
     * solution encore active du tenant sont conservées (partage de code entre
     * manifests, cf. la verticale restaurant).
     *
     * @param  list<string>  $keptCodes  clés à conserver bien que présentes
     *                                   dans le manifest (déclarées par une
     *                                   autre solution active)
     * @return list<string> codes effectivement retirés
     */
    public function revoke(Company $company, SolutionManifest $manifest, array $keptCodes = []): array
    {
        $codes = array_diff(array_keys($manifest->permissions()), $keptCodes);

        if ($codes === []) {
            return [];
        }

        $revoked = [];

        // Suppression PAR MODÈLE (jamais de delete() massif) : le trait
        // `Auditable` d'`EmployeeModuleGrant` écrit une ligne `audit_logs`
        // par révocation — même doctrine que le service HR #7761.
        foreach (EmployeeModuleGrant::forCompany($company)->whereIn('module_key', $codes)->get() as $grant) {
            $revoked[] = $grant->module_key;
            $grant->delete();
        }

        return array_values(array_unique($revoked));
    }
}
