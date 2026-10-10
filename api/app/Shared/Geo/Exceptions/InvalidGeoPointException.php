<?php

declare(strict_types=1);

namespace App\Shared\Geo\Exceptions;

use App\Exceptions\DomainException;

/**
 * GEO-03 (#8352, BC-33 GEO) — coordonnée géographique invalide (fail-closed).
 *
 * Levée par GeoPoint dès qu'une latitude sort de [-90, 90], une longitude
 * de [-180, 180], ou qu'une coordonnée manque : aucune coordonnée non
 * validée ne traverse le core géospatial. Partagée avec le VO (garde #5584).
 */
class InvalidGeoPointException extends DomainException
{
    private function __construct(string $message)
    {
        parent::__construct($message, 422, 'GEO_INVALID_POINT');
    }

    public static function latitude(float $value): self
    {
        return new self("Latitude invalide : {$value} (attendu entre -90 et 90).");
    }

    public static function longitude(float $value): self
    {
        return new self("Longitude invalide : {$value} (attendu entre -180 et 180).");
    }

    public static function missing(): self
    {
        return new self('Coordonnées manquantes : latitude et longitude numériques requises.');
    }
}
