<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\SalaryStructure;
use App\Modules\Payroll\Domain\Models\TaxSlab;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\AlgeriaPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\CemacPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\PayrollCalculator;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #1870 — matrice d'isolation des règles entre pays et tenants.
 *
 * Garantit qu'aucun tenant / structure / calcul ne peut recevoir les règles
 * d'un autre pays : calculs simultanés DZ vs CM (taux différents et corrects),
 * résolveur jamais croisé (CEMAC ≠ DZ), overrides tenant isolés, simulation
 * par pays, instances de règles indépendantes (pas de cache global).
 */
class CountryIsolationMatrixTest extends TestCase
{
    use RefreshTenantDatabase;

    private function makeCountryRun(Company $company, string $country, float $base): PayrollRun
    {
        SalaryStructure::create([
            'company_id' => $company->id,
            'name' => "Grille {$country} (isolation)",
            'base_salary' => $base,
            'currency' => $company->currency,
            'country_code' => $country,
            'frequency' => 'monthly',
            'active' => true,
        ]);

        Employee::factory()->create([
            'company_id' => $company->id,
            'salary_type' => 'fixed',
            'salary_base' => $base,
            // Contrat ancré avant la période : empêche le prorata aléatoire du
            // factory (contract_start aléatoire) de casser les montants.
            'contract_start' => '2020-01-01',
            'contract_end' => null,
        ]);

        $run = PayrollRun::create([
            'company_id' => $company->id,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'country_code' => $country,
            'status' => PayrollRun::STATUS_DRAFT,
        ]);

        (new PayrollCalculator)->calculateRun($run);

        return $run->refresh();
    }

    public function test_dz_and_cm_tenants_calculate_simultaneously_with_their_own_rates(): void
    {
        // Tenant DZ et tenant CM calculent en parallèle, même brut nominal
        // (200 000 en monnaie locale) → taux DIFFÉRENTS et corrects par pays.
        /** @var Company $dzCompany */
        $dzCompany = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        /** @var Company $cmCompany */
        $cmCompany = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);

        $dzRun = $this->makeCountryRun($dzCompany, 'DZ', 200000.0);
        $cmRun = $this->makeCountryRun($cmCompany, 'CM', 200000.0);

        $dzSlip = $dzRun->paySlips()->firstOrFail();
        $cmSlip = $cmRun->paySlips()->firstOrFail();

        // CNAS DZ : 9 % salariale / 26 % patronale → 18 000 / 52 000.
        $this->assertSame(18000.0, (float) $dzSlip->lines()
            ->where('name', 'Cotisations salariales')->firstOrFail()->amount);
        $this->assertSame(52000.0, (float) $dzSlip->lines()
            ->where('name', 'Cotisations patronales')->firstOrFail()->amount);

        // CNPS CM : 4,2 % salariale / 13,2 % patronale → 8 400 / 26 400.
        $this->assertSame(8400.0, (float) $cmSlip->lines()
            ->where('name', 'Cotisations salariales')->firstOrFail()->amount);
        $this->assertSame(26400.0, (float) $cmSlip->lines()
            ->where('name', 'Cotisations patronales')->firstOrFail()->amount);

        // Les montants diffèrent ET chaque tenant garde les siens.
        $this->assertNotSame((float) $dzSlip->employer_contributions, (float) $cmSlip->employer_contributions);
        $this->assertSame(52000.0, (float) $dzSlip->employer_contributions);
        $this->assertSame(26400.0, (float) $cmSlip->employer_contributions);
    }

    public function test_resolver_never_crosses_countries_cemac_vs_dz(): void
    {
        // Un pays CEMAC (CM) ne reçoit JAMAIS les règles DZ (et inversement).
        $calculator = new PayrollCalculator;

        $cmRules = $calculator->getRules('CM');
        $dzRules = $calculator->getRules('DZ');

        $this->assertInstanceOf(CemacPayrollRules::class, $cmRules);
        $this->assertInstanceOf(AlgeriaPayrollRules::class, $dzRules);
        $this->assertSame('CM', $cmRules->countryCode());
        $this->assertSame('DZ', $dzRules->countryCode());
        $this->assertSame('XAF', $cmRules->currency());
        $this->assertSame('DZD', $dzRules->currency());

        // Même brut → charges salariales distinctes par pays.
        $this->assertNotSame(
            $cmRules->calculateSocialCharges(200000.0)['employee'],
            $dzRules->calculateSocialCharges(200000.0)['employee']
        );
    }

    public function test_cross_tenant_tax_slab_override_never_leaks(): void
    {
        // Un barème spécifique au tenant A (tax_slabs.company_id = A) ne doit
        // pas être résolu pour le tenant B.
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);

        // Le workflow #1813 : les lignes nouvellement créées sont draft par
        // défaut (migration 2026_08_14_000001 ALTER ... SET DEFAULT 'draft')
        // — la résolution paie n'utilise que les lignes ACTIVES, donc le test
        // d'isolation doit créer une ligne active explicitement.
        TaxSlab::create([
            'company_id' => $companyA->id,
            'country_code' => 'DZ',
            'name' => 'Tranche A (isolée)',
            'min_amount' => 0,
            'max_amount' => 100000,
            'rate' => 5,
            'fixed_deduction' => 0,
            // Issue #1813 : seules les lignes ACTIVES participent aux
            // calculs (le défaut en base est désormais 'draft' depuis la
            // migration du workflow de validation) — sans ce statut le
            // barème est ignoré et le test échouait (rate 0.0 au lieu de 5.0).
            'status' => TaxSlab::STATUS_ACTIVE,
            'effective_from' => '2026-01-01',
        ]);

        $calculator = new PayrollCalculator;

        $rulesA = $calculator->getRules('DZ')->forCompany((string) $companyA->id)->asOf('2026-07-01');
        $rulesB = $calculator->getRules('DZ')->forCompany((string) $companyB->id)->asOf('2026-07-01');

        $slabsA = $rulesA->taxSlabs();
        $slabsB = $rulesB->taxSlabs();

        $this->assertSame(5.0, (float) $slabsA[0]['rate'], 'Le tenant A doit voir son override');
        $this->assertNotSame(5.0, (float) $slabsB[0]['rate'], 'Le tenant B ne doit pas voir l\'override de A');
    }

    public function test_rule_instances_are_independent_no_shared_state(): void
    {
        // Instances de règles : une instance partagée et immuable par pays
        // (résolveur #1868) ; scoper l'une (forCompany/asOf) produit un clone
        // qui n'affecte jamais l'instance d'origine ni les autres pays.
        $calculator = new PayrollCalculator;

        $dz = $calculator->getRules('DZ');
        $cm = $calculator->getRules('CM');

        $this->assertNotSame($dz, $cm);
        // Le résolveur (#1868) retourne volontairement l'instance partagée et
        // immuable du pays (pas de cache global à purger) : l'isolation vient
        // des clones forCompany()/asOf() vérifiés ci-dessous.
        $this->assertSame($dz, $calculator->getRules('DZ'));

        // Scoper DZ sur un tenant ne modifie pas les valeurs CM (immutabilité).
        $scopedDz = $dz->forCompany('tenant-x')->asOf('2026-07-01');
        $this->assertNotSame($scopedDz, $dz);
        $this->assertSame(
            $cm->calculateSocialCharges(200000.0)['employee'],
            $calculator->getRules('CM')->calculateSocialCharges(200000.0)['employee']
        );
    }

    public function test_simulation_endpoint_returns_country_specific_results(): void
    {
        // La simulation indépendante (sans donnée tenant) répond avec les taux
        // du pays demandé — DZ vs CM, même brut → résultats distincts.
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ']);
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create([
            'company_id' => $company->id,
            'manager_role' => 'principal',
            'email' => fake()->unique()->safeEmail(),
        ]);

        Sanctum::actingAs($manager);

        $dz = $this->postJson('/api/v1/cotisation-simulation', [
            'gross_salary' => 200000,
            'country_code' => 'DZ',
        ])->assertOk()->json('data');

        $cm = $this->postJson('/api/v1/cotisation-simulation', [
            'gross_salary' => 200000,
            'country_code' => 'CM',
        ])->assertOk()->json('data');

        $this->assertSame(18000.0, (float) $dz['total_employee_deduction']);
        $this->assertSame(8400.0, (float) $cm['total_employee_deduction']);
        $this->assertNotSame($dz['total_employee_deduction'], $cm['total_employee_deduction']);
        $this->assertNotSame($dz['net_salary'], $cm['net_salary']);
    }
}
