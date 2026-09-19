<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Infrastructure\Services;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Cache public de l'annuaire des restaurants (RESTO-901, issue #7746).
 *
 * Pattern ShowcasePublicCache/CatalogPublicCache (BC-27/BC-28) : la lecture
 * publique d'un profil (`GET /public/restaurants/{slug}`) est mise en cache
 * (TTL borné) et invalidée à chaque mutation du profil public d'une branche
 * (RestaurantBranchPublicProfileController) — ancien ET nouveau slug purgés
 * lors d'un changement de slug. Le cache stocke le DTO public (tableau
 * JSON-safe), jamais de modèles Eloquent.
 */
final class RestaurantPublicDirectoryCache
{
    public const TTL_SECONDS = 600; // 10 min

    private const KEY_PREFIX = 'restaurant:public:profile:';

    public static function key(string $slug): string
    {
        return self::KEY_PREFIX.$slug;
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $resolver
     * @return T
     */
    public function remember(string $slug, Closure $resolver): mixed
    {
        return Cache::remember(self::key($slug), now()->addSeconds(self::TTL_SECONDS), $resolver);
    }

    public function forget(?string $slug): void
    {
        if ($slug === null || $slug === '') {
            return;
        }

        Cache::forget(self::key($slug));
    }
}
