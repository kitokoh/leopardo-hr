<?php

declare(strict_types=1);

namespace Tests\Unit\Geo;

use App\Shared\Geo\Exceptions\InvalidGeoPointException;
use App\Shared\Geo\GeoPoint;
use PHPUnit\Framework\TestCase;

/**
 * GEO-03 (#8352, BC-33 GEO) — validation des bornes du value object GeoPoint.
 */
class GeoPointTest extends TestCase
{
    public function test_valid_point_is_constructed(): void
    {
        $point = new GeoPoint(48.8566, 2.3522);

        self::assertSame(48.8566, $point->latitude);
        self::assertSame(2.3522, $point->longitude);
        self::assertSame(['latitude' => 48.8566, 'longitude' => 2.3522], $point->toArray());
    }

    public function test_boundary_values_are_accepted(): void
    {
        $north = new GeoPoint(90.0, 180.0);
        $south = new GeoPoint(-90.0, -180.0);

        self::assertSame(90.0, $north->latitude);
        self::assertSame(-180.0, $south->longitude);
    }

    public function test_latitude_above_90_is_rejected(): void
    {
        $this->expectException(InvalidGeoPointException::class);

        new GeoPoint(90.0001, 0.0);
    }

    public function test_latitude_below_minus_90_is_rejected(): void
    {
        $this->expectException(InvalidGeoPointException::class);

        new GeoPoint(-90.5, 0.0);
    }

    public function test_longitude_above_180_is_rejected(): void
    {
        $this->expectException(InvalidGeoPointException::class);

        new GeoPoint(0.0, 180.0001);
    }

    public function test_longitude_below_minus_180_is_rejected(): void
    {
        $this->expectException(InvalidGeoPointException::class);

        new GeoPoint(0.0, -181.0);
    }

    public function test_from_array_accepts_lat_lng_aliases(): void
    {
        $point = GeoPoint::fromArray(['lat' => '45.7640', 'lng' => '4.8357']);

        self::assertSame(45.764, $point->latitude);
        self::assertSame(4.8357, $point->longitude);
    }

    public function test_from_array_accepts_full_key_names(): void
    {
        $point = GeoPoint::fromArray(['latitude' => 45.764, 'longitude' => 4.8357]);

        self::assertSame(45.764, $point->latitude);
        self::assertSame(4.8357, $point->longitude);
    }

    public function test_from_array_rejects_missing_coordinates(): void
    {
        $this->expectException(InvalidGeoPointException::class);

        GeoPoint::fromArray(['lat' => 45.764]);
    }

    public function test_from_nullable_returns_null_when_incomplete(): void
    {
        self::assertNull(GeoPoint::fromNullable(null, 2.35));
        self::assertNull(GeoPoint::fromNullable(48.85, null));
        self::assertNull(GeoPoint::fromNullable(null, null));

        $point = GeoPoint::fromNullable(48.85, 2.35);
        self::assertNotNull($point);
        self::assertSame(48.85, $point->latitude);
    }
}
