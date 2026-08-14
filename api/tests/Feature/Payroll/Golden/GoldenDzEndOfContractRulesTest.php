<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll\Golden;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Domain\Exceptions\UnsupportedCountryRulesException;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\AlgeriaPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\EndOfContractService;
use Carbon\Carbon;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * FOCUS 2 — F-31 : les règles pays de fin de contrat (préavis + indemnité de
 * licenciement) sont consommées par EndOfContractService au lieu de valeurs
 * codées en dur (avant : severanceMonthsPerYear=1.0, noticeDays=0.0 inline).
 *
 * Valeurs pilot DZ documentées dans docs/payroll/DZ_COMPLIANCE.md §7 — aucun
 * changement de calcul par rapport au comportement historique (F-08) : la
 * mécanique devient paramétrable par pays, les valeurs DZ restent 1 mois/an
 * et 0 jour de préavis, à valider par expert comptable DZ (confidenceLevel
 * 'pilot').
 */
class GoldenDzEndOfContractRulesTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_dz_rules_expose_pilot_notice_and_severance_values(): void
    {
        $rules = new AlgeriaPayrollRules;

        // Valeurs pilot DZ (F-31) : préavis 0 j, licenciement 1 mois/an.
        $this->assertSame(0.0, $rules->noticePeriodDays(5.0));
        $this->assertSame(1.0, $rules->severanceMonthsPerYear(5.0));
        $this->assertSame(1.0, $rules->severanceMonthsPerYear(12.0));
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
        // Préavis pilot DZ : 0 jour → aucune indemnité compensatrice par défaut.
        $this->assertSame(0.0, $settlement['breakdown']['notice_pay']);
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

    public function test_unregistered_country_throws_typed_error(): void
    {
        // MULTI-PAYS (#1868) : un pays non enregistré (ex. 'US') lève une
        // erreur métier typée — PLUS AUCUN repli silencieux vers les défauts
        // DZ en fin de contrat (l'ancien comportement masquait les pays mal
        // configurés et appliquait la juridiction DZ à d'autres pays).
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

        $this->expectException(UnsupportedCountryRulesException::class);
        $this->expectExceptionMessage('US');

        $service->settlement($employee, Carbon::parse('2026-01-01'));
    }
}
