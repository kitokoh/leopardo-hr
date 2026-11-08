<?php

declare(strict_types=1);

namespace App\Modules\Library\Infrastructure\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Cache public du catalogue Leopardo Library (BC-40 LIBRARY).
 *
 * Le catalogue public est GLOBAL (`company_id` NULL) : il ne contient aucune
 * donnée d'un tenant privé. Une seule clé de snapshot (catégories + ressources
 * publiées) est donc légitimement partagée entre tous les visiteurs — c'est un
 * agrégat cross-tenant voulu, pas une fuite.
 *
 * TTL : config `library.public_cache_ttl` (600 s). Invalidation explicite à la
 * publication/dépublication d'une ressource globale (cf.
 * `TransitionLibraryResourceStatus`).
 */
final class LibraryPublicCache
{
    private const SNAPSHOT_KEY = 'library:public:snapshot';

    public static function snapshotKey(): string
    {
        return self::SNAPSHOT_KEY;
    }

    public static function ttl(): int
    {
        return (int) config('library.public_cache_ttl', 600);
    }

    /**
     * Lit le snapshot public depuis le cache, ou le construit via $builder.
     *
     * @template TCacheValue
     *
     * @param  \Closure(): TCacheValue  $builder
     * @return TCacheValue
     */
    public static function remember(\Closure $builder): mixed
    {
        // tenant-cache:shared — catalogue public GLOBAL Leopardo (company_id NULL),
        // agrégat cross-tenant voulu : aucune donnée d'un tenant privé n'y entre.
        return Cache::remember(self::SNAPSHOT_KEY, self::ttl(), $builder);
    }

    /** Invalide le snapshot public (publication/dépublication d'une ressource globale). */
    public static function forget(): void
    {
        // tenant-cache:shared — clé du catalogue public GLOBAL (company_id NULL).
        Cache::forget(self::SNAPSHOT_KEY);
    }
}
