<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Domain\Services;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FarmOs\Domain\Enums\FarmCapability;
use App\Modules\FarmOs\Domain\Exceptions\FarmCapabilityNotEnabledException;
use App\Modules\FarmOs\Domain\Models\FarmCapabilitySetting;

/**
 * Farm OS — Porte serveur des CAPACITÉS (outils métier activés) — ADR-FARM-0001.
 *
 * Le module Farm OS lui-même est gardé par le middleware `module.farm_os`
 * (feature flag de solution). Les 13 outils sont gardés ici, plus finement :
 * un outil non activé renvoie 403, même par appel direct de l'API.
 *
 * Fail-closed honnête :
 *   - si le tenant n'a exprimé AUCUNE décision (profil non complété), le socle
 *     minimal (`FarmCapability::coreValues()`) reste disponible pour ne pas
 *     bloquer un nouvel utilisateur ;
 *   - dès qu'au moins une décision existe, seules les capacités explicitement
 *     activées sont autorisées (un refus est respecté).
 */
final class FarmCapabilityGate
{
    /**
     * Capacités effectivement activées pour la company courante.
     *
     * @return list<string>
     */
    public function enabled(Company $company): array
    {
        $decisions = FarmCapabilitySetting::decisions();

        if ($decisions === []) {
            return FarmCapability::coreValues();
        }

        $enabled = [];

        foreach ($decisions as $capability => $isEnabled) {
            if ($isEnabled === true && FarmCapability::tryFrom((string) $capability) !== null) {
                $enabled[] = (string) $capability;
            }
        }

        return array_values(array_unique($enabled));
    }

    /**
     * Décisions brutes, y compris les refus (pour l'écran « Mes outils »).
     *
     * @return array<string, bool>
     */
    public function decisions(): array
    {
        return FarmCapabilitySetting::decisions();
    }

    public function allows(Company $company, FarmCapability $capability): bool
    {
        return in_array($capability->value, $this->enabled($company), true);
    }

    public function assertAllows(Company $company, FarmCapability $capability): void
    {
        if (! $this->allows($company, $capability)) {
            throw FarmCapabilityNotEnabledException::forCapability($capability->value);
        }
    }

    /**
     * Capacités actives ET permission métier détenue : ce que le front peut
     * afficher sans risque (jamais de section vide par manque de permission).
     *
     * Les permissions sont indexées (valeurs de `EmployeeModuleGrant`) : on
     * teste l'APPARTENANCE, jamais la clé.
     *
     * @param  array<int|string, string>  $grantedPermissions
     * @return list<string>
     */
    public function visibleFor(Company $company, array $grantedPermissions): array
    {
        $has = static fn (string $permission): bool => in_array($permission, $grantedPermissions, true);

        return array_values(array_filter(
            $this->enabled($company),
            static function (string $capability) use ($has): bool {
                $enum = FarmCapability::tryFrom($capability);

                if ($enum === null) {
                    return false;
                }

                return $has($enum->requiredPermission()) || $has('farm.admin');
            }
        ));
    }
}
