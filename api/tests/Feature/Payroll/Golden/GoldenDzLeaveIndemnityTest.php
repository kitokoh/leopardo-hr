<?php

namespace Tests\Feature\Payroll\Golden;

use App\Modules\Payroll\Infrastructure\Services\PayrollCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Programme FOCUS — F-07 : golden tests de l'indemnité de congés payés.
 *
 * Règle (docs/payroll/DZ_COMPLIANCE.md §4) : la plus favorable entre
 *  - maintien de salaire : base × jours de congé / jours ouvrés,
 *  - 1/10ᵉ : (salaires bruts 12 mois / 10) × jours pris / congés acquis.
 *
 * Valeurs calculées à la main — jamais reprises du code.
 */
class GoldenDzLeaveIndemnityTest extends TestCase
{
    public static function indemnityProvider(): array
    {
        return [
            // [base, leaveDays, workingDays, refGross12, accrued, expected]
            '5 j, maintien gagne'   => [60000.0, 5.0, 22.0, 720000.0, 30.0, 13636.36], // 60k×5/22 > 72k×5/30
            '10 j, 1/10e gagne'     => [60000.0, 10.0, 22.0, 900000.0, 30.0, 30000.0], // 90k×10/30 > 60k×10/22
            'aucun congé'           => [60000.0, 0.0, 22.0, 720000.0, 30.0, 0.0],
            'mois complet (22 j)'   => [60000.0, 22.0, 22.0, 720000.0, 30.0, 60000.0], // maintien = 60k > 52,8k
            'tout l-acquis (30 j)'  => [60000.0, 30.0, 22.0, 720000.0, 30.0, 81818.18], // 60k×30/22 > 72k
        ];
    }

    #[DataProvider('indemnityProvider')]
    public function test_golden_dz_leave_indemnity(
        float $base,
        float $leaveDays,
        float $workingDays,
        float $refGross,
        float $accrued,
        float $expected
    ): void {
        $this->assertSame(
            $expected,
            (new PayrollCalculator())->computeLeaveIndemnity($base, $leaveDays, $workingDays, $refGross, $accrued)
        );
    }
}
