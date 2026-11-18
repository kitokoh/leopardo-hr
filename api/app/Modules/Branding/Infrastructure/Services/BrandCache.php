<?php

declare(strict_types=1);

namespace App\Modules\Branding\Infrastructure\Services;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Branding\Domain\Contracts\BrandCacheInterface;
use App\Modules\Branding\Domain\Models\Brand;
use Illuminate\Support\Facades\Cache;

/**
 * Implémentation du cache des résolutions publiques de Brand (BC-41, #9578).
 *
 * ## Pourquoi les clés ne sont pas préfixées par le tenant
 *
 * Une résolution publique part d'un slug ou d'un host, PAS d'un tenant : le
 * tenant est précisément ce que la résolution cherche. La clé ne peut donc pas
 * porter un `company_id` qu'on ne connaît pas encore — c'est le cas d'usage
 * explicite de `tenant-cache:shared` (#8058) : un index de résolution
 * cross-tenant, dont la valeur contient le tenant résolu.
 *
 * Le risque de fuite est neutralisé autrement : la valeur mise en cache n'est
 * servie qu'après `isPubliclyVisible()` côté resolver, et la purge est ciblée
 * sur les deux clés possibles à chaque publication.
 */
final class BrandCache implements BrandCacheInterface
{
    /** Durée du cache positif, en secondes. */
    private const CACHE_TTL = 60;

    /**
     * @return array{brand: Brand, company: Company}|null
     */
    public function getBySlug(string $slug): ?array
    {
        // tenant-cache:shared — index de résolution public slug → tenant, le
        // tenant est le RÉSULTAT de la résolution, pas son entrée (#8058).
        /** @var array{brand: Brand, company: Company}|null $cached */
        $cached = Cache::get('brand:public:slug:'.$slug);

        return is_array($cached) ? $cached : null;
    }

    /**
     * @param  array{brand: Brand, company: Company}  $resolved
     */
    public function putBySlug(string $slug, array $resolved): void
    {
        // tenant-cache:shared — voir getBySlug() (#8058).
        Cache::put('brand:public:slug:'.$slug, $resolved, self::CACHE_TTL);
    }

    /**
     * @return array{brand: Brand, company: Company}|null
     */
    public function getByHost(string $host): ?array
    {
        // tenant-cache:shared — index de résolution public host → tenant (#8058).
        /** @var array{brand: Brand, company: Company}|null $cached */
        $cached = Cache::get('brand:public:host:'.$host);

        return is_array($cached) ? $cached : null;
    }

    /**
     * @param  array{brand: Brand, company: Company}  $resolved
     */
    public function putByHost(string $host, array $resolved): void
    {
        // tenant-cache:shared — voir getByHost() (#8058).
        Cache::put('brand:public:host:'.$host, $resolved, self::CACHE_TTL);
    }

    public function forget(string $slug, ?string $host): void
    {
        // tenant-cache:shared — purge ciblée des deux clés de résolution (#8058).
        Cache::forget('brand:public:slug:'.$slug);

        if (is_string($host) && $host !== '') {
            // tenant-cache:shared — purge ciblée de la clé host (#8058).
            Cache::forget('brand:public:host:'.$host);
        }
    }
}
