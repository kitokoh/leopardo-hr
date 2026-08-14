<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #1830 — déclaration IPRES/CSS mensuelle Sénégal (CSV).
 *
 * Vérifie : structure CSV (T1 + T2 cadres), plafonnement T1 432 000 XOF,
 * séparation general/cadre, RBAC principal/comptable, pays ≠ SN → 422,
 * isolation tenant (404). Référence : docs/payroll/SN_COMPLIANCE.md §4-6.
 */
class IpresDeclarationTest extends TestCase
{
    use RefreshTenantDatabase;

    private function snActors(): array
    {
        $company = Company::factory()->create(['country' => 'SN', 'currency' => 'XOF']);
        $manager = Employee::factory()->manager()->create([
            'company_id' => $company->id,
            'manager_role' => 'principal',
            'email' => fake()->unique()->safeEmail(),
        ]);
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'first_name' => 'Fatou',
            'last_name' => 'Diop',
            'email' => fake()->unique()->safeEmail(),
            'matricule' => 'EMP-SN-001',
            'ipres_matricule' => 'IPRES-SN-001',
            'status' => 'active',
        ]);

        return [$company, $manager, $employee];
    }

    private function makeRun(Company $company, string $periodStart = '2026-05-01', string $country = 'SN'): PayrollRun
    {
        return PayrollRun::create([
            'company_id' => $company->id,
            'period_start' => $periodStart,
            'period_end' => '2026-05-31',
            'country_code' => $country,
            'status' => PayrollRun::STATUS_VALIDATED,
        ]);
    }

    private function validatedSlip(PayrollRun $run, Employee $employee, float $gross): void
    {
        PaySlip::create([
            'payroll_run_id' => $run->id,
            'company_id' => $run->company_id,
            'employee_id' => $employee->id,
            'period_start' => $run->period_start,
            'period_end' => $run->period_end,
            'gross_salary' => $gross,
            'total_deductions' => 0,
            'net_salary' => $gross,
            'employer_contributions' => 0,
            'total_cost' => $gross,
            'working_days' => 22,
            'actual_days_worked' => 22,
            'status' => 'validated',
        ]);
    }

    public function test_sn_t1_and_t2_separation(): void
    {
        [$company, $manager, $employee] = $this->snActors();
        $cadre = Employee::factory()->create([
            'company_id' => $company->id,
            'first_name' => 'Moussa',
            'last_name' => 'Sarr',
            'email' => fake()->unique()->safeEmail(),
            'matricule' => 'EMP-SN-002',
            'ipres_matricule' => 'IPRES-SN-002',
            'status' => 'active',
        ]);
        $run = $this->makeRun($company);
        $this->validatedSlip($run, $employee, 200000.0);   // general (≤ 432k)
        $this->validatedSlip($run, $cadre, 600000.0);      // cadre (> 432k)

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/payroll-runs/'.$run->id.'/declarations/ipres-sn');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));

        $csv = (string) $response->getContent();
        $header = '"matricule_ipres","nom","prenom","categorie","salaire_brut","assiette_t1","t1_salariale",'
            .'"t1_patronale","assiette_t2","t2_salariale","t2_patronale","css_famille_patronale"';
        $this->assertStringContainsString($header, $csv);

        // Employé general 200 000 : T1 200 000 · 5,6 % = 11 200 · 8,4 % = 16 800
        //   T2 0 · CSS famille 3 % = 6 000
        $this->assertStringContainsString('"IPRES-SN-001","Diop","Fatou","general","200000.00","200000.00","11200.00","16800.00","0.00","0.00","0.00","6000.00"', $csv);
        // Cadre 600 000 : T1 432 000 (24 192 / 36 288) · T2 base 168 000 (4 032 / 6 048) · CSS 18 000
        $this->assertStringContainsString('"IPRES-SN-002","Sarr","Moussa","cadre","600000.00","432000.00","24192.00","36288.00","168000.00","4032.00","6048.00","18000.00"', $csv);
        // TOTAUX : assiette T1 632 000 · T1 salariale 35 392 · T1 patronale 53 088
        //   assiette T2 168 000 · T2 salariale 4 032 · T2 patronale 6 048 · CSS 24 000
        $this->assertStringContainsString('"TOTAUX","2 bulletins","","","632000.00","632000.00","35392.00","53088.00","168000.00","4032.00","6048.00","24000.00"', $csv);
    }

    public function test_sn_t1_cap_432k_applied(): void
    {
        [$company, $manager, $employee] = $this->snActors();
        $run = $this->makeRun($company);
        $this->validatedSlip($run, $employee, 1000000.0);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/payroll-runs/'.$run->id.'/declarations/ipres-sn');

        $response->assertOk();
        $csv = (string) $response->getContent();

        // Brut 1 000 000 → T1 plafonné à 432 000 (24 192 / 36 288) ·
        //   T2 base 568 000 (13 632 / 20 448) · CSS 30 000
        $this->assertStringContainsString('"IPRES-SN-001","Diop","Fatou","cadre","1000000.00","432000.00","24192.00","36288.00","568000.00","13632.00","20448.00","30000.00"', $csv);
    }

    public function test_sn_wrong_country_returns_422(): void
    {
        [$company, $manager, $employee] = $this->snActors();
        // Run DZ : la déclaration IPRES SN ne s'applique pas.
        $run = $this->makeRun($company, '2026-05-01', 'DZ');
        $this->validatedSlip($run, $employee, 200000.0);

        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/payroll-runs/'.$run->id.'/declarations/ipres-sn')->assertStatus(422);
    }

    public function test_sn_rbac_employee_and_plain_manager_blocked(): void
    {
        [$company, , $employee] = $this->snActors();
        $run = $this->makeRun($company);
        $this->validatedSlip($run, $employee, 200000.0);

        $plainManager = Employee::factory()->manager()->create([
            'company_id' => $company->id,
            'manager_role' => 'rh', // RH ≠ principal/comptable
            'email' => fake()->unique()->safeEmail(),
        ]);

        Sanctum::actingAs($employee);
        $this->getJson('/api/v1/payroll-runs/'.$run->id.'/declarations/ipres-sn')->assertForbidden();

        Sanctum::actingAs($plainManager);
        $this->getJson('/api/v1/payroll-runs/'.$run->id.'/declarations/ipres-sn')->assertForbidden();
    }

    public function test_sn_cross_tenant_declaration_blocked(): void
    {
        [$company, , $employee] = $this->snActors();
        $run = $this->makeRun($company);
        $this->validatedSlip($run, $employee, 200000.0);

        [$otherCompany, $otherManager] = $this->snActors();
        $otherRun = $this->makeRun($otherCompany);

        Sanctum::actingAs($otherManager);

        $this->getJson('/api/v1/payroll-runs/'.$run->id.'/declarations/ipres-sn')->assertNotFound();
        $this->getJson('/api/v1/payroll-runs/'.$otherRun->id.'/declarations/ipres-sn')->assertOk();
    }
}
