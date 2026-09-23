<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Infrastructure\Services;

use App\Modules\Showcase\Domain\Support\ShowcaseLocales;
use Illuminate\Support\Facades\Cache;

/**
 * Cache public des vitrines (BC-27 SHOWCASE, #6867 — étendu V-I18N #6874).
 *
 * - Lecture publique mise en cache Redis (TTL borné) ; la clé inclut la
 *   **locale** (le contenu de section varie par langue — `content_i18n`) :
 *   jamais de contenu d'une langue servi pour une autre ;
 * - invalidation à chaque mutation d'une vitrine publiée (sections,
 *   publication/dépublication, réglages) via {@see forget()} qui purge
 *   toutes les locales de la vitrine (+ clé historique non locale) ;
 * - les réponses d'aperçu (`?token=`) ne sont jamais mises en cache.
 *
 * Le cache stocke le DTO public (tableau JSON-safe), jamais de modèles
 * Eloquent.
 */
final class ShowcasePublicCache
{
    public const TTL_SECONDS = 900; // 15 min

    private const KEY_PREFIX = 'showcase:public:';

    public static function key(string $slug, string $locale = ShowcaseLocales::DEFAULT): string
    {
        return self::KEY_PREFIX.$slug.':'.$locale;
    }

    /**
     * @template T
     *
     * @param  \Closure(): T  $resolver
     * @return T
     */
    public function remember(string $slug, string $locale, \Closure $resolver): mixed
    {
        // tenant-cache:shared — vitrine PUBLIQUE par slug globalement unique (#8058)
        return Cache::remember(self::key($slug, $locale), now()->addSeconds(self::TTL_SECONDS), $resolver);
    }

    /**
     * Purge toutes les locales d'une vitrine (+ clé historique sans locale).
     */
    public function forget(string $slug): void
    {
        // tenant-cache:shared — même vitrine publique (#8058)
        Cache::forget(self::KEY_PREFIX.$slug);

        foreach (ShowcaseLocales::supported() as $locale) {
            // tenant-cache:shared — même vitrine publique (#8058)
            Cache::forget(self::key($slug, $locale));
        }
    }
}
