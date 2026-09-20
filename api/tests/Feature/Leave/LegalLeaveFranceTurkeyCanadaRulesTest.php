<?php

declare(strict_types=1);

namespace Tests\Feature\Leave;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Planning\Infrastructure\Services\CountryRules\CanadaLegalLeaveRule;
use App\Modules\Planning\Infrastructure\Services\CountryRules\LegalLeaveRulesRegistry;
use App\Modules\Planning\Infrastructure\Services\CountryRules\TurkeyLegalLeaveRule;
use App\Modules\Planning\Infrastructure\Services\LegalLeaveEntitlementService;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Issue #7931 — congés légaux France, Turquie, Canada (lot BC-06).
 *
 * Golden tests calculés à la main : FR 2,5 j ouvrables/mois (L3141-3),
 * TR barème d'ancienneté m.53 İş Kanunu (14/20/26 j ouvrables), CA fédéral
 * CLC art. 184.01 (2/3/4 semaines) + indemnité art. 183 (4/6/8 %).
 * Non-régression : les pays SANS barème gardent le calcul historique.
 */
class LegalLeaveFranceTurkeyCanadaRulesTest extends TestCase
{
    private LegalLeaveEntitlementService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new LegalLeaveEntitlementService;
    }

    private function employeeWithContractStart(?string $contractStart): Employee
    {
        $employee = new Employee(['first_name' => 'Test', 'last_name' => 'Employee']);
        $employee->contract_start = $contractStart !== null ? Carbon::parse($contractStart) : null;

        return $employee;
    }

    public function test_registry_resolves_fr_tr_ca(): void
    {
        $expected = [
            'FR' => ['annual' => 30.0, 'monthly' => 2.5],    // C. trav. L3141-3
            'TR' => ['annual' => 14.0, 'monthly' => 1.1667], // İş K. m.53, barème d'entrée
            'CA' => ['annual' => 10.0, 'monthly' => 0.8333], // CLC art. 184.01, 2 semaines
        ];

        foreach ($expected as $countryCode => $values) {
            $rule = LegalLeaveRulesRegistry::resolve($countryCode);

            $this->assertSame($countryCode, $rule->countryCode());
            $this->assertSame($values['annual'], $rule->legalAnnualDays(), "Droit annuel inattendu pour {$countryCode}");
            $this->assertSame($values['monthly'], $rule->accrualDaysPerMonth(), "Acquisition mensuelle inattendue pour {$countryCode}");
            $this->assertNotSame('', $rule->legalSource(), "Source légale manquante pour {$countryCode}");
            $this->assertSame('pilot', $rule->confidenceLevel());
        }
    }

    public function test_france_ten_months_yields_25_days(): void
    {
        // FR : 2,5 j ouvrables/mois × 10 mois (embauche 15/03/2026) = 25 j.
        $employee = $this->employeeWithContractStart('2026-03-15');

        $this->assertSame(25.0, $this->service->projectedEntitlement($employee, 2026, 'FR'));
    }

    public function test_france_full_year_capped_at_30_days(): void
    {
        // FR : 2,5 × 12 = 30 j ouvrables (plafond légal L3141-3).
        $employee = $this->employeeWithContractStart('2020-01-10');

        $this->assertSame(30.0, $this->service->projectedEntitlement($employee, 2026, 'FR'));
    }

    public function test_turkey_seniority_scale_matches_is_kanunu_m53(): void
    {
        $rule = new TurkeyLegalLeaveRule;

        $this->assertSame(0.0, $rule->legalAnnualDaysForSeniority(0.5));   // < 1 an : aucun droit
        $this->assertSame(14.0, $rule->legalAnnualDaysForSeniority(1.0));  // 1 an
        $this->assertSame(14.0, $rule->legalAnnualDaysForSeniority(5.0));  // 5 ans INCLUS
        $this->assertSame(20.0, $rule->legalAnnualDaysForSeniority(5.5));  // > 5 ans
        $this->assertSame(20.0, $rule->legalAnnualDaysForSeniority(14.9)); // < 15 ans
        $this->assertSame(26.0, $rule->legalAnnualDaysForSeniority(15.0)); // 15 ans et +
        $this->assertNull($rule->vacationPayRatePercent(3.0)); // pas de taux légal en %
    }

    public function test_turkey_three_years_seniority_full_year_yields_14_days(): void
    {
        // TR : embauche 10/01/2023 → ancienneté au 01/01/2026 ≈ 2,98 ans → 14 j
        // (barème 1-5 ans) ; 12 mois × 1,1667 = 14,0004 → plafonné à 14 j.
        $employee = $this->employeeWithContractStart('2023-01-10');

        $this->assertSame(14.0, $this->service->projectedEntitlement($employee, 2026, 'TR'));
    }

    public function test_turkey_seven_years_seniority_full_year_yields_20_days(): void
    {
        // TR : embauche 01/06/2019 → ancienneté au 01/01/2026 ≈ 6,59 ans → 20 j.
        $employee = $this->employeeWithContractStart('2019-06-01');

        $this->assertSame(20.0, $this->service->projectedEntitlement($employee, 2026, 'TR'));
    }

    public function test_turkey_sixteen_years_seniority_full_year_yields_26_days(): void
    {
        // TR : embauche 01/02/2009 → ancienneté au 01/01/2026 ≈ 16,92 ans → 26 j.
        $employee = $this->employeeWithContractStart('2009-02-01');

        $this->assertSame(26.0, $this->service->projectedEntitlement($employee, 2026, 'TR'));
    }

    public function test_turkey_first_year_yields_zero_days(): void
    {
        // TR m.53 : aucun droit légal avant un an d'ancienneté — embauche
        // 01/03/2026, année 2026 → ancienneté 0 au 1er janvier → 0 j.
        $employee = $this->employeeWithContractStart('2026-03-01');

        $this->assertSame(0.0, $this->service->projectedEntitlement($employee, 2026, 'TR'));
    }

    public function test_canada_seniority_scale_matches_clc(): void
    {
        $rule = new CanadaLegalLeaveRule;

        $this->assertSame(10.0, $rule->legalAnnualDaysForSeniority(0.5));  // 2 semaines
        $this->assertSame(10.0, $rule->legalAnnualDaysForSeniority(4.9));  // < 5 ans
        $this->assertSame(15.0, $rule->legalAnnualDaysForSeniority(5.0));  // 3 semaines
        $this->assertSame(15.0, $rule->legalAnnualDaysForSeniority(9.9));  // < 10 ans
        $this->assertSame(20.0, $rule->legalAnnualDaysForSeniority(10.0)); // 4 semaines
    }

    public function test_canada_vacation_pay_rates_are_4_6_8_percent(): void
    {
        // CLC art. 183 : indemnité de congé annuel 4 % / 6 % / 8 %.
        $rule = new CanadaLegalLeaveRule;

        $this->assertSame(4.0, $rule->vacationPayRatePercent(2.0));
        $this->assertSame(6.0, $rule->vacationPayRatePercent(5.0));
        $this->assertSame(6.0, $rule->vacationPayRatePercent(9.9));
        $this->assertSame(8.0, $rule->vacationPayRatePercent(10.0));
        $this->assertSame(8.0, $rule->vacationPayRatePercent(25.0));
    }

    public function test_canada_two_years_seniority_full_year_yields_10_days(): void
    {
        // CA : embauche 10/01/2024 → < 5 ans → 2 semaines = 10 jours ouvrables.
        $employee = $this->employeeWithContractStart('2024-01-10');

        $this->assertSame(10.0, $this->service->projectedEntitlement($employee, 2026, 'CA'));
    }

    public function test_canada_six_years_seniority_full_year_yields_15_days(): void
    {
        // CA : embauche 01/06/2019 → ancienneté ≈ 6,59 ans → 3 semaines = 15 j.
        $employee = $this->employeeWithContractStart('2019-06-01');

        $this->assertSame(15.0, $this->service->projectedEntitlement($employee, 2026, 'CA'));
    }

    public function test_canada_twelve_years_seniority_full_year_yields_20_days(): void
    {
        // CA : embauche 01/01/2014 → ancienneté 12 ans → 4 semaines = 20 j.
        $employee = $this->employeeWithContractStart('2014-01-01');

        $this->assertSame(20.0, $this->service->projectedEntitlement($employee, 2026, 'CA'));
    }

    public function test_seniority_years_at_year_start_is_zero_without_contract_start(): void
    {
        $employee = $this->employeeWithContractStart(null);

        $this->assertSame(0.0, $this->service->seniorityYearsAtYearStart($employee, 2026));
    }

    public function test_countries_without_scale_keep_historic_computation(): void
    {
        // Non-régression : DZ (aucun barème) — 30 ans d'ancienneté ne change
        // rien, 12 mois × 2,5 = 30 j comme avant #7931.
        $employee = $this->employeeWithContractStart('1996-01-10');

        $this->assertSame(30.0, $this->service->projectedEntitlement($employee, 2026, 'DZ'));
        $this->assertSame(30.0, LegalLeaveRulesRegistry::resolve('DZ')->legalAnnualDaysForSeniority(30.0));
        $this->assertNull(LegalLeaveRulesRegistry::resolve('DZ')->vacationPayRatePercent(30.0));
    }
}
