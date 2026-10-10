<?php

declare(strict_types=1);

namespace Tests\Unit\Vtc;

use App\Modules\Vtc\Domain\Models\VtcFareProfile;
use App\Modules\Vtc\Domain\Support\VtcFareCalculator;
use PHPUnit\Framework\TestCase;

/**
 * VTC-03 (#8359, BC-34 VTC) — calcul de tarif : formule de la spec §5.4
 * (max(minimum, base + km×per_km + min×per_minute), minor units, arrondi
 * half-up) et estimation de durée à vitesse moyenne.
 */
class VtcFareCalculatorTest extends TestCase
{
    public function test_price_formula(): void
    {
        $profile = new VtcFareProfile([
            'base_minor' => 500_00,
            'per_km_minor' => 250_00,
            'per_minute_minor' => 50_00,
            'minimum_minor' => 1_000_00,
        ]);

        $calculator = new VtcFareCalculator;

        // 10 km, 20 min : 500 + 250×10 + 50×20 = 4 000 (minor).
        self::assertSame(4_000_00, $calculator->priceMinor($profile, 10_000, 1_200));
    }

    public function test_price_floors_at_minimum(): void
    {
        $profile = new VtcFareProfile([
            'base_minor' => 100_00,
            'per_km_minor' => 10_00,
            'per_minute_minor' => 10_00,
            'minimum_minor' => 1_000_00,
        ]);

        $calculator = new VtcFareCalculator;

        // Trajet minimal : le calcul (~110) est sous le minimum → 1 000.
        self::assertSame(1_000_00, $calculator->priceMinor($profile, 500, 60));
    }

    public function test_price_rounds_half_up(): void
    {
        $profile = new VtcFareProfile([
            'base_minor' => 0,
            'per_km_minor' => 333,
            'per_minute_minor' => 0,
            'minimum_minor' => 0,
        ]);

        $calculator = new VtcFareCalculator;

        // 333 × 1.5 km = 499.5 → 500 (arrondi half-up).
        self::assertSame(500, $calculator->priceMinor($profile, 1_500, 0));
    }

    public function test_duration_from_average_speed(): void
    {
        $calculator = new VtcFareCalculator;

        // 11 km à 22 km/h = 0.5 h = 1 800 s.
        self::assertSame(1_800, $calculator->durationSeconds(11_000, 22.0));
        self::assertSame(0, $calculator->durationSeconds(11_000, 0.0));
    }
}
