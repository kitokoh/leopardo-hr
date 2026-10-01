<?php

declare(strict_types=1);

namespace App\Core\Solutions;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Solutions\Contracts\SolutionManifest;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\EmployeeModuleGrant;

/**
 * Installation effective des permissions déclarées par un manifest — BOS-013
 * (#8200).
 *
 * Jusqu'ici `SolutionManifest::permissions()` était déclaré par chaque
 * verticale mais jamais câblé : activer une verticale n'installait aucune
 * permission. Le mécanisme choisi réutilise le socle EXISTANT de délégation
 * (#7761) — une ligne `employee_module_grants` par permission déclarée, posée
 * pour le(s) manager(s) `principal` du tenant — sans aucun nouveau système
 * de rôles (décision 09 §2.2).
 *
 * Propriétés :
 *  - idempotent : une permission déjà installée n'est jamais dupliquée
 *    (unicité `(company_id, employee_id, module_key)` en base) ;
 *  - fail-closed : un manifest inactif ne confère rien — l'installation ne
 *    passe que par l'activation (ici) et la révocation retire exactement les
 *    clés du manifest (moins celles encore déclarées par une autre solution
 *    ACTIVE du tenant — ex. `restaurant.manager`, déclaré par les deux
 *    manifests de la verticale restaurant) ;
 *  - `module_key` porte le code de permission du manifest (ex. `edu.admin`)
 *    SANS étendre le registre `ModuleKey` : celui-ci reste le registre fermé
 *    des modules DÉLÉGUABLES manuellement via l'API HR — ouvrir la délégation
 *    manuelle aux permissions verticales casserait le fail-closed « manifest
 *    inactif ⇒ aucune permission effective ».
 */
final class SolutionPermissionInstaller
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
