<?php

declare(strict_types=1);

namespace App\Modules\Geo\Infrastructure\Services;

use App\Modules\Geo\Domain\Contracts\GeocodingProviderInterface;
use App\Shared\Geo\GeoPoint;

/**
 * GEO-08 (#8466, BC-34 GEO) — fournisseur de géocodage par défaut : ne
 * résout RIEN.
 *
 * C'est le comportement fail-closed du core : tant qu'aucun fournisseur réel
 * n'est configuré, l'endpoint `POST /v1/geo/geocode` répond 503
 * `geocoding_disabled` plutôt que d'inventer des coordonnées ou d'appeler un
 * service tiers sans consentement explicite du tenant.
 */
final class NullGeocodingProvider implements GeocodingProviderInterface
{
    public function geocode(string $address, ?string $countryCode = null): ?GeoPoint
    {
        return null;
    }

    public function name(): string
    {
        return 'null';
    }
}
