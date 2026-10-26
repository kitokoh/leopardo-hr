<?php

declare(strict_types=1);

namespace App\Modules\Geo\Infrastructure\Services;

use App\Modules\Geo\Domain\Contracts\GeocodingProviderInterface;
use App\Shared\Geo\GeoPoint;
use Illuminate\Support\Facades\Cache;

/**
 * GEO-08 (#8466, BC-34 GEO) — façade de géocodage du core géospatial.
 *
 * Propriétés :
 *  - **fail-closed** : sans fournisseur réel, `isEnabled()` est faux et
 *    l'endpoint refuse (503) — aucune coordonnée inventée ;
 *  - **cache** : une adresse normalisée n'est résolue qu'une fois par TTL
 *    (le géocodage est un appel externe coûteux et rate-limité) ;
 *  - **normalisation** : casse, espaces multiples et code pays entrent dans
 *    la clé de cache — « Paris » et «  paris » ne consomment qu'une requête.
 */
final class GeocodingService
{
    public function __construct(
        private readonly GeocodingProviderInterface $provider,
    ) {}

    /** Un fournisseur réel est-il branché ? (fail-closed si non) */
    public function isEnabled(): bool
    {
        return $this->provider->name() !== 'null';
    }

    public function providerName(): string
    {
        return $this->provider->name();
    }

    /**
     * Résout une adresse en coordonnées.
     *
     * @return array{point: GeoPoint, cached: bool}|null null si l'adresse
     *                                                   n'est pas résolue (le fournisseur ne sait pas, ou est désactivé).
     */
    public function geocode(string $address, ?string $countryCode = null): ?array
    {
        $key = $this->cacheKey($address, $countryCode);
        $ttl = (int) config('geo.geocoding.cache_ttl', 86400);

        // Le cache est un stockage non typé : la valeur relue peut être
        // n'importe quoi (entrée écrite par une version antérieure, purge
        // partielle). On annonce donc `mixed` et on valide la forme à
        // l'exécution — c'est le `isset()` ci-dessous qui fait foi.
        /** @var mixed $cached */
        $cached = Cache::get($key);

        if (is_array($cached) && isset($cached['lat'], $cached['lng'])) {
            return [
                'point' => new GeoPoint((float) $cached['lat'], (float) $cached['lng']),
                'cached' => true,
            ];
        }

        $point = $this->provider->geocode($address, $countryCode);

        if ($point === null) {
            return null;
        }

        Cache::put($key, ['lat' => $point->latitude, 'lng' => $point->longitude], $ttl);

        return ['point' => $point, 'cached' => false];
    }

    /** Clé de cache stable : adresse normalisée + code pays. */
    private function cacheKey(string $address, ?string $countryCode): string
    {
        $normalized = mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $address)));

        if ($countryCode !== null && $countryCode !== '') {
            $normalized .= '|'.mb_strtolower($countryCode);
        }

        return 'geo:geocode:'.hash('sha256', $normalized);
    }
}
