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
 *
 * BOS-011 (#8198, ADR-0026) : la source des définitions dépend du mode
 * `module-registry.mode` via {@see ModuleRegistryGateway} —
 * `legacy` (config `feature-flags.flags`, comportement historique inchangé),
 * `dual` (les deux chemins calculés, legacy servi, divergences journalisées
 * sans PII), `registry` (dérivation du ModuleRegistry servie). La parité
 * des deux sources est gardée en CI pendant la transition.
 */
final class FeatureFlagRegistry
{
    /**
     * @param  array<string, mixed>  $config  (config('feature-flags'))
     */
    public function __construct(
        private readonly array $config,
        private readonly ?ModuleRegistryGateway $gateway = null,
    ) {}

    public function version(): string
    {
        if ($this->gateway !== null && $this->gateway->mode() === ModuleRegistryGateway::MODE_REGISTRY) {
            return $this->gateway->registry()->version();
        }

        return (string) ($this->config['version'] ?? '0.0.0');
    }

    /**
     * @return list<string>
     */
    public function knownKeys(): array
    {
        return array_map('strval', array_keys($this->definitionsSource()));
    }

    /**
     * @return array{scope?: string, default?: bool, since?: string, killable?: bool, description?: string}|null
     */
    public function definition(string $key): ?array
    {
        $flags = $this->definitionsSource();

        if (! array_key_exists($key, $flags)) {
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
        $envValue = getenv('FEATURE_FLAG_KILL_'.strtoupper(str_replace('-', '_', $key)));

        if ($envValue !== false && $envValue !== '') {
            return filter_var($envValue, FILTER_VALIDATE_BOOL);
        }

        $killSwitches = $this->config['kill_switches'] ?? [];

        return is_array($killSwitches) && (bool) ($killSwitches[$key] ?? false);
    }

    public function enabled(string $key, ?Company $company): bool
    {
        $legacy = $this->resolveFrom($this->legacyFlags(), $key, $company);

        if ($this->gateway === null || $this->gateway->mode() === ModuleRegistryGateway::MODE_LEGACY) {
            return $legacy;
        }

        $registryValue = $this->resolveFrom($this->gateway->registry()->flags(), $key, $company);

        if ($this->gateway->mode() === ModuleRegistryGateway::MODE_REGISTRY) {
            return $registryValue;
        }

        // Mode dual : legacy servie, divergence journalisée (sans PII).
        if ($registryValue !== $legacy) {
            $this->gateway->logDivergence('enabled', $key, $legacy, $registryValue, $company);
        }

        return $legacy;
    }

    /**
     * Carte complète des flags connus, résolus pour la company (ou défauts).
     *
     * @return array<string, bool>
     */
    public function for(?Company $company): array
    {
        $legacy = $this->mapFrom($this->legacyFlags(), $company);

        if ($this->gateway === null || $this->gateway->mode() === ModuleRegistryGateway::MODE_LEGACY) {
            return $legacy;
        }

        $registryMap = $this->mapFrom($this->gateway->registry()->flags(), $company);

        if ($this->gateway->mode() === ModuleRegistryGateway::MODE_REGISTRY) {
            return $registryMap;
        }

        // Mode dual : divergence de clé OU de valeur journalisée, legacy servie.
        /** @var array<string, bool|null> $union */
        $union = $registryMap + $legacy;

        foreach (array_keys($union) as $key) {
            $legacyValue = $legacy[$key] ?? null;
            $registryValue = $registryMap[$key] ?? null;

            if ($legacyValue !== $registryValue) {
                $this->gateway->logDivergence('for', (string) $key, $legacyValue, $registryValue, $company);
            }
        }

        return $legacy;
    }

    /**
     * Source des définitions exposées (connues) selon le mode — legacy par
     * défaut, dérivation du registre en mode `registry`, legacy servie en
     * mode `dual` (la comparaison a lieu à la résolution).
     *
     * @return array<string, mixed>
     */
    private function definitionsSource(): array
    {
        if ($this->gateway !== null && $this->gateway->mode() === ModuleRegistryGateway::MODE_REGISTRY) {
            return $this->gateway->registry()->flags();
        }

        return $this->legacyFlags();
    }

    /**
     * @return array<string, mixed>
     */
    private function legacyFlags(): array
    {
        $flags = $this->config['flags'] ?? [];

        return is_array($flags) ? $flags : [];
    }

    /**
     * Résolution fail-closed d'une clé depuis une source de définitions
     * (ordre contractuel : inconnu → kill switch → tenant → défaut).
     *
     * @param  array<string, mixed>  $flags
     */
    private function resolveFrom(array $flags, string $key, ?Company $company): bool
    {
        // Fail-closed : flag inconnu = désactivé (comportement historique de
        // FeatureFlag::enabled, désormais versionné et auditable).
        if (! array_key_exists($key, $flags) || ! is_array($flags[$key])) {
            return false;
        }

        // Kill switch : coupé pour tous les tenants, sans toucher aux données.
        if ($this->isKillSwitched($key)) {
            return false;
        }

        if ($company !== null) {
            return $company->hasFeature($key);
        }

        return (bool) ($flags[$key]['default'] ?? false);
    }

    /**
     * @param  array<string, mixed>  $flags
     * @return array<string, bool>
     */
    private function mapFrom(array $flags, ?Company $company): array
    {
        $map = [];

        foreach (array_keys($flags) as $key) {
            $map[(string) $key] = $this->resolveFrom($flags, (string) $key, $company);
        }

        return $map;
    }
}
