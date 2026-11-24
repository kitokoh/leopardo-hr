<?php

declare(strict_types=1);

namespace App\Core\Solutions\Contracts;

use App\Core\Tenant\Domain\Models\Company;

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
 * Propriétés exigées des implémentations :
 *  - idempotent : une permission déjà installée n'est jamais dupliquée
 *    (unicité `(company_id, employee_id, module_key)` en base) ;
 *  - fail-closed : un manifest inactif ne confère rien — l'installation ne
 *    passe que par l'activation et la révocation retire exactement les clés
 *    du manifest (moins celles encore déclarées par une autre solution ACTIVE
 *    du tenant — ex. `restaurant.manager`, déclaré par les deux manifests de
 *    la verticale restaurant) ;
 *  - `module_key` porte le code de permission du manifest (ex. `edu.admin`)
 *    SANS étendre le registre `ModuleKey` : celui-ci reste le registre fermé
 *    des modules DÉLÉGUABLES manuellement via l'API HR.
 *
 * Pourquoi un contrat et non la classe concrète : les modules ne doivent
 * accéder à `Core` que par contrats/interfaces
 * (`IdentityLayerBoundariesArchitectureTest`, #8717 écart I-3). L'import de
 * `App\Core\Solutions\SolutionPermissionInstaller` depuis
 * `Modules/TravelAgency` était une arête concrète refusée par la garde ;
 * l'implémentation vit désormais dans `Core/Solutions/Infrastructure/Services`
 * et est liée au conteneur.
 */
interface SolutionPermissionInstaller
{
    /**
     * Installe les permissions du manifest pour le(s) `principal` du tenant.
     *
     * @return list<string> codes de permissions nouvellement installés
     *                      (les déjà présentes ne sont pas retournées)
     */
    public function install(Company $company, SolutionManifest $manifest): array;

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
    public function revoke(Company $company, SolutionManifest $manifest, array $keptCodes = []): array;
}
