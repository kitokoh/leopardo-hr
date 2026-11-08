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
 *   — #1943 : unité en jours OUVRÉS (22/44), l'indemnité = rémunération de
 *   la période de préavis (art. 98) → 22/22 = 1 mois exact (plus de surpaie
 *   30/22 = 1,36×).
 *
 * Valeurs calculées à la main — référence docs/payroll/DZ_COMPLIANCE.md §7.
 */
class GoldenDzEndOfContractFullTest extends TestCase
{
    /**
     * @return array<string, array{float, float, float}>
     */
    public static function noticeProvider(): array
    {
        return [
            // [ancienneté, jours de préavis (OUVRÉS #1943), indemnité sur base 200 000 / 22 j]
            'moins de 1 an (0,5)' => [0.5, 22.0, 200000.0],  // 200 000 × 22/22 = 1 mois exact
            '5 ans (1 mois)' => [5.0, 22.0, 200000.0],  // 200 000 × 22/22
            '9 ans 11 mois' => [9.9, 22.0, 200000.0],
            '10 ans (2 mois)' => [10.0, 44.0, 400000.0], // 200 000 × 44/22 = 2 mois exacts
            '12 ans' => [12.0, 44.0, 400000.0],
        ];
    }

    #[DataProvider('noticeProvider')]
    public function test_notice_period_days_and_pay(float $years, float $expectedDays, float $expectedPay): void
    {
        $rules = new AlgeriaPayrollRules;

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
        // Ancienneté 3 ans → préavis 1 mois (22 j ouvrés #1943) → 200 000 × 22/22.
        $this->assertSame(22.0, (new AlgeriaPayrollRules)->noticePeriodDays(3.0));

        $settlement = (new PayrollCalculator)->computeFinalSettlement(
            200000.0, 3.0, 22.0, 22.0, 0.0, 2400000.0, 1.0, 22.0
        );

        $this->assertSame(200000.0, $settlement['notice_pay']);
    }

    public function test_notice_period_5_to_10_years(): void
    {
        // Ancienneté 8 ans → préavis 1 mois (22 j ouvrés #1943) → 200 000 × 22/22.
        $this->assertSame(22.0, (new AlgeriaPayrollRules)->noticePeriodDays(8.0));

        $settlement = (new PayrollCalculator)->computeFinalSettlement(
            200000.0, 8.0, 22.0, 22.0, 0.0, 2400000.0, 1.0, 22.0
        );

        $this->assertSame(200000.0, $settlement['notice_pay']);
    }

    public function test_notice_period_over_10_years(): void
    {
        // Ancienneté 15 ans → préavis 2 mois (44 j ouvrés #1943) → 200 000 × 44/22.
        $this->assertSame(44.0, (new AlgeriaPayrollRules)->noticePeriodDays(15.0));

        $settlement = (new PayrollCalculator)->computeFinalSettlement(
            200000.0, 15.0, 22.0, 22.0, 0.0, 2400000.0, 1.0, 44.0
        );

        $this->assertSame(400000.0, $settlement['notice_pay']);
    }

    public function test_unemployment_insurance_included_in_cnas_aggregates(): void
    {
        // Issue #1819/#1943 — le régime contributif CNAC (décret législatif
        // 94-11, art. 94-188) couvre les salariés du privé licenciés pour motif
        // économique, financé par 1 % patron + 0,5 % salarié : DÉJÀ inclus dans
        // les agrégats CNAS (9 % / 26 %). Aucune ligne AC_DZ_EMP/AC_DZ_PAT
        // séparée dans le référentiel DZ (double cotisation sinon).
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
