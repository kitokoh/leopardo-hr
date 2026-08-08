<?php

namespace Tests\Feature\Payroll\Golden;

use App\Modules\Payroll\Infrastructure\Services\CountryRules\AlgeriaPayrollRules;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Programme FOCUS — F-04 : golden tests des BORNES IRG et CNAS (DZ).
 *
 * Chaque valeur est calculée à la main (docs/payroll/DZ_COMPLIANCE.md §1-§2).
 * Les bornes de tranches (20 000/40 000/80 000/160 000/320 000) sont testées
 * exactement (montant dans la tranche ET montant +1 DZD) pour verrouiller la
 * logique progressive `lowerBound - 1` du calculateur.
 *
 * Volontairement SANS base de données : AlgeriaPayrollRules retombe sur les
 * barèmes par défaut quand tax_slabs est vide (F-13).
 */
class GoldenDzIrgBracketsTest extends TestCase
{
    public static function irgProvider(): array
    {
        return [
            'SMIG 20 000'    => [20000.0, 0.0],
            'borne 20 001'   => [20001.0, 0.0],
            'borne 40 000'   => [40000.0, 3100.0],
            'borne 40 001'   => [40001.0, 3100.27],
            'assiette 54 600' => [54600.0, 7042.0],
            'milieu 50 000'  => [50000.0, 5800.0],
            'borne 80 000'   => [80000.0, 13900.0],
            'borne 80 001'   => [80001.0, 13900.3],
            'milieu 100 000' => [100000.0, 19900.0],
            'borne 160 000'  => [160000.0, 37900.0],
            'borne 160 001'  => [160001.0, 37900.33],
            'borne 320 000'  => [320000.0, 90700.0],
            'borne 320 001'  => [320001.0, 90700.35],
            'haut 350 000'   => [350000.0, 101200.0],
        ];
    }

    #[DataProvider('irgProvider')]
    public function test_golden_dz_irg(float $grossTaxable, float $expected): void
    {
        $this->assertSame($expected, (new AlgeriaPayrollRules())->calculateIncomeTax($grossTaxable));
    }

    public static function cnasProvider(): array
    {
        return [
            'zéro'         => [0.0, [0.0, 0.0]],
            'SMIG 20 000'  => [20000.0, [1800.0, 5200.0]],
            'moyen 60 000' => [60000.0, [5400.0, 15600.0]],
            'décimal'      => [123456.78, [11111.11, 32098.76]],
        ];
    }

    #[DataProvider('cnasProvider')]
    public function test_golden_dz_cnas(float $gross, array $expected): void
    {
        $charges = (new AlgeriaPayrollRules())->calculateSocialCharges($gross);

        $this->assertSame($expected[0], $charges['employee']);
        $this->assertSame($expected[1], $charges['employer']);
    }
}
