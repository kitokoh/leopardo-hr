<?php

declare(strict_types=1);

namespace App\Modules\Vtc\Domain\Support;

use App\Modules\Vtc\Domain\Models\VtcFareProfile;

/**
 * Calculateur de tarif VTC (BC-34 VTC, VTC-03/#8359).
 *
 * Formule de la spec §5.4 : prix = max(minimum, base + per_km × distance_km
 * + per_minute × durée_min) — montants en MINOR UNITS, arrondi au plus
 * proche (half-up). La distance est la distance ROUTIÈRE estimée (vol
 * d'oiseau du core geo × coefficient `vtc.pricing.road_factor`, appliqué en
 * amont par EstimateRideFareAction).
 *
 * Classe pure : aucune dépendance Eloquent/DB — testable en unité.
 */
final class VtcFareCalculator
{
    /**
     * Prix estimé en minor units pour une distance/durée données.
     */
    public function priceMinor(VtcFareProfile $profile, int $distanceMeters, int $durationSeconds): int
    {
        $distanceKm = $distanceMeters / 1000.0;
        $durationMinutes = $durationSeconds / 60.0;

        $computed = $profile->base_minor
            + ($profile->per_km_minor * $distanceKm)
            + ($profile->per_minute_minor * $durationMinutes);

        return max($profile->minimum_minor, (int) round($computed));
    }

    /**
     * Durée estimée (secondes) d'un trajet routier à vitesse moyenne.
     */
    public function durationSeconds(int $roadDistanceMeters, float $averageSpeedKmh): int
    {
        if ($averageSpeedKmh <= 0.0) {
            return 0;
        }

        return (int) round(($roadDistanceMeters / 1000.0) / $averageSpeedKmh * 3600.0);
    }
}
