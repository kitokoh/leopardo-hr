<?php

declare(strict_types=1);

namespace App\Core\Solutions\Infrastructure\Services;

use App\Core\Solutions\Contracts\VerticalPermissionCatalogue as VerticalPermissionCatalogueContract;
use App\Core\Solutions\SolutionCatalogue;
use App\Core\Tenant\Domain\Models\Company;

/**
 * Implémentation du contrat {@see VerticalPermissionCatalogueContract}.
 *
 * Voir le contrat pour la doctrine (fail-closed sur les verticales inactives,
 * lecture seule). Déplacée de `Core/Solutions` vers
 * `Core/Solutions/Infrastructure/Services` lors de l'extraction du contrat :
 * les modules importaient la classe concrète, ce que refuse
 * `IdentityLayerBoundariesArchitectureTest` (#8717, écart I-3).
 */
final class CatalogueVerticalPermissionCatalogue implements VerticalPermissionCatalogueContract
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
