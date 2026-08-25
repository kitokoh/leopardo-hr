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

        // Issue #1819/#1943 — préavis DZ (usage dominant, Loi 90-11 art. 73-4/98 —
        // renvoi aux conventions collectives) : 1 mois < 10 ans, 2 mois ≥ 10 ans,
        // exprimé en jours OUVRÉS (22/44) — l'indemnité = rémunération de la
        // période de préavis (art. 98) : base × 22/22 = 1 mois exact, pas 30/22.
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
            'contract_type' => 'CDI',
            'contract_start' => '2021-01-01',
            'contract_end' => '2026-01-01', // 60 mois = 5 ans exacts
        ]);

        $service = new EndOfContractService;
        $settlement = $service->settlement($employee, Carbon::parse('2026-01-01'), [
            'departure_reason' => 'dismissal', // licenciement CDI, préavis non effectué
        ]);

        // Golden F-08 (calculé à la main) : 60 000 × 5 ans × 1,0 mois/an = 300 000 DZD.
        $this->assertSame(300000.0, $settlement['breakdown']['severance']);
        // Issue #1943 — préavis 1 mois en jours OUVRÉS (22) : 60 000 × 22/22 = 60 000 DZD.
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

    public function test_settlement_default_context_pays_no_notice(): void
    {
        // Issue #1943 — sans contexte de départ, AUCUN préavis n'est payé :
        // un CDD à terme naturel, une démission ou une faute lourde ne doivent
        // jamais déclencher d'indemnité compensatrice (surpaie silencieuse
        // sinon). L'appelant doit expliciter licenciement CDI + préavis non
        // effectué pour activer le préavis.
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ']);
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'salary_base' => 60000.0,
            'contract_type' => 'CDD', // CDD à terme naturel → aucun préavis dû
            'contract_start' => '2021-01-01',
            'contract_end' => '2026-01-01',
        ]);

        $service = new EndOfContractService;
        $settlement = $service->settlement($employee, Carbon::parse('2026-01-01'));

        $this->assertSame(0.0, $settlement['breakdown']['notice_pay']);
    }

    public function test_settlement_cdd_or_served_notice_pays_no_notice(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ']);
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'salary_base' => 60000.0,
            'contract_type' => 'CDI',
            'contract_start' => '2021-01-01',
            'contract_end' => '2026-01-01',
        ]);

        $service = new EndOfContractService;

        // Préavis EFFECTUÉ → rien à compenser.
        $served = $service->settlement($employee, Carbon::parse('2026-01-01'), [
            'departure_reason' => 'dismissal',
            'notice_served' => true,
        ]);
        $this->assertSame(0.0, $served['breakdown']['notice_pay']);

        // Démission → aucun préavis dû par l'employeur.
        $resigned = $service->settlement($employee, Carbon::parse('2026-01-01'), [
            'departure_reason' => 'resignation',
        ]);
        $this->assertSame(0.0, $resigned['breakdown']['notice_pay']);

        // Faute lourde → aucun préavis (art. 83).
        $misconduct = $service->settlement($employee, Carbon::parse('2026-01-01'), [
            'departure_reason' => 'misconduct',
        ]);
        $this->assertSame(0.0, $misconduct['breakdown']['notice_pay']);
    }

    public function test_unregistered_country_throws_typed_error_instead_of_dz_fallback(): void
    {
        // Pays totalement inconnu du moteur (ex. 'XX' — GB/US sont désormais
        // enregistrés depuis #5255) : PLUS AUCUN repli silencieux sur les
        // défauts DZ (MULTI-PAYS #1868) — une règle indisponible produit
        // une erreur métier typée et explicable (critère #1869 : « les erreurs
        // expliquent quelle règle ou quel contexte manque »).
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'XX']);
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'salary_base' => 60000.0,
            'contract_start' => '2021-01-01',
            'contract_end' => '2026-01-01',
        ]);

        $service = new EndOfContractService;

        $this->expectException(UnsupportedCountryRulesException::class);
        $service->settlement($employee, Carbon::parse('2026-01-01'));
    }
}
