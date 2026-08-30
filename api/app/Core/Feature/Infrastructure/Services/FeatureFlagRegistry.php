<?php

declare(strict_types=1);

namespace App\Core\Feature\Infrastructure\Services;

use App\Core\Tenant\Domain\Models\Company;

/**
 * MAT-010 (#5868) — Registre versionné des feature flags + kill switches.
 *
 * Résolution d'un flag (dans l'ordre, fail-closed) :
 *   1. flag inconnu du registre            → false (désactivé) ;
 *   2. kill switch global (config/env)     → false (coupé pour TOUS les tenants,
 *      sans suppression de données) ;
 *   3. company présente                    → `company.features` (hasFeature) ;
 *   4. sinon                               → défaut versionné du registre.
 */
final class FeatureFlagRegistry
{
    /**
     * @param  array<string, mixed>  $config  (config('feature-flags'))
     */
    public function __construct(private readonly array $config)
    {
    }

    public function version(): string
    {
        return (string) ($this->config['version'] ?? '0.0.0');
    }

    /**
     * @return list<string>
     */
    public function knownKeys(): array
    {
        return array_keys($this->config['flags'] ?? []);
    }

    /**
     * @return array{scope?: string, default?: bool, since?: string, killable?: bool, description?: string}|null
     */
    public function definition(string $key): ?array
    {
        $flags = $this->config['flags'] ?? [];

        if (! is_array($flags) || ! array_key_exists($key, $flags)) {
            return null;
        }

        $definition = $flags[$key];

        return is_array($definition) ? $definition : null;
    }

    /**
     * Kill switch global pour une clé : config `kill_switches` OU env
     * `FEATURE_FLAG_KILL_<CLE_MAJUSCULE>` (=1/true → coupé). L'env prime.
     */
    public function isKillSwitched(string $key): bool
    {
        $envValue = env('FEATURE_FLAG_KILL_'.strtoupper(str_replace('-', '_', $key)));

        if ($envValue !== null && $envValue !== '') {
            return filter_var($envValue, FILTER_VALIDATE_BOOL);
        }

        $killSwitches = $this->config['kill_switches'] ?? [];

        return is_array($killSwitches) && (bool) ($killSwitches[$key] ?? false);
    }

    public function enabled(string $key, ?Company $company): bool
    {
        $definition = $this->definition($key);

        // Fail-closed : flag inconnu = désactivé (comportement historique de
        // FeatureFlag::enabled, désormais versionné et auditable).
        if ($definition === null) {
            return false;
        }

        // Kill switch : coupé pour tous les tenants, sans toucher aux données.
        if ($this->isKillSwitched($key)) {
            return false;
        }

        if ($company !== null) {
            return $company->hasFeature($key);
        }

        return (bool) ($definition['default'] ?? false);
    }

    /**
     * Carte complète des flags connus, résolus pour la company (ou défauts).
     *
     * @return array<string, bool>
     */
    public function for(?Company $company): array
    {
        $flags = [];

        foreach ($this->knownKeys() as $key) {
            $flags[$key] = $this->enabled($key, $company);
        }

        return $flags;
    }
}
