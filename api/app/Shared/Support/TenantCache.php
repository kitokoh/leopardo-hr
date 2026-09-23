<?php

declare(strict_types=1);

namespace App\Shared\Support;

use App\Core\Tenant\Domain\Models\Company;

/**
 * #8058 — Point d'entrée UNIQUE des clés de cache tenant-scopées.
 *
 * Politique : comme `BelongsToCompany` côté DB, toute clé de cache portant
 * des données d'un tenant DOIT être préfixée par son `company_id` — une clé
 * construite à la main qui l'oublie sert les données d'un tenant à un autre
 * (fuite inter-tenant).
 *
 * Deux usages :
 * - `TenantCache::key($suffix)`    → contexte tenant COURANT (middleware
 *   tenant / job tenantisé) — FAIL-CLOSED hors contexte : mieux vaut une
 *   exception explicite qu'une clé non préfixée silencieuse ;
 * - `TenantCache::keyFor($id, $s)` → company_id EXPLICITE, pour les contextes
 *   publics qui résolvent le tenant par ressource (slug, jeton boutique…).
 *
 * Un cache LÉGITIMEMENT partagé (sitemap global, crédential Firebase,
 * back-office plateforme, throttle par email/IP…) n'utilise PAS ce helper :
 * il pose le marqueur `// tenant-cache:shared — <raison>` à côté de l'appel
 * `Cache::` brut (garde CI `check-tenant-cache-keys.sh`).
 */
final class TenantCache
{
    private const PREFIX = 'tenant';

    /**
     * Clé préfixée par le company_id du contexte tenant courant.
     *
     * @throws \RuntimeException hors contexte tenant (fail-closed).
     */
    public static function key(string $suffix): string
    {
        if (! app()->bound('current_company')) {
            throw new \RuntimeException(
                'TenantCache::key() hors contexte tenant — résoudre le tenant '
                .'et utiliser TenantCache::keyFor($companyId, …), ou assumer un '
                .'cache partagé via le marqueur « tenant-cache:shared » (#8058).'
            );
        }

        /** @var Company $company */
        $company = app('current_company');

        return self::keyFor((string) $company->id, $suffix);
    }

    /**
     * Clé préfixée par un company_id explicite (contextes publics résolvant
     * le tenant par ressource — slug, jeton boutique, référence…).
     */
    public static function keyFor(string $companyId, string $suffix): string
    {
        $companyId = trim($companyId);
        $suffix = trim($suffix);

        if ($companyId === '') {
            throw new \RuntimeException('TenantCache::keyFor() : company_id vide (fail-closed, #8058).');
        }

        if ($suffix === '') {
            throw new \RuntimeException('TenantCache::keyFor() : suffixe de clé vide (#8058).');
        }

        return self::PREFIX.':'.$companyId.':'.$suffix;
    }
}
