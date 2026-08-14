<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll\Golden;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\AlgeriaPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\EndOfContractService;
use App\Modules\Payroll\Infrastructure\Services\PayrollCalculator;
use Carbon\Carbon;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * FOCUS 2 — F-31 : les règles pays de fin de contrat (préavis + indemnité de
 * licenciement) sont consommées par EndOfContractService au lieu de valeurs
 * codées en dur (avant : severanceMonthsPerYear=1.0, noticeDays=0.0 inline).
 *
 * DZ-DEPTH (#1819) : préavis DZ implémenté avec les durées légales
 * conventionnelles — 1 mois (30 j) pour une ancienneté < 10 ans, 2 mois
 * (60 j) à partir de 10 ans (loi 90-11 art. 73-5, ordo 96-21 + conventions
 * collectives ; DZ_COMPLIANCE.md §7ter). Valeurs pilot à confirmer par
 * expert comptable DZ (confidenceLevel 'pilot').
 */
class GoldenDzEndOfContractRulesTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_dz_rules_expose_legal_notice_and_severance_values(): void
    {
        $rules = new AlgeriaPayrollRules;

        // DZ-DEPTH (#1819) : préavis 30 j < 10 ans, 60 j ≥ 10 ans ;
        // licenciement 1 mois/an (inchangé).
        $this->assertSame(30.0, $rules->noticePeriodDays(0.5));
        $this->assertSame(30.0, $rules->noticePeriodDays(7.0));
        $this->assertSame(60.0, $rules->noticePeriodDays(10.0));
        $this->assertSame(60.0, $rules->noticePeriodDays(15.0));
        $this->assertSame(1.0, $rules->severanceMonthsPerYear(5.0));
        $this->assertSame(1.0, $rules->severanceMonthsPerYear(12.0));
    }

    /**
     * DZ-DEPTH (#1819) — indemnité compensatrice de préavis, ancienneté
     * < 1 an : préavis 30 j → base 60 000 × 30/22 = 81 818,18 DZD.
     */
    public function test_golden_dz_notice_pay_seniority_under_one_year(): void
    {
        // Calcul manuel (DZ_COMPLIANCE.md §7ter) :
        //   ancienneté 0,5 an < 10 ans → noticePeriodDays = 30 j
        //   indemnité = 60 000 × 30 / 22 = 81 818,18 DZD
        $rules = new AlgeriaPayrollRules;
        $settlement = (new PayrollCalculator)->computeFinalSettlement(
            monthlyBase: 60000.0,
            yearsOfService: 0.5,
            proratedDays: 22.0,
            workingDays: 22.0,
            unpaidLeaveDays: 0.0,
            referenceGross12Months: 720000.0,
            severanceMonthsPerYear: $rules->severanceMonthsPerYear(0.5),
            noticeDays: $rules->noticePeriodDays(0.5),
        );

        $this->assertSame(30.0, $rules->noticePeriodDays(0.5));
        $this->assertSame(81818.18, $settlement['notice_pay']);
    }

    /**
     * DZ-DEPTH (#1819) — ancienneté 5-10 ans : préavis toujours 30 j →
     * 81 818,18 DZD (borne haute de la tranche < 10 ans).
     */
    public function test_golden_dz_notice_pay_seniority_five_to_ten_years(): void
    {
        // Calcul manuel (DZ_COMPLIANCE.md §7ter) :
        //   ancienneté 7 ans < 10 ans → noticePeriodDays = 30 j
        //   indemnité = 60 000 × 30 / 22 = 81 818,18 DZD
        $rules = new AlgeriaPayrollRules;
        $settlement = (new PayrollCalculator)->computeFinalSettlement(
            monthlyBase: 60000.0,
            yearsOfService: 7.0,
            proratedDays: 22.0,
            workingDays: 22.0,
            unpaidLeaveDays: 0.0,
            referenceGross12Months: 720000.0,
            severanceMonthsPerYear: $rules->severanceMonthsPerYear(7.0),
            noticeDays: $rules->noticePeriodDays(7.0),
        );

        $this->assertSame(30.0, $rules->noticePeriodDays(7.0));
        $this->assertSame(81818.18, $settlement['notice_pay']);
    }

    /**
     * DZ-DEPTH (#1819) — ancienneté > 10 ans : préavis 60 j →
     * 60 000 × 60/22 = 163 636,36 DZD.
     */
    public function test_golden_dz_notice_pay_seniority_over_ten_years(): void
    {
        // Calcul manuel (DZ_COMPLIANCE.md §7ter) :
        //   ancienneté 12 ans ≥ 10 ans → noticePeriodDays = 60 j
        //   indemnité = 60 000 × 60 / 22 = 163 636,36 DZD
        $rules = new AlgeriaPayrollRules;
        $settlement = (new PayrollCalculator)->computeFinalSettlement(
            monthlyBase: 60000.0,
            yearsOfService: 12.0,
            proratedDays: 22.0,
            workingDays: 22.0,
            unpaidLeaveDays: 0.0,
            referenceGross12Months: 720000.0,
            severanceMonthsPerYear: $rules->severanceMonthsPerYear(12.0),
            noticeDays: $rules->noticePeriodDays(12.0),
        );

        $this->assertSame(60.0, $rules->noticePeriodDays(12.0));
        $this->assertSame(163636.36, $settlement['notice_pay']);
    }

    public function test_settlement_resolves_rules_from_company_country(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ']);
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'salary_base' => 60000.0,
            'contract_start' => '2021-01-01',
            'contract_end' => '2026-01-01', // 60 mois = 5 ans exacts
        ]);

        $service = new EndOfContractService;
        $settlement = $service->settlement($employee, Carbon::parse('2026-01-01'));

        // Golden F-08 (calculé à la main) : 60 000 × 5 ans × 1,0 mois/an = 300 000 DZD.
        $this->assertSame(300000.0, $settlement['breakdown']['severance']);
        // Préavis DZ (#1819) : 5 ans < 10 ans → 30 j → 60 000 × 30/22 = 81 818,18 DZD.
        $this->assertSame(81818.18, $settlement['breakdown']['notice_pay']);
        // Cohérence du solde : prorata + congés + préavis + licenciement.
        $expectedTotal = round(
            $settlement['breakdown']['prorated_pay']
            + $settlement['breakdown']['leave_indemnity']
            + $settlement['breakdown']['notice_pay']
            + $settlement['breakdown']['severance'],
            2
        );
        $this->assertSame($expectedTotal, $settlement['breakdown']['total']);
    }

    public function test_unregistered_country_falls_back_to_dz_defaults_without_exception(): void
    {
        // Pays inconnu du moteur (ex. 'US') : repli silencieux sur les défauts DZ
        // (1 mois/an de licenciement, préavis 1 mois < 10 ans — #1819) — aucune
        // exception en fin de contrat (régression évitée : avant F-31 le service
        // utilisait toujours ces défauts).
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'US']);
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'salary_base' => 60000.0,
            'contract_start' => '2021-01-01',
            'contract_end' => '2026-01-01',
        ]);

        $service = new EndOfContractService;
        $settlement = $service->settlement($employee, Carbon::parse('2026-01-01'));

        $this->assertSame(300000.0, $settlement['breakdown']['severance']);
        // 5 ans < 10 ans → préavis DZ 30 j → 60 000 × 30/22 = 81 818,18 DZD.
        $this->assertSame(81818.18, $settlement['breakdown']['notice_pay']);
    }
}
