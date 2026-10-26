<?php

declare(strict_types=1);

namespace App\Modules\Geo\Domain\Contracts;

use App\Shared\Geo\GeoPoint;

/**
 * GEO-08 (#8466, BC-34 GEO) — fournisseur de géocodage adresse → coordonnées.
 *
 * Le core géospatial ne dépend d'AUCUN service externe : le géocodage est
 * optionnel et fail-closed. Sans fournisseur configuré, le provider par
 * défaut (`NullGeocodingProvider`) refuse la requête — jamais de coordonnées
 * inventées, jamais d'appel réseau implicite (décision D1 de la spec : le
 * core reste utilisable sans dépendance tierce).
 *
 * Un fournisseur réel (Nominatim, Mapbox, Google…) s'implémente ici et se
 * branche dans GeoServiceProvider — les verticales ne voient que
 * `GeocodingService`, jamais l'implémentation.
 */
interface GeocodingProviderInterface
{
    /**
     * Coordonnées d'une adresse, ou null si le fournisseur ne sait pas la
     * résoudre (adresse inconnue, hors zone couverte, quota atteint).
     *
     * @param  string  $address  adresse libre (déjà normalisée par le service)
     * @param  string|null  $countryCode  code pays ISO 3166-1 alpha-2 (biais de recherche)
     */
    public function geocode(string $address, ?string $countryCode = null): ?GeoPoint;

    /** Identifiant du fournisseur (`null` | `nominatim` | …) — diagnostic et tests. */
    public function name(): string;
}
