<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll\Golden;

use App\Modules\Payroll\Infrastructure\Services\CountryRules\AlgeriaPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\PayrollCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * DZ-DEPTH (issue #1819) — assurance chômage DZ + préavis légaux.
 *
 * Préavis DZ (Loi 90-11, art. 73-4/98 — renvoi aux conventions collectives ;
 * usage dominant retenu : 1 mois < 10 ans, 2 mois ≥ 10 ans) :
 *   indemnité de préavis non effectué = base × noticeDays / workingDays
 *   (30 j calendaires → 30/22ᵉ du salaire mensuel, golden F-08).
 *
 * Valeurs calculées à la main — référence docs/payroll/DZ_COMPLIANCE.md §7.
 */
class GoldenDzEndOfContractFullTest extends TestCase
{
/** @return array<string, list<mixed>> */
    public static function noticeProvider(): array
    {
        return [
            // [ancienneté, jours de préavis, indemnité sur base 200 000 / 22 j]
            'moins de 1 an (0,5)'   => [0.5, 30.0, 272727.27],  // 200 000 × 30/22
            '5 ans (1 mois)'        => [5.0, 30.0, 272727.27],  // 200 000 × 30/22
            '9 ans 11 mois'         => [9.9, 30.0, 272727.27],
            '10 ans (2 mois)'       => [10.0, 60.0, 545454.55], // 200 000 × 60/22
            '12 ans'                => [12.0, 60.0, 545454.55],
        ];
    }

    #[DataProvider('noticeProvider')]
    public function test_notice_period_days_and_pay(float $years, float $expectedDays, float $expectedPay): void
    {
        $rules = new AlgeriaPayrollRules();

        $this->assertSame($expectedDays, $rules->noticePeriodDays($years));

        $settlement = (new PayrollCalculator)->computeFinalSettlement(
            monthlyBase: 200000.0,
            yearsOfService: $years,
            proratedDays: 22.0,
            workingDays: 22.0,
            unpaidLeaveDays: 0.0,
            referenceGross12Months: 2400000.0,
            severanceMonthsPerYear: $rules->severanceMonthsPerYear($years),
            noticeDays: $rules->noticePeriodDays($years),
        );

        $this->assertSame($expectedPay, $settlement['notice_pay']);
    }

    public function test_notice_period_under_5_years(): void
    {
        // Ancienneté 3 ans → préavis 1 mois (30 j) → indemnité 200 000 × 30/22.
        $this->assertSame(30.0, (new AlgeriaPayrollRules)->noticePeriodDays(3.0));

        $settlement = (new PayrollCalculator)->computeFinalSettlement(
            200000.0, 3.0, 22.0, 22.0, 0.0, 2400000.0, 1.0, 30.0
        );

        $this->assertSame(272727.27, $settlement['notice_pay']);
    }

    public function test_notice_period_5_to_10_years(): void
    {
        // Ancienneté 8 ans → préavis 1 mois (30 j) → indemnité 200 000 × 30/22.
        $this->assertSame(30.0, (new AlgeriaPayrollRules)->noticePeriodDays(8.0));

        $settlement = (new PayrollCalculator)->computeFinalSettlement(
            200000.0, 8.0, 22.0, 22.0, 0.0, 2400000.0, 1.0, 30.0
        );

        $this->assertSame(272727.27, $settlement['notice_pay']);
    }

    public function test_notice_period_over_10_years(): void
    {
        // Ancienneté 15 ans → préavis 2 mois (60 j) → indemnité 200 000 × 60/22.
        $this->assertSame(60.0, (new AlgeriaPayrollRules)->noticePeriodDays(15.0));

        $settlement = (new PayrollCalculator)->computeFinalSettlement(
            200000.0, 15.0, 22.0, 22.0, 0.0, 2400000.0, 1.0, 60.0
        );

        $this->assertSame(545454.55, $settlement['notice_pay']);
    }

    public function test_no_unemployment_insurance_contribution_in_dz(): void
    {
        // Issue #1819 — l'allocation chômage DZ (LF 2022 art. 189, décrets
        // 22-70/26-87) est financée par l'État, pas par les entreprises :
        // aucune cotisation AC_DZ_EMP / AC_DZ_PAT dans le référentiel DZ.
        $codes = array_map(
            static fn (array $c): string => $c['code'],
            (new AlgeriaPayrollRules)->socialContributions()
        );

        $this->assertNotContains('AC_DZ_EMP', $codes);
        $this->assertNotContains('AC_DZ_PAT', $codes);
        $this->assertContains('CNAS_EMP', $codes);
        $this->assertContains('CNAS_PAT', $codes);
    }
}
