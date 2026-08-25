<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Modules\Payroll\Infrastructure\Services\CountryRules\AlgeriaPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\CedeaoPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\CemacPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\SenegalPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\PayrollCalculator;
use Tests\TestCase;

/**
 * Issue #2219 [PAYROLL][P1] — préavis fin de contrat : jours calendaires vs
 * OUVRÉS.
 *
 * Le moteur (`computeFinalSettlement()`) calcule l'indemnité de préavis
 * comme `base × (noticeDays / workingDays)` avec workingDays ≈ 22 jours
 * ouvrés. Si `noticePeriodDays()` renvoie des jours calendaires (30/60/90),
 * l'indemnité est surpayée de 30/22 = 1,36×. Règle : tous les pays pilot
 * renvoient des jours OUVRÉS (alignement DZ #1943).
 *
 * Régression : préavis 1 mois = 1 × salaire mensuel de base pour
 * CM/CI/BF/ML/GA/CG/SN.
 */
class NoticePeriodWorkingDaysTest extends TestCase
{
    /**
     * @return array<string, array{0: string}>
     */
    public static function oneMonthNoticeCountriesProvider(): array
    {
        return [
            'CI (CEDEAO)' => ['CI'],
            'BF (CEDEAO)' => ['BF'],
            'ML (CEDEAO)' => ['ML'],
            'CM (CEMAC)' => ['CM'],
            'GA (CEMAC)' => ['GA'],
            'CG (CEMAC)' => ['CG'],
        ];
    }

    /**
     * @dataProvider oneMonthNoticeCountriesProvider
     */
    public function test_one_month_notice_pays_exactly_one_month_base(string $countryCode): void
    {
        $rules = match ($countryCode) {
            'CM', 'GA', 'CG' => (new CemacPayrollRules)->forMemberCountry($countryCode),
            default => (new CedeaoPayrollRules)->forMemberCountry($countryCode),
        };

        $calculator = new PayrollCalculator;
        $settlement = $calculator->computeFinalSettlement(
            monthlyBase: 200000.0,
            yearsOfService: 3.0,
            proratedDays: 0.0,
            workingDays: 22.0,
            unpaidLeaveDays: 0.0,
            referenceGross12Months: 200000.0,
            severanceMonthsPerYear: 1.0,
            noticeDays: $rules->noticePeriodDays(3.0),
        );

        // Préavis 1 mois = 22 j ouvrés → indemnité = 200 000 × 22/22 = 200 000.
        $this->assertSame(22.0, $rules->noticePeriodDays(3.0), "{$countryCode} : 1 mois = 22 j ouvrés");
        $this->assertSame(200000.0, $settlement['notice_pay'], "{$countryCode} : 1 mois de préavis = 1 × base");
    }

    public function test_senegal_one_month_notice_pays_one_month_base(): void
    {
        $rules = new SenegalPayrollRules;
        $calculator = new PayrollCalculator;

        $settlement = $calculator->computeFinalSettlement(
            monthlyBase: 200000.0,
            yearsOfService: 3.0,
            proratedDays: 0.0,
            workingDays: 22.0,
            unpaidLeaveDays: 0.0,
            referenceGross12Months: 200000.0,
            severanceMonthsPerYear: 1.0,
            noticeDays: $rules->noticePeriodDays(3.0), // employé → 22 j ouvrés
        );

        $this->assertSame(22.0, $rules->noticePeriodDays(3.0));
        $this->assertSame(200000.0, $settlement['notice_pay']);
    }

    public function test_senegal_three_month_cadre_notice_is_three_months_base(): void
    {
        $rules = new SenegalPayrollRules;
        $calculator = new PayrollCalculator;

        // Cadre : 3 mois = 66 j ouvrés → 200 000 × 66/22 = 600 000.
        $settlement = $calculator->computeFinalSettlement(
            monthlyBase: 200000.0,
            yearsOfService: 5.0,
            proratedDays: 0.0,
            workingDays: 22.0,
            unpaidLeaveDays: 0.0,
            referenceGross12Months: 200000.0,
            severanceMonthsPerYear: 1.0,
            noticeDays: $rules->noticePeriodDays(5.0, 'cadre'),
        );

        $this->assertSame(66.0, $rules->noticePeriodDays(5.0, 'cadre'));
        $this->assertSame(600000.0, $settlement['notice_pay']);
    }

    public function test_dz_unchanged_reference(): void
    {
        // Référence #1943 : DZ renvoie déjà des jours ouvrés (22/44) — aucun
        // changement attendu sur les golden DZ.
        $rules = new AlgeriaPayrollRules;

        $this->assertSame(22.0, $rules->noticePeriodDays(3.0));
        $this->assertSame(44.0, $rules->noticePeriodDays(12.0));
    }
}
