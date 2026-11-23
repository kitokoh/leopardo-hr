<?php

declare(strict_types=1);

namespace App\Core\Solutions;

use App\Core\Tenant\Domain\Models\Company;

/**
 * #EVAL — Catalogue des permissions VERTICALES délégables d'un tenant.
 *
 * Contexte : `ModuleKey` est un registre FERMÉ de modules HORIZONTAUX
 * (`marketing`, `accounting`, …) et `PUT /employees/{id}/module-grants` refuse
 * toute clé hors registre (422, fail-closed). Les permissions déclarées par un
 * manifest de verticale (`travel.agent`, `travel.checkin`, …) n'y figurent
 * volontairement PAS : les ouvrir au registre casserait l'invariant
 * « manifest inactif ⇒ aucune permission effective ».
 *
 * Résultat : un responsable d'agence ne pouvait attribuer `travel.agent` à
 * aucun vendeur depuis l'interface — le grant n'existait qu'installé
 * automatiquement pour le `principal` à l'activation.
 *
 * Ce catalogue expose donc, pour un tenant donné, les permissions des
 * solutions RÉELLEMENT ACTIVES (feature flag posé). C'est la seule source
 * admise par la surface de délégation verticale :
 *  - fail-closed : une verticale inactive n'expose AUCUNE permission, donc
 *    aucune clé n'est attribuable (et une clé déjà posée reste sans effet,
 *    les Policies lisant le grant ET le flag tenant) ;
 *  - aucune écriture : pure lecture du catalogue + des flags du tenant.
 */
final class VerticalPermissionCatalogue
{
    public function __construct(private readonly SolutionCatalogue $catalogue) {}

    /**
     * Permissions délégables du tenant : `code => libellé`.
     *
     * @return array<string, string>
     */
    public function forCompany(Company $company): array
    {
        $permissions = [];

        foreach ($this->catalogue->codes() as $code) {
            if (! $company->hasFeature($code)) {
                continue;
            }

            foreach ($this->catalogue->resolve($code)->permissions() as $key => $label) {
                // Un code partagé par deux verticales actives garde le premier
                // libellé rencontré (ordre déterministe : `codes()` est trié).
                $permissions[$key] ??= $label;
            }
        }

        ksort($permissions);

        return $permissions;
    }

    /**
     * Codes seuls (validation `Rule::in`, exposition front).
     *
     * @return list<string>
     */
    public function codesForCompany(Company $company): array
    {
        return array_keys($this->forCompany($company));
    }

    /**
     * Cette clé est-elle une permission verticale ACTIVE du tenant ?
     */
    public function isDelegable(Company $company, string $code): bool
    {
        return array_key_exists($code, $this->forCompany($company));
    }
}
