<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\SalaryStructure;
use App\Modules\Payroll\Infrastructure\Services\PayrollCalculator;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

class CotisationSimulationTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_simulate_dz_cotisations(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();

        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);

        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/v1/cotisation-simulation', [
            'gross_salary' => 50000,
            'country_code' => 'DZ',
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'country_code',
                'gross_salary',
                'employee_contributions',
                'employer_contributions',
                'total_employee_deduction',
                'total_employer_cost',
                'taxable_gross',
                'income_tax',
                'net_before_tax',
                'net_salary',
                'total_cost_employer',
            ],
        ]);

        /** @var array<string, mixed> $data */
        $data = $response->json('data');
        $this->assertEquals('DZ', $data['country_code']);
        $this->assertEquals(50000, $data['gross_salary']);
        $this->assertEquals(4500, $data['total_employee_deduction']);
        $this->assertEquals(13000, $data['total_employer_cost']);
        $this->assertEquals(45500.0, $data['net_before_tax']);
        $this->assertGreaterThan(0, $data['income_tax']);
        $this->assertLessThan($data['net_before_tax'], $data['net_salary']);
    }

    /**
     * Issue #1782 — non-régression : la simulation DZ 60 000 doit reproduire
     * le golden test du moteur de paie (GoldenDzPayrollTest
     * test_golden_dz_full_slip_flow_at_60000) : CNAS 5 400 / 15 600,
     * assiette 54 600, IRG 7 042, net 47 558. Avant le correctif, l'impôt
     * n'était JAMAIS calculé et les tranches IRG du contrôleur étaient fausses
     * (20/30/35/40 % au lieu de 23/27/30/33/35 %).
     */
    public function test_simulate_dz_60000_matches_payroll_golden(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);
        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/v1/cotisation-simulation', [
            'gross_salary' => 60000,
            'country_code' => 'DZ',
        ])->assertOk();

        /** @var array<string, mixed> $data */
        $data = $response->json('data');

        $this->assertEquals(5400.0, $data['total_employee_deduction']);
        $this->assertEquals(15600.0, $data['total_employer_cost']);
        $this->assertEquals(54600.0, $data['taxable_gross']);
        $this->assertEquals(7042.0, $data['income_tax']);
        $this->assertEquals(47558.0, $data['net_salary']);
        $this->assertEquals(75600.0, $data['total_cost_employer']);

        // Le détail déclaré par les règles pays du moteur (CNAS salariale 9 %).
        $this->assertSame('CNAS_EMP', $data['employee_contributions'][0]['code']);
        $this->assertEquals(5400.0, $data['employee_contributions'][0]['amount']);
    }

    /**
     * Issue #1782 — Côte d'Ivoire (membre CEDEAO) : avant le correctif, CI
     * tombait sur les taux DZ (9 % / 26 %). Le moteur applique les taux
     * CEDEAO/UEMOA : 3,6 % salarié / 16,4 % employeur.
     */
    public function test_simulate_ci_uses_cedeao_rates_not_dz(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);
        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/v1/cotisation-simulation', [
            'gross_salary' => 100000,
            'country_code' => 'CI',
        ])->assertOk();

        /** @var array<string, mixed> $data */
        $data = $response->json('data');

        $this->assertSame('CI', $data['country_code']);
        $this->assertEquals(3600.0, $data['total_employee_deduction']);
        $this->assertEquals(16400.0, $data['total_employer_cost']);
        $this->assertEquals(96400.0, $data['net_before_tax']);
    }

    /**
     * Issue #1782 — l'impôt est calculé pour tous les pays supportés :
     * FR (PAS progressif) doit renvoyer un income_tax > 0 et un net_salary
     * strictement inférieur au net_before_tax.
     */
    public function test_simulate_fr_computes_income_tax(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);
        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/v1/cotisation-simulation', [
            'gross_salary' => 5000,
            'country_code' => 'FR',
        ])->assertOk();

        /** @var array<string, mixed> $data */
        $data = $response->json('data');

        $this->assertGreaterThan(0, $data['income_tax']);
        $this->assertSame(
            round($data['net_before_tax'] - $data['income_tax'], 2),
            $data['net_salary']
        );
    }

    public function test_simulate_ma_cotisations(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();

        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);

        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/v1/cotisation-simulation', [
            'gross_salary' => 10000,
            'country_code' => 'MA',
        ]);

        $response->assertOk();
        /** @var array<string, mixed> $data */
        $data = $response->json('data');
        $this->assertEquals('MA', $data['country_code']);
        $this->assertGreaterThan(0, $data['total_employee_deduction']);
    }

    public function test_employee_cannot_simulate(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();

        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
            'status' => 'active',
        ]);

        Sanctum::actingAs($employee);

        $response = $this->postJson('/api/v1/cotisation-simulation', [
            'gross_salary' => 50000,
            'country_code' => 'DZ',
        ]);

        $response->assertForbidden();
    }

    public function test_invalid_country_code_rejected(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();

        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);

        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/v1/cotisation-simulation', [
            'gross_salary' => 50000,
            'country_code' => 'XX',
        ]);

        $response->assertUnprocessable();
    }

    // ── Issue #1869 — contrat de calcul complet et explicable ────────────────

    /**
     * Contrat complet DZ (brut 60 000) — mêmes valeurs que le golden du moteur
     * (GoldenDzPayrollTest) : calcul manuel :
     *   CNAS salariale 9 % = 5 400 ; patronale 26 % = 15 600 ;
     *   assiette = 60 000 − 5 400 = 54 600 ;
     *   IRG mensuel progressif (0/23/27/30 %) sur 54 600 = 8 542 → ×12 = 102 504,
     *   abattement = min(max(102 504 × 40 %, 12 000), 18 000) = 18 000 ;
     *   (102 504 − 18 000) / 12 = 7 042 ;
     *   bracket_tax = 0 (DZ sans TRIMF) ;
     *   net = 60 000 − 5 400 − 7 042 = 47 558 ; coût employeur = 60 000 + 15 600 = 75 600.
     */
    public function test_contract_dz_golden_full_breakdown(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);
        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/v1/cotisation-simulation', [
            'gross_salary' => 60000,
            'country_code' => 'DZ',
        ])->assertOk();

        /** @var array<string, mixed> $data */
        $data = $response->json('data');

        // Contexte du calcul (contrat issue #1869).
        $this->assertSame('DZ', $data['country_code']);
        $this->assertSame('DZD', $data['contract']['currency']);
        $this->assertSame('AlgeriaPayrollRules', $data['contract']['rules_identifier']);
        $this->assertSame('pilot', $data['contract']['confidence_level']);
        $this->assertIsString($data['contract']['rounding_policy']);
        $this->assertSame(12, strlen($data['contract']['slab_version']));
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $data['contract']['rules_period']);

        // Montants (identiques au bulletin).
        $this->assertEquals(60000.0, $data['gross_salary']);
        $this->assertEquals(5400.0, $data['total_employee_deduction']);
        $this->assertEquals(15600.0, $data['total_employer_cost']);
        $this->assertEquals(54600.0, $data['taxable_gross']);
        $this->assertEquals(7042.0, $data['income_tax']);
        $this->assertEquals(0.0, $data['bracket_tax']);
        $this->assertEquals(12442.0, $data['total_deductions']);
        $this->assertEquals(54600.0, $data['net_before_tax']);
        $this->assertEquals(47558.0, $data['net_salary']);
        $this->assertEquals(75600.0, $data['total_cost_employer']);

        // Détail des cotisations (règles du moteur).
        /** @var list<array{code: string, amount: int|float}> $employeeContributions */
        $employeeContributions = $data['employee_contributions'];
        /** @var list<array{code: string, amount: int|float}> $employerContributions */
        $employerContributions = $data['employer_contributions'];
        $this->assertSame('CNAS_EMP', $employeeContributions[0]['code']);
        $this->assertEquals(5400.0, $employeeContributions[0]['amount']);
        $this->assertSame('CNAS_PAT', $employerContributions[0]['code']);
        $this->assertEquals(15600.0, $employerContributions[0]['amount']);
    }

    /**
     * Contrat CI (CEDEAO, brut 100 000) — calcul manuel :
     *   CNSS 3,6 % = 3 600 ; patronale 16,4 % = 16 400 ;
     *   assiette = 96 400 ; annualisée = 1 156 800 → tranche 0–600 000 : 0,
     *   600 001–1 200 000 : 12 % sur 556 800 = 66 816 → /12 = 5 568 ;
     *   bracket_tax = 0 ; net = 100 000 − 3 600 − 5 568 = 90 832 ;
     *   coût = 100 000 + 16 400 = 116 400.
     */
    public function test_contract_ci_cedeao_full_breakdown(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);
        Sanctum::actingAs($manager);

        /** @var array<string, mixed> $data */
        $data = $this->postJson('/api/v1/cotisation-simulation', [
            'gross_salary' => 100000,
            'country_code' => 'CI',
        ])->assertOk()->json('data');

        $this->assertSame('CI', $data['country_code']);
        $this->assertSame('XOF', $data['contract']['currency']);
        $this->assertSame('CedeaoPayrollRules', $data['contract']['rules_identifier']);
        $this->assertSame('placeholder', $data['contract']['confidence_level']);

        $this->assertEquals(3600.0, $data['total_employee_deduction']);
        $this->assertEquals(16400.0, $data['total_employer_cost']);
        $this->assertEquals(96400.0, $data['taxable_gross']);
        $this->assertEquals(5568.0, $data['income_tax']);
        $this->assertEquals(0.0, $data['bracket_tax']);
        $this->assertEquals(9168.0, $data['total_deductions']);
        $this->assertEquals(90832.0, $data['net_salary']);
        $this->assertEquals(116400.0, $data['total_cost_employer']);
    }

    /**
     * Contrat FR (PAS progressif) — la devise et l'identifiant des règles
     * reflètent les règles réellement appliquées ; l'impôt est toujours
     * présent et le net = brut − retenues (sans double comptage).
     */
    public function test_contract_fr_currency_identifier_and_net(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);
        Sanctum::actingAs($manager);

        /** @var array<string, int|float> $data */
        $data = $this->postJson('/api/v1/cotisation-simulation', [
            'gross_salary' => 5000,
            'country_code' => 'FR',
        ])->assertOk()->json('data');

        $this->assertSame('FR', $data['country_code']);
        $this->assertSame('EUR', $data['contract']['currency']);
        $this->assertSame('FrancePayrollRules', $data['contract']['rules_identifier']);
        $this->assertGreaterThan(0, $data['income_tax']);

        // La réponse doit reproduire EXACTEMENT le noyau de calcul du moteur
        // (mêmes appels métier) — comparaison directe, sans re-calculer côté test.
        $breakdown = (new PayrollCalculator)->computeNetBreakdown(5000.0, (new PayrollCalculator)->getRules('FR'));
        $this->assertEqualsWithDelta($breakdown['net_salary'], $data['net_salary'], 0.001);
        $this->assertEqualsWithDelta($breakdown['total_cost'], $data['total_cost_employer'], 0.001);
    }

    /**
     * Critère central de l'issue #1869 : pour un même brut et un même
     * contexte, la simulation et le bulletin calculé par le moteur produisent
     * exactement les mêmes montants (mêmes appels métier via
     * computeNetBreakdown()).
     */
    public function test_contract_simulation_matches_payslip_for_same_case(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);

        /** @var PayrollRun $run */
        $run = PayrollRun::create([
            'company_id' => $company->id,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'country_code' => 'DZ',
            'status' => PayrollRun::STATUS_DRAFT,
        ]);

        SalaryStructure::create([
            'company_id' => $company->id,
            'name' => 'Grille par défaut (test)',
            'base_salary' => 60000,
            'currency' => 'DZD',
            'country_code' => 'DZ',
            'frequency' => 'monthly',
            'active' => true,
        ]);

        Employee::factory()->create([
            'company_id' => $company->id,
            'salary_type' => 'fixed',
            'salary_base' => 60000,
            'status' => 'active',
        ]);

        (new PayrollCalculator)->calculateRun($run);

        /** @var \App\Modules\Payroll\Domain\Models\PaySlip|null $slip */
        $slip = $run->paySlips()->first();
        $this->assertNotNull($slip);
        $this->assertEquals(60000.0, (float) $slip->gross_salary);
        $this->assertEquals(12442.0, (float) $slip->total_deductions);
        $this->assertEquals(47558.0, (float) $slip->net_salary);
        $this->assertEquals(75600.0, (float) $slip->total_cost);

        // Manager créé APRÈS le calcul (sinon il recevrait aussi un bulletin).
        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);
        Sanctum::actingAs($manager);

        /** @var array<string, mixed> $data */
        $data = $this->postJson('/api/v1/cotisation-simulation', [
            'gross_salary' => 60000,
            'country_code' => 'DZ',
        ])->assertOk()->json('data');

        $this->assertEquals((float) $slip->gross_salary, $data['gross_salary']);
        $this->assertEquals((float) $slip->total_deductions, $data['total_deductions']);
        $this->assertEquals((float) $slip->net_salary, $data['net_salary']);
        $this->assertEquals((float) $slip->total_cost, $data['total_cost_employer']);
    }

    /**
     * Brut nul : aucun montant négatif, contrat toujours complet
     * (income_tax et net_salary présents, à 0).
     */
    public function test_contract_zero_gross_salary(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);
        Sanctum::actingAs($manager);

        /** @var array<string, mixed> $data */
        $data = $this->postJson('/api/v1/cotisation-simulation', [
            'gross_salary' => 0,
            'country_code' => 'DZ',
        ])->assertOk()->json('data');

        $this->assertEquals(0.0, $data['gross_salary']);
        $this->assertEquals(0.0, $data['income_tax']);
        $this->assertEquals(0.0, $data['total_deductions']);
        $this->assertEquals(0.0, $data['net_salary']);
        $this->assertEquals(0.0, $data['total_cost_employer']);
        $this->assertTrue($data['net_salary'] >= 0);
    }

    /**
     * Politique d'arrondi uniforme : chaque champ monétaire a ≤ 2 décimales et
     * net_salary = round(brut − total_deductions, 2), sur plusieurs pays.
     */
    public function test_contract_rounding_is_uniform(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);
        Sanctum::actingAs($manager);

        $moneyFields = [
            'gross_salary', 'total_employee_deduction', 'total_employer_cost',
            'taxable_gross', 'income_tax', 'bracket_tax', 'total_deductions',
            'net_before_tax', 'net_salary', 'total_cost_employer',
        ];

        foreach ([['60000', 'DZ'], ['100000', 'CI'], ['200000', 'CM'], ['5000', 'FR'], ['250000', 'SN']] as [$gross, $country]) {
            /** @var array<string, int|float> $data */
            $data = $this->postJson('/api/v1/cotisation-simulation', [
                'gross_salary' => $gross,
                'country_code' => $country,
            ])->assertOk()->json('data');

            foreach ($moneyFields as $field) {
                $this->assertArrayHasKey($field, $data);
                $this->assertLessThanOrEqual(
                    2,
                    $this->decimalPlaces((float) $data[$field]),
                    "{$field} doit avoir ≤ 2 décimales (pays {$country}, brut {$gross})"
                );
            }

            $this->assertEqualsWithDelta(
                round((float) $data['gross_salary'] - (float) $data['total_deductions'], 2),
                (float) $data['net_salary'],
                0.001,
                "net_salary = brut − total_deductions (pays {$country}, brut {$gross})"
            );
        }
    }

    /**
     * IRG multi-tranches DZ (brut 150 000) — calcul manuel :
     *   CNAS 9 % = 13 500 ; assiette = 136 500 ;
     *   IRG mensuel : 0–20 000 : 0 · 20 001–40 000 : 20 000 × 23 % = 4 600 ·
     *   40 001–80 000 : 40 000 × 27 % = 10 800 · 80 001–136 500 : 56 500 × 30 % = 16 950
     *   → total 32 350 → ×12 = 388 200 ; abattement = min(max(155 280, 12 000), 18 000) = 18 000 ;
     *   (388 200 − 18 000) / 12 = 30 850 ; net = 150 000 − 13 500 − 30 850 = 105 650.
     */
    public function test_contract_multi_bracket_income_tax_dz(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);
        Sanctum::actingAs($manager);

        /** @var array<string, mixed> $data */
        $data = $this->postJson('/api/v1/cotisation-simulation', [
            'gross_salary' => 150000,
            'country_code' => 'DZ',
        ])->assertOk()->json('data');

        $this->assertEquals(13500.0, $data['total_employee_deduction']);
        $this->assertEquals(136500.0, $data['taxable_gross']);
        $this->assertEquals(30850.0, $data['income_tax']);
        $this->assertEquals(105650.0, $data['net_salary']);
    }

    /**
     * @return int
     */
    private function decimalPlaces(float $value): int
    {
        $formatted = number_format($value, 6, '.', '');

        return strlen(rtrim(substr($formatted, strpos($formatted, '.') + 1), '0'));
    }
}
