<?php

namespace App\Services;

use App\Models\Company;

/**
 * APV L.08 — Un module = un package activable par company.
 *
 * Service leger pour interroger les feature flags stockes dans companies.features (JSONB).
 * Utilisation :
 *   FeatureFlag::enabled('finance', $company)  => bool
 *   FeatureFlag::for($company)                 => array des flags resolus
 */
class FeatureFlag
{
    /**
     * Retourne true si la feature est active pour la company donnee.
     * Les features inconnues retournent false (fail-closed).
     */
    public static function enabled(string $key, ?Company $company): bool
    {
        if ($company === null) {
            return false;
        }

        return $company->hasFeature($key);
    }

    /**
     * Retourne la carte complete des flags connus pour la company (resolus).
     * Pratique pour serialiser dans /auth/me.
     */
    public static function for(?Company $company): array
    {
        $flags = [];

        foreach (Company::KNOWN_MODULES as $module) {
            $flags[$module] = self::enabled($module, $company);
        }

        return $flags;
    }
}
