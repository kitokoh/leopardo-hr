<?php

declare(strict_types=1);

namespace App\Core\Feature\Infrastructure\Services;

use App\Core\Tenant\Domain\Models\Company;

/**
 * Issue #5742 (CRM PRE) — feature flags et kill switch par tenant pour le
 * CRM client.
 *
 * Évaluation 100 % côté serveur. Trois niveaux :
 *   1. Kill switch global (config crm.kill_switch) — frein d'urgence, prime
 *      sur tout le reste ;
 *   2. Commutateur global du module (config crm.enabled) ;
 *   3. Flag TENANT `crm` (companies.features, opt-in plateforme via
 *      PATCH /platform/companies/{company}/features, ADR-CRM-004) — désactivé
 *      par défaut pour toute nouvelle entreprise.
 *
 * Les canaux d'intégration (whatsapp, email, sms) sont FERMÉS PAR DÉFAUT :
 * évaluation = commutateur global (env, défaut false) ET flag tenant
 * (companies.metadata.crm.integrations.<key>.enabled, défaut false). Le
 * frontend ne peut jamais s'auto-autoriser : aucune route tenant n'écrit ces
 * états (PATCH plateforme super-admin uniquement).
 */
final class CrmFeature
{
    /** Clé du flag tenant (companies.features). */
    public const TENANT_FLAG = 'crm';

    /** Canaux d'intégration connus (allowlist — jamais une valeur client). */
    public const INTEGRATIONS = ['whatsapp', 'email', 'sms'];

    /** Préfixe des métadonnées tenant pour les canaux. */
    private const METADATA_PREFIX = 'crm.integrations.';

    public static function killSwitchActive(): bool
    {
        return (bool) config('crm.kill_switch.enabled', false);
    }

    /**
     * Le module CRM est-il actif pour le tenant ?
     *
     * Fail-closed : company inconnue ou flag absent → désactivé. Le flag
     * `rh` reste le seul vrai par défaut (base de l'app) — le CRM est un
     * opt-in explicite (ADR-CRM-004).
     */
    public static function enabled(?Company $company): bool
    {
        if (self::killSwitchActive()) {
            return false;
        }

        if (! (bool) config('crm.enabled', true)) {
            return false;
        }

        return $company !== null && $company->hasFeature(self::TENANT_FLAG);
    }

    /**
     * Un canal d'intégration est-il actif pour le tenant ?
     *
     * FERMÉ PAR DÉFAUT : le canal doit être autorisé globalement (env) ET au
     * niveau tenant (métadonnées). Kill switch actif → tout coupé.
     */
    public static function integrationEnabled(string $key, ?Company $company): bool
    {
        if (! in_array($key, self::INTEGRATIONS, true)) {
            return false;
        }

        if (self::killSwitchActive()) {
            return false;
        }

        if (! (bool) config("crm.integrations.{$key}.enabled", false)) {
            return false;
        }

        if ($company === null) {
            return false;
        }

        $integration = ($company->metadata ?? [])[self::METADATA_PREFIX.$key] ?? null;

        return is_array($integration) && (bool) ($integration['enabled'] ?? false);
    }

    /**
     * État effectif d'un canal pour le tenant — détail évalué côté serveur.
     *
     * @return array{enabled: bool, global: bool, tenant: bool}
     */
    public static function integrationState(string $key, ?Company $company): array
    {
        $global = in_array($key, self::INTEGRATIONS, true)
            && (bool) config("crm.integrations.{$key}.enabled", false);

        $integration = ($company?->metadata ?? [])[self::METADATA_PREFIX.$key] ?? null;
        $tenant = is_array($integration) && (bool) ($integration['enabled'] ?? false);

        return [
            'enabled' => ! self::killSwitchActive() && $global && $tenant,
            'global' => $global,
            'tenant' => $tenant,
        ];
    }

    /**
     * État complet du CRM pour le tenant — vérité serveur exposée au
     * frontend (menus, diagnostics). Jamais une autorisation : les routes
     * gardent la gate `crm.enabled`.
     *
     * @return array{enabled: bool, kill_switch: bool, integrations: array<string, array{enabled: bool, global: bool, tenant: bool}>}
     */
    public static function status(?Company $company): array
    {
        $integrations = [];

        foreach (self::INTEGRATIONS as $key) {
            $integrations[$key] = self::integrationState($key, $company);
        }

        return [
            'enabled' => self::enabled($company),
            'kill_switch' => self::killSwitchActive(),
            'integrations' => $integrations,
        ];
    }
}
