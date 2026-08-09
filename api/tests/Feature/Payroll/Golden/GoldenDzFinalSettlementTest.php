<?php

namespace Tests\Feature\Payroll\Golden;

use App\Modules\Payroll\Infrastructure\Services\PayrollCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Programme FOCUS — F-08 : golden tests du solde de tout compte.
 *
 * Règles : prorata (F-05) + indemnité congés non pris (F-07) + préavis +
 * indemnité de licenciement (1 mois/an par défaut — à confirmer).
 * Valeurs calculées à la main.
 */
class GoldenDzFinalSettlementTest extends TestCase
{
    public static function settlementProvider(): array
    {
        return [
            // [base, years, proratedDays, working, leaveDays, ref12, notice, expected total]
            'départ mi-mois, 5 ans, 15 j congés' => [60000.0, 5.0, 10.0, 22.0, 15.0, 720000.0, 0.0, 368181.82],
            'aucune ancienneté, mois complet'    => [60000.0, 0.0, 22.0, 22.0, 0.0, 720000.0, 0.0, 60000.0],
            'avec préavis non effectué'          => [60000.0, 2.0, 22.0, 22.0, 0.0, 720000.0, 30.0, 261818.18],
        ];
    }

    #[DataProvider('settlementProvider')]
    public function test_golden_dz_final_settlement(
        float $base,
        float $years,
        float $proratedDays,
        float $working,
        float $leaveDays,
        float $ref12,
        float $notice,
        float $expectedTotal
    ): void {
        $calc = new PayrollCalculator();
        $result = $calc->computeFinalSettlement($base, $years, $proratedDays, $working, $leaveDays, $ref12, 1.0, $notice);

        $this->assertSame($expectedTotal, $result['total']);
    }

    public function test_golden_dz_settlement_components(): void
    {
        // 60 000, 5 ans, 10 j travaillés, 15 j congés, réf. 720 000 :
        // prorata 27 272,73 · congés max(40 909,09 ; 36 000) = 40 909,09
        // licenciement 60 000×5 = 300 000 · total 368 181,82
        $result = (new PayrollCalculator())->computeFinalSettlement(60000.0, 5.0, 10.0, 22.0, 15.0, 720000.0);

        $this->assertSame(27272.73, $result['prorated_pay']);
        $this->assertSame(40909.09, $result['leave_indemnity']);
        $this->assertSame(0.0, $result['notice_pay']);
        $this->assertSame(300000.0, $result['severance']);
        $this->assertSame(368181.82, $result['total']);
    }
}
