<?php

declare(strict_types=1);

namespace App\Core\Feature\Infrastructure\Services;

use App\Core\Tenant\Domain\Models\Company;

/**
 * MAT-010 (#5868) — Feature flags (socle) : délègue au registre versionné.
 *
 * APV L.08 — Un module = un package activable par company.
 *
 * La résolution est déléguée à {@see FeatureFlagRegistry} (registre versionné
 * + kill switches, fail-closed) — l'API statique historique est conservée :
 *   FeatureFlag::enabled('finance', $company)  => bool
 *   FeatureFlag::for($company)                 => array des flags résolus
 */
class FeatureFlag
{
    /**
     * Retourne true si la feature est active pour la company donnée.
     * Les features inconnues retournent false (fail-closed).
     */
    public static function enabled(string $key, ?Company $company): bool
    {
        return app(FeatureFlagRegistry::class)->enabled($key, $company);
    }

    /**
     * Retourne la carte complete des flags connus pour la company (resolus).
     * Pratique pour serialiser dans /auth/me.
     */
    public static function for(?Company $company): array
    {
        return app(FeatureFlagRegistry::class)->for($company);
    }

    /**
     * Version du registre des feature flags (MAT-010).
     */
    public static function version(): string
    {
        return app(FeatureFlagRegistry::class)->version();
    }
}
