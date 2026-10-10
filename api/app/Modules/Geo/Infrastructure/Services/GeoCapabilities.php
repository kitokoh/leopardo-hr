<?php

declare(strict_types=1);

namespace App\Modules\Geo\Infrastructure\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * GEO-01 (#8350, BC-33 GEO) — détection runtime de la capacité PostGIS.
 *
 * Le module `geo` fonctionne en mode dégradé (calculateur Haversine, GEO-03)
 * lorsque l'extension est absente — environnement non provisionné, base de
 * test — plutôt que de casser les verticales consommatrices. La détection
 * est mise en cache (config `geo.capabilities_cache_ttl`) pour ne pas
 * interroger pg_extension à chaque requête.
 */
final class GeoCapabilities
{
    private const CACHE_KEY_AVAILABLE = 'geo.postgis.available';

    private const CACHE_KEY_VERSION = 'geo.postgis.version';

    public function postgisAvailable(): bool
    {
        if (DB::getDriverName() !== 'pgsql') {
            return false;
        }

        /** @var bool $available */
        $available = Cache::remember(
            self::CACHE_KEY_AVAILABLE,
            $this->cacheTtl(),
            fn (): bool => $this->probeExtension() !== null
        );

        return $available;
    }

    public function postgisVersion(): ?string
    {
        if (DB::getDriverName() !== 'pgsql') {
            return null;
        }

        /** @var string|null $version */
        $version = Cache::remember(
            self::CACHE_KEY_VERSION,
            $this->cacheTtl(),
            fn (): ?string => $this->probeExtension()
        );

        return $version;
    }

    /**
     * Invalide le cache de détection (après provisionnement de l'extension).
     */
    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY_AVAILABLE);
        Cache::forget(self::CACHE_KEY_VERSION);
    }

    /**
     * Version de l'extension installée, null si absente ou illisible.
     */
    private function probeExtension(): ?string
    {
        try {
            $row = DB::selectOne("SELECT extversion FROM pg_extension WHERE extname = 'postgis'");
        } catch (Throwable) {
            return null;
        }

        if (! is_object($row) || ! property_exists($row, 'extversion')) {
            return null;
        }

        $version = $row->extversion;

        return is_string($version) ? $version : null;
    }

    private function cacheTtl(): int
    {
        $ttl = config('geo.capabilities_cache_ttl', 300);

        return is_int($ttl) ? $ttl : 300;
    }
}
