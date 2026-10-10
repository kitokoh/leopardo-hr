<?php

declare(strict_types=1);

namespace App\Shared\Geo;

use App\Shared\Geo\Exceptions\InvalidGeoPointException;

/**
 * GEO-03 (#8352, BC-33 GEO) — point géodésique validé (WGS 84).
 *
 * Value object immuable : SEULE façon de passer des coordonnées au core
 * géospatial (validation centralisée, fail-closed via
 * InvalidGeoPointException). Placé dans App\Shared\Geo (et non dans le
 * Domain du module Geo) pour que les verticales consommatrices — VTC en
 * premier — puissent typer leurs appels sans jamais importer
 * `App\Modules\Geo\*` (garde d'isolation #5584, règle spec §2).
 */
final class GeoPoint
{
    public function __construct(
        public readonly float $latitude,
        public readonly float $longitude,
    ) {
        if ($latitude < -90.0 || $latitude > 90.0) {
            throw InvalidGeoPointException::latitude($latitude);
        }

        if ($longitude < -180.0 || $longitude > 180.0) {
            throw InvalidGeoPointException::longitude($longitude);
        }
    }

    /**
     * Construit un point depuis un tableau associatif — clés acceptées :
     * `latitude`/`lat` et `longitude`/`lng` (contrat de l'API v1 GEO-05).
     *
     * @param  array<string, mixed>  $coordinates
     */
    public static function fromArray(array $coordinates): self
    {
        $latitude = $coordinates['latitude'] ?? $coordinates['lat'] ?? null;
        $longitude = $coordinates['longitude'] ?? $coordinates['lng'] ?? null;

        if (! is_numeric($latitude) || ! is_numeric($longitude)) {
            throw InvalidGeoPointException::missing();
        }

        return new self((float) $latitude, (float) $longitude);
    }

    /**
     * Depuis des colonnes nullable (position optionnelle d'un modèle) :
     * null si l'une des deux coordonnées manque.
     */
    public static function fromNullable(?float $latitude, ?float $longitude): ?self
    {
        if ($latitude === null || $longitude === null) {
            return null;
        }

        return new self($latitude, $longitude);
    }

    /** @return array{latitude: float, longitude: float} */
    public function toArray(): array
    {
        return [
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
        ];
    }
}
