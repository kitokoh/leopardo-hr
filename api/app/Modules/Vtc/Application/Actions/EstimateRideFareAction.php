<?php

declare(strict_types=1);

namespace App\Modules\Vtc\Application\Actions;

use App\Modules\Vtc\Application\DTOs\VtcFareEstimate;
use App\Modules\Vtc\Domain\Models\VtcFareProfile;
use App\Modules\Vtc\Domain\Support\VtcFareCalculator;
use App\Shared\Contracts\Geo\GeoServiceContract;
use App\Shared\Geo\GeoPoint;

/**
 * Estimation de tarif d'une course (BC-34 VTC, VTC-03/#8359).
 *
 * Chaîne de calcul (spec §5.4) :
 *   1. distance à vol d'oiseau via le CORE GÉOSPATIAL (BC-33) — jamais de
 *      calcul local (principe fondateur : vtc ne calcule pas de distance) ;
 *   2. distance routière estimée = vol d'oiseau × `vtc.pricing.road_factor`
 *      (défaut 1.3 — moteur d'itinéraire réel hors scope v1) ;
 *   3. durée estimée à vitesse moyenne `vtc.pricing.avg_speed_kmh` ;
 *   4. prix = max(minimum, base + km×per_km + min×per_minute) sur la grille
 *      demandée ou la grille par défaut du tenant.
 *
 * Sans grille tarifaire configurée : distance et durée restent estimées,
 * price_minor vaut null (devis honnête plutôt que prix inventé).
 */
final class EstimateRideFareAction
{
    public function __construct(
        private readonly GeoServiceContract $geo,
        private readonly VtcFareCalculator $calculator,
    ) {}

    public function execute(GeoPoint $pickup, GeoPoint $dropoff, ?int $fareProfileId = null): VtcFareEstimate
    {
        $distanceMeters = $this->geo->distanceMeters($pickup, $dropoff);

        $roadDistanceMeters = (int) round($distanceMeters * $this->roadFactor());
        $durationSeconds = $this->calculator->durationSeconds($roadDistanceMeters, $this->averageSpeedKmh());

        $profile = $this->resolveFareProfile($fareProfileId);

        $priceMinor = $profile !== null
            ? $this->calculator->priceMinor($profile, $roadDistanceMeters, $durationSeconds)
            : null;

        return new VtcFareEstimate(
            $distanceMeters,
            $roadDistanceMeters,
            $durationSeconds,
            $priceMinor,
            $profile?->currency ?? $this->defaultCurrency(),
            $profile?->id,
        );
    }

    private function resolveFareProfile(?int $fareProfileId): ?VtcFareProfile
    {
        if ($fareProfileId !== null) {
            /** @var VtcFareProfile|null $scoped */
            $scoped = VtcFareProfile::query()->whereKey($fareProfileId)->first();

            return $scoped;
        }

        /** @var VtcFareProfile|null $default */
        $default = VtcFareProfile::query()->where('is_default', true)->first();

        return $default;
    }

    private function roadFactor(): float
    {
        $factor = config('vtc.pricing.road_factor', 1.3);

        return is_numeric($factor) ? max(1.0, (float) $factor) : 1.3;
    }

    private function averageSpeedKmh(): float
    {
        $speed = config('vtc.pricing.avg_speed_kmh', 22.0);

        return is_numeric($speed) && (float) $speed > 0.0 ? (float) $speed : 22.0;
    }

    private function defaultCurrency(): string
    {
        $currency = config('vtc.pricing.default_currency', 'XAF');

        return is_string($currency) && $currency !== '' ? $currency : 'XAF';
    }
}
