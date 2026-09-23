<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Services;

use App\Shared\Support\TenantCache;
use Illuminate\Support\Facades\Cache;

/**
 * Cache public du catalogue B2B (BC-28 CATALOG, C-PUBLIC #6882).
 *
 * Deux clés par tenant (UUID company_id, jamais de slug mutable) :
 *   - snapshot : liste publique (company + catégories + produits publiés) ;
 *   - fiche    : produit publié individuel (`…/p/{slug}`).
 *
 * Invalidation écrite depuis les mutations privées du tenant (publication,
 * dépublication, update, destroy, catégories) — spec : « Cache Redis TTL ;
 * invalidation à la publication ; 404 propre ». Le TTL (config
 * `catalog.public_cache_ttl`, 600 s) borne les cas sans flush dédié.
 */
final class CatalogPublicCache
{
    // #8058 — construction des clés DÉLÉGUÉE au helper central TenantCache
    // (préfixe company_id systématique, fail-closed). Le format change par
    // rapport à l'historique « catalog:public:v1:… » : les anciennes entrées
    // s'auto-purgent avec le TTL (600 s) — aucune invalidation manuelle.
    private const SUFFIX = 'catalog:public:v1';

    public static function snapshotKey(string $companyId): string
    {
        return TenantCache::keyFor($companyId, self::SUFFIX);
    }

    public static function productKey(string $companyId, string $productSlug): string
    {
        return TenantCache::keyFor($companyId, self::SUFFIX.':p:'.$productSlug);
    }

    /** Invalide le snapshot liste d'un tenant (mutations catégorie, store produit). */
    public static function forgetCompany(string $companyId): void
    {
        // tenant-cache:via-helper — clé construite par TenantCache::keyFor (#8058)
        Cache::forget(self::snapshotKey($companyId));
    }

    /** Invalide la fiche d'un produit ET le snapshot (mutations produit). */
    public static function forgetProduct(string $companyId, string $productSlug): void
    {
        // tenant-cache:via-helper — clé construite par TenantCache::keyFor (#8058)
        Cache::forget(self::productKey($companyId, $productSlug));
        Cache::forget(self::snapshotKey($companyId));
    }
}
