<?php

declare(strict_types=1);

namespace App\Core\Auth\Application\Services;

use App\Core\Auth\Domain\Enums\DelegablePermission;
use App\Core\Auth\Domain\Models\Employee;

/**
 * IAM-L5 (#9095) — le plafond de délégation.
 *
 * ## La règle qui manque aujourd'hui
 *
 * Tant qu'aucune table de rôles n'existe, le scénario « un rôle personnalisé
 * dépasse les droits de son créateur » est **structurellement impossible** —
 * c'est un état sûr, mais une limite produit. Dès qu'on introduit
 * `role_assignments`, ce scénario devient **critique** : sans plafond, un
 * `rh` pourrait fabriquer un rôle portant `is_principal` et se l'attribuer.
 *
 * ## La règle
 *
 * `permissions ⊆ capabilities(créateur)`, appliquée à la **création** ET à la
 * **modification**. Le plafond est calculé par `DelegablePermission::
 * forManagerRole()` — le miroir exact de `EmployeeResource::capabilities()` —
 * augmenté des grants de modules réellement détenus.
 *
 * ## Ce que ce garde ne fait pas
 *
 * Il ne modifie aucun gate existant : il **refuse** une écriture, il ne
 * restreint pas une lecture. Un rôle déjà en base n'est jamais réévalué
 * rétroactivement — la règle s'applique au geste, pas à l'état.
 */
final class RoleDelegationGuard
{
    /**
     * Les permissions que `$actor` détient réellement, donc qu'il peut
     * déléguer. Un `principal` détient tout le registre.
     *
     * @return list<string>
     */
    public function ceilingFor(Employee $actor): array
    {
        $ceiling = DelegablePermission::forManagerRole($actor->manager_role);

        foreach ($actor->grantedModuleKeys() as $moduleKey) {
            $ceiling[] = 'module:'.$moduleKey;
        }

        return array_values(array_unique($ceiling));
    }

    /**
     * `$requested` dépasse-t-il le plafond de `$actor` ?
     *
     * @param  list<string>  $requested
     * @return list<string> les permissions refusées (vide = autorisé)
     */
    public function excessFor(Employee $actor, array $requested): array
    {
        $ceiling = $this->ceilingFor($actor);

        return array_values(array_filter(
            $requested,
            static fn (string $permission): bool => ! in_array($permission, $ceiling, true),
        ));
    }

    /**
     * Un acteur peut-il administrer les rôles personnalisés de son tenant ?
     *
     * Seul un `principal` détient `delegation.manage` — c'est la seule
     * permission qui permet de fabriquer des rôles, donc celle qu'un
     * non-principal ne peut ni détenir ni accorder.
     */
    public function canManageDelegation(Employee $actor): bool
    {
        return in_array(
            DelegablePermission::ManageDelegation->value,
            $this->ceilingFor($actor),
            true,
        );
    }
}
