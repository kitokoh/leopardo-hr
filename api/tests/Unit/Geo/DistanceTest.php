<?php

declare(strict_types=1);

namespace Tests\Unit\Geo;

use App\Shared\Geo\Distance;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * GEO-03 (#8352, BC-33 GEO) — comportement du value object Distance.
 */
class DistanceTest extends TestCase
{
    public function test_distance_in_meters(): void
    {
        $distance = Distance::fromMeters(391900);

        self::assertSame(391900, $distance->meters());
        self::assertSame(391.9, $distance->kilometers());
    }

    public function test_from_float_meters_rounds(): void
    {
        $distance = Distance::fromFloatMeters(1234.56);

        self::assertSame(1235, $distance->meters());
    }

    public function test_zero_distance_is_allowed(): void
    {
        self::assertSame(0, Distance::fromMeters(0)->meters());
    }

    public function test_negative_distance_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Distance::fromMeters(-1);
    }
}
