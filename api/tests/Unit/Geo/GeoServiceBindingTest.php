<?php

declare(strict_types=1);

namespace Tests\Unit\Geo;

use App\Modules\Geo\Domain\Contracts\DistanceCalculatorInterface;
use App\Modules\Geo\Infrastructure\Services\GeoCapabilities;
use App\Modules\Geo\Infrastructure\Services\HaversineDistanceCalculator;
use App\Modules\Geo\Infrastructure\Services\PostgisDistanceCalculator;
use App\Shared\Contracts\Geo\GeoServiceContract;
use App\Shared\Geo\GeoPoint;
use Tests\TestCase;

/**
 * GEO-03 (#8352, BC-33 GEO) — sélection du calculateur via GeoCapabilities
 * et binding de la façade transverse GeoServiceContract.
 */
class GeoServiceBindingTest extends TestCase
{
    public function test_distance_calculator_matches_postgis_capability(): void
    {
        /** @var GeoCapabilities $capabilities */
        $capabilities = $this->app->make(GeoCapabilities::class);
        $capabilities->forget();

        $calculator = $this->app->make(DistanceCalculatorInterface::class);

        if ($capabilities->postgisAvailable()) {
            self::assertInstanceOf(PostgisDistanceCalculator::class, $calculator);
        } else {
            self::assertInstanceOf(HaversineDistanceCalculator::class, $calculator);
        }
    }

    public function test_geo_service_contract_is_bound_and_computes(): void
    {
        /** @var GeoServiceContract $geo */
        $geo = $this->app->make(GeoServiceContract::class);

        $paris = new GeoPoint(48.8566, 2.3522);
        $lyon = new GeoPoint(45.764, 4.8357);

        $meters = $geo->distanceMeters($paris, $lyon);

        // Paris → Lyon ≈ 392 km quel que soit le moteur — ±1 % (critère issue).
        self::assertGreaterThan(388000, $meters);
        self::assertLessThan(396000, $meters);
        self::assertContains($geo->distanceEngine(), ['postgis', 'haversine']);
        self::assertSame($meters, $geo->distance($paris, $lyon)->meters());
    }
}
