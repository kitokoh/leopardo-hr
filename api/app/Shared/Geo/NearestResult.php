<?php

declare(strict_types=1);

namespace App\Shared\Geo;

use App\Shared\Contracts\Geo\GeoLocatable;

/**
 * GEO-04 (#8353, BC-33 GEO) — un résultat de recherche « le plus proche ».
 *
 * Value object partagé (garde #5584) : l'entité localisée + sa distance au
 * centre de recherche, triés par distance croissante par le moteur.
 */
final class NearestResult
{
    public function __construct(
        public readonly GeoLocatable $locatable,
        public readonly Distance $distance,
    ) {
    }

    /**
     * Représentation API (GEO-05) : identifiant si le locatable est un modèle
     * Eloquent persisté, libellé, distance et position.
     *
     * @return array{id: int|string|null, label: string, distance_meters: int, latitude: float|null, longitude: float|null}
     */
    public function toArray(): array
    {
        $id = null;

        if (method_exists($this->locatable, 'getKey')) {
            /** @var int|string|null $key */
            $key = $this->locatable->getKey();
            $id = $key;
        }

        $point = $this->locatable->geoPoint();

        return [
            'id' => $id,
            'label' => $this->locatable->geoLabel(),
            'distance_meters' => $this->distance->meters(),
            'latitude' => $point?->latitude,
            'longitude' => $point?->longitude,
        ];
    }
}
