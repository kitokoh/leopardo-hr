<?php

declare(strict_types=1);

namespace Tests\Unit\Geo;

use App\Modules\Geo\Infrastructure\Services\HaversineDistanceCalculator;
use App\Shared\Geo\GeoPoint;
use PHPUnit\Framework\TestCase;

/**
 * GEO-03 (#8352, BC-33 GEO) — exactitude du calculateur de repli Haversine
 * sur des distances connues (villes, zéro, antipodes).
 */
class HaversineDistanceCalculatorTest extends TestCase
{
    private HaversineDistanceCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = new HaversineDistanceCalculator;
    }

    public function test_paris_lyon_is_about_392_km(): void
    {
        $paris = new GeoPoint(48.8566, 2.3522);
        $lyon = new GeoPoint(45.764, 4.8357);

        $distance = $this->calculator->distance($paris, $lyon)->meters();

        // Référence géodésique ≈ 392 km — tolérance ±1 % (critère de l'issue).
        self::assertGreaterThan(388000, $distance);
        self::assertLessThan(396000, $distance);
    }

    public function test_zero_distance_between_identical_points(): void
    {
        $point = new GeoPoint(48.8566, 2.3522);

        self::assertSame(0, $this->calculator->distance($point, $point)->meters());
    }

    public function test_antipodes_is_about_half_earth_circumference(): void
    {
        $a = new GeoPoint(0.0, 0.0);
        $b = new GeoPoint(0.0, 180.0);

        $distance = $this->calculator->distance($a, $b)->meters();

        // Demi-circonférence sur sphère de 6 371 km ≈ 20 015 km — ±0,5 %.
        self::assertGreaterThan(19915000, $distance);
        self::assertLessThan(20116000, $distance);
    }

    public function test_distance_is_symmetric(): void
    {
        $paris = new GeoPoint(48.8566, 2.3522);
        $lyon = new GeoPoint(45.764, 4.8357);

        self::assertSame(
            $this->calculator->distance($paris, $lyon)->meters(),
            $this->calculator->distance($lyon, $paris)->meters()
        );
    }

    public function test_engine_identifier(): void
    {
        self::assertSame('haversine', $this->calculator->engine());
    }
}
