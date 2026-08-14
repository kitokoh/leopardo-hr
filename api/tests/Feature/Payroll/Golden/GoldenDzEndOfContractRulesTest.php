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
 * Valeurs pilot DZ documentées dans docs/payroll/DZ_COMPLIANCE.md §7 : la
 * mécanique devient paramétrable par pays, les valeurs DZ restent 1 mois/an
 * et 22/44 jours ouvrés de préavis (#1943 — plus de surpaie 30/22), à valider
 * par expert comptable DZ (confidenceLevel 'pilot').
 */
class GoldenDzEndOfContractRulesTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_dz_rules_expose_pilot_notice_and_severance_values(): void
    {
        $rules = new AlgeriaPayrollRules();

        // Issue #1819/#1943 — préavis DZ (usage dominant, Loi 90-11 art.
        // 73-4/98) : 1 mois < 10 ans, 2 mois ≥ 10 ans, en JOURS OUVRÉS
        // (22/44 — 30/60 calendaires sur-payaient de 1,36×).
        $this->assertSame(22.0, $rules->noticePeriodDays(5.0));
        $this->assertSame(22.0, $rules->noticePeriodDays(0.5));
        $this->assertSame(44.0, $rules->noticePeriodDays(12.0));
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
            'contract_type' => 'CDI', // préavis dû (licenciement CDI)
        ]);

        $service = new EndOfContractService();
        $settlement = $service->settlement($employee, Carbon::parse('2026-01-01'));

        // Golden F-08 (calculé à la main) : 60 000 × 5 ans × 1,0 mois/an = 300 000 DZD.
        $this->assertSame(300000.0, $settlement['breakdown']['severance']);
        // Issue #1943 — préavis 1 mois en jours ouvrés : 60 000 × 22/22 = 60 000 DZD.
        $this->assertSame(60000.0, $settlement['breakdown']['notice_pay']);
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

    public function test_unregistered_country_throws_typed_error_instead_of_dz_fallback(): void
    {
        // Pays inconnu du moteur (ex. 'US') : PLUS AUCUN repli silencieux sur
        // les défauts DZ (MULTI-PAYS #1868) — une règle indisponible produit
        // une erreur métier typée et explicable (critère #1869 : « les erreurs
        // expliquent quelle règle ou quel contexte manque »).
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'US']);
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'salary_base' => 60000.0,
            'contract_start' => '2021-01-01',
            'contract_end' => '2026-01-01',
        ]);

        $service = new EndOfContractService();

        $this->expectException(UnsupportedCountryRulesException::class);
        $service->settlement($employee, Carbon::parse('2026-01-01'));
    }

    public function test_no_notice_pay_for_fixed_term_end_or_resignation(): void
    {
        // Issue #1943 — l'indemnité de préavis n'est due que si l'employeur
        // licencie (layoff) et dispense du préavis : CDD à terme naturel,
        // démission et faute lourde ne génèrent AUCUN préavis.
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ']);
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'salary_base' => 60000.0,
            'contract_start' => '2024-01-01',
            'contract_end' => '2026-01-01',
            'contract_type' => 'CDD',
        ]);

        $service = new EndOfContractService();

        $endOfTerm = $service->settlement($employee, Carbon::parse('2026-01-01'), 'end_of_term');
        $this->assertSame(0.0, $endOfTerm['breakdown']['notice_pay']);

        $resignation = $service->settlement($employee, Carbon::parse('2026-01-01'), 'resignation');
        $this->assertSame(0.0, $resignation['breakdown']['notice_pay']);

        $misconduct = $service->settlement($employee, Carbon::parse('2026-01-01'), 'misconduct');
        $this->assertSame(0.0, $misconduct['breakdown']['notice_pay']);

        // Licenciement (layoff) : préavis dû — 60 000 × 22/22 = 60 000.
        $layoff = $service->settlement($employee, Carbon::parse('2026-01-01'), 'layoff');
        $this->assertSame(60000.0, $layoff['breakdown']['notice_pay']);
    }
}
