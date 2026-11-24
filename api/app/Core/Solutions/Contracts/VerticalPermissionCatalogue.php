<?php

declare(strict_types=1);

namespace App\Core\Solutions\Contracts;

use App\Core\Tenant\Domain\Models\Company;

/**
 * #EVAL — Catalogue des permissions VERTICALES délégables d'un tenant.
 *
 * Contrat exposé aux modules (HR notamment) : `ModuleKey` est un registre
 * FERMÉ de modules HORIZONTAUX (`marketing`, `accounting`, …) et
 * `PUT /employees/{id}/module-grants` refuse toute clé hors registre (422,
 * fail-closed). Les permissions déclarées par un manifest de verticale
 * (`travel.agent`, `travel.checkin`, …) n'y figurent volontairement PAS :
 * les ouvrir au registre casserait l'invariant « manifest inactif ⇒ aucune
 * permission effective ».
 *
 * Ce catalogue expose donc, pour un tenant donné, les permissions des
 * solutions RÉELLEMENT ACTIVES (feature flag posé). C'est la seule source
 * admise par la surface de délégation verticale :
 *  - fail-closed : une verticale inactive n'expose AUCUNE permission, donc
 *    aucune clé n'est attribuable (et une clé déjà posée reste sans effet,
 *    les Policies lisant le grant ET le flag tenant) ;
 *  - aucune écriture : pure lecture du catalogue + des flags du tenant.
 *
 * Pourquoi un contrat et non la classe concrète : les modules ne doivent
 * accéder à `Core` que par contrats/interfaces
 * (`IdentityLayerBoundariesArchitectureTest`, #8717 écart I-3). L'import de
 * `App\Core\Solutions\VerticalPermissionCatalogue` depuis `Modules/HR` était
 * une arête concrète refusée par la garde ; l'implémentation vit désormais
 * dans `Core/Solutions/Infrastructure/Services` et est liée au conteneur.
 */
interface VerticalPermissionCatalogue
{
    /**
     * Permissions délégables du tenant : `code => libellé`.
     *
     * @return array<string, string>
     */
    public function forCompany(Company $company): array;

    /**
     * Codes seuls (validation `Rule::in`, exposition front).
     *
     * @return list<string>
     */
    public function codesForCompany(Company $company): array;

    /**
     * Cette clé est-elle une permission verticale ACTIVE du tenant ?
     */
    public function isDelegable(Company $company, string $code): bool;
}
