<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
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
        $data = $response->json('data');
        $this->assertEquals('MA', $data['country_code']);
        $this->assertGreaterThan(0, $data['total_employee_deduction']);
    }

    public function test_employee_cannot_simulate(): void
    {
        $company = Company::factory()->create();

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
}
